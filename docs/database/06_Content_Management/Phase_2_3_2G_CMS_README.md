# JUANET Content Management System (CMS) Architecture Manual
## Master README & Enterprise Architecture Entry Point
**Document Version:** 1.0  
**Author:** Chief Enterprise Architect, VP of Engineering, and Technical Operations Council  
**Classification:** Public / Enterprise Architecture Ledger, Navigation Guide, and Domain Index  

---

## 1. PURPOSE & BOUNDED CONTEXT DOMAIN

The **JUANET Content Management System (CMS)** is the authoritative, system-of-record bounded context responsible for the lifecycle, organization, optimization, localization, auditing, and multi-channel delivery of all digital content, media assets, sitemaps, and editorial workflows within the JUANET Enterprise SaaS Platform.

Unlike traditional monolithic platforms, the JUANET CMS is designed from a headless-first perspective. It isolates content modeling and management operations from client-facing rendering layers.

```
                         [JUANET CMS SERVICE BOUNDARIES]

          ┌────────────────────────────────────────────────────────┐
          │                  JUANET EDITORIAL PORTAL               │
          │   - Content Modeling, DAM Uploads, Workflow Approvals  │
          └───────────────────────────┬────────────────────────────┘
                                      │
                                      ▼
          ┌────────────────────────────────────────────────────────┐
          │                    CMS API GATEWAY                     │
          │   - Row-Level Security (RLS) & Multi-Tenant Isolation  │
          └───────────────────────────┬────────────────────────────┘
                                      │
                                      ├────────────────────────────┐
                                      ▼ (Headless Delivery)        ▼ (Asynchronous Outbox)
          ┌────────────────────────────────────────────────────────┐ ┌─────────────────────┐
          │                    CLIENT CHANNELS                     │ │  DOWNSTREAM SYSTEMS │
          │   - Web Portals, Mobile Apps, Headless Frontends       │ │  - CRM, Support, AI │
          └────────────────────────────────────────────────────────┘ └─────────────────────┘
```

The system enforces the following core architectural rules:
*   **API & Event Isolation**: External applications and other platform modules are prohibited from querying or writing to the CMS database directly. All interactions must proceed through secure, public API endpoints or asynchronous outbox event channels.
*   **Headless-First Delivery**: Content is managed as channel-agnostic, structured JSON structures, allowing the same content payload to be delivered to web portals, native mobile apps, or external digital displays seamlessly.
*   **Comprehensive Lifecycle Controls**: Houses all capabilities to support content drafts, translation fallbacks, media conversions, full-text searches, SEO redirection tables, and approval tasks within a single domain context.

---

## 2. DOMAIN ARCHITECTURE OVERVIEW

The CMS architecture is structured into eight integrated domain modules, separating transactional writes from delivery and processing pipelines:

*   **Content System of Record**: Serves as the authoritative registry for content models, taxonomy tags, and categories. It preserves every document update as an immutable version record, enabling instant rollbacks and auditable revision histories.
*   **Publishing Pipeline**: Manages publication schedules, coordinates time-limited embargo dates, and executes atomic release swaps to push content live on CDN edges.
*   **Media & DAM Pipeline**: Processes media uploads asynchronously. It validates binary file signatures (magic-byte checks), executes malware scans, generates responsive image formats (WebP, AVIF), and delivers private files using time-limited signed URLs.
*   **Localization Pipeline**: Couples localized variations to primary master documents, managing language fallback paths and translating drafts using AI translation engines with mandatory human editors-in-the-loop review.
*   **Search & Semantic Discovery**: Performs hybrid search query lookups, combining keyword full-text searches (PostgreSQL GIN trgm indices) with semantic concept matching (pgvector HNSW indexes), ranking results using Reciprocal Rank Fusion (RRF).
*   **Editorial Workflows**: Coordinates collaborative pipelines, role assignments, parallel review task paths, SLA timers, and compliance approvals (Maker-Checker, Four-Eyes policies).
*   **Content Delivery Pipeline**: Distributes content to public headless APIs, utilizing Redis replica caches and global CDN edge servers to serve requests within a sub-15ms response SLA.
*   **Operational Analytics & Telemetry**: Aggregates reporting metrics, refreshes materialized views concurrently in the background, and forwards system telemetry records to central SIEM log platforms.

---

## 3. CMS DOCUMENTATION TREE

The engineering documentation tree consists of 15 comprehensive specifications, detailing every layer of the CMS domain from physical schemas to testing strategies:

1.  **Physical Tables Specification (`Phase_2_3_2G_CMS_Physical_Tables.md`)**  
    Defines physical database tables, indexes, schemas, UUIDv7 primary keys, and Row-Level Security (RLS) policies.
2.  **Content Modeling & Publishing (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**  
    Governs content structure rules, taxonomy categorizations, and document lifecycle state machines.
3.  **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**  
    Manages media storage abstractions, SHA-256 deduplication, magic-byte validations, and responsive image optimizations.
4.  **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**  
    Coordinates multi-language fallback routes, translation variant tables, and translation memory caching.
5.  **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**  
    Governs full-text indexing, trigram fuzzy search, semantic vector matching, and hybrid ranking (RRF).
6.  **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**  
    Manages headless REST and GraphQL API gateways, connection pooling, and multi-tier edge caching networks.
7.  **Workflow & Collaboration (`Phase_2_3_2G_6_Workflow_Editorial_Collaboration_and_Content_Governance.md`)**  
    Coordinates approval task queues, task assignments, comment registries, and Maker-Checker policy validations.
8.  **SEO & Site Management (`Phase_2_3_2G_7_SEO_Site_Management_and_Web_Experience.md`)**  
    Governs custom site directories, domain validations, redirect configurations, and background sitemap compilations.
9.  **CMS Integration & Events (`Phase_2_3_2G_8_CMS_Integration_and_Event_Contracts.md`)**  
    Governs event-driven decouplings, transactional outbox schemas, and integration event payload structures.
10. **Dashboards & Telemetry (`Phase_2_3_2G_9_CMS_Dashboards_Analytics_and_Operational_Telemetry.md`)**  
    Governs pre-aggregated materialized views, operational metrics formulas, and partitioned logging systems.
11. **Performance & Scalability (`Phase_2_3_2G_10_CMS_Performance_and_Scalability.md`)**  
    Governs table partitioning keys, custom fillfactors, concurrency locks, and background worker queue priorities.
12. **Security & Compliance (`Phase_2_3_2G_11_CMS_Security_Privacy_and_Compliance.md`)**  
    Governs Zero-Trust models, RLS tenant isolation, cryptographic envelopes, audit hash-chains, and GDPR workflows.
13. **Testing & Validation (`Phase_2_3_2G_12_CMS_Testing_and_Validation.md`)**  
    Governs unit, integration, and pgTAP database testing suites, disaster recovery drills, and CI/CD quality gates.
14. **Architecture Decision Records (`Phase_2_3_2G_13_CMS_Architecture_Decision_Records.md`)**  
    Logs permanent architectural decisions, evaluated alternatives, and long-term consequence analyses.
15. **Implementation Roadmap (`Phase_2_3_2G_14_CMS_Implementation_Roadmap.md`)**  
    Orchestrates database migration batches (MIG-001 to MIG-015), service implementation sequences, and deployment plans.

---

## 4. ROLE-BASED READING GUIDES

To help onboarding engineers locate relevant specifications quickly, the following reading guides outline recommended documentation paths:

```
                            [ROLE-BASED READING PATHS]

  [DB Administrators]   ──► Physical Tables (00)  ──► Performance (10) ──► Testing / pgTAP (12)
  [Backend Engineers]   ──► Modeling (01)         ──► API (05)         ──► Events (08)
  [Frontend Engineers]  ──► Modeling (01)         ──► API (05)         ──► SEO (07)
  [Security Auditors]   ──► Physical Tables (00)  ──► Security (11)    ──► Testing / pgTAP (12)
```

### Database Administrators (DBAs)
Focuses on indexing, storage scaling, schema integrity, and database isolation.
*   *Reading Path*: `00_Physical_Tables` ──► `10_Performance` ──► `11_Security_Compliance` ──► `12_Testing_Validation`.

### Backend Software Engineers
Focuses on business logic, API gateways, database repositories, and outbox event publishing.
*   *Reading Path*: `01_Content_Modeling` ──► `05_Headless_API` ──► `08_Event_Contracts` ──► `13_Architecture_Records`.

### Frontend Software Engineers
Focuses on headless query lookups, GraphQL data structures, sitemaps, and editor component forms.
*   *Reading Path*: `01_Content_Modeling` ──► `05_Headless_API` ──► `07_SEO_Site_Management` ──► `14_Implementation_Roadmap`.

### DevOps & Infrastructure Engineers
Focuses on replica routing, background workers, edge CDN invalidate setups, and deployment gates.
*   *Reading Path*: `05_Headless_API` ──► `10_Performance` ──► `12_Testing_Validation` ──► `14_Implementation_Roadmap`.

### QA Engineers
Focuses on test cases, automated validation scripts, pgTAP assertions, and load benchmarks.
*   *Reading Path*: `12_Testing_Validation` ──► `11_Security_Compliance` ──► `10_Performance` ──► `14_Implementation_Roadmap`.

### Security Auditors
Focuses on tenant isolation verification, data encryption, audit trails, and data privacy policies (GDPR).
*   *Reading Path*: `11_Security_Compliance` ──► `00_Physical_Tables` ──► `06_Editorial_Collaboration` ──► `12_Testing_Validation`.

---

## 5. CROSS-DOMAIN INTEGRATION MAP

The CMS is decoupled from other domains, interacting with the broader JUANET platform using standard API contracts and event channels:

*   **Authentication & Session Contexts**: Retreives the user's organization context from JWT tokens generated by our Identity Provider, configuring database RLS settings on every connection.
*   **Media Object Storage (S3)**: Interacts with S3 buckets through cloud-agnostic storage wrappers, saving file locations to database tables.
*   **Global Content Delivery Network (CDN)**: Directs sitemap deployments, invalidates page paths on publishing events, and caches content at the edge.
*   **Customer Relationship Management (CRM)**: Dispatches publishing updates and task notifications to CRM systems via webhook and event processors.
*   **Project & Task Management**: Syncs editorial review schedules and task calendars with our core task management platform.
*   **Financial & Billing Subsystems**: Verifies tenant subscription tiers before allowing access to premium capabilities (such as AI translations or custom domains).
*   **AI Platform & LLM Hub**: Communicates with central AI endpoints to generate content drafts, translate texts, and generate semantic text vector embeddings.

---

## 6. THE CMS CONSTITUTION: INVIOLABLE RULES

To maintain architectural integrity as the platform scales, developers must adhere to the following 10 structural rules:

1.  **Strict Data Ownership**: The CMS is the absolute, single system of record for all content metadata, pages, sitemaps, and localized variations. No other platform module may bypass CMS APIs or write to these records directly.
2.  **Event-Driven Publishing**: All content releases, CDN invalidations, and index rebuilds must run asynchronously via outbox events. Synchronous RPC calls within main database transactions are prohibited.
3.  **Binary File Immutability**: Files saved to our DAM system are unalterable. Updates generate new files and database records to prevent broken links on older pages.
4.  **Zero-Trust Identity**: Every API request must undergo token verification and permission checks before execution. No internal node or application is trusted by default.
5.  **Row-Level Security (RLS)**: Row-Level Security policies must remain active on all database tables and materialized views, isolating tenant data at the database engine layer.
6.  **Immutable History Records**: Historical content revisions are append-only and cannot be overwritten, providing high audit compliance and SOC 2 readiness.
7.  **No Direct Cross-Domain Writes**: Database schema interactions across domains must proceed through public REST/GraphQL APIs or event brokers, avoiding tight database coupling.
8.  **Maker-Checker Compliance**: Publishing releases require review and sign-off from an independent authorized editor, preventing users from self-approving their own drafts.
9.  **Idempotent Event Consumers**: Event processing networks must enforce idempotency checks, rejecting previously processed message IDs to prevent duplicate execution errors during retries.
10. **Headless-First Architecture**: Content schemas must remain strictly presentation-agnostic, storing structured metadata, text, or markdown blocks while avoiding HTML/CSS styling code inside databases.

---

## 7. CORE DATABASE ENGINE STANDARDS

The CMS database architecture relies on built-in PostgreSQL 16 performance and scalability features:

*   **Ordered UUIDv7 Identifiers**: All physical tables utilize UUIDv7 primary keys to maintain global uniqueness, speed up B-Tree inserts, and support clean sorting.
*   **Declarative Table Partitioning**: High-volume log and outbox tables are partitioned by date ranges, keeping active indexes small and responsive.
*   **GIN Trigram Indices**: Text search and fuzzy keyword matches are optimized using native PostgreSQL GIN indices with the `pg_trgm` extension.
*   **pgvector Similarity Search**: Storing and searching semantic embeddings uses the native `pgvector` extension indexed with HNSW algorithms.
*   **Materialized View Aggregations**: Dashboard reports query pre-aggregated materialized views, refreshed concurrently in the background.
*   **Optimistic Version Columns**: Updates check version numbers before committing to prevent editorial changes from overwriting each other.

---

## 8. SECURITY & COMPLIANCE CONTEXT

Data protection and regulatory compliance are integrated directly into the database and application layers:

*   **Zero-Trust Session Mapping**: Connection pools extract tenant contexts from verified JWT tokens, setting configuration settings on every query.
*   **Envelope Encryption Standards**: Sensitive credentials and API tokens are secured using AES-256 envelope encryption.
*   **Immutable Hash-Chain Audits**: Audit trail entries include cryptographic hash signatures of the preceding row, protecting audit trails from modification.
*   **Regulatory Privacy Workflows**: Built-in GDPR workflows anonymize personal contributor records during right-to-erasure requests while preserving document histories.
*   **Asset Upload Firewalls**: Media uploads undergo magic-byte signature checks and malware scanning before being uploaded to S3.

---

## 9. ENGINEERING GOVERNANCE STANDARDS

Architectural integrity and database quality are managed using strict development governance standards:

*   **Architecture Review Board (ARB)**: Proposed changes to database schemas, APIs, or integration events require formal review and approval from our ARB.
*   **Backwards-Compatible Migrations**: Database changes must use the expand-and-contract model, allowing schema updates to deploy under active workloads without downtime.
*   **Mandatory ADR Tracking**: Significant architectural changes or library adoptions must be logged in formal Architecture Decision Records (ADRs).
*   **Strict Quality Release Gates**: Code and database changes must pass automated lint audits, security scans, pgTAP database tests, and integration benchmarks before deployment.

---

## 10. FUTURE STRATEGIC ROADMAP

The CMS is designed to scale and support future capabilities without requiring core database refactoring:

*   **Interactive Visual Builders**: Expose drag-and-drop page builders, allowing editors to arrange structured blocks visually while saving changes to structured JSONB schemas.
*   **Dynamic Custom Forms**: Build forms and collect submission data, validating inputs against configured schemas.
*   **Contextual Personalization**: Expose segment-specific content variations to visitors using headless APIs, tracking options without duplicating master files.
*   **Multi-Region Global DB Publishing**: Distribute delivery replicas across global cloud zones to minimize delivery latencies for international audiences.
*   **Automated Content Translation Workflows**: Integrate translation pipelines with professional localization networks to translate drafts automatically on save.

---

## 11. CMS ARCHITECTURAL MATRIX

The matrix below maps core CMS subsystems to their governing database tables, API gateways, background workers, and technical specifications:

| Subsystem Module | Core Database Tables | Primary APIs | Background Workers | Governing Specification |
| :--- | :--- | :--- | :--- | :--- |
| **Content Lifecycle**| `content_items`, `content_versions` | REST / GraphQL | Version archiver | `01_Content_Modeling_Publishing` |
| **Media & DAM**      | `media_assets`, `asset_renditions` | REST / Media API | Image optimizer | `02_Media_Asset_Management` |
| **Localization**     | `localized_content` | GraphQL | Translation synchronizer| `03_Localization_Multilanguage` |
| **Search Engine**    | `search_documents`, `search_embeddings`| Search / REST | Search vector compiler | `04_Search_Content_Discovery` |
| **Headless Delivery**| `mv_content_dashboard`, `site_routes` | REST / GraphQL | CDN Edge Purger | `05_Content_Delivery_API` |
| **Editorial Flow**   | `editorial_tasks`, `task_comments` | REST / Workflow | Task SLA monitor | `06_Workflow_Collaboration` |
| **SEO & Sites**      | `site_domains`, `site_redirects` | REST / Route API | Sitemap builder | `07_SEO_Site_Management` |
| **Integration Events**| `cms_outbox_events` | Webhook API | Outbox event processor | `08_CMS_Integration_Events` |
| **Dashboards & Logs**| `cms_telemetry_logs`, `cms_api_metrics` | Analytics API | Log rotater, MV refresher | `09_Dashboards_Telemetry` |

---

*Authorized by the JUANET Architecture Review Board & Global Technical Operations Council.*
