# JUANET Support Performance & Scalability Specification
## Phase 2.3.2F.9 — Support Performance and Scalability
**Document Version:** 1.0  
**Author:** Chief Enterprise Database Architect, JUANET Platform  
**Classification:** Engineering Specification / High-Scale Database Operations  

---

## 1. ARCHITECTURAL SCALING PHILOSOPHY

Operating the JUANET Support domain at enterprise scale requires accommodating **10 million+ tickets**, **100 million+ conversation messages**, and **thousands of concurrent agents** while maintaining sub-100ms API response times. To achieve this, the Support database layer rejects single-node vertical scaling (scale-up) as a long-term strategy and instead implements a **Shared-Nothing, Decoupled, and Tiered Operational Architecture**.

```
                           ┌────────────────────────┐
                           │   Client API Gateway   │
                           └───────────┬────────────┘
                                       │
                      ┌────────────────┴────────────────┐
                      ▼                                 ▼
         ┌─────────────────────────┐       ┌─────────────────────────┐
         │ Write-Heavy Route (OLTP)│       │ Read-Heavy Route (OLAP) │
         └────────────┬────────────┘       └────────────┬────────────┘
                      │                                 │
                      ▼                                 ▼
         ┌─────────────────────────┐       ┌─────────────────────────┐
         │   Primary Database      │───────▶│     Read Replicas       │
         │   (Write Master Node)   │       │   (Reporting / Widgets) │
         └────────────┬────────────┘       └────────────┬────────────┘
                      │                                 │
                      ▼                                 ▼
         ┌─────────────────────────┐       ┌─────────────────────────┐
         │   Outbox Event Logger   │       │ Materialized Views/Redis│
         │ (audit.outbound_events) │       │  (Active Dashboards)    │
         └─────────────────────────┘       └─────────────────────────┘
```

### 1.1 Scaling Principles
1.  **Strict Read-Write Path Segregation (CQRS Lite)**:
    *   **Write-Path**: All state-mutating transactions (ticket creation, message insertion, status changes, SLA evaluations) are strictly executed on the Primary Master database node. This path is optimized for high-write ACID throughput using highly indexed single-row lookups.
    *   **Read-Path**: All analytical, reporting, dashboard, and lookup operations (CSAT reviews, search, telemetry, supervisor lists) are offloaded to **hot-standby Read Replicas** or read from **Materialized Views** and **Redis cache layers**. This mitigates lock contention on the active write-path.
2.  **Shared-Nothing Multi-Tenancy**:
    *   `organization_id` acts as the primary logical partitioning key across all schemas. 
    *   The database is physically unified but logically isolated via PostgreSQL Row-Level Security (RLS). High-tier enterprise tenants are provisioned with dedicated physical schemas or separate database instances to guarantee noisy-neighbor containment.
3.  **Database Decoupling via Outbox Event Pattern**:
    *   Support transactions must never block on external domain calls (e.g., Billing checks, Project management syncs, CRM integrations). 
    *   All cross-domain communications are achieved via an append-only transaction-scoped Outbox log (`audit.outbound_events`). Local transactions commit in under 10ms, while a background worker propagates events asynchronously to Kafka or RabbitMQ.

### 1.2 Core Target SLA & Performance Metrics

| Metric | Target SLA (P95) | Target SLA (P99) | Operational Scale Target |
| :--- | :--- | :--- | :--- |
| **Ticket Ingestion Latency** | $\le 15\text{ ms}$ | $\le 50\text{ ms}$ | 500 tickets per second |
| **Message Append Speed** | $\le 10\text{ ms}$ | $\le 30\text{ ms}$ | 2,500 messages per second |
| **FTS Keyword Query (10M Rows)** | $\le 80\text{ ms}$ | $\le 200\text{ ms}$ | 1,000 concurrent search sessions |
| **SLA Evaluation Timer Pass** | $\le 5\text{ ms}$ | $\le 15\text{ ms}$ | 50,000 active SLA trackers |
| **Dashboard Metric Fetch** | $\le 50\text{ ms}$ | $\le 120\text{ ms}$ | 5,000 concurrent coordinators |

---

## 2. POSTGRESQL PARTITIONING STRATEGY

As database tables grow beyond **50 Gigabytes** or **50 million rows**, B-tree indexes lose cache efficiency, autovacuum processes experience high resource lock durations, and single-table queries slow down significantly due to deep index traversal trees. JUANET mitigates this by enforcing **Declarative Horizontal Partitioning** for high-volume logs, history tracking, and messaging tables.

### 2.1 Range Partitioning: Audit & History Logs

The `public.ticket_activity_logs` and `public.ticket_status_history` tables represent append-only data paths that capture transactional audits. These tables grow linearly and are range-partitioned **monthly** using the `created_at` timestamp column.

```
                  public.ticket_activity_logs (Parent Table)
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
   _logs_y2026m01            _logs_y2026m02            _logs_y2026m03
(Range: Jan 1-31, 2026)   (Range: Feb 1-28, 2026)   (Range: Mar 1-31, 2026)
```

#### SQL Implementation DDL
```sql
-- Create the parent partitioned table for activity logs
CREATE TABLE public.ticket_activity_logs (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    ticket_id uuid NOT NULL,
    actor_id uuid NOT NULL,
    activity_type varchar(50) NOT NULL,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone,
    PRIMARY KEY (organization_id, id, created_at)
) PARTITION BY RANGE (created_at);

-- Sample manual partition creation for year 2026 Month 01
CREATE TABLE public.ticket_activity_logs_y2026m01 PARTITION OF public.ticket_activity_logs
    FOR VALUES FROM ('2026-01-01 00:00:00+00') TO ('2026-02-01 00:00:00+00');

-- Sample manual partition creation for year 2026 Month 02
CREATE TABLE public.ticket_activity_logs_y2026m02 PARTITION OF public.ticket_activity_logs
    FOR VALUES FROM ('2026-02-01 00:00:00+00') TO ('2026-03-01 00:00:00+00');
```

*Architectural Rules for Partitioned Primary Keys*: In PostgreSQL, the partition key (`created_at`) **must** be part of any unique constraint, including the Primary Key. Therefore, all partitioned primary keys within the JUANET Support domain are composite: `(organization_id, id, created_at)`.

### 2.2 Hash Partitioning: High-Volume Conversations

The `public.ticket_messages` and `public.ai_sentiment_analysis` tables experience highly concurrent inserts and reads. To spread the write I/O load across distinct disk locations and prevent index hot-spotting, these tables are partitioned by **HASH** on `organization_id` into **64 static logical partitions**.

#### SQL Implementation DDL
```sql
-- Create the parent partitioned table for ticket messages
CREATE TABLE public.ticket_messages (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    ticket_id uuid NOT NULL,
    sender_id uuid NOT NULL,
    message_body text NOT NULL,
    search_vector tsvector,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone,
    PRIMARY KEY (organization_id, id)
) PARTITION BY HASH (organization_id);

-- Automating partition generation (Sample 0 to 3 of 64 partitions)
CREATE TABLE public.ticket_messages_p00 PARTITION OF public.ticket_messages
    FOR VALUES WITH (MODULUS 64, REMAINDER 0);
CREATE TABLE public.ticket_messages_p01 PARTITION OF public.ticket_messages
    FOR VALUES WITH (MODULUS 64, REMAINDER 1);
CREATE TABLE public.ticket_messages_p02 PARTITION OF public.ticket_messages
    FOR VALUES WITH (MODULUS 64, REMAINDER 2);
CREATE TABLE public.ticket_messages_p03 PARTITION OF public.ticket_messages
    FOR VALUES WITH (MODULUS 64, REMAINDER 3);
```

### 2.3 Partition Lifecycle Automation Policies

To eliminate manual administrative overhead and prevent application errors due to missing partitions, JUANET employs automated lifecycle management via **`pg_partman`** or a dedicated native database trigger.

1.  **Pre-creation Buffer**: The partition manager must maintain a buffer of **2 months of future partitions** at all times.
2.  **Partition Creation Routine**: Every Sunday at `01:00 UTC`, an automated cron job triggers the creation of partitions for the month after next.
3.  **Active Partition Maintenance**:
    ```sql
    -- Automating via pg_partman configuration (inserted into system catalog)
    INSERT INTO partman.part_config (
        parent_table, 
        partition_interval, 
        partition_type, 
        type, 
        premake, 
        automatic_maintenance
    ) VALUES (
        'public.ticket_activity_logs', 
        'monthly', 
        'range', 
        'native', 
        2, 
        'on'
    );
    ```

---

## 3. ADVANCED INDEXING OPTIMIZATION

Default B-tree indexes applied uniformly across millions of records lead to wasted memory, degraded write performance, and high disk usage. The Support domain enforces **Partial, Covering, and Specialized Full-Text Indexing Strategies** designed for high-density querying.

### 3.1 Partial Indexes (Mitigating Index Bloat)

Operational queries predominantly target active, open tickets. Indexing completed, closed, or soft-deleted tickets wastes precious RAM inside the shared buffer pool.

```
                    Total Tickets (10,000,000 Rows)
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│  Closed & Archival Tickets (9,800,000 Rows - Unindexed)       │
│                                                              │
│         ┌──────────────────────────────────────────┐         │
│         │ Active, Unresolved Tickets               │         │
│         │ (200,000 Rows - Partially Indexed)       │         │
│         └──────────────────────────────────────────┘         │
└──────────────────────────────────────────────────────────────┘
```

#### Optimization 1: Active Tickets Filter
We index only active, unresolved tickets to maintain compact B-tree sizes.
```sql
CREATE INDEX tickets_active_operational_idx ON public.tickets (
    organization_id, 
    assigned_team_id, 
    assigned_agent_id
) 
WHERE deleted_at IS NULL 
  AND status_id NOT IN (
      'f3e9a5c8-1029-4c91-b3b4-4e5a6a7b8c9d', -- 'closed' status ID constant
      'a7b8c9d0-1234-4567-89ab-cdef01234567'  -- 'resolved' status ID constant
  );
```

#### Optimization 2: Filtered Outbox Queue
The transactional outbox queue must be processed as quickly as possible. We only index events that are waiting to be processed.
```sql
CREATE INDEX outbound_events_unprocessed_idx ON audit.outbound_events (
    organization_id, 
    created_at ASC
) 
WHERE status = 'pending';
```

### 3.2 Covering Indexes (Index-Only Scans)

Dashboard widgets query status counts and SLA tracking continuously. To prevent the database from fetching table heap blocks from disk, we leverage covering indexes using the `INCLUDE` keyword to support fast Index-Only Scans.

```sql
-- Covering index for real-time ticket priority heatmaps
CREATE INDEX tickets_priority_dashboard_covering_idx ON public.tickets (
    organization_id, 
    priority_id, 
    status_id
) 
INCLUDE (id, assigned_agent_id, created_at)
WHERE deleted_at IS NULL;
```
*Mathematical Performance Advantage*: This index keeps the raw row pointers and essential values inside a single memory page, reducing disk page accesses from $O(\log N + N)$ random reads to a sequential index scan in memory ($O(\log N)$ steps).

### 3.3 GIN Indexes for Full-Text Search (FTS) & Fuzzy Search

Enterprise customers demand near-instant searches across millions of messages. We implement a native two-phase search indexing strategy.

```sql
-- GIN Index for pre-compiled English FTS search vector
CREATE INDEX tickets_search_vector_gin_idx ON public.tickets 
USING GIN (search_vector);

-- Trigram GIN index for partial subject/number matches (supports prefix auto-completion)
CREATE INDEX tickets_subject_trigram_gin_idx ON public.tickets 
USING GIN (subject gin_trgm_ops) 
WHERE deleted_at IS NULL;
```

### 3.4 Multi-Tenant RLS-Aware Compound Indexing Rules

Under Row-Level Security, PostgreSQL forces a filter on `organization_id` on virtually every query. If an index does not include `organization_id` as its leftmost prefix, the planner will bypass the index and perform a costly parallel sequential scan.

**The Golden Indexing Formula**:
$$\text{Index Composition} = (\mathtt{organization\_id}, \text{Query Filter Key}, \text{Sort Key}) \text{ INCLUDE } (\text{Select Targets})$$

*   **Rule 1**: Every custom index **must** begin with `organization_id` as the leading column.
*   **Rule 2**: Unique constraints **must** include `organization_id` to prevent cross-tenant collisions and ensure indexes match RLS plans.

---

## 4. LOCK CONTENTION, CONCURRENCY & DEADLOCK MITIGATION

High agent concurrency combined with high-frequency webhook ingest creates high row-level lock contention. The following patterns are mandatory to preserve high transactional throughput.

### 4.1 Avoiding Hot-Spot Row Locks: Sequential vs. UUIDv7 Writes

Standard auto-incrementing serial sequences (`bigserial`, `serial`) write records sequentially. Under heavy parallel load, this causes intensive disk write contention on the final index block (Right-Sided Index Bloat).

*   **Design Decision**: All primary keys in JUANET utilize **UUIDv7**.
*   **Performance Rationale**: UUIDv7 is time-ordered, incorporating a UNIX millisecond timestamp in its most significant 48 bits. This preserves sequential disk insertion performance (reducing page-split overhead) while permitting stateless, concurrent client-side UUID generation with zero locking.

### 4.2 Optimistic vs. Pessimistic Concurrency Controls

When thousands of support agents review tickets, multiple agents may try to assign or update the same ticket at once.

#### Pattern 1: Optimistic Locking for Routine Updates
For standard ticket updates (e.g., adding tags, changing categories), we employ Optimistic Concurrency Control using a monotonically increasing version counter.
```sql
UPDATE public.tickets
SET 
    category_id = :new_category_id,
    version = version + 1,
    updated_at = now()
WHERE id = :ticket_id 
  AND organization_id = :organization_id
  AND version = :expected_version; -- Fails if another agent updated first
```

#### Pattern 2: Selective Pessimistic Locking with SKIP LOCKED for Auto-Assignment Workers
Background workers matching tickets to available agents must never attempt to process the same ticket, which causes transaction locking queues and transaction timeouts.
```sql
-- Select and lock a single available ticket, bypassing already locked rows
BEGIN;

SELECT id, subject
FROM public.tickets
WHERE organization_id = :org_id
  AND status_id = 'c1a2b3c4-9012-3456-789a-bcdef0123456' -- 'unassigned'
  AND assigned_agent_id IS NULL
ORDER BY created_at ASC
LIMIT 1
FOR UPDATE SKIP LOCKED; -- Prevents waiting on other worker locks

-- Process assignment logic...
UPDATE public.tickets 
SET assigned_agent_id = :agent_id, status_id = :assigned_status_id
WHERE id = :selected_ticket_id;

COMMIT;
```

### 4.3 Deadlock Avoidance Protocol

Deadlocks occur when transaction $A$ locks Row 1 and waits for Row 2, while transaction $B$ locks Row 2 and waits for Row 1. The Support domain enforces three design rules to eliminate deadlocks:

1.  **Strict Locking Hierarchy**: Transactions must always lock tables in a deterministic top-down order:
    $$\text{Security.Users} \longrightarrow \text{Public.Tickets} \longrightarrow \text{Public.Ticket\_Messages} \longrightarrow \text{Public.Ticket\_SLA\_Instances}$$
2.  **Ordered Array Modifications**: When modifying or locking a batch of child elements, arrays **must** be sorted sequentially by UUID value before execution:
    ```typescript
    // Ensure deterministic array sorting order to avoid locking overlaps
    const sortedTicketIds = [...ticketIds].sort((a, b) => a.localeCompare(b));
    ```
3.  **Short-Lived Transactions**: Transactions must never execute network calls, AI processing, or webhook dispatches inside their active database execution boundaries.

### 4.4 Autovacuum and Analyze Tuning

High-frequency updates on tables like `public.ticket_sla_instances` and `public.ai_suggested_responses` create dead tuples (obsolete row versions). Standard autovacuum defaults are insufficient, leading to table bloat and performance degradation.

We enforce aggressive, specialized autovacuum overrides for high-turnover tables:

```sql
ALTER TABLE public.ticket_sla_instances SET (
    autovacuum_vacuum_scale_factor = 0.05,  -- Trigger vacuum after 5% change
    autovacuum_analyze_scale_factor = 0.02, -- Trigger analyze after 2% change
    autovacuum_vacuum_cost_limit = 2000,    -- Allocate more I/O bandwidth to vacuum
    autovacuum_vacuum_cost_delay = 2        -- Reduce sleep intervals between pages
);

ALTER TABLE public.ai_suggested_responses SET (
    autovacuum_vacuum_scale_factor = 0.02,
    autovacuum_analyze_scale_factor = 0.01,
    autovacuum_vacuum_cost_limit = 3000
);
```

---

## 5. ASYNCHRONOUS WORKER ARCHITECTURE & EVENT-DRIVEN SLA TIMERS

The Support SLA and Escalation Engine depends on continuous, real-time tracking of deadlines. Running continuous timers inside database transaction blocks causes significant performance bottlenecks. Instead, JUANET implements a **Stateless, Decoupled Timer Evaluation Architecture**.

```
                        ┌────────────────────────┐
                        │  cron / pg_cron Trigger│
                        └───────────┬────────────┘
                                    │ Runs every 10 seconds
                                    ▼
                        ┌────────────────────────┐
                        │ SLA Evaluator Workers  │
                        └───────────┬────────────┘
                                    │
                  ┌─────────────────┴─────────────────┐
                  ▼                                   ▼
   Fetch Breached SLA Targets          Distribute Webhooks / Alerts
   `SELECT ... FOR UPDATE`             Via `audit.outbound_events`
   `SKIP LOCKED LIMIT 100`             (Asynchronous Processing Queue)
```

### 5.1 SLA Engine Worker Pull Model

The database serves as a state-based scoreboard. Background SLA workers pull tasks from `public.ticket_sla_instances` using high-performance queueing queries.

```sql
-- High-performance worker loop query fetching breached SLAs
UPDATE public.ticket_sla_instances
SET 
    status = 'breached',
    updated_at = now()
WHERE id IN (
    SELECT id 
    FROM public.ticket_sla_instances
    WHERE status IN ('active', 'warned')
      AND target_deadline <= now()
    ORDER BY target_deadline ASC
    LIMIT 100
    FOR UPDATE SKIP LOCKED
)
RETURNING id, ticket_id, target_metric, organization_id;
```
*Why this scales*: Multiple server instances can run this query simultaneously. The `FOR UPDATE SKIP LOCKED` clause ensures that workers never block or fetch duplicate records, providing clean, linear scale-out capability.

### 5.2 Decoupled Omnichannel Webhook Dispatch

Outbound communication (Slack alerts, SMS alerts, PagerDuty incidents) can experience high latency or downstream failures. The transaction path writes events to `audit.outbound_events`, ensuring database operations are completely isolated from external network reliability.

#### High-Performance Outbox Reader Loop
```sql
-- Worker queries outbox to ingest events in batches
SELECT id, event_type, payload, organization_id
FROM audit.outbound_events
WHERE status = 'pending'
ORDER BY created_at ASC
LIMIT 500
FOR UPDATE SKIP LOCKED;
```

---

## 6. ENTERPRISE CAPACITY PLANNING & DATA TIERING

To ensure the JUANET database remains performant over a multi-year horizon, we establish clear capacity projections and a structured **Tiered Storage Architecture**.

### 6.1 Enterprise Scale Growth Projections

The calculations below model an enterprise deployment with **500 corporate tenants**, **5,000 active agents**, and an annual volume of **10,000,000 tickets**.

#### 1. Average Payload Storage Estimations
*   **Ticket Row (`public.tickets`)**: 420 bytes average (including UUIDs, timestamps, indexes, status keys, custom metadata fields).
*   **Message Row (`public.ticket_messages`)**: 2.5 kilobytes average (including raw conversational text, sender identifiers, and pre-computed search vectors).
*   **Activity Log Row (`public.ticket_activity_logs`)**: 450 bytes average (structured audit state delta JSON).

#### 2. Year-over-Year Volumetric Growth Projections
$$\text{Annual Message Volume} = 10,000,000 \text{ tickets} \times 8 \text{ messages/ticket} = 80,000,000 \text{ messages}$$
$$\text{Annual Activity Logs} = 10,000,000 \text{ tickets} \times 12 \text{ actions/ticket} = 120,000,000 \text{ logs}$$

| Support Data Table | Projected Rows (Year 1) | Storage Size (Year 1) | Projected Rows (Year 5) | Storage Size (Year 5) |
| :--- | :--- | :--- | :--- | :--- |
| `public.tickets` | $10,000,000$ | $4.2\text{ GB}$ | $50,000,000$ | $21.0\text{ GB}$ |
| `public.ticket_messages` | $80,000,000$ | $200.0\text{ GB}$ | $400,000,000$ | $1.0\text{ TB}$ |
| `public.ticket_activity_logs` | $120,000,000$ | $54.0\text{ GB}$ | $600,000,000$ | $270.0\text{ GB}$ |
| `public.ticket_sla_instances`| $30,000,000$ | $10.5\text{ GB}$ | $150,000,000$ | $52.5\text{ GB}$ |
| **Total Core Payload Size** | — | **$268.7\text{ GB}$** | — | **$1.34\text{ TB}$** |
| **Index Overhead (Approx. 40%)**| — | **$107.5\text{ GB}$** | — | **$537.4\text{ GB}$** |
| **Combined Database Footprint**| — | **$376.2\text{ GB}$** | — | **$1.88\text{ TB}$** |

### 6.2 Structured Data Tiering Policies

To maintain system speed and minimize expensive SSD storage costs, JUANET implements a **Three-Tier Data Lifecycle Strategy**.

```
                Hot Tier (Active OLTP Database)
             - Tickets & Messages < 180 days old
             - Highly optimized NVMe SSD block storage
                                │
                                ▼
               Warm Tier (Compressed Partitions)
             - Tickets & History 181 to 365 days old
             - Read-only partitioned tables, compressed
                                │
                                ▼
                Cold Tier (Archived Parquet files)
             - Resolved tickets & logs > 365 days old
             - Archived to Google Cloud Storage (GCS)
             - Accessible via BigQuery or DuckDB FDW
```

1.  **Hot Storage (First 180 Days)**:
    *   **Data Profile**: All active tickets, conversations, and audits completed within the last 6 months.
    *   **Hosting Target**: Active primary database running on enterprise NVMe SSD block storage. High-frequency queries are served directly from RAM buffers.
2.  **Warm Storage (181 to 365 Days)**:
    *   **Data Profile**: Tickets resolved between 6 and 12 months ago.
    *   **Optimization**: Partition tables are detached from the active write-path, marked read-only, and compressed using PostgreSQL columnar formats (e.g., Citus columnar extensions or compressed storage profiles) to reduce storage footprints by up to 70%.
3.  **Cold Storage & Archival (> 365 Days)**:
    *   **Data Profile**: Archived ticket logs and messages older than 1 year.
    *   **Archival Pipeline**: A nightly database routine exports cold partitions to **Apache Parquet** format and uploads them to secure object storage (e.g., Google Cloud Storage or AWS S3).
    *   **Deletion from OLTP**: Once successfully uploaded and validated, the cold partitions are permanently dropped from the PostgreSQL active instance.
    *   **Archived Query Pattern**: If long-term analytical queries are requested, managers read archival data using Google BigQuery or PostgreSQL Foreign Data Wrappers (`postgres_fdw` or `aws_s3` integrations) to avoid resource contention on the active database.

---

## 7. ROW-LEVEL SECURITY (RLS) PERFORMANCE TUNING

While Row-Level Security (RLS) provides excellent multi-tenant isolation, poorly written security policies can degrade query performance. If a policy executes a subquery for every row scanned, database performance will collapse.

```
❌ BAD PATTERN (Subquery Executed For Every Row Scanned):
ALTER TABLE public.tickets ENABLE ROW LEVEL SECURITY;
CREATE POLICY tickets_tenant_policy ON public.tickets
  FOR ALL USING (
    organization_id = (
      -- Causes a redundant query execution on every single row check
      SELECT organization_id FROM security.users WHERE id = auth.uid()
    )
  );

========================================================================

✔ GOOD PATTERN (Session Parameter Read with zero database overhead):
ALTER TABLE public.tickets ENABLE ROW LEVEL SECURITY;
CREATE POLICY tickets_tenant_policy ON public.tickets
  FOR ALL USING (
    -- Directly extracts claim from memory context in < 1 microsecond
    organization_id = NULLIF(current_setting('request.jwt.claim.organization_id', true), '')::uuid
  );
```

### 7.1 High-Performance RLS Implementation Standards

To keep RLS overhead near zero, JUANET enforces three key security policies:

1.  **Direct Parameter Extraction**: Security policies must never execute tables joins or select statements to verify basic tenant identities. They must directly extract variables from transaction-local session settings:
    `current_setting('request.jwt.claim.organization_id', true)`
2.  **Role-Based Bypass Optimization**: High-throughput automated workers and replication streams must run using specific database roles configured with `BYPASSRLS` privileges to avoid unnecessary RLS planning cycles:
    ```sql
    -- Grant background replication or processing roles RLS bypass privileges
    ALTER ROLE support_replication_worker BYPASSRLS;
    ```
3.  **Ensure Strict Match with Partition Keys**: The RLS isolation column (`organization_id`) must match the leading field of our composite primary keys. This ensures that the query planner immediately prunes partition routes during planning, before executing individual row policy validations.

---

## 8. SUMMARY ARCHITECTURAL RECOMMENDATIONS

To guarantee scalability up to 10M+ tickets, ensure the following checklist is strictly met during physical setup:

*   [ ] **Primary Key Standard**: Use UUIDv7 for all primary keys to guarantee sequential insertions and avoid index fragmentation.
*   [ ] **Partitioning Standard**: Partition the `ticket_activity_logs` and `ticket_status_history` tables monthly on `created_at`. Partition `ticket_messages` using a 64-way hash modulo on `organization_id`.
*   [ ] **Covering Indexes**: Apply covering indexes (`INCLUDE` clauses) for all dashboard widgets to enable fast Index-Only Scans.
*   [ ] **Autovacuum Optimization**: Apply aggressive autovacuum thresholds for high-churn SLA and AI tables to prevent table bloat.
*   [ ] **Zero-Query RLS Policies**: Enforce memory-bound session settings for all tenant isolation policies to prevent redundant database checks.
*   [ ] **SLA Pull Strategy**: Ensure that automated background SLA workers retrieve tasks using `FOR UPDATE SKIP LOCKED` to enable linear scale-out capability.
