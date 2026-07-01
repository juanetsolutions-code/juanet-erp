# JUANET CMS Integration and Event Contracts Engine
## Phase 2.3.2G.8 — Event-Driven Decoupling, Transactional Outbox Schemas, Idempotent Consumers, and Canonical CMS Event Payload Definitions
**Document Version:** 1.0  
**Author:** Chief Integration Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Integration Specification  

---

## 1. INTEGRATION PHILOSOPHY & BOUNDARY POLICIES

Within high-scale, multi-tenant enterprise architectures, tight decoupling between transactional domain logic and downstream consumer modules is crucial to maintaining system safety, data integrity, and high-performance scalability. The **JUANET Content Management System (CMS)** establishes a strict integration boundary, designating the CMS as the **authoritative System of Record (SoR) for content entities only**.

No external operational module (e.g., CRM, Finance, Support, Marketplace) is permitted to write directly to CMS database tables. All cross-boundary mutations must go through authorized CMS API gateways or be processed asynchronously using event-driven communication patterns.

```
                             [JUANET DECUPLED CMS INTEGRATION]

        ┌────────────────────────┐  (Outbox Commit)   ┌────────────────────────┐
        │  CMS Bounded Context   ├───────────────────►│  public.cms_outbox_ref │
        │  (Transactional SoR)   │                    │ (Atomically Committed) │
        └────────────────────────┘                    └───────────┬────────────┘
                                                                  │
                                                                  │ (Polling / CDC Sweep)
                                                                  ▼
        ┌────────────────────────┐  (Signed Payloads) ┌────────────────────────┐
        │     Consumer Ledger    │◄───────────────────┤ Asynchronous Dispatch  │
        │  (public.cms_processed)│                    │ (Event Broker Bus)     │
        └───────────┬────────────┘                    └───────────┬────────────┘
                    │                                             │
                    ▼ (Idempotent Action Execution)               ▼ (Asynchronous Integrations)
        ┌──────────────────────────────────────────────────────────────────────┐
        │    Downstream Systems: CRM, Search, Finance, Localization, Projects  │
        └──────────────────────────────────────────────────────────────────────┘
```

The system enforces the following core integration guidelines:
*   **Authoritative SoR Boundary**: The CMS retains sole write authority over content assets, folder layouts, taxonomies, and redirect routing indexes.
*   **Decoupled Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all outbox tables, isolating transactional event payloads based on verified tenant session context parameters.
*   **Asynchronous Processing**: Operational side-effects (such as updating CRM lead records, processing localized translations, and rebuilding search indexes) run asynchronously via background workers, keeping core CMS write paths fast and responsive.
*   **No Cross-Context Joins**: Downstream services are forbidden from joining core CMS tables directly. Services query dedicated denormalized read-models or build local state projections from canonical outbox logs instead.
*   **At-Least-Once Delivery Guarantees**: State mutations and outbox entries are committed within a single, unified database transaction, preventing event capture issues even during system crashes.

---

## 2. SYSTEM-WIDE INTEGRATION ARCHITECTURE

The CMS coordinates actions across the platform by dispatching structured, versioned business events to our global messaging broker. The integration matrix below maps communication flows across bounded contexts:

```
                          CROSS-CONTEXT EVENT ROUTING MAP

                        ┌─────────────────────────────────┐
                        │     CMS Core Bounded Context    │
                        └────────────────┬────────────────┘
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
┌───────────────┐                ┌───────────────┐                ┌───────────────┐
│  Search Engine│                │ Media Core DAM│                │  L10n Engine  │
│(FTS/pgvector) │                │(Asset Status) │                │(Translations) │
└───────────────┘                └───────────────┘                └───────────────┘
        ▼                                ▼                                ▼
  - index.rebuild                  - asset.ready                    - sync.drift
  - query.executed                 - asset.purged                   - l10n.approved
```

*   **Authentication & Security**: Provides fine-grained user groups and permission profiles, allowing the CMS to validate edit locks and routing permissions dynamically.
*   **CRM & Projects**: Updates lead generation campaigns, active project assets, and user profiles asynchronously when marketing content goes live.
*   **Finance & Billing**: Tracks multi-tenant asset storage metrics and API call counts to compile subscription bills.
*   **Marketplace**: Distributes digital product guides, layout assets, and plugin descriptions across regional storefronts.
*   **Automation & Notifications**: Sends Slack, email, and Microsoft Teams notifications to reviewers when workflow steps are assigned.
*   **Search & Content Discovery**: Builds read-model search documents asynchronously, keeping full-text GIN search and pgvector semantic embeddings up to date.
*   **Media & DAM**: Generates thumbnails, crops, and responsive variations, notifying the CMS when assets are ready to deploy.
*   **Localization & Multi-Language**: Tracks master document revisions, updating translation states and fallback routing graphs dynamically when master files change.
*   **Analytics & Business Intelligence (BI)**: Records page views, click-through rates, and sitemap status logs to track web experience performance.

---

## 3. TRANSACTIONAL OUTBOX SCHEMA (`public.cms_outbox_events`)

To prevent event tracking failures during database connection drops or system crashes, the platform implements the **Transactional Outbox** pattern. Content modifications and outbox event entries are committed within a single, unified database transaction:

```sql
-- DDL for Transactional Outbox Log
CREATE TABLE public.cms_outbox_events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL, -- Tenant partitioning identifier
    aggregate_type VARCHAR(64) NOT NULL, -- 'content_item', 'media_asset', 'translation'
    aggregate_id UUID NOT NULL, -- ID of the target modified record
    event_name VARCHAR(128) NOT NULL, -- Canonical identifier (e.g., 'content.published.v1')
    version_number INTEGER NOT NULL DEFAULT 1,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb, -- Strongly-typed event payload
    headers JSONB NOT NULL DEFAULT '{}'::jsonb, -- Middleware headers: tracing, authentication
    
    -- Telemetry & Queue tracking parameters
    status VARCHAR(32) NOT NULL DEFAULT 'Pending', -- 'Pending', 'Processing', 'Published', 'Failed'
    retry_count INTEGER NOT NULL DEFAULT 0,
    max_retries INTEGER NOT NULL DEFAULT 5,
    correlation_id UUID NOT NULL, -- Grouping ID for related multi-step transactions
    trace_id VARCHAR(128) NOT NULL, -- OpenTelemetry trace identifier
    partition_key VARCHAR(128) NOT NULL, -- Message broker partition routing key
    
    -- Processing timestamps
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMPTZ,
    error_log TEXT
);

-- Indexing Outbox for High-Performance Queue Polling
CREATE INDEX idx_cms_outbox_polling ON public.cms_outbox_events (organization_id, status, scheduled_at) 
WHERE status = 'Pending' OR status = 'Failed';

-- Partitioning and Row-Level Security Configuration
ALTER TABLE public.cms_outbox_events ENABLE ROW LEVEL SECURITY;

CREATE POLICY cms_outbox_tenant_isolation ON public.cms_outbox_events
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 3.1 Metadata Retention & Archiving
To prevent outbox logs from slowing down query execution times as database transaction counts grow, completed outbox records are archived systematically:
*   **Processing Retries**: Failing outbox records are retried automatically using exponential backoff schedules.
*   **Dead-Letter Queue (DLQ) Transfer**: Records that exceed the maximum retry count are flagged as `Failed`, triggering administrative alerts.
*   **Log Archiving**: A cron job runs every 24 hours to transfer outbox records flagged as `Published` or `Failed` older than 14 days to historical audit storage buckets, keeping the active outbox index fast and responsive.

---

## 4. IDEMPOTENT CONSUMER LEDGER (`public.cms_processed_events`)

Because messaging brokers can occasionally deliver events more than once during network glitches (at-least-once delivery), downstream consumer services must verify event IDs to prevent duplicate processing or data corruption. The platform tracks processed event IDs in an append-only consumer ledger:

```sql
-- DDL for Idempotent Consumer Ledger
CREATE TABLE public.cms_processed_events (
    consumer_name VARCHAR(128) NOT NULL, -- Target consumer module (e.g., 'search_indexer')
    event_id UUID NOT NULL, -- Unique outbox event identifier
    checksum_sha256 CHAR(64) NOT NULL, -- SHA-256 payload checksum signature
    processed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retry_count INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'Success', -- 'Success', 'Compensated', 'Failed'
    error_message TEXT,
    
    PRIMARY KEY (consumer_name, event_id)
);

-- Indexing processed event hashes for fast duplicate lookups
CREATE INDEX idx_processed_event_hash ON public.cms_processed_events (checksum_sha256);
```

---

## 5. CANONICAL CMS SYSTEM EVENTS

The CMS publishes structured business events, enabling downstream services to coordinate tasks asynchronously.

### 5.1 Content Lifecycle Events

#### `content.created.v1`
*   *Producer*: CMS Write-Path Gateway
*   *Consumers*: Translation Memory, Content Analytics
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000001",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.created.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:00:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "content_type_code": "press_release",
    "version": 1,
    "created_by": "018f6001-ffff-7000-8000-000000000005",
    "default_locale": "en-US",
    "global_attributes": {
      "campaign_code": "Q2-LAUNCH",
      "estimated_read_time_mins": 5
    }
  }
}
```

#### `content.updated.v1`
*   *Producer*: CMS Write-Path Gateway
*   *Consumers*: Localization Sync, Preview Engine, Search Indexer
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000002",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.updated.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:01:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "version": 2,
    "updated_by": "018f6001-ffff-7000-8000-000000000005",
    "modified_fields": ["global_attributes.campaign_code"],
    "previous_version": 1
  }
}
```

#### `content.deleted.v1`
*   *Producer*: CMS Write-Path Gateway
*   *Consumers*: Search Indexer, Redis Invalidator, Redirect Router
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000003",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.deleted.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:05:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "deleted_by": "018f6001-ffff-7000-8000-000000000005",
    "is_permanent_purge": false
  }
}
```

#### `content.version.created.v1`
*   *Producer*: CMS Version Control Svc
*   *Consumers*: Content History Dashboard, Audit Reporter
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000004",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.version.created.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:05:30.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "version": 3,
    "parent_version": 2,
    "commit_log": "Updated marketing tags for press release.",
    "committed_by": "018f6001-ffff-7000-8000-000000000005"
  }
}
```

#### `content.approved.v1`
*   *Producer*: Editorial Workflow Board
*   *Consumers*: Publishing Scheduler, Campaign Dashboard
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000005",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.approved.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:10:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "version": 3,
    "workflow_instance_id": "018f6001-aaaa-7000-8000-000000000009",
    "approved_by": "018f6001-bbbb-7000-8000-000000000007"
  }
}
```

#### `content.published.v1`
*   *Producer*: CMS Release Engine
*   *Consumers*: Edge CDN Invalidator, Sitemap Generator, RSS Feeds
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000006",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.published.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:15:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "version": 3,
    "published_by": "018f6001-bbbb-7000-8000-000000000007",
    "active_routing_urls": [
      { "locale": "en-US", "url": "https://www.juanet-solutions.com/news/q2-launch" }
    ],
    "embargo_ends_at": null
  }
}
```

#### `content.unpublished.v1`
*   *Producer*: CMS Release Engine
*   *Consumers*: Redis Invalidator, Search Indexer, CDN Gateway
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000007",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.unpublished.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:20:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "unpublished_by": "018f6001-bbbb-7000-8000-000000000007"
  }
}
```

#### `content.archived.v1`
*   *Producer*: CMS Content Admin Svc
*   *Consumers*: S3 Cold Storage Worker, Audit Recorder
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000008",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.archived.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:22:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "archived_by": "018f6001-ffff-7000-8000-000000000005"
  }
}
```

#### `content.restored.v1`
*   *Producer*: CMS Content Admin Svc
*   *Consumers*: Search Indexer, Campaign Dashboard
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000009",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.restored.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:24:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "restored_by": "018f6001-ffff-7000-8000-000000000005",
    "target_status": "Draft"
  }
}
```

### 5.2 Translation & Localization Events

#### `content.translation.created.v1`
*   *Producer*: Localization Engine
*   *Consumers*: Translation Memory, AI Translation Queue
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000010",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.translation.created.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:30:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "locale_code": "fr-CA",
    "source_locale_code": "en-US",
    "created_by": "018f6001-dddd-7000-8000-000000000014"
  }
}
```

#### `content.translation.updated.v1`
*   *Producer*: Localization Engine
*   *Consumers*: Translation Memory, Search Indexer
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000011",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.translation.updated.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:32:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "locale_code": "fr-CA",
    "version": 2,
    "updated_by": "018f6001-dddd-7000-8000-000000000014"
  }
}
```

#### `content.translation.published.v1`
*   *Producer*: Localization Engine
*   *Consumers*: Edge CDN Invalidator, Sitemap Generator, CRM
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000012",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "content.translation.published.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:35:00.000Z",
  "data": {
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "locale_code": "fr-CA",
    "version": 2,
    "published_by": "018f6001-bbbb-7000-8000-000000000007",
    "localized_url": "https://www.juanet-solutions.com/fr/news/q2-launch"
  }
}
```

### 5.3 Digital Asset Management (DAM) Events

#### `asset.uploaded.v1`
*   *Producer*: Storage Upload Gateway
*   *Consumers*: Security Malware Scanner, Checksum Dedup Svc
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000013",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "asset.uploaded.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:40:00.000Z",
  "data": {
    "media_asset_id": "7e2b4011-0002-4000-a000-000000000003",
    "s3_object_key": "raw/org-7e2b/DSC_5821.png",
    "file_size_bytes": 2489102,
    "checksum_sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  }
}
```

#### `asset.ready.v1`
*   *Producer*: DAM Processing Pipeline
*   *Consumers*: CMS Content Editor UI, Search Indexer, CDN Cache Prefetcher
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000014",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "asset.ready.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:42:00.000Z",
  "data": {
    "media_asset_id": "7e2b4011-0002-4000-a000-000000000003",
    "mime_type": "image/png",
    "extracted_metadata": {
      "width": 3840,
      "height": 2160,
      "aspect_ratio": "16:9"
    },
    "generated_renditions": [
      { "size": "thumbnail", "s3_key": "renditions/org-7e2b/DSC_5821_thumb.webp" },
      { "size": "medium", "s3_key": "renditions/org-7e2b/DSC_5821_med.webp" }
    ]
  }
}
```

#### `asset.deleted.v1`
*   *Producer*: DAM Storage Admin Svc
*   *Consumers*: S3 Purge Scheduler, CMS Content Verification Svc
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000015",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "asset.deleted.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:45:00.000Z",
  "data": {
    "media_asset_id": "7e2b4011-0002-4000-a000-000000000003",
    "soft_deleted_at": "2026-06-30T06:45:00.000Z"
  }
}
```

### 5.4 Search Discovery & SEO Events

#### `search.index.requested.v1`
*   *Producer*: CMS Release Engine
*   *Consumers*: Search Indexing Worker, AI Vector Generator
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000016",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "search.index.requested.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:46:00.000Z",
  "data": {
    "document_id": "7e2b4011-0001-4000-a000-000000000002",
    "document_type": "content_item",
    "locale_code": "en-US",
    "raw_text_payload": "Click here to upgrade your active enterprise account."
  }
}
```

#### `search.index.completed.v1`
*   *Producer*: Search Indexing Worker
*   *Consumers*: Admin Dashboard Monitor, Telemetry Log Svc
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000017",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "search.index.completed.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:46:02.000Z",
  "data": {
    "document_id": "7e2b4011-0001-4000-a000-000000000002",
    "duration_ms": 1450,
    "vector_embeddings_generated": true
  }
}
```

#### `seo.updated.v1`
*   *Producer*: SEO Optimization Svc
*   *Consumers*: Sitemap Incremental Updater, CDN Cache Invalidator
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000018",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "seo.updated.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:48:00.000Z",
  "data": {
    "route_id": "7e2b4011-1000-4000-a000-000000000005",
    "meta_title": "Enterprise Multi-Site Configurations on Postgres",
    "og_image_id": "7e2b4011-0002-4000-a000-000000000003"
  }
}
```

#### `route.changed.v1`
*   *Producer*: CMS Path Router Svc
*   *Consumers*: Redirect History Recorder, Edge Routing Proxy, Sitemap Generator
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000019",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "route.changed.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:50:00.000Z",
  "data": {
    "site_id": "7e2b4011-2000-4000-a000-000000000004",
    "old_slug_path": "news/previous-launch-details",
    "new_slug_path": "news/q2-launch",
    "generate_301_redirect": true
  }
}
```

#### `navigation.updated.v1`
*   *Producer*: Navigation Tree Engine
*   *Consumers*: Redis Invalidator, Edge CDN Cache Purger
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000020",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "navigation.updated.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:52:00.000Z",
  "data": {
    "site_id": "7e2b4011-2000-4000-a000-000000000004",
    "menu_code": "header_main"
  }
}
```

### 5.5 Workflow Governance Events

#### `workflow.completed.v1`
*   *Producer*: Editorial Workflow Board
*   *Consumers*: Content Release Engine, Notification Hub, Slack Bot
*   *Payload*:
```json
{
  "event_id": "018f6001-0000-7000-8000-000000000021",
  "organization_id": "7e2b4011-0000-4000-a000-000000000001",
  "event_name": "workflow.completed.v1",
  "trace_id": "ot_82ba34d19f01aefbc6",
  "timestamp": "2026-06-30T06:55:00.000Z",
  "data": {
    "workflow_instance_id": "018f6001-aaaa-7000-8000-000000000009",
    "content_item_id": "7e2b4011-0001-4000-a000-000000000002",
    "final_version": 3,
    "approval_signoffs": [
      { "role": "Technical_Reviewer", "user_id": "018f6001-bbbb-7000-8000-000000000007", "approved_at": "2026-06-30T06:10:00.000Z" },
      { "role": "Legal_Officer", "user_id": "018f6001-cccc-7000-8000-000000000008", "approved_at": "2026-06-30T06:55:00.000Z" }
    ]
  }
}
```

---

## 6. API INTEGRATION STANDARDS & PROTOCOLS

To support headless integrations and multi-tenant web layouts, all API gateways adhere to a strict set of architectural protocols:

### 6.1 Authentication & Scope Enforcement
Every API request must be authenticated, validating access credentials on every call:
*   **Cryptographic API Keys**: Sourced via request headers to authenticate headless integrations:
```
Header: Authorization: Bearer jnt_live_a82bc194dfa1...
```
*   **JSON Web Tokens (JWT)**: Authenticates client editors, mapping user permissions to roles (e.g., `Translator`, `Legal_Officer`, `SEO_Editor`) to enforce role-based access boundaries.

### 6.2 Rate Limiting Policies
The API Gateway applies rate limits dynamically based on the client's subscription tier:
*   *Developer Sandbox Sandbox Key*: Maximum 60 requests/minute.
*   *Enterprise High-Scale Key*: Maximum 10,000 requests/minute, protecting backend database replica clusters from unexpected traffic spikes.

### 6.3 Standardized Problem Details (RFC 7807)
If an API request fails, the gateway returns a standardized JSON error payload to simplify integration debugging:
```json
{
  "type": "https://api.juanet.platform/errors/lock-acquisition-failed",
  "title": "Resource Lock Contention",
  "status": 409,
  "detail": "Cannot update content item. Active lock is currently held by user '018f6001-ffff-7000-8000-000000000005' until 2026-06-30T07:15:00Z.",
  "instance": "/api/v1/cms/org_7e2b-4011/content/press-release",
  "error_code": "RESOURCE_LOCKED",
  "meta": {
    "locked_by": "018f6001-ffff-7000-8000-000000000005",
    "expires_at": "2026-06-30T07:15:00Z"
  }
}
```

---

## 7. EVENT ORDERING AND REPLAY GUARANTEES

To prevent out-of-order event processing (e.g., processing `content.deleted.v1` before `content.created.v1`), the platform implements a partition-key routing scheme:

### 7.1 Partition-Key Routing
*   **Key Design**: Outbox records are routed to messaging broker partitions using the document's unique identifier (`content_item_id`) as the partition key.
*   **Single-Partition Processing**: This routing design guarantees that all lifecycle events for a specific content item are processed by the same message broker partition in sequential order.

### 7.2 Event Replays & Re-Indexing
*   **Index Repopulation**: If search indexes or vector databases are corrupted, administrators can trigger a sitemap replay event (`search.rebuild.started.v1`).
*   **Idempotency Filtering**: Downstream services evaluate incoming event IDs using the consumer ledger table (`public.cms_processed_events`), filtering out older event versions to prevent data overrides.

---

## 8. FAILURE RECOVERY & COMPENSATION MECHANISMS

The platform features built-in retry mechanisms and dead-letter queues to maintain high availability and prevent data loss during integration failures:

```
                         FAILURE RETRY & DLQ FLOW
  [Integration Step Fails] ──► [Verify Retry Threshold] ──► [Trigger Exponential Backoff]
                                                                     │
         ┌───────────────────────────────────────────────────────────┴─────────────────────────┐
         ▼ (Under 5 retries)                                                                   ▼ (Over 5 retries)
  [Schedule Next Retry Run] ──► [Retry Event Process]                         [Transfer Event to DLQ]
```

*   **Exponential Backoff Schedule**: Failed outbox dispatches trigger auto-retries using exponential backoff intervals:

$$\text{Retry Delay} = \text{Base Delay} \times 2^{\text{Retry Count}} \quad (\text{e.g., } 2\text{s}, 4\text{s}, 8\text{s}, 16\text{s}, 32\text{s})$$

*   **Dead-Letter Queue (DLQ)**: Records that exceed the maximum retry limit (5 retries) are moved to the DLQ table (`public.cms_dlq_events`), and administrative alerts are sent to our operations dashboard.
*   **Compensating Transactions**: If a multi-step orchestration workflow fails halfway through (e.g., publishing succeeds but CDN caching fails), the system triggers compensating transactions to roll back the changes, ensuring system consistency across services.

---

## 9. SECURITY & SIGNATURE VALIDATION

To protect endpoints from malicious attacks and prevent unauthorized data modifications, all outbound webhook dispatches are secured using cryptographic signatures:

*   **HMAC Payload Signatures**: Webhook requests include a cryptographic signature header calculated using a shared tenant secret:
```
Header: X-Juanet-Signature: sha256=a82bc194dfa1892bf3010b47...
```
*   **Signature Verification**: Downstream servers recalculate the SHA-256 hash of the received payload using their shared secret, verifying that the payload was sent by the JUANET platform and has not been modified in transit.
*   **Replay Protection**: Webhook headers include a timestamp parameter. Receivers reject requests older than 5 minutes to prevent replay attacks.

---

## 10. HIGH-VOLUME PERFORMANCE PIPELINES

The outbox processor is optimized to handle high transaction volumes and deliver messages with low latency:

*   **Outbox Batching**: Workers aggregate pending outbox records, executing batch upsert transactions (e.g., 100 records per write) to reduce database transaction overhead.
*   **Read replica Routing**: Read-heavy analytics pipelines and search indexer tasks are routed to read-only replica nodes, preventing locks on primary write volumes.
*   **Multi-Tenant Partitioning**: Outbox and transaction tables are partitioned by organization ID, enabling fast query execution times even as record counts grow past 500M+ events.

---

## 11. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **Atomic Commits** | Trigger a database crash during content updates. | Rollback reverts both the content modification and outbox event entry. | Atomic Transaction Tests |
| **Consumer Ledger** | Send duplicate event payloads to consumer nodes. | Ledger detects duplicate event IDs, preventing duplicate processing. | Idempotency Verification Tests|
| **Signatures** | Intercept webhook and attempt to edit payload values. | Signature verification fails on the receiver, blocking the request. | Signature Integrity Audits |
| **Partition Ordering**| Send overlapping created and deleted event runs. | Partition keys route events sequentially, preserving event order. | Sequence Delivery Tests |
| **Tenant Isolation** | Query outbox logs without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Tenant Isolation Audits |
| **Backoff Retries** | Disconnect receiver and verify outbox retry logs. | Processor schedules retries using exponential backoff schedules. | Failure Recovery Tests |
| **Throughput Loads** | Run load tests simulating 10,000+ simultaneous edits. | Outbox batching and replica routing keep primary tables locks below 5%.| High-Performance Load Suite |

---

## 12. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.
*   **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**: Manages CDN delivery networks, edge caches, and headless GraphQL query interfaces.
*   **Workflow & Collaboration (`Phase_2_3_2G_6_Workflow_Editorial_Collaboration_and_Content_Governance.md`)**: Coordinates collaborative pipelines, role assignments, parallel approvals, and compliance logs.
*   **SEO & Site Management (`Phase_2_3_2G_7_SEO_Site_Management_and_Web_Experience.md`)**: Governs site directories, custom domain verifications, redirects, sitemaps, and robots configurations.

---

*Authorized by the JUANET Content Integration Board & Global Architecture Council.*
