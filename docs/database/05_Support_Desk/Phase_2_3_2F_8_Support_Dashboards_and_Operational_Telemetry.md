# JUANET Support Dashboards & Operational Telemetry Specification
## Phase 2.3.2F.8 — Support Dashboards and Operational Telemetry
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Operational Analytics  

---

## 1. OPERATIONAL PHILOSOPHY

The JUANET Operational Telemetry architecture enforces a clean separation of concerns between transactional state engines and analytical processing engines. To prevent database lock contention and maintain transactional speed, the system classifies, partitions, and retrieves state-based operational data across distinct tiers.

```
+---------------------------------------------------------------------------------+
|                        OLTP TRANSACTIONS (Read/Write)                           |
|      - public.tickets, public.conversations, public.ticket_messages             |
|      - Highly normalized, optimized for single-row index updates                |
+---------------------------------------------------------------------------------+
                                         │
                         Asynchronous Replication / Outbox Event
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                       ANALYTICAL TELEMETRY LAYER (OLAP)                         |
|      - Materialized Views (mv_support_dashboard, mv_agent_performance)          |
|      - Time-series logging, aggregated operational metrics                      |
+---------------------------------------------------------------------------------+
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                        VISUALIZATION / DASHBOARD LAYER                          |
|      - Read Replicas, Caching (Redis), Materialized Views                       |
|      - Strictly read-only, decoupled from transactional write locks            |
+---------------------------------------------------------------------------------+
```

### 1.1 Data Classification
*   **Transactional Data (System of Record)**: Highly normalized tables representing the immediate, active state of support records (e.g., active ticket FSM statuses, conversation participants, messaging streams, active SLA timers). These tables are optimized for high-write/update frequencies under ACID transactional guarantees.
*   **Operational Metrics**: Real-time calculated indicators computed on active transactions (e.g., active ticket counts in queue, SLA countdowns, active queue lengths). These are short-lived, refreshed frequently, and used by live coordinators to balance queue workloads.
*   **Reporting**: Historical, aggregated summaries evaluated over structured intervals (e.g., weekly CSAT counts, monthly first contact resolution rates). These are retrieved from dedicated materialized views or read replicas, isolating computational stress from active agent transactions.
*   **Analytics**: Long-term trend analysis, cohort correlations, and root-cause clustering (e.g., tracking customer sentiment shifts across several quarters or evaluating agent productivity trends post-coaching). This data is loaded into dedicated warehouse buckets or isolated database partitions.
*   **Observability**: System health diagnostics (e.g., database connection pool depths, background job queues, API latencies, embedding generation runtimes, email bounce frequencies). These are kept in isolated application logs and time-series indexes, preventing diagnostic processes from polluting customer support ledgers.

### 1.2 Dashboards Must Never Become Systems of Record
To protect database integrity, **analytical dashboards and materialized views are strictly prohibited from serving as systems of record (SoR)**. This rule is enforced because:
1.  **Staleness and Cache Drift**: Analytics and dashboards read from read replicas, cached indices, or materialized views, which are subject to replication lags. Treating a lagging dashboard view as the authoritative source of truth will lead to state corruption.
2.  **No Direct State Mutations**: Dashboards must never write back to transactional tables directly. Status changes, assignments, or SLA resets must run through the validated transaction pathways of the FSM (e.g., invoking `ticket.status_changed` transaction methods), ensuring proper validation and audit logging.
3.  **Isolation of Compute Overload**: Analytical queries combine, group, and calculate millions of records. Isolating these queries from OLTP database contexts ensures that high-volume reporting loads never block customer-facing messaging lines or agent workspaces.

---

## 2. DASHBOARD ARCHITECTURE

The platform implements a multi-tiered dashboard model optimized for specific user personas, refresh requirements, and security permissions.

| Dashboard Profile | Primary Audience | Target Latency / Refresh Interval | Security Role / RBAC Requirements | Primary Data Source |
| :--- | :--- | :--- | :--- | :--- |
| **Agent Workspace** | Front-line support agents | 1 second (Live WebSockets) | `support_agent` | OLTP active tickets & queues (Indexed) |
| **Team Lead / Supervisor** | Queue coordinators, team leads | 10 seconds (Polling / WS) | `support_supervisor` | Materialized Views with RLS / Read Replicas |
| **Support Manager** | Department managers | 5 minutes (Materialized) | `support_manager` | Materialized Views with RLS / Read Replicas |
| **Operations Dashboard**| System administrators, DevOps | 5 seconds (Time-series) | `platform_administrator`| Time-series logs & telemetry databases |
| **Executive Analytics**| Directors, C-level executives | 1 hour (Materialized / Cache) | `executive_viewer` | Aggregated Materialized Views & Read Replicas |
| **Customer Portal** | Authenticated clients, guests | Real-time (Active Session) | `portal_customer` | Local client-authenticated session filters |

---

## 3. DASHBOARD WIDGETS

The operational dashboard is composed of specialized widgets. The catalog below defines their formulas, refresh intervals, and access controls:

### 3.1 Live Queue & Operational Status Widgets

#### 3.1.1 Open Tickets Count
*   **Purpose**: Displays the total count of active, unresolved tickets within an organization's queue.
*   **Formula / Calculation**:
    $$\text{Open Tickets} = \sum (\text{Tickets WHERE status IN ('open', 'assigned', 'customer_replied', 'in_progress')})$$
*   **Refresh Interval**: Real-time (WebSocket update on state mutation).
*   **Data Source**: `public.tickets` (filtered by active `organization_id`).
*   **Permissions**: `support_agent`, `support_supervisor`, `support_manager`.
*   **Drill-down Capability**: Clicking redirects the user to the active ticket queue page, pre-filtered by unresolved statuses.

#### 3.1.2 Tickets by Priority Heatmap
*   **Purpose**: Visualizes active tickets grouped by priority to highlight critical bottlenecks.
*   **Formula / Calculation**:
    $$\text{Group Count} = \text{Count of tickets grouped by } \mathtt{priority} \text{ WHERE status } \neq \mathtt{'closed'}$$
*   **Refresh Interval**: 10 seconds.
*   **Data Source**: `public.tickets`.
*   **Permissions**: `support_agent`, `support_supervisor`, `support_manager`.
*   **Drill-down Capability**: Expands a detailed list of tickets filtered by the selected priority (e.g., `Urgent`).

#### 3.1.3 SLA Countdown Timer
*   **Purpose**: Visualizes active SLA targets, displaying remaining time before breach.
*   **Formula / Calculation**:
    $$\text{Time Remaining} = \text{SLA Deadline Timestamp} - \text{Current Server Timestamp}$$
*   **Refresh Interval**: 1 second (Calculated client-side from server deadline).
*   **Data Source**: `public.ticket_sla_statuses`.
*   **Permissions**: `support_agent`, `support_supervisor`, `support_manager`.
*   **Drill-down Capability**: Redirects agents directly to the ticket interface of the closest-to-breach record.

#### 3.1.4 CSAT Gauge
*   **Purpose**: Displays the overall customer satisfaction rate for the current reporting period.
*   **Formula / Calculation**:
    $$\text{CSAT \%} = \left( \frac{\text{Completed surveys with rating } \ge 4}{\text{Total completed surveys with rating}} \right) \times 100$$
*   **Refresh Interval**: 5 minutes.
*   **Data Source**: `public.ticket_surveys`.
*   **Permissions**: `support_supervisor`, `support_manager`, `executive_viewer`.
*   **Drill-down Capability**: Launches the raw survey response viewer, pre-filtered for ratings of 1 or 2.

#### 3.1.5 AI Suggestion Acceptance Rate
*   **Purpose**: Tracks how often agents use AI-suggested responses in their replies.
*   **Formula / Calculation**:
    $$\text{Acceptance \%} = \left( \frac{\text{Suggested replies inserted unchanged or edited } \le 10\% \text{ distance}}{\text{Total suggested replies displayed}} \right) \times 100$$
*   **Refresh Interval**: 5 minutes.
*   **Data Source**: `public.ai_interaction_logs`.
*   **Permissions**: `support_supervisor`, `support_manager`.
*   **Drill-down Capability**: Displays suggestions vs. final sent drafts, highlighting areas for prompt engineering optimizations.

---

## 4. MATERIALIZED VIEWS

To protect OLTP databases from slow aggregate queries, the platform uses **Materialized Views** to process metrics. These views are refreshed concurrently on read replicas to keep data fresh without blocking active queues.

```
+---------------------------------------------------------------------------------+
|                        OLTP DATABASE (Write Operations)                         |
|             Updates on tickets, conversations, SLA status, QA logs              |
+---------------------------------------------------------------------------------+
                                         │
                             Asynchronous Replication
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                         READ-ONLY REPLICA DATABASE                              |
|           Executes REFRESH MATERIALIZED VIEW CONCURRENTLY queries               |
+---------------------------------------------------------------------------------+
```

### 4.1 `public.mv_support_dashboard` (Unified Operational Snapshot)

```sql
CREATE MATERIALIZED VIEW public.mv_support_dashboard AS
SELECT 
    t.organization_id,
    COUNT(t.id) AS total_tickets,
    COUNT(t.id) FILTER (WHERE t.status = 'open') AS open_count,
    COUNT(t.id) FILTER (WHERE t.status = 'assigned') AS assigned_count,
    COUNT(t.id) FILTER (WHERE t.status = 'customer_replied') AS customer_replied_count,
    COUNT(t.id) FILTER (WHERE t.status = 'in_progress') AS in_progress_count,
    COUNT(t.id) FILTER (WHERE t.status = 'resolved') AS resolved_count,
    COUNT(t.id) FILTER (WHERE t.status = 'closed') AS closed_count,
    COUNT(t.id) FILTER (WHERE t.priority = 'urgent' AND t.status != 'closed') AS active_urgent_count,
    AVG(EXTRACT(EPOCH FROM (t.resolved_at - t.created_at)) FILTER (WHERE t.status IN ('resolved', 'closed')))::numeric(12,2) AS avg_resolution_time_seconds
FROM public.tickets t
GROUP BY t.organization_id
WITH NO DATA;

-- Enforce high-performance concurrent refreshes
CREATE UNIQUE INDEX mv_support_dashboard_org_idx ON public.mv_support_dashboard (organization_id);
```

### 4.2 `public.mv_agent_performance` (Aggregated Capability Metrics)

```sql
CREATE MATERIALIZED VIEW public.mv_agent_performance AS
SELECT 
    t.organization_id,
    t.assigned_agent_id AS agent_user_id,
    COUNT(t.id) FILTER (WHERE t.status IN ('resolved', 'closed')) AS resolved_count,
    COUNT(t.id) FILTER (WHERE t.status != 'closed' AND t.status != 'resolved') AS active_assigned_count,
    AVG(EXTRACT(EPOCH FROM (t.first_responded_at - t.created_at)))::numeric(12,2) AS avg_first_response_seconds,
    COUNT(t.id) FILTER (WHERE t.sla_breach_flag = true) AS total_sla_breaches,
    (COUNT(t.id) FILTER (WHERE t.sla_breach_flag = false)::numeric / NULLIF(COUNT(t.id), 0) * 100)::numeric(5,2) AS sla_compliance_percent
FROM public.tickets t
WHERE t.assigned_agent_id IS NOT NULL
GROUP BY t.organization_id, t.assigned_agent_id
WITH NO DATA;

-- Enforce high-performance concurrent refreshes
CREATE UNIQUE INDEX mv_agent_performance_uidx ON public.mv_agent_performance (organization_id, agent_user_id);
```

### 4.3 Concurrent Refresh Policy and Trigger Routines
To keep views updated without degrading query performance, refreshes are executed as asynchronous concurrent background tasks:

```sql
-- Executed via a cron scheduler (e.g., pg_cron or background worker) every 5 minutes
REFRESH MATERIALIZED VIEW CONCURRENTLY public.mv_support_dashboard;
REFRESH MATERIALIZED VIEW CONCURRENTLY public.mv_agent_performance;
```

*   **Concurrency Constraint**: Refresh queries must use the `CONCURRENTLY` keyword. This requires a unique index on the materialized view, allowing users to query old data while the view is updated in the background.
*   **Partition Awareness**: On monthly partitioned tables (e.g., `public.ticket_messages_partitioned`), materialized queries target specific active partition tables rather than scanning historical archives, reducing disk I/O.

---

## 5. KPI ENGINE

The KPI Engine acts as the central calculator for operational and performance metrics, computing key performance indicators across the Support domain:

```
                  +----------------------------------------------+
                  |               SUPPORT KPI ENGINE             |
                  |  - Computes operational efficiency metrics   |
                  +----------------------------------------------+
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
+------------------+             +------------------+             +------------------+
|      Speed       |             |     Quality      |             |    Automation    |
| - First Response |             | - CSAT, NPS, CES |             | - AI Acceptance  |
| - Resolution     |             | - SLA Compliance |             | - Deflection     |
+------------------+             +------------------+             +------------------+
```

### 5.1 Speed & Efficiency Metrics

#### 5.1.1 Average First Response Time (AFRT)
*   **Purpose**: Measures the average time it takes for an agent to reply to a customer's initial inquiry.
*   **Formula**:
    $$\text{AFRT} = \frac{\sum(\text{FirstResponseTimestamp} - \text{TicketCreationTimestamp})}{\text{Total Responded Tickets}}$$
*   **Computation Frequency**: Hourly (from read replica).
*   **Target Operational Threshold**: 15 minutes (Standard SLA).
*   **Alerting Rules**: If AFRT increases by more than 25% over the monthly baseline, the system alerts the support manager.

#### 5.1.2 Average Handle Time (AHT)
*   **Purpose**: Tracks the average duration of active, focused agent effort on a single ticket.
*   **Formula**:
    $$\text{AHT} = \frac{\sum(\text{Agent Work Duration Logs})}{\text{Total Resolved Tickets}}$$
*   **Computation Frequency**: Daily.
*   **Target Operational Threshold**: 10 minutes.

### 5.2 Quality and Customer Experience Metrics

#### 5.2.1 SLA Compliance Rate
*   **Purpose**: Measures the percentage of tickets that met response and resolution SLA deadlines.
*   **Formula**:
    $$\text{SLA Compliance \%} = \left( \frac{\text{Tickets meeting all SLA targets}}{\text{Total Tickets with SLAs}} \right) \times 100$$
*   **Computation Frequency**: Hourly.
*   **Target Operational Threshold**: **95.00%**.
*   **Alerting Rules**: If compliance falls below **90.00%**, the system escalates the issue to the supervisor and schedules a queue audit.

#### 5.2.2 Knowledge Deflection Rate
*   **Purpose**: Evaluates how effectively the public knowledge base helps customers find answers autonomously.
*   **Formula**:
    $$\text{Deflection \%} = \left( \frac{\text{Portal sessions reading KB articles with no ticket creation}}{\text{Total Portal Sessions}} \right) \times 100$$
*   **Computation Frequency**: Daily.
*   **Target Operational Threshold**: **20.00%**.

### 5.3 System and AI Efficiency Metrics

#### 5.3.1 AI Acceptance Rate
*   **Purpose**: Monitors the effectiveness of AI-suggested responses in agent workflows.
*   **Formula**:
    $$\text{AI Acceptance \%} = \left( \frac{\text{Suggested replies inserted with Levenshtein similarity } \ge 90\%}{\text{Total suggested replies displayed}} \right) \times 100$$
*   **Computation Frequency**: Daily.
*   **Target Operational Threshold**: **65.00%**.

---

## 6. SUPERVISOR WORKSPACE

The Supervisor Workspace acts as the centralized control panel for team leads, providing real-time operational visibility and team coordination tools.

```
  +---------------------------------------------------------------------------------+
  |                             SUPERVISOR CONTROL PANEL                            |
  +---------------------------------------------------------------------------------+
          │                                 │                                │
          ▼                                 ▼                                ▼
  +---------------+                 +---------------+                +---------------+
  | Live Queues   |                 | Alerts Panel  |                | Team Health   |
  | - Queue sizes |                 | - SLA Breaches|                | - Utilization |
  | - Waiting time|                 | - High-risk   |                | - Workloads   |
  +---------------+                 +---------------+                +---------------+
```

### 6.1 Real-Time Queue & SLA Breach Tracking
*   **Queue Sizes and Backlog Density**: Real-time charts display active ticket densities across different support teams (e.g., Billing, Technical Tier 2).
*   **SLA Breach Warnings**: Lists active tickets that are within 15 minutes of breach, allowing team leads to re-route assignments before deadlines are missed.
*   **Urgent Escalation Logs**: Highlights tickets flagged with extreme customer frustration or VIP escalation markers, placing them at the top of supervisor triage lists.

### 6.2 Team Coordination and QA Monitoring
*   **Live Workload and Staffing metrics**: Tracks agent connection states, active ticket capacities, and average response speeds, helping team leads balance workloads.
*   **QA score distribution**: Aggregates weekly team QA grades to track training and coaching effectiveness.
*   **Coaching alerts**: Prompts supervisors to schedule coaching sessions for agents with declining QA scores or low customer feedback.

---

## 7. EXECUTIVE ANALYTICS

The Executive Dashboard condenses high-volume operational data into high-level business metrics, tracking support costs and team performance across quarters:

*   **Quarterly Cost trends**: Computes support costs by comparing staffing costs against ticket volumes:
    $$\text{Cost Per Ticket} = \frac{\text{Support Operating Expenses (Staffing + Tools)}}{\text{Total Tickets Resolved}}$$
*   **AI Savings**: Calculates operational savings from AI draft recommendations and automated self-service deflections:
    $$\text{Estimated Savings} = (\text{Deflected Tickets} \times \text{Average Cost per Ticket}) + (\text{Copilot time saved hours} \times \text{Agent hourly rate})$$
*   **Customer Retention & Health Scores**: Maps support metrics directly to customer accounts. Highlights accounts experiencing high SLA breaches or low feedback ratings, alerting customer success teams of churn risks.

---

## 8. OPERATIONAL TELEMETRY & SYSTEM HEALTH STREAMS

The Platform Observability layer monitors system health and latency across database, communication, and AI pipelines to ensure horizontal stability under load.

```
[Inbound Connection] ──> [Measure Latency] ──> [Log to Observability Database]
                                                        │
                                                        ▼
                                         [Compare with SLA Thresholds]
                                                        │
                                            (Exceeds threshold?)
                                                        │
                                               ┌────────┴────────┐
                                               ▼ (YES)           ▼ (NO)
                                         [Trigger Alert]    [Log Telemetry]
```

### 8.1 Observability Indicators and Latency Targets

*   **Database Query Performance**: Monitors database connection pool depths, transaction lock holds, and query runtimes. Queries taking longer than **250ms** are logged for performance review.
*   **Email Queue Telemetry**: Tracks MIME processing latencies, SMTP response times, and outbound email delivery queues.
*   **Live Chat WebSocket Performance**: Monitors message routing speeds, WebSocket handshake durations, and broker connection pools to keep message delivery latency under **50ms**.
*   **AI and RAG Pipeline Latency**: Logs response times across different AI sub-systems:
    *   *Embedding generation*: Target < **100ms**.
    *   *Vector similarity searches*: Target < **50ms**.
    *   *Streaming model inferences*: Target < **3,500ms** (total response time).
*   **Outbound Delivery Success Rates (DSR)**: Logs delivery success rates and webhook responses across external channels (WhatsApp, Slack, Telegram).

---

## 9. ALERT ENGINE

The automated Alert Engine monitors system metrics, flags processing failures, and routes alerts to administrative coordinators based on severity:

```
[System Metric Out of Bounds] ──> [Determine Severity] ──> [Map Target Alerts] ──> [Dispatch Alert]
```

### 9.1 Alert Severity Levels and Thresholds

#### 9.1.1 Critical Severity (Requires immediate administrative action)
*   **Criteria**: Core platform failures affecting service availability:
    *   Database connection pool exhaustion (95% capacity for over 30s).
    *   Outbound email queue backlog exceeding 5,000 pending messages.
    *   Webhook verification failures on external platforms (e.g., 100% WhatsApp webhook failure for 1 minute).
*   **Dispatch Targets**: Slack DevOps channels, pager services, system dashboard banners.

#### 9.1.2 Warning Severity (Requires review within active business shifts)
*   **Criteria**: Declines in queue performance or minor service interruptions:
    *   SLA breach rate spikes (exceeding 10% breach rates within an hour).
    *   AI processing timeouts (exceeding 5% failure rates on inferences).
    *   Database slow query frequency (over 5% of queries taking > 500ms).
*   **Dispatch Targets**: Internal administrator dashboards, slack alert channels.

#### 9.1.3 Info Severity (Logs and diagnostic markers)
*   **Criteria**: Standard operational updates and baseline metrics:
    *   Materialized view refreshes completed successfully.
    *   RAG chunk index regeneration tasks completed.
*   **Dispatch Targets**: Operational event logs.

---

## 10. OPERATIONAL REPORTING

The Reporting Engine handles the scheduled generation, formatting, and delivery of operational reports to key stakeholders:

*   **Daily Queue Metrics**: Formats daily open ticket volumes, first response times, and active queues. Sent to team leads in CSV and PDF formats every morning.
*   **Weekly CSAT and QA Summaries**: Compiles weekly team CSAT scores, scorecard distributions, and coaching alerts. Sent to department managers in PDF and Excel formats.
*   **Quarterly Executive Performance Audits**: Aggregates operating cost trends, AI efficiency metrics, and SLA compliance statistics over previous quarters.
*   **Export and Formatting Controls**: Reports are generated using background workers, converting raw metrics into CSV, PDF, and Excel files. Exports enforce active tenant RLS rules, ensuring teams can only view or export records belonging to their organization.

---

## 11. PERFORMANCE ARCHITECTURE & QUERY OPTIMIZATIONS

To support high-frequency analytics and reporting without impacting transactional operations, the system applies the following performance optimizations on the database layer:

*   **Read-Only Database Replicas**: High-load reporting queries are routed to read-only replicas, keeping the primary database free for active transactional write operations.
*   **Materialized View Caching**: Frequently requested dashboard datasets are cached in memory (e.g., in Redis) with short-lived expiration windows (e.g., 30s), reducing database stress under peak traffic.
*   **Covering and BRIN Indexes**:
    *   *Covering Indexes*: High-frequency query columns are indexed with non-key columns included using the `INCLUDE` clause, supporting index-only scans on search queries.
    *   *BRIN (Block Range Index) Indexes*: Applied to chronological logging tables (e.g., `public.telemetry_logs` on `created_at`), reducing index size and speeding up query performance on time-series datasets:
        ```sql
        CREATE INDEX telemetry_created_at_brin_idx ON public.telemetry_logs USING BRIN (created_at);
        ```

---

## 12. SECURITY, ACCESS PRIVILEGES & DATA PRIVACY

Security rules are enforced on all reporting and telemetry pipelines to protect customer privacy and maintain tenant isolation:

*   **Row-Level Security (RLS)**: Enforces database-level isolation. Materialized views and reporting queries must explicitly carry the tenant's `organization_id` context, blocking unauthorized access to peer organizational data.
*   **Sensitive Metric Masking**: High-level reports shared with external stakeholders mask customer names, email addresses, and phone numbers, using anonymized user IDs.
*   **Audit Logging**: Access to dashboard pages and report exports is logged in `public.audit_logs`. Logs record the requesting user, execution timestamp, SQL trace ID, and active tenant ID, ensuring a secure forensic history.

---

## 13. AI ANALYTICS & QUALITY OBSERVABILITY

To maintain visibility on artificial intelligence components, a dedicated AI Analytics Dashboard tracks LLM performance and reliability:

```
  +---------------------------------------------------------------------------------+
  |                             AI ANALYTICS INTERFACES                             |
  +---------------------------------------------------------------------------------+
          │                                 │                                │
          ▼                                 ▼                                ▼
  +---------------+                 +---------------+                +---------------+
  | Cost Tracker  |                 | Latency panel |                | Hallucinations|
  | - Token usage |                 | - Stream speeds|               | - Match rates |
  | - Cost trend  |                 | - DB searches |                | - Overrides   |
  +---------------+                 +---------------+                +---------------+
```

*   **Cost and Latency Tracking**: Displays real-time API token counts and model costs, helping teams manage operational expenses.
*   **Suggestion Acceptance Rates**: Tracks agent acceptance, edit, and rejection rates, helping identify prompt templates that need optimization.
*   **Hallucination and Safety Telemetry**: Logs instances where generated responses mismatch source RAG citations, or where inputs trigger prompt injection blocks, helping engineers refine system prompts.

---

## 14. VALIDATION MATRIX

Before deploying dashboards or activating automated alert engines, developers must verify the following checklist:

| Area | Verification Scenario / Validation Objective | Expected Result | Checked |
| :---: | :--- | :--- | :---: |
| **Data Flow**| Querying open ticket counts during peak write traffic. | Returns accurate counts from cached views without database locks. | [ ] |
| **Concurrency**| Refreshing materialized views during queue transactions. | Concurrent background refreshes complete without blocking writes. | [ ] |
| **SLA Engine**| Ticket approaches SLA breach target. | System triggers countdown update, displays red alert, and logs warning. | [ ] |
| **Security** | Agent tries to query dashboard data for another tenant. | Database-level RLS blocks the query, preventing data exposure. | [ ] |
| **Fault-Tolerance**| External SMTP service drops offline. | Telemetry records failure, sets alert state to Warning, and routes to Slack. | [ ] |
| **Accuracy** | Reviewing CSAT gauge calculation accuracy. | Computes accurate percentages, ignoring incomplete or expired surveys. | [ ] |

---

## 15. CROSS REFERENCES

This operational telemetry and intelligence layer is built upon and references the physical and logical architectures defined in the following support manuals:

*   **Support Physical Tables**: Reference physical entity tables (`public.tickets`, `public.ticket_messages`) documented in `Phase_2_3_2F_Support_Physical_Tables.md`.
*   **Ticket Lifecycle**: Leverages transition states and FSM events detailed in `Phase_2_3_2F_1_Ticket_Lifecycle_Engine.md`.
*   **SLA Engine**: Queries timers, deadlines, and escalations defined in `Phase_2_3_2F_2_SLA_and_Escalation_Engine.md`.
*   **Knowledge Base**: Extracts article and search taxomony metrics structured in `Phase_2_3_2F_3_Knowledge_Base_Architecture.md`.
*   **Omnichannel Communication**: Intercepts WebSockets and message delivery rates specified in `Phase_2_3_2F_4_Omnichannel_Communication_Engine.md`.
*   **Customer Satisfaction & QA**: Monitors survey metrics and scorecard distributions governed by `Phase_2_3_2F_5_Customer_Satisfaction_and_Quality_Assurance.md`.
*   **AI Copilot**: Measures autocomplete and suggestion accept rates defined in `Phase_2_3_2F_6_AI_Copilot_and_Intelligent_Support_Assistance.md`.
*   **Event Contracts**: Coordinates async integration events detailed in `Phase_2_3_2F_7_Support_Integration_and_Event_Contracts.md`.

---

This document serves as the architectural reference for implementing support dashboards, operational telemetry, and alert management workflows within the JUANET Support Platform. All reporting layers must adhere strictly to these specifications.
