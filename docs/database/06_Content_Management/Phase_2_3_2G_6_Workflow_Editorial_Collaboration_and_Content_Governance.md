# JUANET Workflow, Editorial Collaboration & Content Governance Implementation Manual
## Phase 2.3.2G.6 — Collaborative Pipelines, Review State Machines, Parallel Approval Engines, and Compliance Audit Governance
**Document Version:** 1.0  
**Author:** Chief Content Operations Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Workflow Specification  

---

## 1. WORKFLOW ARCHITECTURE & BOUNDED CONTEXT

In a high-scale multi-tenant enterprise CMS, coordinating human operations (such as drafting, translations, legal reviews, SEO optimization, and final publishing approvals) requires a robust workflow engine. The **JUANET Workflow, Editorial Collaboration & Content Governance Bounded Context** designates the workflow engine as an **independent, isolated service layer**.

The workflow engine is not the System of Record (SoR) for content items; content states remain owned and managed by the CMS content modeling core. Instead, the workflow engine manages:
*   Editorial approval paths and validation chains.
*   Assignee queues, task allocations, and escalation paths.
*   In-line discussions, feedback notes, and collaboration histories.
*   Regulatory audit records and compliance enforcement policies.

```
                           [JUANET WORKFLOW DECOUPLED ARCHITECTURE]

       ┌───────────────────────────┐  (Launches Review)  ┌───────────────────────────┐
       │     CMS Content Core      ├────────────────────►│      Workflow Engine      │
       │  (Physical Tables / SoR)  │◄────────────────────┤  (Independent Context)    │
       └───────────────────────────┘  (Sets Publish Flag)└─────────────┬─────────────┘
                                                                       │
                                                                       │ (Spawns Review Tasks)
                                                                       ▼
       ┌───────────────────────────┐                     ┌───────────────────────────┐
       │   User Interface Queues   │◄────────────────────┤   Background Job Worker   │
       │ (Assigned Reviewer Tasks) │  (Dispatches Push)  │ (Timeouts / Escalations)  │
       └───────────────────────────┘                     └─────────────┬─────────────┘
                                                                       │
                                                                       ▼ (Publishes Events)
                                                         ┌───────────────────────────┐
                                                         │   Transactional Outbox    │
                                                         │     (audit.outbound)      │
                                                         └───────────────────────────┘
```

The system enforces the following core architectural rules:
*   **Separation of State Ownership**: Content remains owned by the CMS and is referenced within workflows by unique identifiers. The workflow engine manages only the step states and review decisions.
*   **Complete Statelessness**: The workflow service processes operations using stateless request headers, validating user context, tenant IDs, and action permissions on every request.
*   **Version-Aware Isolation**: Workflows are bound to specific content versions (e.g., `content_item_id` at `version_number`). Modifying a content item during active review halts the active workflow or creates a new review version.
*   **Decoupled Async Background Processing**: Heavy workflow calculations (such as evaluating fallback assignments, processing timeouts, and dispatching notifications) run inside asynchronous job workers.
*   **Strict Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all workflow and task tables, preventing cross-tenant data leaks at the database layer.

---

## 2. WORKFLOW TEMPLATE ENGINE

To support diverse business structures, the template engine allows organizations to design, version, and deploy custom approval chains:

```
                            [WORKFLOW TEMPLATE DESIGN]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Workflow Template                            │
  │   - Scope: Technical Release Review                                    │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Sequential Phase Transitions)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│Step 1: Writer    │         │Step 2: Legal     │         │Step 3: Executive │
│- Technical copy  │         │- Disclosure and  │         │- Final publishing│
│  validation      │         │  policy check    │         │  approval        │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The database structures support versioned workflow templates and sequential step configurations:

```sql
-- DDL for Workflow Templates
CREATE TABLE public.workflow_templates (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    template_code VARCHAR(64) NOT NULL, -- e.g., 'technical_review', 'legal_only_compliance'
    version_number INTEGER NOT NULL DEFAULT 1,
    display_name VARCHAR(128) NOT NULL,
    description VARCHAR(512),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_workflow_template_version UNIQUE (organization_id, template_code, version_number)
);

-- DDL for Workflow Template Steps
CREATE TABLE public.workflow_template_steps (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    template_id UUID NOT NULL REFERENCES public.workflow_templates(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL, -- Execution priority order
    step_name VARCHAR(128) NOT NULL,
    step_type VARCHAR(32) NOT NULL, -- 'Single_Approval', 'Parallel_Quorum', 'Four_Eyes'
    allotted_duration_minutes INTEGER, -- SLA limit before timeout escalations occur
    required_role VARCHAR(64) NOT NULL, -- Target reviewer role
    routing_logic JSONB NOT NULL DEFAULT '{}'::jsonb, -- Conditional logic triggers
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_workflow_template_step_order UNIQUE (template_id, step_order)
);
CREATE INDEX idx_workflow_step_lookup ON public.workflow_template_steps (template_id, step_order);
```

---

## 3. WORKFLOW INSTANCE STATE MACHINE (FSM)

Each workflow instance goes through a deterministic lifecycle, transitioning through validated states to guarantee task safety and compliance:

```
                              [WORKFLOW INSTANCE FSM]

              ┌──────────────────► [ 1. Created ]
              │                           │
              │                           ▼
              │                    [ 2. Assigned ]
              │                           │
              │                           ▼
              │                   [ 3. In Progress ] ◄─────┐
              │                           │                │
              │                           ▼                │ (Request Changes)
              │                    [ 4. Pending_Review ] ──┘
              │                           │
              │             ┌─────────────┴─────────────┐
              │             ▼                           ▼
              │       [ 5. Approved ]             [ 6. Rejected ]
              │             │                           │
              │             ▼                           ▼
              └─────────────────── [ 7. Completed ] ◄───┘ (Archived)
```

### 3.1 State Specifications and Mutation Invariants

*   **1. Created**
    *   *Purpose*: Workflow instantiation. System generates the review instance and parses template metadata.
    *   *Entry Criteria*: Editor triggers review on a content item.
    *   *Exit Criteria*: System identifies step assignees and queues review tasks.
    *   *Allowed Transitions*: `Assigned`, `Cancelled`.
*   **2. Assigned**
    *   *Purpose*: Task allocation. Review tasks are added to the assignees' queues.
    *   *Entry Criteria*: Workflow parser assigns users to the active step.
    *   *Exit Criteria*: Assigned reviewer opens and locks the task.
    *   *Allowed Transitions*: `In Progress`, `Cancelled`, `Escalated`.
*   **3. In Progress**
    *   *Purpose*: The active review phase. The reviewer evaluates the content item and draft payload.
    *   *Entry Criteria*: Reviewer opens the task and begins work.
    *   *Exit Criteria*: Reviewer completes their checks and submits their decision.
    *   *Allowed Transitions*: `Pending_Review`, `Returned_for_Changes`, `Cancelled`.
*   **4. Pending_Review**
    *   *Purpose*: Decision evaluation. The system aggregates review outcomes (e.g., waiting for parallel approvals to complete).
    *   *Entry Criteria*: Active step reviewers submit their decisions.
    *   *Exit Criteria*: Voting conditions (e.g., majority quorum) are met.
    *   *Allowed Transitions*: `Approved`, `Rejected`, `Returned_for_Changes`.
*   **5. Approved**
    *   *Purpose*: Phase completion. Reviewer signs off on the current workflow step.
    *   *Entry Criteria*: Review decisions meet step requirements.
    *   *Exit Criteria*: System routes the workflow to the next step or marks it complete.
    *   *Allowed Transitions*: `Completed`, `Assigned` (Moves to next step).
*   **6. Rejected**
    *   *Purpose*: Review rejection. The workflow is halted due to compliance or quality issues.
    *   *Entry Criteria*: Reviewer rejects the content.
    *   *Exit Criteria*: System updates the content state and logs audit records.
    *   *Allowed Transitions*: `Completed`, `Returned_for_Changes`.
*   **7. Returned_for_Changes**
    *   *Purpose*: Revision request. The editor receives the task back to make requested updates.
    *   *Entry Criteria*: Reviewer requests changes.
    *   *Exit Criteria*: Editor applies changes and resubmits the task.
    *   *Allowed Transitions*: `In Progress`, `Cancelled`.
*   **8. Completed**
    *   *Purpose*: Workflow completion. The content item is approved and ready to be published.
    *   *Entry Criteria*: All sequential workflow steps are successfully approved.
    *   *Exit Criteria*: Workflow records are archived.
    *   *Allowed Transitions*: `Archived`.

---

## 4. EDITORIAL TASK ENGINE

The Task Engine manages task allocations, assignees, and deadlines for active workflows:

```sql
-- DDL for Workflow Instances
CREATE TABLE public.workflow_instances (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    template_id UUID NOT NULL REFERENCES public.workflow_templates(id),
    content_item_id UUID NOT NULL,
    current_step_order INTEGER NOT NULL DEFAULT 1,
    instance_status VARCHAR(32) NOT NULL DEFAULT 'Created', -- 'Created', 'Assigned', 'In_Progress', 'Completed'
    created_by UUID,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- DDL for Editorial Tasks
CREATE TABLE public.editorial_tasks (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    workflow_instance_id UUID NOT NULL REFERENCES public.workflow_instances(id) ON DELETE CASCADE,
    step_id UUID NOT NULL REFERENCES public.workflow_template_steps(id),
    assignee_id UUID, -- NULL indicates a team queue assignment
    target_role VARCHAR(64) NOT NULL,
    due_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    task_status VARCHAR(32) NOT NULL DEFAULT 'Unassigned', -- 'Unassigned', 'Assigned', 'In_Progress', 'Completed'
    checklist_requirements JSONB NOT NULL DEFAULT '[]'::jsonb, -- List of required verification checks
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_editorial_task_assignee ON public.editorial_tasks (organization_id, assignee_id) WHERE completed_at IS NULL;
```

---

## 5. COLLABORATION ENGINE & DISCUSSION FORUMS

To streamline reviews, editors can add inline comments, mention teammates, and suggest changes directly within the document workspace:

```
                            [COLLABORATION ENGAGEMENT]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                         CMS Collaboration Board                        │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Interactive Discussions)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│  Inline Comments │         │Team User Mentions│         │ Reviewer Notes   │
│- Position tags   │         │- Triggers push   │         │- Change requests │
│  on exact lines  │         │  notifications   │         │  history logs    │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The database structures store comments, mentions, and edit histories:

```sql
-- DDL for Editorial Comments
CREATE TABLE public.editorial_comments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    content_item_id UUID NOT NULL,
    author_id UUID NOT NULL,
    parent_comment_id UUID REFERENCES public.editorial_comments(id) ON DELETE CASCADE,
    inline_selector VARCHAR(256), -- Maps comments to specific paragraphs or lines
    comment_body TEXT NOT NULL,
    is_resolved BOOLEAN NOT NULL DEFAULT FALSE,
    resolved_by UUID,
    resolved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_editorial_comments_lookup ON public.editorial_comments (organization_id, content_item_id);
```

---

## 6. PARALLEL & CONDITIONAL APPROVAL ENGINE

The approval engine supports diverse reviewer voting and routing schemes to match enterprise verification standards:

*   **Single Sign-Off**: Requires approval from only one reviewer to complete the step.
*   **Sequential Multi-Level Check**: Routes the task through multiple reviewers in sequence (e.g., Supervisor ──► Director ──► VP).
*   **Parallel Quorum Voting**: Routes the task to multiple reviewers simultaneously, requiring a minimum number of votes (quorum) to approve the step.
*   **Four-Eyes / Maker-Checker**: Ensures that the user who drafted the content cannot approve their own changes, requiring a separate reviewer to sign off.

```sql
-- DDL for Reviewer Decisions and Sign-Offs
CREATE TABLE public.review_decisions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    task_id UUID NOT NULL REFERENCES public.editorial_tasks(id) ON DELETE CASCADE,
    reviewer_id UUID NOT NULL,
    decision_outcome VARCHAR(32) NOT NULL, -- 'Approved', 'Rejected', 'Requested_Changes'
    rejection_comment TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_reviewer_decision_per_task UNIQUE (task_id, reviewer_id)
);
```

---

## 7. COHERENCY & ENTERPRISE GOVERNANCE RULES

To comply with SOC 2, HIPAA, and GDPR auditing standards, the workflow engine enforces strict security and operational boundaries:

*   **Maker-Checker Enforcement**: Blocks content creators from approving their own drafts, preventing self-publishing:
```sql
-- Query checking self-publishing violations before completing steps
SELECT COUNT(*) 
FROM public.workflow_instances wi
WHERE wi.id = :workflow_instance_id
  AND wi.created_by = :current_reviewer_id;
```
*   **Delegation Rules**: Allows users to delegate approval permissions to designated colleagues while they are out of the office.
*   **Compliance Blackout Windows**: Blocks content deployment during predefined content freeze periods or legal holds.

---

## 8. REGIONAL EDITORIAL CALENDARS & CAMPAIGNS

The editorial calendar dashboard schedules releases across regions, helping marketing teams coordinate campaign launches:

```
                            [REGIONAL RELEASES]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Editorial Calendar                           │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Validates Launch Conditions)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│Staging Campaign  │         │Regional Embargo  │         │Global Blackout   │
│- Draft updates   │         │- Scheduled launch│         │- Freezes active  │
│  validation      │         │  dates per region│         │  deployments     │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The database structures manage campaign embargoes and content freeze windows:

```sql
-- DDL for Editorial Campaigns
CREATE TABLE public.editorial_campaigns (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    campaign_name VARCHAR(128) NOT NULL,
    start_at TIMESTAMPTZ NOT NULL,
    end_at TIMESTAMPTZ NOT NULL,
    is_frozen BOOLEAN NOT NULL DEFAULT FALSE, -- Freezes deployments during active campaigns
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

## 9. INTEGRATED ENTERPRISE NOTIFICATION HUB

The workflow engine routes task assignments and escalation notices to the notification hub, dispatching updates to editors via Slack, Teams, email, and in-app alerts:

```
                         NOTIFICATION ROUTING MAP
  [Workflow Task Event] ──► [Filter & User Preferences] ──► [Select Target Channel]
                                                                     │
         ┌───────────────────────────────────────────────────────────┴─────────────────────────┐
         ▼ (Instant Alert)                                                                     ▼ (Chat Bot)
  [Send In-App & Email Push] ──► [Audit Confirmation]                         [Dispatch Slack Message]
```

*   **SLA Warning Escalations**: Sends warning alerts to assignees and supervisors when tasks approach their deadlines.
*   **Group Notifications**: Notifies role groups when tasks are assigned to team queues, allowing users to claim tasks.
*   **Mentions**: Mentions in comments trigger instant push alerts to help team members respond quickly.

---

## 10. SYSTEM TELEMETRY & WORKFLOW ANALYTICS

To help administrators optimize workflows and identify bottlenecks, the engine tracks execution metrics for completed workflows:

```sql
-- DDL for Workflow Telemetry and SLAs
CREATE TABLE audit.workflow_telemetry (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    workflow_instance_id UUID NOT NULL,
    template_code VARCHAR(64) NOT NULL,
    step_name VARCHAR(128) NOT NULL,
    assigned_reviewer_id UUID,
    allotted_duration_minutes INTEGER,
    actual_duration_minutes INTEGER NOT NULL,
    is_sla_violated BOOLEAN GENERATED ALWAYS AS (actual_duration_minutes > allotted_duration_minutes) STORED,
    rejection_occurred BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_workflow_telemetry_reporting ON audit.workflow_telemetry (organization_id, created_at);
```

Reporting modules aggregate telemetry logs to track operations:
*   **Average Review Duration**: Monitors step execution times to identify process bottlenecks.
*   **SLA Compliance**: Tracks the percentage of steps completed within deadlines. Target SLA: > 95% compliance.
*   **Task Rejection Rate**: Tracks step rejection rates, helping editors identify common content errors.

---

## 11. SECURITY, RBAC & IMMUTABLE AUDIT LOGS

To prevent unauthorized changes and maintain security, the workflow engine applies role-based access controls and row-level security:

*   **Row-Level Security (RLS)**: Enforces strict tenant boundaries at the database layer, isolating workflow schemas using PostgreSQL RLS policies:
```sql
ALTER TABLE public.workflow_instances ENABLE ROW LEVEL SECURITY;

CREATE POLICY workflow_instance_tenant_isolation ON public.workflow_instances
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```
*   **Immutable Audit Logs**: The system logs all decisions, review results, and approvals to audit tables, preserving compliance records.

---

## 12. CONCURRENT EDITING SAFEGUARDS

To prevent editors from overwriting each other's changes, the platform implements optimistic locking and document locking safeguards:

*   **Active Document Locking**: Locking an article for edit blocks other users from saving changes until the lock is released:
```sql
-- DDL for Active Document Locks
CREATE TABLE public.content_item_locks (
    content_item_id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    locked_by UUID NOT NULL,
    locked_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT chk_lock_expiration CHECK (expires_at > locked_at)
);
```
*   **Lock Verification Rules**: Update actions verify edit locks before saving changes, rejecting transactions if a lock is held by another user.

---

## 13. WORKFLOW COHERENCE SYSTEM EVENTS

All workflow creations, assignee changes, and step completions write transactional records to the outbox table (`audit.outbound_events`), enabling background workers to coordinate tasks asynchronously:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [Workflow State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 13.1 Workflow Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Workflow Created**| `workflow.created.v1`| `WorkflowEngine`| Workflow Admin, Slack | `{ "instance_id": "uuid", "content_id": "uuid" }` |
| **Workflow Started**| `workflow.started.v1`| `WorkflowEngine`| Notification Hub | `{ "instance_id": "uuid", "started_by": "uuid" }` |
| **Workflow Assigned**| `workflow.assigned.v1`| `TaskService` | Reviewer Notification | `{ "task_id": "uuid", "assignee_id": "uuid" }` |
| **Workflow Approved**| `workflow.approved.v1`| `WorkflowEngine`| SyncEngine, CRM | `{ "instance_id": "uuid", "step_order": 2 }` |
| **Workflow Rejected**| `workflow.rejected.v1`| `WorkflowEngine`| Editor Alert Service | `{ "instance_id": "uuid", "rejected_by": "uuid" }` |
| **Workflow Complete**| `workflow.completed.v1`| `WorkflowEngine`| Publishing Service | `{ "instance_id": "uuid", "content_id": "uuid" }` |
| **Review Requested**| `review.requested.v1` | `WorkflowEngine`| Slack Bot | `{ "task_id": "uuid", "target_role": "string" }` |
| **Review Completed**| `review.completed.v1` | `WorkflowEngine`| Task Engine, Notification | `{ "task_id": "uuid", "outcome": "Approved" }` |
| **Comment Created** | `editorial.comment.created.v1`| `DiscussionSvc`| Slack Bot, Notification | `{ "comment_id": "uuid", "content_id": "uuid" }` |

### 13.2 Delivery & Idempotency Rules
*   **At-Least-Once Delivery**: Events are written to the outbox table (`audit.outbound_events`) within the same database transaction as the workflow update, preventing sync issues.
*   **Idempotency Keys**: Consumers track incoming events using unique composite hashes to prevent duplicate processing:
```
Idempotency Key: hash('workflow.completed' + workflow_instance_id + version)
```

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **FSM Transition Paths**| Simulate transition sequences across states. | Validates step sequences, blocking unauthorized transition paths. | Workflow FSM Test Suite |
| **Maker-Checker Block** | Attempt to self-approve a self-drafted content item. | Security policies block the action, logging an audit exception. | Self-Publishing Audits |
| **Task Escalation SLA** | Inject a delayed step update past the deadline. | Worker detects timeout, escalates task status, and notifies supervisor. | SLA Escalation Tests |
| **Parallel Approvals**  | Process parallel voting approvals. | Moves step to completed state only after quorum conditions are met. | Voting Engine Tests |
| **Multi-Tenant Isolation**| Query workflows without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Tenant Leakage Audits |
| **Optimistic Locks**    | Attempt to edit locked content items. | Lock verification rejects the edit, returning active lock details. | Edit Locking Audits |
| **Outbox Atomic Rollback**| Inject a failure during workflow save transactions. | Database rolls back the transaction, reverting both the update and outbox entry. | Atomic Transaction Tests |

---

## 15. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.
*   **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**: Manages CDN delivery networks, edge caches, and headless GraphQL query interfaces.

---

*Authorized by the JUANET Content Workflows Board & Technical Security Council.*
