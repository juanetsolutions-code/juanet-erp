# JUANET Support Ticket Lifecycle Engine Specification
## Phase 2.3.2F.1 — Ticket Lifecycle Engine
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Lifecycle Architecture  

---

## 1. ARCHITECTURAL PHILOSOPHY

The JUANET Support Ticket Lifecycle Engine governs the lifecycle of customer inquiries and incident workflows. It is designed around the following architectural principles:

*   **Ticket as Single Source of Truth**: The `public.tickets` table remains the authoritative hub. All communications, audit trails, and classifications must resolve back to this entity.
*   **Immutable Activity History**: All physical state transitions, assignees, and actions are captured in append-only logs (`public.ticket_activity_logs`, `public.ticket_status_history`, `public.ticket_assignment_history`). Update operations on historical records are strictly prohibited at the database engine level.
*   **Event-Driven Architecture**: The lifecycle engine is decoupled and non-blocking. State mutations commit locally, write to an outbox log, and dispatch events (`ticket.created`, `ticket.assigned`, etc.) to the enterprise message bus.
*   **Stateless Workflow Execution**: Workflows, escalations, and SLA evaluations execute statelessly by evaluating current database records against active rulesets, rather than relying on in-memory state machines.
*   **Logical Isolation**: Physical columns are separated into specialized tables. Conversation chains (`public.ticket_messages`) are divorced from operational metadata (`public.tickets`) and advisory AI computations (`public.ai_sentiment_analysis`), preventing lock contention.
*   **Multi-Tenant Isolation (RLS)**: Row-Level Security (RLS) policies are applied using `organization_id` to prevent cross-tenant exposure.
*   **Optimistic Locking**: State concurrency is maintained via a `version` column. Every read-modify-write operation must check and increment the record version, raising serialization failures on conflicts.
*   **Soft Delete Policies**: Support records are never purged physically from primary transactional tables. Soft-deletion is indicated by `deleted_at IS NOT NULL`, excluding records from standard application operations.

---

## 2. TICKET STATE MACHINE

The support lifecycle is modeled as a finite state machine (FSM). Below are the states, permitted transitions, and rule sets:

```
[New] ──> [Open] ──> [Assigned] ──> [In Progress] ──> [Resolved] ──> [Closed]
  │        │            │                  │              │
  │        ▼            ▼                  ▼              │
  └───────────────────> [Waiting for Cust/3rd Party] <────┘
                        │                  │
                        └─> [Escalated] <──┘
```

### 2.1 State Catalog & Transition Rules

#### 2.1.1 `new`
*   **Purpose**: The default entry point for newly ingested, unvalidated, and unrouted inquiries.
*   **Allowed Entry Transitions**: None (origin state).
*   **Allowed Exit Transitions**: `open`, `assigned`, `canceled`.
*   **Forbidden Transitions**: Direct transition to `resolved`, `closed`, `in_progress`, `waiting_for_customer`, `waiting_for_third_party`, `escalated`, or `archived`.
*   **Automatic Transitions**: Moves to `open` upon automatic routing or first communication. Moves to `assigned` if routed to a human agent.
*   **Timeout Rules**: If unresolved within 15 minutes of creation, triggers an automated warning hook and re-prioritizes the ticket.

#### 2.1.2 `open`
*   **Purpose**: Validated tickets awaiting queue allocation or team/agent triage.
*   **Allowed Entry Transitions**: `new`, `waiting_for_customer`, `waiting_for_third_party`.
*   **Allowed Exit Transitions**: `assigned`, `in_progress`, `waiting_for_customer`, `waiting_for_third_party`, `escalated`, `canceled`.
*   **Forbidden Transitions**: Direct transition to `closed` or `archived`.
*   **Automatic Transitions**: Reverts from `waiting_for_customer` to `open` when a customer reply is logged.

#### 2.1.3 `assigned`
*   **Purpose**: The ticket has been assigned to a specific support team or individual agent.
*   **Allowed Entry Transitions**: `new`, `open`, `in_progress`, `waiting_for_customer`, `waiting_for_third_party`, `escalated`.
*   **Allowed Exit Transitions**: `in_progress`, `waiting_for_customer`, `waiting_for_third_party`, `escalated`, `resolved`, `canceled`.
*   **Forbidden Transitions**: Direct transition to `archived`.

#### 2.1.4 `in_progress`
*   **Purpose**: The assigned agent is actively diagnosing, researching, or writing a response.
*   **Allowed Entry Transitions**: `assigned`, `open`.
*   **Allowed Exit Transitions**: `waiting_for_customer`, `waiting_for_third_party`, `escalated`, `resolved`, `canceled`.
*   **Forbidden Transitions**: Direct transition to `new` or `archived`.

#### 2.1.5 `waiting_for_customer`
*   **Purpose**: Work is paused pending information, log files, or validation from the customer.
*   **Allowed Entry Transitions**: `assigned`, `in_progress`, `escalated`.
*   **Allowed Exit Transitions**: `open`, `in_progress`, `resolved` (auto-close), `canceled`.
*   **Forbidden Transitions**: Direct transition to `new`, `closed`, or `archived`.
*   **Timeout Rules**: Stays in this state for up to 72 hours. After 24 and 48 hours, automated reminders are dispatched. If no reply is received by 72 hours, the ticket automatically transitions to `resolved` with the resolution code `no_customer_response`.

#### 2.1.6 `waiting_for_third_party`
*   **Purpose**: Investigation is paused awaiting external vendor feedback, engineering reviews, or upstream provider validation.
*   **Allowed Entry Transitions**: `assigned`, `in_progress`, `escalated`.
*   **Allowed Exit Transitions**: `open`, `in_progress`, `escalated`, `resolved`.
*   **SLA Impact**: Response/Resolution SLA timers are suspended (paused) depending on tenant policy configuration.

#### 2.1.7 `pending_approval`
*   **Purpose**: Operational resolutions requiring manager, financial, or security sign-off (e.g., refund approvals, security posture bypasses).
*   **Allowed Entry Transitions**: `in_progress`, `assigned`.
*   **Allowed Exit Transitions**: `resolved` (approved), `in_progress` (rejected).

#### 2.1.8 `escalated`
*   **Purpose**: Breach of SLA thresholds, complex technical hurdles, or explicit manual request by team management.
*   **Allowed Entry Transitions**: `open`, `assigned`, `in_progress`, `waiting_for_third_party`.
*   **Allowed Exit Transitions**: `assigned` (re-assigned), `in_progress`, `resolved`, `canceled`.

#### 2.1.9 `resolved`
*   **Purpose**: The solution has been provided. Awaiting customer confirmation or expiration of the reopen window.
*   **Allowed Entry Transitions**: `assigned`, `in_progress`, `pending_approval`, `escalated`, `waiting_for_customer`.
*   **Allowed Exit Transitions**: `open` (reopened), `closed` (hard transition).
*   **Timeout Rules**: Moves to `closed` automatically after 120 hours (5 days) of inactivity.

#### 2.1.10 `closed`
*   **Purpose**: The ticket lifecycle is complete. The ticket is locked.
*   **Allowed Entry Transitions**: `resolved` (via timeout or manual action).
*   **Allowed Exit Transitions**: `archived` (system only).
*   **Forbidden Transitions**: Cannot be transitioned back to `open`, `assigned`, `in_progress`, or `resolved`.
*   **Security Standard**: Message edits, attachment uploads, and internal note creation are disabled.

#### 2.1.11 `canceled`
*   **Purpose**: The request was invalid, spam, or withdrawn by the customer.
*   **Allowed Entry Transitions**: `new`, `open`, `assigned`, `in_progress`, `waiting_for_customer`.
*   **Allowed Exit Transitions**: `archived` (system only).

#### 2.1.12 `archived`
*   **Purpose**: Read-only historical data. Moved out of the active operational index.
*   **Allowed Entry Transitions**: `closed`, `canceled`.
*   **Allowed Exit Transitions**: None (terminal state).

---

## 3. TICKET CREATION PIPELINE

Tickets are ingested through several channels, validated, classified, and assigned.

```
[Ingest Channel] ──> [Sanitize & Validate] ──> [Spam/Dup Check] ──> [Classify & Route] ──> [Publish Created Event]
```

### 3.1 Ingestion Processing Mechanics

#### 3.1.1 Portal Creation (Web UI)
*   **Authentication**: Enforced via JWT claims verifying `organization_id` and `user_id`.
*   **Validation**: Fields are validated on submission. Rich text input is sanitized to safe markdown.
*   **Duplicate Prevention**: A front-end submission token prevents double-posting from multiple clicks.

#### 3.1.2 Email Ingestion
*   **Handler**: Inbound email processors parse multipart payloads, extract plain text/HTML, and convert inline attachments.
*   **Sender Verification**: Resolves the sender email address against `security.users` and `crm.contacts`. If not found:
    *   Creates a new contact record under a default organization (or raises a quarantine exception based on tenant config).
*   **Threading Check**: Scans headers (`In-Reply-To`, `References`, `Subject` containing `[JUANET-#\d+]`) to associate the email with an existing ticket instead of creating a new one.

#### 3.1.3 Inbound API & Webhooks
*   **Authentication**: Validated via API Key or OAuth Bearer Tokens mapping to `system.organizations`.
*   **Payload Validation**: Strictly validated against JSON Schema specifications. Missing mandatory attributes reject the request with HTTP `422 Unprocessable Entity`.

#### 3.1.4 AI-Generated (Anomaly Detection)
*   **Trigger**: Platform logs or infrastructure monitoring systems raise anomalies, generating a ticket via the AI engine.
*   **Formatting**: Includes automated system diagnostics and stack trace payloads inside the `metadata` JSONB block.

---

### 3.2 Verification, Anti-Spam, and Deduplication Rules

1.  **System Spam Filter**: Checks incoming subject and body against pattern matches (e.g., out-of-office loops, marketing campaigns). If classified as spam, the ticket status is set to `canceled` with the metadata attribute `spam_classified: true` and routing is bypassed.
2.  **Duplicate Detection Window**: Scans for existing open tickets from the same `requester_user_id` with an identical `category_id` or matching subject within a rolling 15-minute window.
    *   If a duplicate is found, the system links the new payload as a reply/thread message to the existing active ticket and drops the new ticket creation.

---

### 3.3 Default Values, Categorization, and Route Assignments

*   **Default Status**: Set to `new` (code: `TS-001`).
*   **Priority Inference**: Evaluates structural rules (e.g., if requester belongs to a VIP Tier, raise priority to `Urgent`). If the subject contains key terms like "Production Down", "Outage", or "Security Leak", priority is elevated to `Critical`.
*   **Category Inference**: Scans keywords in the ticket subject or body and maps them to `public.ticket_categories` using pg_trgm weight thresholds.
*   **Tag Assignments**: Applies system-level tracking tags (`#email-ingest`, `#unassigned`) during the creation process.
*   **Outbox Logging**: Inserts an outbox message into the events table in the same transaction as the ticket commit, ensuring transaction safety.

---

## 4. ASSIGNMENT ENGINE

Once a ticket moves beyond `new`, it enters the Routing and Assignment Engine.

### 4.1 Routing & Allocation Models

#### 4.1.1 Manual Assignment
*   An administrator, queue manager, or the agent themselves manually claims ownership.
*   Updates the `assigned_agent_id`, sets the status to `assigned`, and appends to `public.ticket_assignment_history`.

#### 4.1.2 Round Robin Algorithm
*   **Scope**: Applied within a specific `support_team_id`.
*   **Execution**: Resolves the list of active, online agents (`public.support_agents.status = 'online'`) who are under their `max_capacity` limit.
*   **Selection**: Selects the agent with the oldest `last_assigned_at` timestamp. Updates `last_assigned_at` on assignment.

#### 4.1.3 Least Loaded Algorithm
*   **Scope**: Applied to high-volume queues.
*   **Selection**: Selects the active, online agent within the target team who has the lowest `current_load` count.
*   **Capacity Guard**: If all online agents are at `max_capacity`, the ticket remains in the unassigned queue (`assigned_agent_id = NULL`) and triggers a team alert event.

#### 4.1.4 Skill-Based Routing (SBR)
*   **Scope**: Applied to complex technical or billing tickets.
*   **Mechanics**:
    1.  Calculates required skill tags by inspecting the ticket's `category_id` and active `ticket_tags`.
    2.  Queries the agent skills catalog to identify matching agents.
    3.  Scores matching agents based on weight.
    4.  Routes to the highest-scoring available agent.

---

### 4.2 Queue, Department, Regional, and Language Constraints

*   **Regional Allocation**: Maps the customer's tenant region (`organizations.region_id`) to regional queues (e.g., US-WEST, EMEA, APAC).
*   **Language Detection**: If the incoming text language does not match the default locale, the ticket is routed to queues with agents matching that language qualification.
*   **Vacation & Schedule Checks**: Before routing to an agent, the system checks `public.support_agents.status` and vacation calendars. If an agent is on `break`, `away`, or `offline`, they are excluded from assignment calculations.
*   **Reassignment Policies**: If an assigned ticket remains unaddressed (`status = assigned` and no messages logged) for more than 2 hours, the ticket is unassigned and returned to the queue, incrementing the ticket's `reassignment_count`.

---

## 5. COMMUNICATION WORKFLOW

Tracks threaded conversations and manages the security of customer messages and internal notes.

```
Message Ingest ──> Sanitize HTML/Markdown ──> Determine Visibility ──> Dispatch Push Alerts
```

### 5.1 Content Processing Standards
*   **Markdown Conversion**: Inbound text body is converted to markdown before rendering.
*   **HTML Sanitization**: To prevent Cross-Site Scripting (XSS), any HTML content is sanitized, stripping inline styles, `<script>`, `<iframe>`, and `<object>` tags.
*   **Message Types**: Classified as `standard` (customer/agent communication), `auto_response` (automated templates), `transcript` (chat session text), or `system_event` (assignment or status updates).

### 5.2 Visibility and Permission Enforcements
*   **Agent Notes**: Records added to `public.ticket_internal_notes` are flagged as system-internal. Under no circumstances are internal notes exposed to customer portal queries or customer email responses.
*   **Push Alerts & Mentions**: Scans new entries for user tags (`@user_id`). Validates that the mentioned user belongs to the active organization and has permissions to view the ticket, then dispatches an alert.
*   **Reaction Tracking**: Lightweight reaction emoji updates must validate that the user has read permissions for the ticket before inserting records into `public.ticket_message_reactions`.

---

## 6. RESOLUTION WORKFLOW

Governs the completion of the ticket lifecycle, validation rules, knowledge linking, and reopening policies.

### 6.1 Resolution Requirements
To transition a ticket to `resolved` (status code: `TS-005`), the database engine enforces the following requirements:

*   **Resolution Code**: A valid code from `public.ticket_resolutions.code` must be provided (e.g., `solved_by_patch`, `configuration_fix`, `user_error`, `third_party_resolved`).
*   **Resolution Description**: A text description detailing the corrective actions taken must be provided.
*   **Agent Ownership**: The ticket must have an assigned agent (`assigned_agent_id IS NOT NULL`).

---

### 6.2 Knowledge Linkage and Approval Policies

1.  **Knowledge Base Association**: To prevent recurring issues, agents are encouraged to link the resolving ticket to an existing knowledge base article ID (`public.knowledge_articles.id`).
2.  **Approval Gates**: High-severity resolutions (e.g., security patches, critical service restorations) require manager or security lead approval (`public.ticket_approvals`). The ticket remains in `pending_approval` until approved.
3.  **Customer Validation & Auto-Closure**: Once resolved, the customer receives an alert requesting validation.
    *   If confirmed, the status transitions to `closed`.
    *   If no action is taken within 5 days (120 hours), the system automatically closes the ticket.

---

### 6.3 Reopen Rules & Constraints

*   **Permitted Window**: A ticket can be reopened by the customer within 5 days of resolution. This is triggered by replying to the resolution email or clicking "Reopen" in the portal.
*   **Reopen Transitions**: The status reverts to `open`, the resolution record is archived, and the assigned agent receives an immediate alert.
*   **Maximum Reopen Cap**: A ticket may be reopened a maximum of **3 times**.
    *   If a customer attempts to reopen a ticket for a 4th time, the action is rejected. Instead, the system creates a new ticket linked via `public.ticket_followups`.

---

## 7. ESCALATION ENGINE

Escalations occur when SLAs are breached, technical blocks arise, or an explicit manual request is made.

### 7.1 Escalation Channels

#### 7.1.1 Manual Escalation
*   Authorized users or support leads can manually escalate a ticket.
*   Requires inputting an escalation reason and selecting an escalation tier.

#### 7.1.2 Automatic SLA Escalation
*   If an active SLA timer in `public.ticket_sla_instances` hits the `warning_at` or `target_deadline` threshold without completion, the escalation routine is triggered automatically.

#### 7.1.3 Hierarchical Transition
*   The ticket is unassigned from the current tier-1 agent, re-routed to the tier-2 escalation queue, and its priority is elevated.

---

### 7.2 Escalation Processing Lifecycle

```
[SLA Breach Warning] ──> [Elevate Priority] ──> [Re-route Queue] ──> [Alert Team Leads]
```

1.  **State Mutation**: The ticket priority is elevated (e.g., `Medium` -> `Urgent`).
2.  **Queue Re-assignment**: Re-routes the ticket to the targeted escalation queue.
3.  **Event Notification**: Dispatches urgent slack/pager alerts to the escalation team and logs the transaction in `public.ticket_activity_logs`.

---

## 8. MERGE & SPLIT ENGINE

Governs the deduplication of related issues and the isolation of unrelated threads.

### 8.1 Ticket Merge Operations (Consolidation)
When merging multiple duplicate tickets into a single parent ticket, the engine enforces the following rules:

1.  **Parent Identification**: One ticket is designated as the primary `Parent`. The duplicate tickets are designated as `Children`.
2.  **Status Update**: Children tickets are set to status `canceled` with the resolution code `merged_into_parent`.
3.  **Relationship Mapping**: Inserts records into `public.ticket_followups` mapping the relationship as `merged`.
4.  **Message Re-parenting**: Communication history and attachments from the children are linked to the parent ticket.
5.  **Watchers Consolidation**: Distinct watchers from the children are added to the parent's watchers list.

---

### 8.2 Ticket Split Operations (Isolation)
When a customer raises an unrelated issue within an active ticket thread, the agent can split the message sequence into a new ticket:

1.  **Ticket Creation**: Creates a new ticket under the same organization and requester.
2.  **Message Re-association**: Moves the selected message and its descendant replies to the new ticket.
3.  **Relationship Logging**: Creates a record in `public.ticket_followups` with the relation type `split`.
4.  **Auditing**: Generates a system note in both tickets explaining the split action and references the corresponding ticket numbers.

---

## 9. APPROVAL WORKFLOW

Certain support actions require explicit organizational approvals before proceeding.

### 9.1 Approval Configurations
Approvals are categorized into distinct workflows:

*   **Manager Approval**: Required for operational overrides.
*   **Financial Approval**: Required for refunding invoices or applying ledger adjustments.
*   **Technical Approval**: Required for staging emergency database updates or code hotfixes.
*   **Security Approval**: Required for credential resets or MFA bypasses.

---

### 9.2 Execution Architecture
1.  **Trigger**: The agent requests an action (e.g., "Request Refund"). The ticket transitions to `pending_approval` and locks.
2.  **Approval Record Creation**: Inserts a record into the approvals queue containing the required approval tier and authorization rules.
3.  **Notification Dispatch**: Alerts the authorized approvers.
4.  **Action Evaluation**:
    *   **Approved**: The ticket unlocks and transitions to the next phase (e.g., `resolved` or `assigned`).
    *   **Rejected**: Returns the ticket to `in_progress` with an explanatory note.
5.  **Emergency Overrides**: Authorized directors can bypass standard approvals by supplying an emergency override token. This bypass is logged as a critical security event.

---

## 10. AUTOMATION HOOKS

Allows executing automated actions without human intervention.

### 10.1 Automation Types
Automations are evaluated at three execution points:

1.  **Trigger Automations (Event-Driven)**: Evaluated immediately upon database mutations (e.g., "On Ticket Creation", "On Status Change").
2.  **Time-Based Automations (Scheduled Crons)**: Evaluated hourly or daily (e.g., "If ticket is waiting for customer for 48 hours, send reminder").
3.  **Macros & Quick Actions**: Preconfigured action templates applied manually by agents with one click (e.g., "Apply standard database password reset template").

---

## 11. AI INTERACTION BOUNDARIES

The platform integrates AI to assist support agents. To ensure data privacy and prevent issues like hallucinated responses, the following architectural boundaries are enforced:

### 11.1 Advisory-Only Principle
All AI outputs are stored in dedicated tables (`public.ai_ticket_summaries`, `public.ai_suggested_responses`) and treated as advisory. Under no circumstances can the system send an AI-generated message directly to a customer without human review and approval.

### 11.2 Safety and Compliance Scans
*   **PII Masking**: Before transmitting ticket details to external LLM endpoints for summarization, a local pre-processor masks sensitive details like credit card numbers, passwords, and access keys.
*   **Sentiment Metrics**: Calculated sentiment scores are stored in `public.ai_sentiment_analysis` and used for reporting and routing, but do not directly alter core ticket state fields.

---

## 12. SLA INTERACTION

The state machine is integrated with the SLA engine. Ticket state changes directly affect SLA timer calculations:

| Ticket State | Response SLA Impact | Resolution SLA Impact | Architectural Action |
| :--- | :--- | :--- | :--- |
| `new` | **Active** | **Active** | SLA timers run. |
| `open` | **Active** | **Active** | SLA timers run. |
| `assigned` | **Completed** | **Active** | Response SLA stops. Resolution SLA continues. |
| `in_progress` | **Completed** | **Active** | Response SLA stops. Resolution SLA continues. |
| `waiting_for_customer` | Suspended | Suspended | SLA timers pause. Paused duration is logged. |
| `waiting_for_third_party`| Suspended | Suspended | SLA timers pause. Paused duration is logged. |
| `resolved` | **Completed** | **Completed** | All SLA targets stop and are marked as achieved. |
| `closed` | **Completed** | **Completed** | All SLA targets stop and are locked. |

*   **SLA Pausing**: When entering a paused state, the engine records `paused_at`. Upon resuming, the deadline is extended by the paused duration.
*   **Business Hours Correction**: SLA calculations are evaluated against the assigned `public.sla_calendars` and `public.business_hours`. If an incident occurs during off-hours or holidays, the SLA countdown is paused until the next business day starts.

---

## 13. SECURITY, ACCESS CONTROL & GDPR COMPLIANCE

Enforces strict tenant isolation, access controls, and data privacy.

### 13.1 Row-Level Security (RLS) policies
Every query must carry an active `organization_id` context. Cross-tenant queries are blocked at the database engine level.

### 13.2 Field-Level Access Control (RBAC)
*   **Internal Notes**: Customer portal sessions are restricted from querying records where `is_internal = true` or accessing `public.ticket_internal_notes`.
*   **Attachment Security**: Accessing file attachments requires verifying that the requester's user ID belongs to the tenant organization.

### 13.3 GDPR Compliance: Erase Workflows
*   Upon receiving an approved "Forget Me" request, the system triggers a secure redaction process.
*   Replaces the customer's name, email, phone number, and IP addresses with obfuscated placeholder values.
*   Clears communication bodies (`body_text`, `body_html`, `body_markdown`) in messages, or replaces them with `[REDACTED]`.
*   Unlinks and deletes associated files from storage.

---

## 14. PERFORMANCE & GROWTH OPTIMIZATIONS

To support high transaction volumes, the database utilizes the following performance optimization strategies:

*   **Monthly Partitioning**: `public.ticket_activity_logs` is range partitioned monthly on the `created_at` column.
*   **Materialized Views**: Aggregated metrics (such as agent average response times and daily ticket volumes) are queried from materialized views refreshed during low-traffic periods.
*   **Search Optimizations**: Full-text searches on tickets and messages use generated `tsvector` columns with GIN indexes, bypassing slow runtime wildcard searches (`LIKE %term%`).
*   **Text Trigram Matching**: Fuzzy searches on subjects use pg_trgm indexes.

---

## 15. EVENT CONTRACTS

The Support domain publishes events to notify other systems of ticket status and lifecycle updates.

### 15.1 Core System Events

#### 15.1.1 `ticket.created`
*   **Trigger**: A new ticket is successfully validated and inserted into the database.
*   **Payload Schema**:
```json
{
  "event_id": "evt_91a03f42-494a-4b05-9f1e-f3b140131109",
  "event_type": "ticket.created",
  "timestamp": "2026-06-29T06:00:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "ticket_number": "JUANET-10902",
    "subject": "Critical DB Connection Outage",
    "requester_user_id": "usr_7382f123-90ba-4cbd-a912-f04b2c39e120",
    "priority": "Critical",
    "status": "new",
    "source": "web"
  }
}
```

#### 15.1.2 `ticket.assigned`
*   **Trigger**: A ticket is assigned to a team or agent.
*   **Payload Schema**: Includes `ticket_id`, `assigned_team_id`, and `assigned_agent_id`.

#### 15.1.3 `ticket.replied`
*   **Trigger**: A new reply is logged.
*   **Payload Schema**: Includes `ticket_id`, `message_id`, and `sender_type` (agent/customer).

#### 15.1.4 `ticket.resolved`
*   **Trigger**: A ticket is marked as resolved.
*   **Payload Schema**: Includes `ticket_id`, `resolution_code`, and `resolution_time_seconds`.

#### 15.1.5 `sla.breached`
*   **Trigger**: An active SLA target misses its deadline.
*   **Payload Schema**: Includes `ticket_id`, `sla_instance_id`, `metric_type`, and `breached_timestamp`.

---

## 16. VALIDATION MATRIX

Below is the verification and testing checklist to ensure the lifecycle engine functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **State Machine** | Transition `new` -> `resolved` | **Rejected**. Must transition through `open`/`assigned`. |
| **State Machine** | Transition `resolved` -> `open` (Reopen) | **Permitted**. Resets active SLA resolution timers. |
| **State Machine** | Transition `closed` -> `open` | **Rejected**. Terminal state modification is blocked. |
| **Concurrency** | Simultaneous updates to same ticket | **Rejected**. Raises Optimistic Locking conflict on version mismatch. |
| **Security** | Customer session querying `is_internal = true` | **Empty/Access Denied**. Filtered at database driver level. |
| **SLA** | SLA pause on `waiting_for_customer` | **Success**. Timer pauses. |
| **GDPR** | Erase workflow request | **Success**. Replaces PII with placeholders, updates audit logs. |
| **Deduplication** | Identical tickets filed in 5-minute window | **Success**. Links the duplicate ticket as a reply thread to the original. |

---

This document serves as the architectural reference for implementing ticket lifecycles, routing rules, and automation systems within the JUANET Platform. All components must adhere strictly to these specifications.
