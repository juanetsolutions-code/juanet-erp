# JUANET CMS Architecture Decision Records (ADR)
## Phase 2.3.2G.13 — Permanent Rationale Log, Architectural Constraints, Trade-off Analyses, and System Design Decisions
**Document Version:** 1.0  
**Author:** Chief Software Architect, Principal Database Engineer, and Technical Review Board  
**Classification:** Public / Enterprise Architecture Ledger, Design Rationale Log, and Technical Governance Record  

---

## 1. ARCHITECTURE DECISION RECORD (ADR) PHILOSOPHY

In a global, enterprise-grade multi-tenant SaaS platform, maintaining architectural consistency over time is one of the most significant engineering challenges. As teams evolve and the system grows, the lack of recorded design context often leads to design drift, technical debt, and duplicated efforts. 

This **Architecture Decision Record (ADR) Ledger** serves as the constitutional design record for the **JUANET CMS Bounded Context**. It documents not only what decisions were made, but *why* they were made, the alternatives considered, the trade-offs evaluated, and the long-term consequences of those choices.

### 1.1 Decision Lifecycle States
Every ADR in this log progresses through a structured lifecycle managed by the Architecture Review Board (ARB):

```
                        ADR LIFECYCLE STATES
  [Proposed] ──► [Under Review] ──► [Accepted] ──► [Superseded / Deprecated]
```

*   **Proposed**: The decision is drafted by an engineering lead and presented for review.
*   **Under Review**: The ARB and domain teams evaluate the proposal, trade-offs, and compliance with platform standards.
*   **Accepted**: The decision is approved and becomes an active architectural standard.
*   **Superseded**: A newer ADR replaces the design standard. The historical record is retained with a link to the replacing ADR.
*   **Deprecated**: The architectural pattern is retired and is no longer recommended for new services.

---

## 2. DATABASE ARCHITECTURE DECISIONS (ADR-01 to ADR-09)

### ADR-01: Adoption of UUIDv7 for Database Primary Keys
*   **Status**: `Accepted`
*   **Context**: The CMS requires globally unique identifiers across multiple tenant databases to support database replication, database sharing, and off-line content creation without key collision risks.
*   **Decision**: Enforce UUIDv7 as the standard primary key type for all transactional and log tables across the CMS context.
*   **Alternatives Considered**: Auto-incrementing sequences (bigint), UUIDv4 (random).
*   **Rationale**: 
    *   *Auto-incrementing bigint* exposes tenant volume metrics and causes key collisions during data migrations.
    *   *UUIDv4* is completely random, resulting in heavy index fragmentation and poor write performance under high insert rates.
    *   *UUIDv7* integrates a millisecond-precision Unix timestamp in its most significant bits, making it time-ordered and index-friendly while maintaining global uniqueness.
*   **Consequences**: Improves database insert speeds on B-Tree indexes, supports clean cursor-based pagination, and simplifies multi-region database replication.

### ADR-02: Avoidance of PostgreSQL ENUM Types in Favor of Constrained Text Lookup Tables
*   **Status**: `Accepted`
*   **Context**: Core entities require categorization fields (e.g., `publishing_status`, `translation_status`, `workflow_state`) that evolve over time.
*   **Decision**: Prohibit the use of native PostgreSQL `ENUM` types. Instead, implement state fields as `VARCHAR(32)` columns backed by validation lookup tables or application-level constraints.
*   **Alternatives Considered**: PostgreSQL Native ENUM Types, Integer codes.
*   **Rationale**:
    *   *PostgreSQL ENUM types* are stored as physical system catalogs, making schema modifications (such as dropping or renaming values) complex and risky under active production loads.
    *   *Constrained VARCHAR fields* coupled with foreign-key lookup tables allow teams to add or rename states safely without database migration overhead.
*   **Consequences**: Simplifies database schema maintenance, prevents schema-lock escalations during deployment, and maintains high data integrity.

### ADR-03: Utilizing JSONB for Schema Extensibility
*   **Status**: `Accepted`
*   **Context**: Enterprise content types require custom metadata structures and configurable settings that vary significantly across tenants.
*   **Decision**: Store unstructured and semi-structured metadata attributes in PostgreSQL `JSONB` columns, applying schema validation rules at the application layer.
*   **Alternatives Considered**: Multi-table inheritance, Entity-Attribute-Value (EAV) tables, standard JSON text.
*   **Rationale**:
    *   *EAV tables* result in complex, slow queries with numerous joins.
    *   *JSONB* stores data in a binary format that supports indexing (GIN indexes), enabling fast JSON query operations.
*   **Consequences**: Enables flexible content modeling for tenants, speeds up development iteration times, and keeps database query execution paths efficient.

### ADR-04: PostgreSQL Declarative Table Partitioning
*   **Status**: `Accepted`
*   **Context**: Operational metrics, telemetry logs, and event outbox tables grow by millions of rows daily, slowing down indexes over time.
*   **Decision**: Partition high-volume transactional and logging tables using PostgreSQL declarative range or list partitioning.
*   **Alternatives Considered**: Single monolithic tables, application-level sharding, external logging databases (Elasticsearch).
*   **Rationale**: Partitioning large tables by date ranges or tenant identifiers isolates queries to specific partitions (partition pruning), maintaining fast index lookups as record counts grow.
*   **Consequences**: Keeps active indexes small and responsive, simplifies log cleanup, and prevents analytical queries from locking transactional tables.

### ADR-05: Standardizing Composite Indexing over Single-Column Indexes
*   **Status**: `Accepted`
*   **Context**: High-frequency queries often join tables or filter records by multiple columns (e.g., querying site routes by `organization_id`, `site_id`, and `slug_path`).
*   **Decision**: Implement composite indexes for multi-column query paths, including non-key columns using the `INCLUDE` clause where beneficial.
*   **Alternatives Considered**: Relying on PostgreSQL single-column index merges.
*   **Rationale**: Index merges can be CPU-intensive. Composite indexes allow the database engine to locate matched records in a single index scan, reducing disk I/O.
*   **Consequences**: Drastically speeds up lookups on core API paths and reduces database CPU utilization.

### ADR-06: Materialized Views for Analytical Dashboards
*   **Status**: `Accepted`
*   **Context**: Portal dashboards require aggregated metrics (e.g., editorial throughput, translation backlogs) without querying active transactional tables.
*   **Decision**: Use PostgreSQL Materialized Views to cache pre-aggregated metrics, updating views asynchronously in the background.
*   **Alternatives Considered**: Real-time aggregation queries against transactional tables, application-layer cache tables.
*   **Rationale**: Keeps dashboard response times fast while protecting primary transactional tables from locking during complex reporting runs.
*   **Consequences**: Minimizes dashboard response latencies and keeps transaction processing paths unburdened by analytics operations.

### ADR-07: Native Fuzzy Matching with pg_trgm
*   **Status**: `Accepted`
*   **Context**: Editors require fast, typographical-tolerant search lookups when managing large content repositories.
*   **Decision**: Use the native PostgreSQL `pg_trgm` extension paired with GIN indexes to support fuzzy and wildcard searches.
*   **Alternatives Considered**: Elasticsearch/OpenSearch clusters, application-level text matching.
*   **Rationale**: Native extensions allow fuzzy matching directly inside the database, removing the need to synchronize data with external search clusters.
*   **Consequences**: Lowers operational complexity, maintains real-time index synchronization, and delivers fast fuzzy matching queries.

### ADR-08: Semantic Search Integration via pgvector
*   **Status**: `Accepted`
*   **Context**: The platform requires semantic and concept-based content recommendations across articles.
*   **Decision**: Store vector embeddings in PostgreSQL using the `pgvector` extension, indexing vectors with HNSW indexes for fast retrieval.
*   **Alternatives Considered**: Pinecone, Milvus, Qdrant.
*   **Rationale**: Keeping embeddings in PostgreSQL ensures transactional safety, respects multi-tenant RLS boundaries natively, and avoids the cost of managing external vector databases.
*   **Consequences**: Protects tenant isolation boundaries, simplifies backup and restore operations, and reduces infrastructure complexity.

### ADR-09: Separating Write and Read Paths via CQRS Lite
*   **Status**: `Accepted`
*   **Context**: High-scale content delivery networks and intensive analytical dashboards can starve editorial write operations of database resources.
*   **Decision**: Enforce a CQRS Lite architecture. Editorial writes execute on the primary PostgreSQL instance, while public delivery APIs and analytics engines query read-only replica nodes and Redis caches.
*   **Alternatives Considered**: Single database instance, microservices with isolated databases.
*   **Rationale**: Prevents heavy delivery traffic from locking transactional tables, ensuring high availability for editorial teams.
*   **Consequences**: Ensures high database write availability and enables horizontal read scaling via read replica clusters.

---

## 3. CONTENT MODELING DECISIONS (ADR-10 to ADR-16)

### ADR-10: Composition Over Inheritance
*   **Status**: `Accepted`
*   **Context**: Custom content models require reusable components (e.g., SEO blocks, author profiles, call-to-action blocks) across different page templates.
*   **Decision**: Design content schemas using composition rather than inheritance, nesting reusable schemas inside primary content templates.
*   **Alternatives Considered**: Table-per-class inheritance, single-table inheritance.
*   **Rationale**: Inheritance models result in rigid database structures that are difficult to update. Composition allows editors to mix and match components flexibly.
*   **Consequences**: Simplifies schema updates, reduces database join overhead, and enables modular content modeling.

### ADR-11: Immutable Content Versions
*   **Status**: `Accepted`
*   **Context**: Legal and regulatory compliance audits require tracking every change made to content items over time.
*   **Decision**: Store all content edits as immutable revision records in a history table, treating the main content table as a pointer to the current active revision.
*   **Alternatives Considered**: In-place column updates, soft deletes with delta logs.
*   **Rationale**: Immutable revisions prevent historical data loss, support instant rollbacks, and eliminate write-lock contentions during draft updates.
*   **Consequences**: Simplifies content auditing and rollbacks, while increasing database storage requirements over time.

### ADR-12: Schema Validation via JSON Schema
*   **Status**: `Accepted`
*   **Context**: Headless APIs require consistent, structured JSON payloads to prevent rendering errors on frontend applications.
*   **Decision**: Validate incoming content payloads against pre-configured JSON Schema definitions before committing edits to database tables.
*   **Alternatives Considered**: Database-level check constraints, application-layer code validation rules.
*   **Rationale**: JSON Schema is a standardized format that is easy to update and can be shared with frontend teams for client-side validations.
*   **Consequences**: Guarantees data consistency, prevents malformed content saves, and simplifies frontend validation integrations.

### ADR-13: Strict Separation of Content and Presentation
*   **Status**: `Accepted`
*   **Context**: Content must be delivered to multiple channels (e.g., web, mobile apps, email campaigns) without including platform-specific styling or layout code.
*   **Decision**: Prohibit the storage of presentation code (HTML/CSS) inside content bodies. Content is stored as clean, structured key-value pairs or Markdown formats.
*   **Alternatives Considered**: WYSIWYG editors outputting rich HTML blobs.
*   **Rationale**: Rich HTML blobs are difficult to parse and render consistently on non-web channels (such as native mobile applications).
*   **Consequences**: Ensures content remains channel-agnostic, supporting headless delivery networks natively.

### ADR-14: Structured Block Fields Over Monolithic HTML Blobs
*   **Status**: `Accepted`
*   **Context**: Monolithic text fields make it difficult to query specific content components or extract specific media references.
*   **Decision**: Model long-form content as arrays of structured blocks (e.g., heading blocks, image blocks, text blocks) within JSONB fields.
*   **Alternatives Considered**: Standard text columns containing Markdown or raw HTML.
*   **Rationale**: Structured blocks allow analytical engines to index, search, and parse specific content segments easily.
*   **Consequences**: Enables advanced search indexing and component-level personalization.

### ADR-15: Reusable Content Components and Snippets
*   **Status**: `Accepted`
*   **Context**: Shared information (e.g., legal disclaimers, global site banners) must be updated in one place and sync across multiple pages.
*   **Decision**: Model shared content blocks as independent reusable entities, referencing their IDs from target page documents.
*   **Alternatives Considered**: Copy-pasting content across pages, custom database sync scripts.
*   **Rationale**: Referencing shared blocks prevents duplicate content, reduces database storage, and ensures updates sync instantly across pages.
*   **Consequences**: Improves content reuse rates and simplifies content management.

### ADR-16: Headless-First Delivery API Architecture
*   **Status**: `Accepted`
*   **Context**: Content must be accessible by external applications, headless rendering frameworks, and third-party integrations.
*   **Decision**: Expose content delivery through headless REST and GraphQL APIs, rather than traditional server-rendered templates.
*   **Alternatives Considered**: Traditional server-side rendering (Thymeleaf, JSP, Blade).
*   **Rationale**: A headless API decoupling separates content management from rendering, allowing developers to use modern frontend frameworks (Next.js, Remix, Nuxt).
*   **Consequences**: Enables high performance and ensures scalability across diverse frontend applications.

---

## 4. PUBLISHING ARCHITECTURE DECISIONS (ADR-17 to ADR-23)

### ADR-17: Transactional Outbox Pattern for Publishing Events
*   **Status**: `Accepted`
*   **Context**: Content publishing must notify external search indexes, CDNs, and integration partners without introducing network delays to database transactions.
*   **Decision**: Write publishing events to an outbox database table inside the main database transaction, processing events asynchronously using background sweepers.
*   **Alternatives Considered**: Synchronous external API calls within database transactions, direct message broker writes.
*   **Rationale**: Prevents external API failures from rolling back database transactions, ensuring publishing safety.
*   **Consequences**: Guarantees event delivery (at-least-once) while maintaining database write speeds.

### ADR-18: Event-Driven Publishing Workflows
*   **Status**: `Accepted`
*   **Context**: Complex publishing actions require updating multiple disconnected services (e.g., refreshing search indexes, compiling sitemaps, invalidating edge caches).
*   **Decision**: Publish actions emit asynchronous events to an event bus (e.g., Redis Streams, RabbitMQ), triggering individual service consumers.
*   **Alternatives Considered**: Monolithic synchronous execution chains.
*   **Rationale**: Event-driven decoupling prevents service failures from blocking publishing runs and allows teams to scale consumers independently.
*   **Consequences**: High system reliability and scalable, non-blocking publishing pipelines.

### ADR-19: Enforcing Idempotent Event Consumers
*   **Status**: `Accepted`
*   **Context**: Network issues or retries can cause event buses to deliver the same publishing event multiple times.
*   **Decision**: Require all event consumers to verify and log event execution states, rejecting previously processed event IDs.
*   **Alternatives Considered**: Relying on exactly-once network delivery guarantees.
*   **Rationale**: Exactly-once network delivery is difficult to guarantee in distributed networks. Idempotency ensures the system remains consistent during retries.
*   **Consequences**: Prevents duplicate execution errors and ensures data consistency across downstream services.

### ADR-20: Blue-Green Publishing Strategy
*   **Status**: `Accepted`
*   **Context**: Heavy publishing releases or massive sitemap rebuilds must not cause downtime or partial-page states for site visitors.
*   **Decision**: Build and stage new site revisions in a draft environment before updating the active delivery route pointer, swapping visitors over instantly.
*   **Alternatives Considered**: In-place live document updates.
*   **Rationale**: Avoids partial-page states and allows teams to verify large releases before making them visible to users.
*   **Consequences**: Zero-downtime publishing releases and easy rollbacks if errors are detected.

### ADR-21: Non-Blocking Scheduled Publishing
*   **Status**: `Accepted`
*   **Context**: Organizations require scheduling releases for future dates (e.g., embargoed campaign launches) without locking databases or blocking editors.
*   **Decision**: Store scheduled release metadata in database tables, using background sweepers to publish items once target timestamps are reached.
*   **Alternatives Considered**: Holding transactions open, utilizing standard system cron executors directly.
*   **Rationale**: Avoids long-lived database transactions and ensures publishing schedules run reliably regardless of active platform connections.
*   **Consequences**: Accurate release timing, minimal database overhead, and automatic failure retries.

### ADR-22: Instant Routing Rollbacks
*   **Status**: `Accepted`
*   **Context**: If a published release contains errors, administrators must be able to restore the previous version instantly to minimize impact.
*   **Decision**: Revert changes by pointing the active route identifier back to the previous immutable version, skipping database rebuild runs.
*   **Alternatives Considered**: Creating a new revert edit draft and republishing it.
*   **Rationale**: Pointing routes back to previous versions takes milliseconds, reducing downtime during emergencies.
*   **Consequences**: Sub-second routing rollbacks and improved platform recovery targets.

### ADR-23: Immutable Publishing History Logs
*   **Status**: `Accepted`
*   **Context**: Security compliance audits require maintaining unalterable records of who published what content and when.
*   **Decision**: Write all publishing actions to an append-only log table, using database permissions to block updates or deletes on log records.
*   **Alternatives Considered**: Writing history logs to external files or standard application log tools.
*   **Rationale**: Database-level write-prevention policies protect historical logs from being modified, even by compromised application servers.
*   **Consequences**: High-fidelity audit records and SOC 2 audit readiness.

---

## 5. DIGITAL ASSET MANAGEMENT DECISIONS (ADR-24 to ADR-30)

### ADR-24: DAM as the Authoritative System of Record for Assets
*   **Status**: `Accepted`
*   **Context**: Media files must be organized, optimized, and audited in a single place to prevent duplicate assets across portals.
*   **Decision**: Treat the DAM subsystem as the absolute system of record for all media files, requiring all applications to reference assets via unified DAM endpoints.
*   **Alternatives Considered**: Storing media files directly inside content databases or individual page directories.
*   **Rationale**: Avoids duplicate storage costs, ensures consistent file optimization, and simplifies asset licensing audits.
*   **Consequences**: Lowers storage costs, maintains consistent asset delivery speeds, and simplifies asset audits.

### ADR-25: Immutable Media Storage
*   **Status**: `Accepted`
*   **Context**: Content pages must not display broken images or incorrect visual assets because an asset file was renamed or modified on the server.
*   **Decision**: Store media uploads as immutable files in object storage, generating new unique filenames for file updates instead of overwriting existing objects.
*   **Alternatives Considered**: Overwriting existing files, manual folder paths.
*   **Rationale**: Immutable files prevent broken image paths on older pages and maximize edge CDN caching efficiency.
*   **Consequences**: High link reliability, optimized CDN caches, and increased storage requirements for historical file versions.

### ADR-26: SHA-256 Binary Deduplication
*   **Status**: `Accepted`
*   **Context**: Users frequently upload identical images across different pages, leading to duplicate storage costs and redundant CDN caching.
*   **Decision**: Calculate SHA-256 hashes of file contents on upload, pointing duplicate uploads to existing object storage pointers.
*   **Alternatives Considered**: Relying on manual folder organization.
*   **Rationale**: Automates storage deduplication, reducing cloud hosting costs and optimization overhead.
*   **Consequences**: Lowers cloud storage costs and prevents redundant asset optimization runs.

### ADR-27: Cloud-Agnostic Object Storage Abstraction
*   **Status**: `Accepted`
*   **Context**: The platform must support deployment across multiple cloud providers (AWS S3, Google Cloud Storage, Azure Blob) without rewriting media pipelines.
*   **Decision**: Interact with object storage through an abstraction interface, wrapping cloud-specific SDKs inside standard storage methods.
*   **Alternatives Considered**: Writing code tied directly to AWS S3 SDK libraries.
*   **Rationale**: Allows the platform to change cloud providers or use local S3-compatible storage (like MinIO) without changing application code.
*   **Consequences**: Flexible multi-cloud deployments and simplified local development configurations.

### ADR-28: Private Asset Delivery via Time-Limited Signed URLs
*   **Status**: `Accepted`
*   **Context**: Sensitive media (such as internal training videos or PDF manuals) must be restricted to authorized users.
*   **Decision**: Deliver protected assets using time-limited signed URLs, restricting direct access to public bucket links.
*   **Alternatives Considered**: Serving files through application server proxies.
*   **Rationale**: Database proxies introduce heavy server loads. Signed URLs delegate file transfers to the storage provider directly, keeping application nodes unburdened.
*   **Consequences**: Secure asset delivery with minimal application server load.

### ADR-29: Asynchronous Asset Processing Pipeline
*   **Status**: `Accepted`
*   **Context**: Media files (such as high-resolution images or videos) require resizing, thumbnail generation, and metadata extraction, which are CPU-intensive operations.
*   **Decision**: Run media processing asynchronously in background queues, returning a fast "Upload Completed" response to users while processing continues.
*   **Alternatives Considered**: Processing assets synchronously during upload requests.
*   **Rationale**: Prevents web server timeouts during large uploads and maintains fast responsive times for users.
*   **Consequences**: Stable web servers and scalable, asynchronous media processing queues.

### ADR-30: Automated Multi-Format Image Rendition Generation
*   **Status**: `Accepted`
*   **Context**: Sites must deliver optimized images to mobile devices and high-resolution screens without requiring editors to crop and resize images manually.
*   **Decision**: Automate image processing, generating modern responsive variations (WebP, AVIF) at configured dimensions on upload.
*   **Alternatives Considered**: Serving original raw images, on-the-fly image manipulation proxies.
*   **Rationale**: Pre-generating responsive variations reduces edge delivery latencies and avoids CPU spikes associated with real-time resizing proxies.
*   **Consequences**: Improved site speed scores and lower CDN delivery costs.

---

## 6. LOCALIZATION & MULTI-LANGUAGE DECISIONS (ADR-31 to ADR-36)

### ADR-31: Master Content Ownership Model
*   **Status**: `Accepted`
*   **Context**: Multi-language websites must maintain layout consistency across localized variations of the same page.
*   **Decision**: Link localized variations to a primary master document, tracking schema structures and fallback paths from the master record.
*   **Alternatives Considered**: Treating localized pages as completely independent documents.
*   **Rationale**: Master relationships allow the system to flag translation discrepancies and push layout updates across localized variations automatically.
*   **Consequences**: High layout consistency across localized pages and simplified translation management.

### ADR-32: Explicit Translation Variants Over JSON Merges
*   **Status**: `Accepted`
*   **Context**: Storing multiple languages inside a single document can make translation queries complex and slow down search indexing.
*   **Decision**: Store localized variations in separate records, rather than merging multiple languages into single JSON columns.
*   **Alternatives Considered**: Multi-lingual JSON columns (e.g., `{ "title": { "en": "Hello", "es": "Hola" } }`).
*   **Rationale**: Separate records keep translations clean, support localized search indexing naturally, and prevent concurrent update lock conflicts.
*   **Consequences**: High query performance and simplified search indexes.

### ADR-33: Centralized Translation Memory Database
*   **Status**: `Accepted`
*   **Context**: Re-translating identical phrases across content updates increases localization costs and overhead.
*   **Decision**: Maintain a translation memory database to cache approved translations, suggesting matches for new translation requests.
*   **Alternatives Considered**: Sending full pages to translators on every content update.
*   **Rationale**: Reusing approved translations reduces translation turnarounds and maintains brand terminology consistency.
*   **Consequences**: Lower localization costs and faster translation turnarounds.

### ADR-34: Hierarchical Locale Fallback Chains
*   **Status**: `Accepted`
*   **Context**: Localized sites must display content to visitors even if specific regional pages are missing.
*   **Decision**: Define fallback paths (e.g., falling back from `de-CH` to `de` to `en`), routing requests to the next available locale if target pages are missing.
*   **Alternatives Considered**: Returning 404 Page-Not-Found errors for missing regional pages.
*   **Rationale**: Prevents visitor navigation errors and provides a consistent localized user experience.
*   **Consequences**: Improved visitor engagement and reliable routing fallbacks.

### ADR-35: Independent Regional Publishing Release Controls
*   **Status**: `Accepted`
*   **Context**: Regional marketing teams require publishing localized variations independently to align with local campaigns or holiday schedules.
*   **Decision**: Decouple publishing controls, allowing localized variations to be published independently from the master document.
*   **Alternatives Considered**: Monolithic global publishing releases (all languages go live together).
*   **Rationale**: Gives regional teams the flexibility to coordinate local campaigns without being blocked by global translation timelines.
*   **Consequences**: Flexible regional marketing and decentralized publishing releases.

### ADR-36: AI-Assisted Translations with Mandatory Human Sign-Off
*   **Status**: `Accepted`
*   **Context**: AI translation engines provide instant translations, but raw AI output can contain context errors or brand style deviations.
*   **Decision**: Use AI translation tools to generate initial draft translations, requiring manual editor review and sign-off before publishing.
*   **Alternatives Considered**: Fully autonomous AI publishing, manual human-only translation workflows.
*   **Rationale**: Keeps translation times fast while ensuring content accuracy, brand safety, and quality checks.
*   **Consequences**: Fast translation turnarounds with human oversight and quality control.

---

## 7. SEARCH ARCHITECTURE DECISIONS (ADR-37 to ADR-43)

### ADR-37: Native PostgreSQL Full-Text Search
*   **Status**: `Accepted`
*   **Context**: The search engine must return relevant content quickly without adding the operational overhead of external search systems in early stages.
*   **Decision**: Build the primary search engine using native PostgreSQL Full-Text Search (FTS), indexing text columns using TSVECTOR and GIN indexes.
*   **Alternatives Considered**: Deploying Elasticsearch, OpenSearch, or Algolia integrations from day one.
*   **Rationale**: Native PostgreSQL search is transactionally safe, keeps data in a single place, and respects multi-tenant RLS boundaries without custom sync tools.
*   **Consequences**: Low operational complexity, real-time search indexing, and simple search architecture.

### ADR-38: Fuzzy Text Matching using pg_trgm
*   **Status**: `Accepted`
*   **Context**: Search queries must handle spelling errors and partial words to deliver a helpful search experience.
*   **Decision**: Use the PostgreSQL `pg_trgm` extension paired with GIN indexes to support typo-tolerant lookups.
*   **Alternatives Considered**: Custom search indexing libraries, external fuzzy search tools.
*   **Rationale**: Delivers fast, typo-tolerant search results directly in the database without syncing data to external search indexes.
*   **Consequences**: Clean, typo-tolerant search queries with minimal database overhead.

### ADR-39: Semantic and Hybrid Retrieval via pgvector
*   **Status**: `Accepted`
*   **Context**: Search engines must understand search intent and concepts, rather than just matching literal keywords.
*   **Decision**: Combine keyword searches and semantic vector search (using pgvector), ranking results using Reciprocal Rank Fusion (RRF).
*   **Alternatives Considered**: Keyword-only search engines, vector-only search systems.
*   **Rationale**: Hybrid search models deliver more relevant search results by combining precise keyword matching with contextual semantic search.
*   **Consequences**: Highly accurate search results and native multi-tenant data protection.

### ADR-40: Denormalized Search Document Indexes
*   **Status**: `Accepted`
*   **Context**: Querying content across multiple related tables (e.g., categories, tags, authors, locations) during searches slows down response times.
*   **Decision**: Maintain a denormalized search document table, combining all searchable text fields and metadata into a single index record.
*   **Alternatives Considered**: Executing complex multi-table joins on every search request.
*   **Rationale**: Keeps search queries fast by limiting scans to a single table, reducing disk I/O and query latency.
*   **Consequences**: High-speed searches with asynchronous background index synchronization.

### ADR-41: Eventual Consistency for Search Indexes
*   **Status**: `Accepted`
*   **Context**: Updating search indexes synchronously inside main database transactions can slow down content publishing actions.
*   **Decision**: Sync search indexes asynchronously using outbox event sweepers, allowing search results to update within a 15-second SLA.
*   **Alternatives Considered**: Synchronous search index updates inside publishing transactions.
*   **Rationale**: Decouples index updates from publishing actions, keeping publishing response times fast for editors.
*   **Consequences**: Rapid content publishing with eventually consistent search updates.

### ADR-42: User-Specific Search Facet Generation
*   **Status**: `Accepted`
*   **Context**: Visitors require dynamic filter counts (facets) to narrow down search results across large content catalogs.
*   **Decision**: Pre-aggregate search category counts, generating filter facets dynamically while respecting active tenant RLS boundaries.
*   **Alternatives Considered**: Calculating filter counts across the entire catalog on every query.
*   **Rationale**: Pre-aggregating facet counts keeps filter lookups fast, avoiding slow table scans on high-traffic pages.
*   **Consequences**: Quick filter selections and responsive search experiences.

### ADR-43: Reciprocal Rank Fusion (RRF) for Search Results Ranking
*   **Status**: `Accepted`
*   **Context**: Hybrid search queries must combine and prioritize keyword scores and vector similarity scores into a single ranked list.
*   **Decision**: Rank search results using Reciprocal Rank Fusion, prioritizing items that score highly across both keyword and vector searches.
*   **Alternatives Considered**: Normalizing search scores mathematically, arbitrary sorting rules.
*   **Rationale**: RRF is a proven search ranking algorithm that combines different scoring models reliably without requiring manual score normalization.
*   **Consequences**: Highly relevant, balanced search results across keyword and concept queries.

---

## 8. REJECTED ALTERNATIVES & DESIGN ANTI-PATTERNS (ADR-44 to ADR-51)

### ADR-44: Rejected — Direct Cross-Domain Database Writes
*   **Status**: `Rejected`
*   **Context**: System modules (such as the billing or shipping contexts) occasionally need to notify the CMS of customer updates or status changes.
*   **Decision**: Strictly prohibit direct cross-domain writes to the CMS database. All cross-domain communication must use public API endpoints or event buses.
*   **Rationale**: Direct writes create tight coupling between database schemas, making it difficult to update individual service databases without breaking unrelated services.
*   **Consequences**: Clean database separation and independent, reliable database schema updates.

### ADR-45: Rejected — Synchronous Inter-Service RPC for Content Publishing
*   **Status**: `Rejected`
*   **Context**: Publishing events must notify downstream systems (such as CDNs, search indexes, sitemaps, translation systems) of content changes.
*   **Decision**: Reject synchronous inter-service RPC calls (e.g., synchronous HTTP/gRPC) during publishing actions.
*   **Rationale**: Synchronous chains create cascading failure risks, where a failure on a single downstream service blocks the entire publishing run.
*   **Consequences**: Scalable publishing pipelines that are insulated from downstream service outages.

### ADR-46: Rejected — Mutable Content Revision History
*   **Status**: `Rejected`
*   **Context**: Historical content records can consume significant database storage as page revisions grow.
*   **Decision**: Reject mutable history systems. All historical content revisions are unalterable and must not be overwritten.
*   **Rationale**: Modifying historical records breaks regulatory compliance rules, compromises audit trails, and complicates disaster recovery.
*   **Consequences**: High audit compliance and SOC 2 readiness at the cost of disk space over time.

### ADR-47: Rejected — Proliferation of Database ENUM Types
*   **Status**: `Rejected`
*   **Context**: System models require status flags to track editorial stages, translations, workflows, and task assignments.
*   **Decision**: Prohibit the creation of native PostgreSQL `ENUM` types for fields that are expected to grow or change.
*   **Rationale**: System catalog locks associated with updating native ENUM values cause schema lockups and service interruptions during deployments.
*   **Consequences**: Zero deployment downtime and safe schema updates using lookup tables or constrained text values.

### ADR-48: Rejected — Monolithic Global Search Indexes
*   **Status**: `Rejected`
*   **Context**: Search lookups query content across all tenants in shared multi-tenant database environments.
*   **Decision**: Reject global search indexes. All search queries must include organization filters to isolate searches within tenant boundaries.
*   **Rationale**: Global search indexes introduce severe data leak risks, where tenants could view each other's search results.
*   **Consequences**: Safe tenant data isolation and compliance with privacy standards.

### ADR-49: Rejected — Shared Multi-Tenant Digital Assets
*   **Status**: `Rejected`
*   **Context**: Tenants occasionally upload identical media assets (such as public logos or standard illustrations) to the DAM.
*   **Decision**: Reject shared asset pointers. All assets are isolated within tenant boundaries, even if files are binary duplicates.
*   **Rationale**: Sharing asset files across tenants introduces data privacy risks and can lead to unauthorized access if a tenant deletes a shared asset.
*   **Consequences**: Clear tenant asset boundaries and secure data isolation.

### ADR-50: Rejected — Fully Autonomous AI Content Publishing
*   **Status**: `Rejected`
*   **Context**: AI generation tools can write, translate, and tag articles quickly to speed up content creation.
*   **Decision**: Prohibit fully autonomous AI publishing. All AI-generated text, translations, and metadata require human editor review and approval.
*   **Rationale**: AI models can generate incorrect information, and automated publishing risks spamming sites or distributing brand-damaging content.
*   **Consequences**: High editorial quality and brand safety with human review gates.

### ADR-51: Rejected — Soft Security Boundaries and Network-Only Firewalls
*   **Status**: `Rejected`
*   **Context**: Protecting data across multiple tenants in shared cloud hosting environments.
*   **Decision**: Reject network-only security models. All data tables and materialized views must enforce database-level Row-Level Security (RLS) policies.
*   **Rationale**: Network firewalls can be bypassed. Database-level RLS policies act as a final layer of defense, securing data even if application servers are compromised.
*   **Consequences**: Strong multi-tenant data protection and compliance with enterprise security standards.

---

## 9. ARCHITECTURAL REVIEW BOARD (ARB) GOVERNANCE

To prevent design drift and maintain architectural standards as the platform scales, the development of the CMS is managed by the **Architecture Review Board (ARB)**:

*   **Review Cadence**: The ARB meets bi-weekly to review proposed ADRs, evaluate system performance benchmarks, and audit compliance metrics.
*   **Deprecation and Superseding Policies**: When a design standard is updated, the associated ADR's status is changed to `Superseded`, with a link to the replacing ADR to maintain design context.
*   **ARB Approval Process**: New ADR proposals require review from at least three principal engineers, including security and database representatives, ensuring comprehensive reviews before approval.
*   **Audit Trail Compliance**: Approved ADR files are committed directly to version control, serving as unalterable records of system design decisions for compliance audits (SOC 2, ISO 27001).

---

## 10. DECISION DEPENDENCY MATRIX

The matrix below maps major architectural decisions to their related technical specifications, dependencies, and risk factors:

| Decision Identifier | Related Specification Manuals | Dependent Subsystems | Key Risk Factors | Future System Impacts |
| :--- | :--- | :--- | :--- | :--- |
| **ADR-01 (UUIDv7)** | Physical Tables Specification | All Database Tables | Key Generation Overhead | High-speed migrations, sequential indices. |
| **ADR-09 (CQRS Lite)**| Delivery and API Gateway | Public APIs, Replicas | Replica Lag Latency | Decoupled scaling, sub-15ms response SLAs. |
| **ADR-11 (Immutability)**| Content Modeling & Publishing| Revisions, History Logs | Storage Volume Growth | Instant rollbacks, audit compliance. |
| **ADR-17 (Outbox)**   | Integration and Events | Event Buses, Workers | Outbox Sweeper Delays | Transaction safety, eventual consistency. |
| **ADR-26 (Deduplication)**| Media and DAM Specification | Object Storage, DAM APIs | Hashing CPU Spikes | Reduced storage costs, optimized caches. |
| **ADR-34 (Fallbacks)**| Localization & Multi-Language | Routing Tables, API | Loop Routing Configs | Continuous content delivery, SEO fallbacks. |
| **ADR-39 (Hybrid FTS)**| Search & Content Discovery | Vector Database, FTS | Multi-Tenant Vector Leaks | Contextual search, secure tenant data. |
| **ADR-51 (RLS Policies)**| Security & Compliance | All Database Tables | Session Context Missing | Bulletproof tenant isolation, security. |

---

## 11. REFS & GOVERNANCE DOCUMENT MAP

This ledger coordinates architectural decisions across the entire CMS Bounded Context. Refer to the associated manuals for implementation details:
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
*   **Performance & Scalability (`Phase_2_3_2G_10_CMS_Performance_and_Scalability.md`)**: Governs database partitioning, indexing configurations, and multi-tier caching structures.
*   **Security & Compliance (`Phase_2_3_2G_11_CMS_Security_Privacy_and_Compliance.md`)**: Governs Zero Trust security, RLS policies, encryption, and data privacy regulations.
*   **Testing & Validation (`Phase_2_3_2G_12_CMS_Testing_and_Validation.md`)**: Governs automated verification matrices, pgTAP tests, disaster recovery validation, and CI/CD release gates.

---

*Authorized by the JUANET Architecture Review Board & Global Technical Operations Council.*
