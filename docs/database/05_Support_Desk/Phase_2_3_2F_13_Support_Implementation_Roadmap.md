# JUANET Support Implementation Roadmap Specification
## Phase 2.3.2F.13 — Support Implementation Roadmap
**Document Version:** 1.0  
**Author:** Chief Enterprise Solution Architect & Principal Delivery Architect  
**Classification:** Technical / Implementation & Migration Roadmap  

---

## 1. IMPLEMENTATION PHILOSOPHY

Operating an enterprise support platform at scale demands an implementation strategy that guarantees zero service interruption, eliminates data drift, and ensures absolute stability of existing logical systems. The JUANET Support Domain delivery plan is governed by five core delivery principles:

```
                  CONTINUOUS VERIFICATION PIPELINE
  [Dependency Gates] ──> [Incremental Schemas] ──> [Dual-Write Channels]
                                                           │
                                                           ▼
  [Zero Downtime Release] <── [Performance Checks] <───────┘
```

*   **Dependency-First Delivery**: Structural base layers (schemas, index profiles, static lookups, security definitions) must be fully deployed and validated before application repositories, services, or APIs are constructed.
*   **Incremental Deployment**: Large, complex system transitions are broken down into small, isolated phases. Changes are released in modular updates to prevent wide blast radiuses during deployment failures.
*   **Zero Downtime (Blue-Green & Canary)**: Migration execution must never require taking systems offline. Any schema alteration, column separation, or table partition must leverage safe, non-blocking online execution patterns (e.g., using `CREATE INDEX CONCURRENTLY` and dual-write phases).
*   **Strict Backward Compatibility**: Database schemas and API interfaces must maintain compatibility with adjacent versions. Structural renames require a transition phase (add field, dual-write, migrate, deprecate, drop) to avoid breaking active integration clients.
*   **Continuous Verification**: Every delivery milestone is bound to a corresponding test suite (pgTAP assertions, API contracts, automated load simulations). A gate cannot be bypassed until its validation metrics are fully met.

---

## 2. MIGRATION ROADMAP (MIGRATION MATRIX)

The Support database schema is deployed across 14 discrete, sequential migrations. Each migration contains defined targets, execution rules, and isolated rollback triggers.

```
+-------------------------------------------------------------------------+
|                          MIGRATION ROADMAP                              |
|  [MIG-001] -> [MIG-002] -> [MIG-003] -> [MIG-004] -> [MIG-005]          |
|  [MIG-006] -> [MIG-007] -> [MIG-008] -> [MIG-009] -> [MIG-010]          |
|  [MIG-011] -> [MIG-012] -> [MIG-013] -> [MIG-014]                       |
+-------------------------------------------------------------------------+
```

---

### MIG-001: Lookup Tables Setup
*   **Purpose**: Establish core static tables (`public.support_statuses`, `public.support_priorities`, `public.support_categories`, `public.support_teams`, `public.sla_metrics`).
*   **Dependencies**: None.
*   **Rollback Strategy**: Truncate static schemas and drop lookup tables from system dictionary.
*   **Validation**: Assert lookup key counts and verify foreign key indexes are active.
*   **Complexity**: Low.

---

### MIG-002: Core Tickets Schema
*   **Purpose**: Provision the primary ticket storage ledger (`public.tickets`) including compound primary keys, indexes, and constraints.
*   **Dependencies**: MIG-001.
*   **Rollback Strategy**: Backup tables, drop foreign constraints, and drop `public.tickets` table.
*   **Validation**: Verify that inserting columns with invalid category or status IDs fails with foreign key violations.
*   **Complexity**: Medium.

---

### MIG-003: Conversations and Message Streams
*   **Purpose**: Provision partitioned messaging ledgers (`public.ticket_messages`) using 64-way hash partitioning.
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Drop active partitioned child tables and drop parent tables.
*   **Validation**: Confirm that inserting mock conversation records distributes items evenly across hash partitions.
*   **Complexity**: High.

---

### MIG-004: File and Attachment Registries
*   **Purpose**: Deploy attachment registries (`public.ticket_attachments`) and configure file integrity constraint checks.
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Drop tables and revoke storage bucket policies.
*   **Validation**: Insert test attachment metadata, verifying that incorrect file hashes or invalid signatures are blocked.
*   **Complexity**: Low.

---

### MIG-005: Ticket Activity History Partitioning
*   **Purpose**: Create monthly partitioned range tables (`public.ticket_activity_logs`, `public.ticket_status_history`).
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Archive and drop monthly partition schemas.
*   **Validation**: Insert records spanning multiple calendar months and confirm they route to corresponding monthly tables.
*   **Complexity**: High.

---

### MIG-006: SLA Targets and Tracker Instances
*   **Purpose**: Provision tables for tracking active SLA targets, SLA parameters, and active trackers (`public.ticket_sla_instances`, `public.sla_rules`).
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Drop active tracker tables and delete active triggers.
*   **Validation**: Verify that inserting ticket rows automatically triggers corresponding SLA tracker creation.
*   **Complexity**: Medium.

---

### MIG-007: Knowledge Base and Search Taxonomies
*   **Purpose**: Deploy knowledge base articles, categories, translations, and vector indexes (`public.kb_articles`, `public.kb_versions`, `public.kb_embeddings`).
*   **Dependencies**: None.
*   **Rollback Strategy**: Revoke pgvector indexes and drop kb schemas.
*   **Validation**: Query articles with natural language inputs to confirm correct pgvector and FTS index matches.
*   **Complexity**: Medium.

---

### MIG-008: Customer Satisfaction and Survey Engines
*   **Purpose**: Deploy customer satisfaction surveys and agent evaluation records (`public.ticket_csat_surveys`, `public.agent_qa_evaluations`).
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Drop feedback tables and purge index contexts.
*   **Validation**: Ensure CSAT submissions accurately update associated agent scorecards.
*   **Complexity**: Low.

---

### MIG-009: AI Assistant Log Registries
*   **Purpose**: Deploy AI prompt history and suggested response logging tables (`public.ai_suggested_responses`, `public.ai_interaction_logs`).
*   **Dependencies**: MIG-002.
*   **Rollback Strategy**: Drop AI tables and clear associated search indexes.
*   **Validation**: Confirm that metadata payloads are written cleanly to JSONB targets.
*   **Complexity**: Low.

---

### MIG-010: Event Outbox System
*   **Purpose**: Provision transaction-safe event outbox tables (`audit.outbound_events`).
*   **Dependencies**: None.
*   **Rollback Strategy**: Drop table and clear background processor queues.
*   **Validation**: Mutate a core support row and confirm the action automatically writes an outbox log.
*   **Complexity**: Medium.

---

### MIG-011: Materialized View Compilations
*   **Purpose**: Compile aggregated operational dashboards and metrics into materialized view tables.
*   **Dependencies**: MIG-002, MIG-005.
*   **Rollback Strategy**: Drop materialized views.
*   **Validation**: Verify that calling concurrent refreshes updates dashboard metrics in under 50ms.
*   **Complexity**: Medium.

---

### MIG-012: Telemetry Logging Schema
*   **Purpose**: Deploy real-time system diagnostic and API connection logging tables (`public.support_telemetry_logs`).
*   **Dependencies**: None.
*   **Rollback Strategy**: Purge table.
*   **Validation**: Ensure API gateway requests generate corresponding telemetry logs.
*   **Complexity**: Low.

---

### MIG-013: Security and RLS Activations
*   **Purpose**: Enable Row-Level Security on all active tables and deploy tenant isolation policies.
*   **Dependencies**: MIG-001 to MIG-012.
*   **Rollback Strategy**: Disable Row-Level Security on database tables.
*   **Validation**: Execute query assertions across different tenant scopes to confirm data separation.
*   **Complexity**: High.

---

### MIG-014: Seed Data Populations
*   **Purpose**: Inject default system categories, priority targets, system-status entries, and SLA rules.
*   **Dependencies**: MIG-001, MIG-006.
*   **Rollback Strategy**: Truncate all tables.
*   **Validation**: Verify that system queries return populated lookup tables.
*   **Complexity**: Low.

---

## 3. ORM ENTITY GENERATION ORDER

Object-Relational Mapping (ORM) entities must be generated in a precise sequence to resolve structural dependency trees and preserve database integrity:

```
1. Lookup Schemas (Statuses, Priorities, Categories, Teams)
                       │
                       ▼
2. Target Schemas (Tickets, Users, Security Contexts)
                       │
                       ▼
3. Communication & Logging (Messages, Attachments, Activity Logs)
                       │
                       ▼
4. SLA, CSAT, & AI Log engines (SLA Instances, CSAT Scores, AI Metrics)
```

1.  **Phase 1: Lookup Entities**:
    *   `SupportStatus`, `SupportPriority`, `SupportCategory`, `SupportTeam`, `SLAMetricType`.
2.  **Phase 2: Target Entities**:
    *   `Ticket`, `User`, `TenantOrganization`.
3.  **Phase 3: Communication & Logging Entities**:
    *   `TicketMessage`, `TicketAttachment`, `TicketActivityLog`, `TicketStatusHistory`.
4.  **Phase 4: SLA, CSAT, & AI Log Entities**:
    *   `TicketSLAInstance`, `SLARule`, `TicketCSATSurvey`, `AgentQAEvaluation`, `AISuggestedResponse`, `AIInteractionLog`, `OutboundEvent`.

---

## 4. REPOSITORY LAYER DEVELOPMENT SEQUENCE

The repository layer provides abstract database access for application code. Repositories must be constructed in order of increasing complexity:

*   **Step 1: Core Lookup Repositories**: Build basic lookup systems (`SupportCategoryRepository`, `SupportTeamRepository`) to manage category routing rules and agent assignments.
*   **Step 2: Ticket Ledger Repositories**: Construct the primary ticket data repository (`TicketRepository`), implementing Optimistic Concurrency Control checks and tenant session parameters.
*   **Step 3: Message & Attachment Repositories**: Implement repositories to handle high-frequency message streams and attachment uploads (`MessageRepository`, `AttachmentRepository`).
*   **Step 4: SLA & Tracking Repositories**: Build time-sensitive repositories (`SLARepository`, `ActivityLogRepository`) to support real-time SLA evaluations and audit logs.
*   **Step 5: Feedback & Analytics Repositories**: Implement analytics and reporting repositories (`CSATRepository`, `QARepository`, `DashboardRepository`).

---

## 5. SERVICE LAYER CONSTRUCTION ORDER

Services coordinate business logic and manage database transactions. They must be constructed in a logical sequence to resolve dependency trees:

```
+-------------------------------------------------------------------------+
|                              SERVICE LAYER                              |
|  [TicketService] ──────────> [AssignmentService]                        |
|        │                             │                                  |
|        ▼                             ▼                                  |
|  [ConversationService] ────> [SLAService] ──────────────────────────┐   |
|                                                                     │   |
|                                                                     ▼   |
|  [KBService] ──> [FeedbackService] ──> [AIService] ──> [Dashboard/SRE]  |
+-------------------------------------------------------------------------+
```

### 5.1 `TicketService`
*   **Core Responsibilities**: Manage ticket lifecycles, enforce state machine transitions, write to activity logs, and dispatch transaction events to outbox tables.
*   **Dependencies**: `TicketRepository`, `ActivityLogRepository`.

### 5.2 `AssignmentService`
*   **Core Responsibilities**: Manage ticket routing, track team capacities, and assign tickets to available agents.
*   **Dependencies**: `TicketService`, `TeamRepository`.

### 5.3 `ConversationService`
*   **Core Responsibilities**: Manage message threads, process attachment uploads, compute content hashes, and route messages to outbound channels.
*   **Dependencies**: `MessageRepository`, `AttachmentRepository`.

### 5.4 `SLAService`
*   **Core Responsibilities**: Calculate SLA deadlines, handle pause-and-resume transitions, and manage escalation workflows.
*   **Dependencies**: `TicketService`, `SLARepository`.

### 5.5 `KnowledgeBaseService`
*   **Core Responsibilities**: Manage article drafts, handle version rollbacks, organize category structures, and manage pgvector-based searches.
*   **Dependencies**: `KBRepository`.

### 5.6 `CustomerFeedbackService`
*   **Core Responsibilities**: Process CSAT surveys, compute agent QA scores, and update feedback metrics.
*   **Dependencies**: `CSATRepository`.

### 5.7 `AISupportService`
*   **Core Responsibilities**: Generate suggested replies, calculate text sentiment scores, and log prompt interactions.
*   **Dependencies**: `ConversationService`.

### 5.8 `DashboardService`
*   **Core Responsibilities**: Query materialized views and compile performance dashboards.
*   **Dependencies**: `DashboardRepository`.

### 5.9 `TelemetryService`
*   **Core Responsibilities**: Track API performance and log system errors.
*   **Dependencies**: `TelemetryRepository`.

---

## 6. REST API IMPLEMENTATION ROADMAP

APIs expose the service layer to web portals and mobile applications. Endpoints are released in four distinct phases:

```
  Phase 1 (Core Operations)  ──>  Phase 2 (Communications)
              │                                │
              ▼                                ▼
  Phase 3 (SLA & Management) ──>  Phase 4 (AI & Analytics)
```

*   **Phase 1: Core Ticket Operations** (v1/tickets):
    *   `POST /api/v1/tickets`: Create new support ticket.
    *   `GET /api/v1/tickets/{id}`: Fetch detailed ticket profile.
    *   `PATCH /api/v1/tickets/{id}`: Modify ticket parameters (e.g., tags, assignment, status).
*   **Phase 2: Conversations & Communications** (v1/conversations):
    *   `GET /api/v1/tickets/{id}/messages`: Fetch ticket message threads.
    *   `POST /api/v1/tickets/{id}/messages`: Append response to thread.
    *   `POST /api/v1/attachments/upload`: Upload attachment metadata.
*   **Phase 3: SLA & Knowledge Systems** (v1/kb, v1/sla):
    *   `GET /api/v1/kb/search`: Search knowledge base with vectors or keyword filters.
    *   `POST /api/v1/kb/articles`: Create article draft.
    *   `POST /api/v1/sla/policies`: Propose updated SLA targets (Maker-Checker approval).
*   **Phase 4: Feedback, AI, & Dashboards** (v1/feedback, v1/ai, v1/telemetry):
    *   `POST /api/v1/tickets/{id}/csat`: Submit customer satisfaction survey.
    *   `GET /api/v1/ai/suggest`: Retrieve AI suggested replies.
    *   `GET /api/v1/dashboards/operational`: Fetch dashboard metrics.

---

## 7. EVENT CONSUMERS DEVELOPMENT ORDER

Event consumers process messages asynchronously, updating data states and dispatching system notifications:

1.  **`NotificationConsumer`**: Listens for ticket assignment and response events, sending email and SMS updates to users.
2.  **`AssignmentEngineConsumer`**: Processes unassigned ticket events, running allocation algorithms to route tickets to available agents.
3.  **`SLACriggerConsumer`**: Monitors ticket state transitions to pause, resume, or recalculate active SLA timers.
4.  **`CRMSynchronizationConsumer`**: Syncs ticket statuses with external CRM systems.
5.  **`AuditLogAggregatorConsumer`**: Processes system action logs, generating historical logs and checking security parameters.

---

## 8. BACKGROUND WORKERS IMPLEMENTATION SEQUENCE

Stateless background workers perform time-sensitive tasks outside the user request path:

```
                  BACKGROUND WORKER DEPLOYMENT ORDER
  [SLA Worker (Priority 1)] ────> [Escalation Worker (Priority 2)]
                                         │
                                         ▼
  [Index Refresher (Priority 4)] <── [AI Assistant (Priority 3)]
```

*   **Worker 1: SLA Evaluation Worker** (Priority 1):
    *   *Task*: Scan active SLA targets (`public.ticket_sla_instances`) in batches of 100, flag breaches, and write escalation events.
    *   *Execution*: Runs continuously every 10 seconds using `pg_cron` loops.
*   **Worker 2: Escalation Dispatch Worker** (Priority 2):
    *   *Task*: Process breached SLA records and escalate tickets to supervisors.
    *   *Execution*: Triggered immediately by incoming SLA breach events.
*   **Worker 3: Assignment Engine Worker** (Priority 2):
    *   *Task*: Route unassigned ticket backlogs to available support agents.
    *   *Execution*: Triggered by new ticket creation events.
*   **Worker 4: Omnichannel Sync Worker** (Priority 3):
    *   *Task*: Poll external communication APIs (Slack, WhatsApp, SMTP) and route inbound messages to ticket threads.
    *   *Execution*: Runs continuously every 15 seconds.
*   **Worker 5: Vector Index Worker** (Priority 3):
    *   *Task*: Detect modifications to knowledge base articles, generate vector embeddings, and update pgvector search indexes.
    *   *Execution*: Triggered by article publishing events.
*   **Worker 6: AI Assistant Worker** (Priority 4):
    *   *Task*: Analyze ticket sentiment, suggest response drafts, and update interaction histories.
    *   *Execution*: Runs asynchronously in response to customer message events.
*   **Worker 7: Materialized View Refresher** (Priority 4):
    *   *Task*: Refresh analytical materialized views concurrently, updating team dashboards.
    *   *Execution*: Runs every 5 minutes.
*   **Worker 8: Data Archiving Worker** (Priority 5):
    *   *Task*: Detach old partitions, compress data states, export records to Parquet files, and clear OLTP databases.
    *   *Execution*: Runs nightly at 02:00 UTC.

---

## 9. FRONTEND WORKSPACE DELIVERY MILESTONES

The user interface is released in six progressive milestones:

```
+-------------------------------------------------------------------------+
|                           FRONTEND ROADMAP                              |
|  [Milestone 1: Portal] ──> [Milestone 2: Agent] ──> [Milestone 3: KB]   |
|                                                            │            |
|                                                            ▼            |
|  [Milestone 6: Admin]  <── [Milestone 5: AI]    <── [Milestone 4: Dash] |
+-------------------------------------------------------------------------+
```

1.  **Milestone 1: Customer Ticket Portal**:
    *   *Features*: Ticket creation form, ticket history views, attachment uploads, and real-time chat.
2.  **Milestone 2: Agent Workspace Interface**:
    *   *Features*: Assigned queue views, split message panels, private notes, and status transition controllers.
3.  **Milestone 3: Integrated Knowledge Base**:
    *   *Features*: Document directory, localized article view, and vector search.
4.  **Milestone 4: Operational Dashboard**:
    *   *Features*: Ticket volume graphs, team capacity charts, SLA breach tickers, and live CSAT feedback streams.
5.  **Milestone 5: AI Copilot Helper**:
    *   *Features*: Suggested responses box, sentiment analyzers, and quick article referencing tools.
6.  **Milestone 6: System Administration & Rules Configuration**:
    *   *Features*: SLA rule designer, team shift scheduler, category router, and dual-authorization queue panels.

---

## 10. SYSTEM TESTING SEQUENCE

Tests are executed in a sequential pipeline to identify issues early and verify high availability:

```
  Unit Tests ──> Database Constraints ──> Security & RLS ──> Load Tests ──> Chaos Failover
```

1.  **Layer 1: Unit & Functional Verification**: Run local unit tests to verify core algorithms, date parsing utilities, and data validation rules.
2.  **Layer 2: Schema Constraints & Trigger Verification**: Execute pgTAP test suites to verify database constraints, state machines, and outbox triggers.
3.  **Layer 3: Security & Logical RLS Audits**: Perform automated penetration testing to verify tenant isolation, RBAC privileges, and malicious payload sanitization.
4.  **Layer 4: High-Scale Load & Stress Benchmarks**: Simulate peak operational loads to monitor query latencies, index efficiency, and resource utilization.
5.  **Layer 5: Chaos & Recovery Drills**: Intentionally inject failures (kill database replicas, simulate SMTP timeouts) to evaluate system resilience.
6.  **Layer 6: Regression & UAT**: Execute automated end-to-end tests across browser environments, preparing the build for deployment.

---

## 11. DEPLOYMENT STAGE STRATEGY

Releases progress through four isolated environments to ensure stability and control:

| Stage | Target Infrastructure | Deployment Strategy | Rollback Window | Verification Scope |
| :--- | :--- | :--- | :---: | :--- |
| **Development**| Single DB / Container | Direct Push | Immediate | Developer testing, prototype checks |
| **Testing** | Dedicated DB Cluster | Automated CI/CD | Immediate | pgTAP, API contracts, integration |
| **Staging** | Production Replica | Blue-Green | 1 Hour | High-scale load tests, failover drills|
| **Production** | Multi-Region Cluster | Blue-Green / Canary | 15 Minutes | Operational system, active monitoring |

---

## 12. PRODUCTION READINESS CHECKLIST

Before promoting the Support domain to production, engineers must verify that all readiness criteria are met:

*   [ ] **Primary Key Standard**: All tables utilize sequentially ordered UUIDv7 primary keys to prevent index fragmentation.
*   [ ] **Schema Partitions**: Ranger-based monthly partitions are established for activity tables, and hash partitions are configured for message logs.
*   [ ] **Indices Configured**: Composite indexes are prefixed with `organization_id` to optimize RLS queries, and covering indexes are configured for dashboard widgets.
*   [ ] **Row-Level Security**: Row-level security is active across all tables, and RLS bypasses are restricted to verified replication systems.
*   [ ] **Locking Optimization**: Data writers retrieve queue rows using non-blocking `FOR UPDATE SKIP LOCKED` queries.
*   [ ] **SLA Engine Rules**: Business calendars and timezone transition checks are fully deployed and integrated.
*   [ ] **Outbox Pattern**: Transaction-safe event outbox rules are active and integrated with downstream services.
*   [ ] **API Rate Limiting**: Token-bucket limiters are active on all public API endpoints.
*   [ ] **File Sanitizers**: File upload gateways scan incoming attachments and restrict downloads to short-lived signed URLs.
*   [ ] **Backup Policies**: Point-In-Time-Recovery (PITR) systems are configured and verified.

---

## 13. PROJECT RISK REGISTER

We track potential risks to project delivery alongside defined mitigation plans:

| Risk Identifier | Risk Description | Severity | Probability | Structural Mitigation Strategy |
| :---: | :--- | :---: | :---: | :--- |
| **RSK-001** | Database lock contention on message tables under heavy load. | Critical | Low | Implement hash partitioning and transition writes to append-only operations. |
| **RSK-002** | RLS planning overhead slows down complex search queries. | High | Medium| Prefix all compound indexes with `organization_id` to optimize query planning. |
| **RSK-003** | Transient network issues cause outbox events to drop. | High | Low | Enforce idempotent consumer logging and retry schedules on event processors. |
| **RSK-004** | Unauthorized SLA modifications bypass compliance controls. | Critical | Low | Enforce Maker-Checker dual-authorization policies on SLA configuration adjustments. |
| **RSK-005** | Malware uploads compromise customer attachment storage. | Critical | Low | Enforce byte signature checks and integrate automated virus scanners on file uploads. |

---

## 14. EMERGENCY ROLLBACK PLAYBOOK

When a deployment fails or causes service degradation, operations teams must execute the following rollback steps:

```
  Identify Failure ──> Route Traffic to Green ──> Run Rollback Scripts ──> Verify Restored State
```

1.  **Immediate Isolation**: If issues are detected during canary releases, immediately route user traffic away from the affected node back to the stable environment.
2.  **Database Migration Rollback**: Execute rollback scripts to revert database schema changes:
    ```sql
    -- Example schema rollback script
    ALTER TABLE public.tickets DISABLE ROW LEVEL SECURITY;
    DROP POLICY IF EXISTS tickets_tenant_isolation_policy ON public.tickets;
    DROP TABLE IF EXISTS public.tickets;
    ```
3.  **Restore Application State**: Roll back application container deployments to the last known stable release version.
4.  **Verify Restored State**: Run automated testing suites to verify system functionality and confirm database consistency.

---

## 15. LONG-TERM EVOLUTION ROADMAP

The long-term development pipeline guides the ongoing enhancement of the Support domain:

*   **Year 1: Foundation Stability**: Deploy core database schemas, establish tenant isolation, and optimize SLA evaluation performance.
*   **Year 2: Intelligent Assistance**: Enhance vector-based search algorithms, improve AI reply suggestion accuracy, and deploy automated category routers.
*   **Year 3: Global Scale**: Implement multi-region active-active database replication and deploy Debezium change-data-capture pipelines.

---

## 16. ENGINEERING EXECUTION MATRIX

The matrix below maps Support subsystems to their required database migrations, service layers, API paths, and target testing stages:

| Subsystem Component | Migration Code | Service Dependency | REST API Route | Testing Stage | Deployment Milestone |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **Core Tickets** | MIG-001, MIG-002 | `TicketService` | `/api/v1/tickets` | Unit, Integration, RLS | M1 |
| **Conversations** | MIG-003, MIG-004 | `ConversationService`| `/api/v1/tickets/msg` | Load, Security, FTS | M2 |
| **Activity History**| MIG-005 | `TicketService` | `/api/v1/tickets/logs`| Database, Partition | M2 |
| **SLA Tracking** | MIG-006 | `SLAService` | `/api/v1/sla/policies`| Unit, SLA Clock, DST | M3 |
| **Knowledge Base** | MIG-007 | `KnowledgeBaseService`| `/api/v1/kb/search` | FTS, pgvector Search | M3 |
| **Feedback System** | MIG-008 | `FeedbackService` | `/api/v1/feedback` | Integration, QA | M4 |
| **AI Assistant** | MIG-009 | `AISupportService` | `/api/v1/ai/suggest` | Safety, Prompt Injection| M5 |
| **Event Outbox** | MIG-010 | `TicketService` | `/api/v1/events` | Integration, Outbox | M5 |
| **Dashboards** | MIG-011 | `DashboardService` | `/api/v1/dashboards` | Load, Refresh | M6 |
| **Telemetry** | MIG-012 | `TelemetryService` | `/api/v1/telemetry` | SRE, Telemetry | M6 |

---

## 17. CROSS REFERENCES

This implementation roadmap coordinates the delivery of systems, configurations, and physical schemas defined across the following support manuals:

*   **Support Physical Tables**: Outlines core database tables (`public.tickets`, `public.ticket_messages`) documented in `Phase_2_3_2F_Support_Physical_Tables.md`.
*   **Ticket Lifecycle**: Coordinates state machine transitions verified in `Phase_2_3_2F_1_Ticket_Lifecycle_Engine.md`.
*   **SLA Engine**: Coordinates response timers and business hours structured in `Phase_2_3_2F_2_SLA_and_Escalation_Engine.md`.
*   **Knowledge Base**: Details authoring pipelines and search taxonomies mapped in `Phase_2_3_2F_3_Knowledge_Base_Architecture.md`.
*   **Omnichannel Communication**: Connects communication sync routines defined in `Phase_2_3_2F_4_Omnichannel_Communication_Engine.md`.
*   **Customer Satisfaction & QA**: Coordinates feedback loops and agent evaluations detailed in `Phase_2_3_2F_5_Customer_Satisfaction_and_Quality_Assurance.md`.
*   **AI Copilot**: Integrates prompt safety and suggestion logic governed by `Phase_2_3_2F_6_AI_Copilot_and_Intelligent_Support_Assistance.md`.
*   **Event Contracts**: Coordinates transactional outbox patterns defined in `Phase_2_3_2F_7_Support_Integration_and_Event_Contracts.md`.
*   **Dashboards & Telemetry**: Details dashboard metric aggregations structured in `Phase_2_3_2F_8_Support_Dashboards_and_Operational_Telemetry.md`.
*   **Performance & Scalability**: Guides database partitions and index optimizations detailed in `Phase_2_3_2F_9_Support_Performance_and_Scalability.md`.
*   **Security & Compliance**: Outlines RLS, encryption keys, and auditing policies defined in `Phase_2_3_2F_10_Support_Security_and_Compliance.md`.
*   **Testing & Validation**: Integrates test suites and quality gates governed by `Phase_2_3_2F_11_Support_Testing_and_Validation.md`.
*   **ADR Registry**: Matches architectural decision histories documented in `Phase_2_3_2F_12_Support_Architecture_Decision_Records.md`.

---

This document serves as the authoritative implementation manual for coordinating the delivery and deployment of the JUANET Support platform. All execution phases and delivery teams must adhere strictly to these roadmaps.
