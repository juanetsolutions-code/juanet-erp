# JUANET Content Modeling and Publishing Engine
## Phase 2.3.2G.1 — Operational Governance, Lifecycle State Machines, and Decoupled Publishing Workflows
**Document Version:** 1.0  
**Author:** Chief Enterprise Content Architect, Lead Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and CMS Specification  

---

## 1. ARCHITECTURAL PHILOSOPHY

In high-scale enterprise multi-tenant systems, the management of digital content must be isolated from the presentation layers and core transactional engines. The **JUANET Content Management System (CMS) Bounded Context** treats **Content as the Enterprise System of Record (SoR)**. 

To achieve maximum scalability, maintain absolute multi-tenant boundary isolation, and support diverse omni-channel headless publishing, the system is designed around a clean decoupling of six distinct operational areas:

```
                          [CMS OPERATIONAL DECOUPLING BOUNDARIES]
  ┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
  │   CONTENT CORE   ├────►│  LAYOUT ENGINE   ├────►│   MEDIA REPO     │
  │ (Raw Struct Data)│     │(Component Grids) │     │ (CDN & Storage)  │
  └────────┬─────────┘     └────────┬─────────┘     └────────┬─────────┘
           │                        │                        │
           ▼                        ▼                        ▼
  ┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
  │  WORKFLOW ENGINE │     │PUBLISHING ENGINE │     │  DELIVERY LAYER  │
  │ (State Machine)  │     │(Immutable Snap)  │     │  (REST/GraphQL)  │
  └──────────────────┘     └──────────────────┘     └──────────────────┘
```

The system enforces the following architectural principles:
*   **Content Never Contains Presentation**: Content is represented as structured, semantic key-value payloads or references (such as text, numbers, dates, lists, and relations). It does not embed HTML inline styling, layout coordinates, CSS selectors, or font configurations.
*   **Layouts Never Own Content**: Page layouts and rendering components define structural grids and variable placeholders. They do not hold localized copywriting, media bytes, or SEO titles.
*   **Media Never Embeds Business Logic**: Media folders and assets represent binary resources mapped with cryptographic checksums. They do not dictate user routing patterns, gate accesses, or contain target channel logic.
*   **Publishing Never Modifies Historical Versions**: Initiating a publishing job creates an immutable snapshot of a specific version tag. Past active versions remain unmodifiable and archived to support auditing, compliance, and point-in-time recovery.
*   **Operational Systems Consume Published Content via APIs and Events**: Downstream domains (such as CRM, Finance, or Support) consume CMS assets exclusively through read-optimized delivery APIs and system events, maintaining decoupling at the database layer.

---

## 2. CONTENT MODELING PRINCIPLES

To prevent content from degenerating into unparseable HTML blobs, the CMS enforces a highly structured, strongly typed content modeling standard:

```
                            [STRUCTURED CONTENT COMPOSITION]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                             Content Type                               │
  │   - System Name: "marketing_hero_campaign"                             │
  │   - JSON Schema Constraint Validation Rules                            │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Composes)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────┐              ┌──────────────┐              ┌──────────────┐
│Field Groups  │              │Nested Comp   │              │Reusable Snipp│
│- SEO Config  │              │- CTA Button  │              │- Legal Discl │
└──────────────┘              └──────────────┘              └──────────────┘
```

### 2.1 Core Modeling Components
*   **Content Types**: Declarative blueprints configured via JSON Schema. They define properties, fields, expected types (e.g., String, Number, Array, GeoPoint), and validation parameters.
*   **Reusable Components**: Modular field compositions (e.g., `cta_button`, `testimonial_card`) that do not exist as independent page items but can be nested inside any page section.
*   **Rich Text Blocks**: Decoupled, semantic representations of styled text (e.g., Draft.js, Portable Text, or lexical nodes) rather than raw HTML. This ensures identical rendering across Web, Mobile, and Voice interfaces.
*   **Structured Content**: The standard representation of a content instance. Every property is stored as a distinct JSON element, allowing fast querying, sorting, and field-level localization.
*   **Dynamic Fields**: Fields that support dynamic, runtime values, such as referencing a user's location or displaying a product's live price from the CRM.
*   **Field Groups**: Logical groupings of related fields (e.g., `social_media_sharing`) designed to simplify the editing interface.
*   **Nested Components**: Multi-level hierarchical arrays (e.g., a FAQ list containing individual question/answer blocks) validated using deep schema validation.
*   **Reusable Snippets**: Global content items (e.g., standard header disclaimers, privacy policies) designed to be referenced across multiple pages without duplication.
*   **Content Templates**: Structure definitions that map Content Types to page positions, serving as headless rendering guides for presentation clients.

### 2.2 Composition over Inheritance
The CMS modeling engine prefers **Composition over Inheritance**. Complex content types are built by assembling reusable field groups and components, rather than deriving child content models from parents. This prevents structural changes from cascading and breaking existing page definitions.

---

## 3. DETAILED CONTENT LIFECYCLE STATE MACHINE (FSM)

The CMS coordinates content lifecycle changes through a deterministic Finite State Machine (FSM). All state mutations are validated, recorded in audit logs, and trigger system-wide integration events.

```
                                [CONTENT LIFECYCLE FSM]

               ┌───────────────► [ 1. Draft ] ◄───────────────┐
               │                     │                        │
               │                     ▼                        │ (Reject / Rollback)
               │             [ 2. Editing ]                   │
               │                     │                        │
               │                     ▼                        │
               │             [ 3. In_Review ] ────────────────┘
               │                     │
               │ (Revert)            ▼
               ├───────────── [ 4. Approved ] ◄───────────────┐
               │                     │                        │ (Unpublish)
               │                     ▼                        │
               │            [ 5. Scheduled ]                  │
               │                     │                        │
               │                     ▼                        │
               └──────────── [ 6. Published ] ────────────────┘
                                     │
                                     ▼
                              [ 7. Deprecated ]
                                     │
                                     ▼
                              [ 8. Archived ]
```

### 3.1 State Specifications and Mutation Invariants

| Current State | Target State | Transition Rule | Automatic Triggers | Required Actors |
| :--- | :--- | :--- | :--- | :--- |
| **Draft** | Editing | Initial content save action. | None | Content Author |
| **Editing** | In_Review | Editor submits the content item for approval. | Verification trigger checks required fields. | Content Author |
| **In_Review** | Approved | Editor approves the content item. | None | Content Editor / Manager |
| **In_Review** | Draft | Editor rejects the content item, providing feedback. | None | Content Editor / Manager |
| **Approved** | Scheduled | Release scheduler configures a future publishing date. | Scheduler validates publication target. | Release Coordinator |
| **Approved** | Published | Publisher initiates immediate publication. | Live CDN routing activation trigger. | Publisher |
| **Scheduled** | Published | System detects target publish timestamp. | Job worker dispatches publication task. | System Task Worker |
| **Published** | Deprecated | Content is replaced by a newer article or version. | Search index deprecation trigger. | Content Manager |
| **Published** | Draft | Content manager unpublishes the item to make changes. | None | Content Manager |
| **Deprecated**| Archived | Content is retired from public indexing channels. | Automated cleanup process. | System Task Worker |
| **Archived** | Deleted | Content is soft-deleted. | System retention process runs. | System Task Worker |

### 3.2 Transition Restrictions
*   **Direct Pathing**: Content in `Draft` cannot transition directly to `Published` or `Scheduled` without passing through `In_Review` and `Approved` states.
*   **State Locking**: Once content enters `Published`, its associated `content_versions` record is locked. Any subsequent edit creates a new `Draft` revision, preserving the published article's integrity.
*   **Timeouts**: Content in `In_Review` that is not approved or rejected within 14 days is automatically flagged and returned to `Draft` state to prevent stalled workflows.

---

## 4. VERSIONING ENGINE SPECIFICATIONS

To maintain audit compliance, prevent content conflicts, and support point-in-time rollbacks, the CMS utilizes an append-only, immutable versioning engine:

```
                            [IMMUTABLE VERSION TRACKING]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                       public.content_items                             │
  │   - id: 7e2b-4011 (Active Version Reference Points to v2)              │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │
         ┌────────────────────────────┼────────────────────────────┐
         ▼                            ▼                            ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│Version v1 (Arch) │         │Version v2 (Pub)  │         │Version v3 (Draft)│
│- Payload: Q1 Copy│         │- Payload: Q2 Copy│         │- Payload: Q3 Edit│
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

### 4.1 Version Numbering Rules
*   **Major Versions (`X.0`)**: Represent approved, published snapshots (e.g., `1.0`, `2.0`). Creating a major version locks the payload and updates the public CDN cache.
*   **Minor Versions (`X.Y`)**: Represent active, intermediate drafts (e.g., `1.1`, `1.2`). These are used to track incremental changes and are visible only within the authoring interface.

### 4.2 Handling Edit Conflicts
The versioning engine uses **Optimistic Locking** to resolve edit conflicts when multiple authors edit the same article:
```sql
-- Ensure update target version matches current database version
UPDATE public.content_items
SET version = version + 1,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :content_item_id
  AND version = :expected_version;
```
If the query returns 0 rows, a conflict is detected. The editing service halts the transaction and prompts the author to merge their changes with the current active version.

### 4.3 Version Rollbacks
Rollbacks do not delete historical records. Instead, rolling back content to a previous version (e.g., reverting to `1.0`) copies the past version's payload and inserts it as a new draft revision, preserving the audit trail:
```sql
-- Restore version 1.0 content payload as a new draft revision
INSERT INTO public.content_versions (
    id, organization_id, content_item_id, revision_number, status_code, structured_payload, change_comment, created_by
)
SELECT 
    uuid_generate_v7(), organization_id, content_item_id, :new_revision_number, 'Draft', structured_payload, 'Rollback to v1.0', :user_id
FROM public.content_versions
WHERE content_item_id = :content_item_id 
  AND revision_number = 1;
```

---

## 5. REVOLUTIONARY PUBLISHING ENGINE

The CMS publishing engine coordinates content deployments across channels and environments, ensuring consistent, high-availability delivery:

```
                          [DECOUPLED PUBLISHING DISPATCH]
  ┌───────────────────────┐                                 ┌───────────────────────┐
  │   Primary DB Cluster  ├────────────────────────────────►│  Active Edge CDN      │
  │   (Mutates Content)   │                                 │  (Serves Cache)       │
  └──────────┬────────────┘                                 └───────────▲───────────┘
             │                                                          │
             │ (Writes Transactional Outbox Event)                      │ (Invalidates Cache)
             ▼                                                          │
  ┌─────────────────────────────────────────────────────────────────────┴───────────┐
  │                         TRANSACTIONAL INTEGRATION LAYER                         │
  │   - audit.outbound_events (atomic capture of publication events)                │
  │   - background task workers process the outbox and update target environments   │
  └─────────────────────────────────────────────────────────────────────────────────┘
```

### 5.1 Publishing Pipelines
*   **Immediate Publishing**: The publishing engine processes the content item immediately, updating edge servers and dispatching invalidation requests to the CDN.
*   **Scheduled Publishing**: An automated system worker scans the publishing queue for scheduled tasks, verifying that target timestamps are met before triggering deployments:
```sql
-- Retrieve and lock due publishing jobs
SELECT id, organization_id, publish_target_id
FROM public.publish_jobs
WHERE job_status = 'Scheduled'
  AND schedule_at <= CURRENT_TIMESTAMP
FOR UPDATE SKIP LOCKED;
```
*   **Blue-Green Publishing**: Deploys content updates to a staging target environment (Green) for automated testing. Once tests pass, the router switches production traffic to the new content branch (Green) and deprecates the old branch (Blue).

### 5.2 Failure Isolation and Retry Logic
To prevent API timeouts or network blips from stalling workflows, the publishing engine isolates publishing tasks within background job workers. If a deployment fails, the worker executes a standardized retry schedule:
*   *Retry 1*: 30 seconds delay.
*   *Retry 2*: 5 minutes delay.
*   *Retry 3*: 30 minutes delay.
If all retries fail, the system transitions the job status to `Failed`, sends an alert notification, and writes the event payload to the Dead-Letter Queue (DLQ).

---

## 6. SLUG AND URL PATHWAY GOVERNANCE

URL pathways serve as direct entry points for search engines and visitors. The CMS enforces strict path generation and validation policies to maintain search visibility and prevent routing errors:

### 6.1 Slug Generation and Uniqueness
Slugs are generated automatically from the content title, converting characters to lowercase and replacing spaces with hyphens:
```
Title: "Corporate Q2 Strategy Update!" ──► Slug: "corporate-q2-strategy-update"
```
The system ensures slug uniqueness across each locale and organization by combining the route, locale, and slug in a composite unique index constraint on the database:
```sql
-- Database constraint enforces unique paths per tenant and locale
ALTER TABLE public.localized_pages 
ADD CONSTRAINT uq_localized_pages_slug UNIQUE (organization_id, locale_code, slug_path);
```

### 6.2 Managing URL Changes and Redirects
To prevent broken links when a page slug changes, the system creates a 301 Permanent Redirect rule. The old slug path is registered as a source, pointing to the new target URL pathway:
```sql
-- Register a permanent redirect for changed paths
INSERT INTO public.redirect_rules (
    id, organization_id, source_url_path, target_url_path, redirect_code, is_active
) VALUES (
    uuid_generate_v7(), :org_id, :old_slug_path, :new_slug_path, 301, TRUE
);
```

---

## 7. SCHEMA VALIDATION ENGINE

The validation engine evaluates content types and field rules, verifying that items meet system requirements before transitioning to active publishing states:

```
                            [CONTENT VALIDATION PIPELINE]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Incoming Draft Content                       │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Verifies Fields)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────┐              ┌──────────────┐              ┌──────────────┐
│Schema Check  │              │Reference Val │              │Language Comp │
│- Properties  │              │- Broken links│              │- Localized   │
│- Value types │              │- Circular ref│              │  required fld│
└──────────────┘              └──────────────┘              └──────────────┘
```

The validation engine runs the following checks during the publishing pipeline:
*   **Schema Schema Validation**: Compares the incoming JSON content payload against the model's schema definition, checking properties, data types, value ranges, and required fields.
*   **Reference Integrity Check**: Scans referenced content items (`content_references`) and media folders to ensure they exist and have not been soft-deleted.
*   **Circular Reference Detection**: Analyzes relationship trees to ensure parent-child dependencies do not form infinite rendering loops.
*   **Localization Verification**: Confirms that required translation fields exist and are populated for all target locales.
*   **Publishing Eligibility**: Verifies that the associated content item has been approved, contains valid slugs, and matches target release schedules.

---

## 8. GRAPH RELATIONSHIPS AND DEPENDENCY TREES

The CMS structures page and article dependencies as a directed graph, tracking relations to maintain referential integrity:

```
                           [CONTENT DEPENDENCY MAP]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                        public.pages (Landing Page)                     │
  └───────────────────┬────────────────────────────┬───────────────────────┘
                      │ (Composes)                 │ (Composes)
                      ▼                            ▼
         ┌──────────────────────────┐   ┌──────────────────────────┐
         │ public.page_sections     │   │ public.page_sections     │
         │ (Hero Banner Section)    │   │ (Main Features Grid)     │
         └────────────┬─────────────┘   └────────────┬─────────────┘
                      │                              │
                      ▼ (Binds)                      ▼ (References)
         ┌──────────────────────────┐   ┌──────────────────────────┐
         │ public.page_components   │   │ public.content_references│
         │ (CTA Component block)    │   │ (Related Products)       │
         └────────────┬─────────────┘   └────────────┬─────────────┘
                      │                              │
                      ▼ (Uses)                       ▼ (Links)
         ┌──────────────────────────┐   ┌──────────────────────────┐
         │ public.media_assets      │   │ public.content_items     │
         │ (Promo Hero Image)       │   │ (Product Specifications) │
         └──────────────────────────┘   └──────────────────────────┘
```

The dependency engine manages relationships across components:
*   **Parent-Child Relations**: Structures hierarchical layouts (such as landing pages containing nested sections and feature components).
*   **Content References**: Connects reusable content blocks (such as CTA components or legal disclaimers) to pages.
*   **Media Usage Tracking**: Links media assets to the layout components referencing them, preventing active image or document files from being deleted.
*   **Cross-Language Synced References**: Links translation copies back to their master content containers, maintaining layout consistency across languages.

---

## 9. MULTI-LANGUAGE PUBLISHING ENGINE

The CMS supports localized content structures, enabling tenants to deliver personalized content across regions:

### 9.1 Localized Routing Paths
The system generates unique routing paths for each locale. Presentation engines resolve paths based on locale prefixes:
*   *English (en-US)*: `/marketing/products/enterprise-solutions`
*   *Spanish (es-ES)*: `/es/marketing/productos/soluciones-empresariales`
*   *Arabic (ar-SA)*: `/ar/marketing/products/enterprise-solutions` (Sets the Right-to-Left `is_rtl` script flag for page layouts)

### 9.2 Fallback Resolution Path
If a requested translation is incomplete or unpublished, the routing engine falls back to the tenant's default locale to ensure pages render without errors:
```sql
-- Resolve requested translation with fallback support
SELECT 
    COALESCE(loc.rich_body, fallback.rich_body) AS resolved_body,
    COALESCE(loc.title, fallback.title) AS resolved_title
FROM public.content_items item
LEFT JOIN public.localized_content loc 
    ON loc.content_item_id = item.id AND loc.locale_code = :requested_locale AND loc.deleted_at IS NULL
LEFT JOIN public.localized_content fallback 
    ON fallback.content_item_id = item.id AND fallback.locale_code = :default_locale AND fallback.deleted_at IS NULL
WHERE item.id = :content_item_id;
```

---

## 10. SEARCH INFRASTRUCTURE INTEGRATIONS

The database schema is pre-configured to support high-performance text search across content fields:

```
                         SEARCH INDEX PROCESSING PIPELINE
  [Localized Copywriting] ──► [Precomputed tsvector Column] ──► [GIN Text Search Index]
                                                                       │
         ┌─────────────────────────────────────────────────────────────┴─────────────────────┐
         ▼ (Keyword-Based Query)                                                             ▼ (Trigram Partial Search)
  [FTS ts_query matches] ──► [Fast Index Returns]                           [trgm GIN partial matches]
```

*   **Pre-computed Search Vectors**: Automatically compiles translated text fields into search vectors (`tsvector` layout blocks) on row insertion or update.
*   **Weighted Search Ranking**: Assigns relevance weights to search vectors to prioritize matches during keyword queries (e.g., matching titles are weighted higher than matching body text):
```sql
-- Weighted full-text search across localized content
CREATE INDEX idx_localized_content_fts ON public.localized_content USING GIN (
    (setweight(to_tsvector('english', COALESCE(title, '')), 'A') ||
     setweight(to_tsvector('english', COALESCE(excerpt, '')), 'B') ||
     setweight(to_tsvector('english', COALESCE(rich_body, '')), 'C'))
);
```
*   **Vector Search Support**: Maps semantic search indexes to embedding columns (`public.kb_embeddings`), enabling fast similarity search queries using the `pgvector` database extension.

---

## 11. SECURITY & ACCESS CONTROL

The security framework protects content access, enforces editing controls, and records user actions directly within the database:

### 11.1 Row-Level Security Policies
Every table within the CMS schema has Row-Level Security enabled, isolating data using verified tenant context values:
```sql
ALTER TABLE public.content_items ENABLE ROW LEVEL SECURITY;

CREATE POLICY cms_item_tenant_isolation ON public.content_items
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 11.2 Editorial Roles & Permissions (RBAC)
*   **Content Author**: Can create draft revisions and edit content items, but cannot approve releases.
*   **Content Editor**: Can review draft revisions and transition items between draft, review, and approved states.
*   **Publisher**: Can coordinate releases, schedule publishing tasks, and publish approved content items.

### 11.3 Maker-Checker (Dual Authorization) Workflow
To prevent unauthorized or accidental modifications to critical resources (such as legal notices or security disclaimers), the system requires two distinct users to complete the publishing pipeline: the Author (Maker) who initiates the change, and the Editor (Checker) who approves and deploys it.

---

## 12. CMS SYSTEM EVENT PIPELINE

All content changes, status updates, and publishing tasks trigger system-wide integration events, allowing decoupled services to process postings asynchronously:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [FSM State Mutation] ──► [Transactional Outbox Table] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 12.1 CMS Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Content Created** | `content.created` | `ContentService` | SEO Engine, Search Indexer | `{ "content_id": "uuid", "type": "blog_post" }` |
| **Content Updated** | `content.updated` | `ContentService` | Translators, Search Indexer | `{ "content_id": "uuid", "revision": 3 }` |
| **Review Requested**| `content.review_requested` | `WorkflowEngine` | Notification Service | `{ "content_id": "uuid", "author_id": "uuid" }` |
| **Content Approved**| `content.approved` | `WorkflowEngine` | Release Scheduler | `{ "content_id": "uuid", "approver_id": "uuid" }` |
| **Content Rejected**| `content.rejected` | `WorkflowEngine` | Notification Service | `{ "content_id": "uuid", "comments": "string" }` |
| **Content Published**| `content.published` | `PublishEngine` | CDN Edge, Search, CRM | `{ "content_id": "uuid", "target": "prod" }` |
| **Content Unpublished**| `content.unpublished`| `PublishEngine` | CDN Edge, Search | `{ "content_id": "uuid", "target": "prod" }` |
| **Content Archived**| `content.archived` | `ContentService` | Search, Analytics | `{ "content_id": "uuid", "archive_date": "date" }`|
| **Version Created** | `version.created` | `VersionEngine` | Auditing, Backup Systems | `{ "content_id": "uuid", "version": "2.0" }` |

### 12.2 Event Delivery Guarantees
*   **At-Least-Once Delivery**: Events are written to the transactional outbox table (`audit.outbound_events`) within the same database transaction as the content update. Background workers poll the table and dispatch events to the message broker, guaranteeing event delivery.
*   **Idempotency Protection**: Consumers verify incoming event payloads using an idempotency key consisting of the event type, content ID, and revision number to prevent duplicate processing:
```
Idempotency Key: hash('content.published' + content_item_id + version_number)
```

---

## 13. PERFORMANCE AND DATABASE OPTIMIZATION

The CMS schema is optimized to maintain sub-15ms query times under heavy transaction volumes:

*   **Version Storage Optimization**: Compresses older, inactive draft payloads in `public.content_versions` using GZIP to reduce database disk space.
*   **Materialized View Cache**: Pre-computes and caches aggregated dashboard metrics using concurrently refreshed Materialized Views, preventing slow analytical queries from blocking operational transactions.
*   **Queued Task Processing**: Dispatches heavy processing tasks (such as generating vector search embeddings or processing image crops) to background worker queues, keeping API response times fast.
*   **Partial Covering Indexes**: Creates index structures that target active, published content items while ignoring soft-deleted or draft records to speed up lookups:
```sql
CREATE INDEX idx_content_items_active_pub ON public.content_items (organization_id, id) 
WHERE current_status_code = 'Published' AND deleted_at IS NULL;
```

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **FSM Transitions** | Simulate valid and forbidden transitions on content items. | Restricts incorrect transitions, emitting clean state validation errors. | State Machine Unit Tests |
| **Optimistic Concurrency**| Run parallel edit routines on a single content record. | System allows only the first update to complete, rejecting subsequent edits. | Concurrency Integration Tests |
| **Multi-Tenant Isolation**| Query database records without setting session context parameters. | RLS policies block the query, preventing tenant data leaks. | RLS Penetration Audits |
| **Reference Integrity** | Attempt to delete a media asset referenced by a page component. | Database constraints block the deletion, returning referential errors. | Database Integrity Checks |
| **Slug Redirect Rules** | Modify an active page slug and verify URL routing behavior. | Old URL path returns a 301 redirect pointing to the new slug path. | Routing Redirect Integration Tests|
| **Search Indexing** | Create and publish a localized article. | System automatically generates GIN search vectors and updates index targets.| Search Vector Indexing Tests |
| **Outbox Atomic Rollback**| Inject a failure during content save actions. | Database rolls back both the content update and the outbound event record. | Atomic Transaction Tests |

---

## 15. CONSTITUTIONAL ENGINEERING PRINCIPLES

All CMS implementations within the JUANET platform adhere strictly to the following architectural guidelines:

*   **Content is Immutable Once Published**: Approved and published content versions are locked. Modifying content generates a new draft revision, preserving historical integrity.
*   **Publishing Creates New Major Versions**: Publishing dispatches create new major version records, while intermediate draft saves create minor version iterations.
*   **Presentation Never Owns Content**: Layout containers define grids and variable slots. They do not store localized copywriting or media files.
*   **Content Models Remain Structured**: Content fields are strongly typed and schema-validated. Unstructured HTML blobs are prohibited.
*   **Operational Systems Never Mutate CMS Data Directly**: External domains interact with CMS tables exclusively through read-optimized delivery APIs and system events.
*   **Every Published Artifact is Reproducible**: System configurations and version records guarantee that any previous page state can be reconstructed and reviewed.
*   **Every Change is Auditable**: Every content update, state transition, and publication task writes detailed audit records containing user context to immutable tables.

---

*Authorized by the JUANET Content Architecture Review Board & Technical Governance Council.*
