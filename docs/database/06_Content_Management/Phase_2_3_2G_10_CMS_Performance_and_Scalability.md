# JUANET CMS Performance & Scalability Implementation Manual
## Phase 2.3.2G.10 — Database Partitioning, GIN & Vector Indexing, Concurrency Controls, and Multi-Tier Caching Architecture
**Document Version:** 1.0  
**Author:** Chief Performance Engineer, Principal Database Architect, and Global Infrastructure Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Scalability Specification  

---

## 1. PERFORMANCE PHILOSOPHY & CQRS LITE

In a global multi-tenant enterprise CMS, maintaining low latency and high availability requires separating high-frequency write operations from high-volume read delivery paths. The **JUANET CMS Performance & Scalability Context** implements a strict **CQRS Lite** architecture. 

Core editorial transactions (creation, revision, and workflow sign-offs) operate on primary, write-optimized database instances. Delivery lookups, searches, navigation, and analytics are decoupled entirely, querying read replica clusters, distributed Redis caches, and global CDN edge servers instead.

```
                            [JUANET PERFORMANCE ENGINE]

     ┌──────────────────────────────────────────────────────────────────────┐
     │                     CMS WRITE GATEWAY (OLTP WRITE)                   │
     │   - Primary PostgreSQL Node (Single Master)                          │
     │   - Optimistic locking, advisory locks, write-optimized indexing      │
     └──────────────────────────────────┬───────────────────────────────────┘
                                        │
                                        │ (Physical Streaming Replication / WAL)
                                        ▼
     ┌──────────────────────────────────────────────────────────────────────┐
     │                      READ REPLICAS (OLAP READ)                       │
     │   - Distributed, Read-Only PostgreSQL 16 Instances                  │
     │   - Serving Materialized Views & High-Volume Complex Search Queries  │
     └──────────────────────────────────┬───────────────────────────────────┘
                                        │
                                        │ (Cache Warming & CDN Purges)
                                        ▼
     ┌──────────────────────────────────────────────────────────────────────┐
     │                     DISTRIBUTED REDIS & EDGE CDN                     │
     │   - Low-latency cache layers (sub-15ms response SLA)                 │
     │   - GraphQL Persisted Queries & Edge-Cached Route Resolution         │
     └──────────────────────────────────────────────────────────────────────┘
```

The system enforces the following core performance guidelines:
*   **Database-First Design**: Leverage native database engine optimizations (such as Postgres 16 covering indexes, partition pruning, and GIN/trgm engines) before implementing application-layer code patches.
*   **Non-Blocking Operations**: Heavy jobs (such as media conversions, sitemap updates, translation synchronization, and search index builds) run in the background via asynchronous worker queues, preventing write-lock contention.
*   **Immutable Releases**: Published content revisions are stored as immutable document logs, reducing database lock contention and maximizing CDN edge caching efficiency.
*   **Efficient Connection Pooling**: Restricts active database connection counts using dedicated database proxies (such as PgBouncer), preventing connection starvation during traffic spikes.
*   **Aggressive Index Isolation**: Indexes are carefully configured and isolated to match specific query patterns, keeping write overhead minimal.

---

## 2. CAPACITY PLANNING & HARDWARE SIZING

To maintain sub-15ms response latencies as the platform grows, infrastructure allocations are calculated using standardized multi-tenant sizing models:

### 2.1 Scale Metric Projections

$$\text{Total Storage} = \text{Tenant Count} \times \left( \text{Content Items} \times 15\text{KB} + \text{Versions} \times 20\text{KB} + \text{Asset Metadata} \times 5\text{KB} \right) + \text{Telemetry Logs}$$

$$\text{Outbox IOPS} = \frac{\text{Throughput} \times \text{Average Event Count}}{\text{Polling Interval (seconds)}}$$

### 2.2 Sizing Guidelines Matrix

The table below outlines hardware, storage, and networking requirements to support tenant growth from sandbox to high-scale enterprise:

| Capacity Metrics | Tier 1: Sandbox (100 Tenants) | Tier 2: Startup (1,000 Tenants) | Tier 3: Growth (10,000 Tenants) | Tier 4: Enterprise (100,000 Tenants) |
| :--- | :--- | :--- | :--- | :--- |
| **Active Row Counts** | 1.5 Million | 15 Million | 150 Million | 1.5 Billion |
| **Total DB Storage**  | 100 GB | 1 TB | 10 TB | 100 TB |
| **Object Storage (S3)**| 500 GB | 5 TB | 50 TB | 500 TB |
| **Target Read IOPS**  | 1,000 | 5,000 | 25,000 | 100,000+ |
| **Target Write IOPS** | 500 | 2,500 | 10,000 | 50,000+ |
| **Compute Nodes (VCPU)**| 4 vCPUs | 16 vCPUs | 64 vCPUs | 256 vCPUs |
| **Memory Capacity**   | 16 GB RAM | 64 GB RAM | 256 GB RAM | 1 TB+ RAM |
| **Network Throughput**| 1 Gbps | 5 Gbps | 25 Gbps | 100 Gbps+ |

---

## 3. POSTGRESQL PARTITIONING STRATEGY

To keep query execution times fast as database tables grow past 1B+ records, write-heavy and transactional logging tables are partitioned using PostgreSQL range, list, and hash partitioning:

```sql
-- DDL for Partitioned Outbox Event Logs
CREATE TABLE public.cms_outbox_events_partitioned (
    id UUID NOT NULL,
    organization_id UUID NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

-- Generate sample monthly partitions
CREATE TABLE public.cms_outbox_events_2026_m06 PARTITION OF public.cms_outbox_events_partitioned
    FOR VALUES FROM ('2026-06-01 00:00:00+00') TO ('2026-07-01 00:00:00+00');

CREATE TABLE public.cms_outbox_events_2026_m07 PARTITION OF public.cms_outbox_events_partitioned
    FOR VALUES FROM ('2026-07-01 00:00:00+00') TO ('2026-08-01 00:00:00+00');
```

### 3.1 Sub-System Partition Mapping

| Target Database Table | Partitioning Strategy | Partition Key | Default Partition Interval | Retention Schedule |
| :--- | :--- | :--- | :--- | :--- |
| `content_versions` | Range Partitioning | `created_at` | Monthly | Keep hot 12 months, then archive |
| `editorial_comments` | Hash Partitioning | `content_item_id` | 16 Hash Partitions | Keep hot indefinately |
| `cms_outbox_events` | Range Partitioning | `created_at` | Daily | Keep hot 14 days, then purge |
| `telemetry_logs` | Range Partitioning | `created_at` | Daily | Keep hot 30 days, then purge |
| `cms_api_metrics` | Range Partitioning | `created_at` | Weekly | Keep hot 60 days, then archive |
| `search_metrics` | Range Partitioning | `created_at` | Weekly | Keep hot 90 days, then purge |

### 3.2 Automated Partition Maintenance
An automated background task runs every night to pre-create upcoming partitions (e.g., preparing the next month's outbox partition 7 days in advance) and detach expired logs, keeping transaction indexes fast.

---

## 4. SYSTEM-WIDE INDEXING CONVENTIONS

To deliver fast response times on search lookups and multi-tenant queries, the database engine utilizes targeted index configurations:

```sql
-- 1. COVERING INDEX: Optimize route lookups without hitting tables
CREATE INDEX idx_routes_covering 
ON public.site_routes (organization_id, site_id, slug_path) 
INCLUDE (content_item_id, canonical_override) 
WHERE is_active = TRUE;

-- 2. PARTIAL INDEX: Tracks pending outbox events
CREATE INDEX idx_outbox_pending_partial 
ON public.cms_outbox_events (organization_id, created_at) 
WHERE status = 'Pending';

-- 3. GIN TRGM INDEX: Fast fuzzy text matching
CREATE INDEX idx_content_search_trgm 
ON public.localized_content USING gin (body_content gin_trgm_ops);

-- 4. BRIN INDEX: High-speed historical audit log lookups
CREATE INDEX idx_audit_logs_brin 
ON audit.cms_telemetry_logs (created_at) USING brin;

-- 5. PGVECTOR HNSW INDEX: Semantic search embeddings index
CREATE INDEX idx_content_vector_hnsw 
ON public.search_embeddings USING hnsw (embedding_vector vector_cosine_ops) 
WITH (m = 16, ef_construction = 64);
```

### 4.1 HOT (Heap-Only Tuple) Update Optimization
To reduce write I/O overhead on high-frequency tables (such as editorial task states or user sessions), tables use custom `FILLFACTOR` configurations, leaving spare capacity on database pages to support fast in-place (HOT) updates:
```sql
ALTER TABLE public.editorial_tasks SET (FILLFACTOR = 85);
```

---

## 5. QUERY OPTIMIZATION RULES

To prevent slow queries and minimize memory overhead on high-volume routes, developers follow strict database optimization rules:

### 5.1 Keyset Pagination vs. Offset Pagination
High-scale listing endpoints must use Keyset Pagination instead of offset pagination, avoiding query slowdowns as user scroll depth increases:

```sql
-- ❌ BAD: High performance penalty at deep scroll depths
SELECT id, title FROM public.content_items 
WHERE organization_id = :org_id 
ORDER BY created_at DESC 
LIMIT 50 OFFSET 50000;

-- ✅ GOOD: Constant execution time regardless of page depth
SELECT id, title FROM public.content_items 
WHERE organization_id = :org_id 
  AND created_at < :last_seen_timestamp 
ORDER BY created_at DESC 
LIMIT 50;
```

### 5.2 JSONB Optimization and Key Extraction
JSONB column updates must modify specific target keys instead of rewriting entire payloads, keeping WAL log volumes low:
```sql
UPDATE public.content_items 
SET metadata = jsonb_set(metadata, '{campaign_code}', '"Q2-LAUNCH-REVISED"') 
WHERE id = :content_item_id;
```

---

## 6. MULTI-USER CONCURRENCY CONTROL

To prevent editorial changes from overwriting each other, the database engine enforces optimistic locking and document edit locks:

### 6.1 Optimistic Locking via Version Columns
Update actions verify document version numbers before committing changes. If a version conflict is detected, the transaction is rejected to prevent data loss:

```sql
-- Core update query verifying version state
UPDATE public.content_items 
SET body_content = :new_body,
    version_number = version_number + 1,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :content_item_id 
  AND version_number = :expected_version;
```

### 6.2 Concurrent Task Processing (FOR UPDATE SKIP LOCKED)
Background workers claim tasks using non-blocking queues, preventing lock contention and ensuring workers process items in parallel:

```sql
-- Claiming pending tasks concurrently without locking rows
UPDATE public.editorial_tasks 
SET assignee_id = :worker_id,
    task_status = 'In_Progress'
WHERE id = (
    SELECT id FROM public.editorial_tasks
    WHERE task_status = 'Unassigned'
    ORDER BY created_at ASC
    LIMIT 1
    FOR UPDATE SKIP LOCKED
)
RETURNING id;
```

---

## 7. MULTI-LEVEL CACHING HIERARCHY

To deliver content with low latency and handle traffic spikes efficiently, the platform utilizes a multi-level caching structure:

| Caching Layer | Target System Platform | Storage & Software | Average SLA | Cache Invalidation Strategy |
| :--- | :--- | :--- | :--- | :--- |
| **Edge Cache** | Global Edge CDN | Cloudflare Edge CDN | **< 15ms** | Event-driven path purging |
| **Regional Cache**| Regional Compute Nodes| Redis Replica Cluster | **< 20ms** | LRU eviction or explicit key invalidate |
| **Local Cache** | Node.js API Gateways | Local In-Memory Store | **< 2ms** | sliding TTL window (max 120s) |
| **Database Cache**| PostgreSQL Replica Nodes| Shared Buffers | **< 50ms** | LRU eviction of indexed table blocks |

### 7.1 Cache Key Namespace Structure
*   *GraphQL Persisted Query*: `org:{org_id}:graphql:sha256({query_hash})`
*   *Resolved Route Key*: `org:{org_id}:site:{site_id}:route:hash({slug_path})`
*   *Asset Metadata*: `org:{org_id}:asset:{asset_id}`

---

## 8. DECOUPLED BACKGROUND WORKERS

The platform routes long-running tasks to background job workers, prioritizing critical publishing actions over logging and analytics updates:

```
                            [WORKER QUEUE DESIGN]
  [Job Outbox Events] ──► [Filter Tasks by Priority] ──► [Select Target Worker Queue]
                                                                   │
         ┌─────────────────────────────────────────────────────────┼─────────────────────────┐
         ▼ (High Priority)                                         ▼ (Medium Priority)       ▼ (Low Priority)
  [Publishing & CDN Purge]                                  [Image Resize & L10n]     [Metrics & Archiving]
```

*   **Priority 1: Publishing & CDN Purge**: Processes content releases, updates Redis replicas, and invalidates global CDN edge caches immediately.
*   **Priority 2: DAM Assets & Localization**: Resizes media uploads, generates responsive variations, and updates translation memory databases asynchronously.
*   **Priority 3: Analytics & Log Archiving**: Aggregates reporting metrics, refreshes materialized views concurrently, and detaches old log partitions daily.

---

## 9. STORAGE LIFECYCLE MANAGEMENT

To minimize cloud storage costs and maintain fast query execution times, data is migrated across storage stages systematically based on creation date:

```
                          [DATA STORAGE LIFECYCLE]
  [Primary Database (Hot)] ──► [Compress Partitions (Warm)] ──► [Export Parquet to S3 (Cold)]
```

*   **Hot Storage (Postgres SSDs)**: Stores active content, recent versions, and active telemetry logs (under 30 days) to support real-time operations.
*   **Warm Storage (Compressed Tables)**: Stores completed outbox logs and older version records (30–90 days) on compressed PostgreSQL partitions.
*   **Cold Storage (Parquet files on S3)**: Exports historical telemetry logs and audit trails older than 90 days to S3-compatible cold storage buckets using compressed Parquet files, keeping primary database indexes small and responsive.

---

## 10. HIGH AVAILABILITY & DISASTER RECOVERY

The database infrastructure is built to guarantee high availability and prevent data loss during hardware failures:

*   **Streaming Replication**: Uses continuous WAL streaming replication to synchronize data across primary write nodes and regional read replica clusters.
*   **Automated Failover Management**: Utilizes failover coordinators (such as Patroni) to monitor node health and promote replica instances to primary master nodes during hardware failures.
*   **Continuous Backups**: Schedules automated daily physical backups alongside continuous WAL archiving to support Point-in-Time Recovery (PITR).
*   **High-Performance Connection Pooling**: Configures database proxies (such as PgBouncer) with transaction-level connection pooling to handle high client request volumes efficiently:
```
Default Pool Mode: transaction
Max Client Connections: 10,000
```

---

## 11. OBSERVABILITY & SLAS

The platform monitors system health against target Service Level Agreements (SLAs) in real time, triggering alerts when performance thresholds are violated:

```sql
-- DDL for System SLO Violations Alerts
CREATE TABLE audit.slo_alerts_log (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    metric_name VARCHAR(128) NOT NULL, -- 'p99_api_latency', 'cdn_cache_hit_ratio'
    observed_value NUMERIC(10,2) NOT NULL,
    target_slo_limit NUMERIC(10,2) NOT NULL,
    trace_details JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

Target system SLAs:
*   *Slow Query Threshold*: Queries taking longer than 250ms are logged to slow query monitors immediately.
*   *API Response Latency*: The 99th percentile response latency for API requests must remain below 15ms.
*   *Replication Lag Limit*: Alarms alert administrators if database replication lag between primary and replica nodes exceeds 5 seconds.

---

## 12. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **Pruning Verifications**| Run explain plan queries on partitioned tables. | Database searches only relevant partitions, skipping expired logs. | Partition Pruning Tests |
| **Index Effectiveness** | Verify index usage on high-volume tables. | Queries run via fast index scans, avoiding slow sequential table scans. | Index Verification Audits |
| **Lock Contentions**    | Simulate overlapping updates to the same row. | Optimistic locks detect conflict, reverting conflicting changes. | Concurrency Contention Suite|
| **Worker Concurrencies**| Run parallel queue claim tasks on workers. | Workers process tasks concurrently, skipping locked tasks. | Queue Concurrency Tests |
| **Cache Invalidations** | Modify content and check CDN edge caches. | CDN edge caches are purged and updated within our 1000ms SLA. | Cache Invalidation Audits |
| **Disaster Failovers**  | Shut down primary master node during write load. | Failover coordinator promotes replica to master with zero data loss. | HA Failover Audits |
| **Volume Scaling**      | Simulate database sizes of 1B+ rows. | Query response times remain consistent, meeting target SLAs. | High-Volume Load Suite |

---

## 13. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.
*   **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**: Manages CDN delivery networks, edge caches, and headless GraphQL query interfaces.
*   **Workflow & Collaboration (`Phase_2_3_2G_6_Workflow_Editorial_Collaboration_and_Content_Governance.md`)**: Coordinates collaborative pipelines, role assignments, parallel approvals, and compliance logs.
*   **SEO & Site Management (`Phase_2_3_2G_7_SEO_Site_Management_and_Web_Experience.md`)**: Governs site directories, custom domain verifications, redirects, sitemaps, and robots configurations.
*   **CMS Integration & Events (`Phase_2_3_2G_8_CMS_Integration_and_Event_Contracts.md`)**: Governs event-driven decoupling, transactional outbox schemas, and canonical event payloads.
*   **Dashboards & Telemetry (`Phase_2_3_2G_9_CMS_Dashboards_Analytics_and_Operational_Telemetry.md`)**: Governs materialized OLAP aggregations, system telemetry, and operational dashboards.

---

*Authorized by the JUANET Performance Engineering Board & Global Infrastructure Council.*
