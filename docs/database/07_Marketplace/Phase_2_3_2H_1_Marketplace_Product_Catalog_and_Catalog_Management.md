# JUANET Marketplace Product Catalog and Catalog Management Manual
## Phase 2.3.2H.1 — Business Architecture, Lifecycle State Machines, Event Contracts, and Integration Standards
**Document Version:** 1.0  
**Author:** Principal Enterprise Solutions Architect, VP of Commerce Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. ARCHITECTURAL PHILOSOPHY

The **JUANET Marketplace Product Catalog** is the authoritative, multi-tenant **System of Record (SoR)** for all products, variations, categories, and attributes across the entire marketplace platform. This subsystem must be implemented with strict boundaries separating it from high-velocity transactional systems, ensuring physical scaling limits, localization accuracy, and global multi-tenant performance matching industry benchmarks such as SAP Commerce Cloud, Salesforce Commerce Cloud, and Adobe Commerce (Magento).

```
                      [DECOUPLED CATALOG BOUNDED CONTEXT]

     ┌───────────────────────┐              ┌───────────────────────┐
     │   Pricing Service     │ ◄───Events──►│  Inventory Service    │
     └───────────▲───────────┘              └───────────▲───────────┘
                 │ (API Queries)                        │ (API Queries)
                 ▼                                      ▼
     ┌──────────────────────────────────────────────────────────────┐
     │                 PRODUCT CATALOG BOUNDED CONTEXT              │
     │  - Master SKU Registry                                       │
     │  - Taxonomies & Dynamic Categories                           │
     │  - Custom Variant Matrix & Attributes                        │
     │  - Versioned Immutable Publishing Snapshots                  │
     └───────────────────────────┬──────────────────────────────────┘
                                 │
                     (Transactional Outbox Events)
                                 ▼
                     ┌───────────────────────┐
                     │    Search Engine      │
                     └───────────────────────┘
```

### 1.1 Bounded Context & Aggregate Roots
Under Domain-Driven Design (DDD) principles, the Catalog domain operates as an independent bounded context centered on two main aggregate roots:
*   **Vendor Aggregate Root**: Owns vendor profiles, compliance records, and settlement preferences (configured in `public.vendors` of the [Physical Tables Specification](Phase_2_3_2H_Marketplace_Placeholders.md#3-vendor-management)).
*   **Product Aggregate Root**: Governs the lifecycle, metadata, specifications, and layout rules of catalog items (configured in `public.products` of the [Physical Tables Specification](Phase_2_3_2H_Marketplace_Placeholders.md#4-product-catalog)). 

### 1.2 Isolation & Decoupling Boundaries
To prevent database locking contention and maintain microservice autonomy, the Catalog domain strictly separates its models from high-velocity operational boundaries:
*   **Decoupling from Orders**: The catalog does not record order lists or cart items. Instead, it exposes lightweight, version-locked API snapshots. When an order is placed, reference links are recorded in `public.marketplace_order_links` to preserve historic product snapshots.
*   **Decoupling from Inventory**: Dynamic inventory balances are tracked inside the Inventory Subsystem (`public.inventory_items`). The catalog remains blind to actual warehouse levels, querying quantity states via real-time endpoints or receiving `inventory.threshold_breached` events.
*   **Decoupling from Pricing**: Base MSRP is held in catalog tables for display, but dynamic pricing, customer-specific contract rates, coupon codes, and regional promotions are managed by the Pricing Engine (`public.price_lists` and `public.product_prices`). This allows high-velocity price changes without locking catalog records.
*   **Decoupling from Finance**: Payout rules, ledger mappings, tax withholding calculations, and ledger balances are isolated in the Finance Context. The catalog interacts with Finance through transactional outbox messages.
*   **Decoupling from Search**: Search document parsing, tokenizing, synonyms, and pgvector embeddings are decoupled asynchronously via `search_documents` tables. Write paths to products never block on search indices or translation pipelines.
*   **Decoupling from CMS**: Product marketing media, manuals, videos, and dynamic graphics are stored in the CMS Digital Asset Management (DAM) subsystem. The catalog stores only unified content UUIDv7 pointers, rendering files via signed CDN urls.

### 1.3 Architectural Patterns
*   **API-First Architecture**: Internal admin portals, external developer suites, and customer interfaces access the catalog exclusively through strict REST and GraphQL gateways.
*   **CQRS Lite Separation**: Read queries are optimized via materialized views (`mv_live_routes`) and pre-computed indexes, while writes route through transactional state machines to ensure consistency.
*   **Transactional and Eventual Consistency**: Modifications to local catalog entities guarantee ACID compliance inside database transaction blocks. Downstream services—such as search index updates, category counts, and CDN flushes—are updated eventually using outbox pattern events.

---

## 2. PRODUCT TYPES

The catalog accommodates diverse business models by supporting native product types, each with specific ownership and validation patterns:

```
                            [PRODUCT TYPE TAXONOMY]
    ┌───────────────────────────────────┬───────────────────────────────────┐
    │ Physical Catalog (Physical, Kits) │ Digital Catalog (SaaS, Licences)  │
    ├───────────────────────────────────┼───────────────────────────────────┤
    │ Service Catalog (Bookings, Rent)  │ Hybrid Catalog (Bundled Packages) │
    └───────────────────────────────────┴───────────────────────────────────┘
```

*   **Physical Products**: Standard goods requiring weight calculations, dimensions, custom variants, warehouse inventory counts, and shipping. Owned by vendors, validated via SKU-uniqueness checks.
*   **Digital Products**: Intangible assets (e.g., software binaries, e-books) that do not require shipping. Ownership maps to vendors; fulfillment requires license key allocations (`public.license_keys`) and dynamic download links (`public.download_links`).
*   **Services**: Professional, custom, or hourly deliverables. Billed by time or project size; bypasses physical stock validations but requires coordination with calendaring engines.
*   **Memberships**: Tenant-defined access passes granting customer privileges over set durations. Managed through OIDC role mappings, skipping shipping and warehouse checks.
*   **Subscription Products**: Recurring-interval items (e.g., monthly boxes, SaaS subscriptions) governed by `public.subscription_pricing`. These require automatic recurring billing runs.
*   **Bundles**: Collections of standalone catalog products sold together under a single master SKU. The catalog manages bundle structures in relationship tables (`public.product_relationships`), while fulfillment systems split bundles into child SKU orders.
*   **Kits**: Pre-assembled product packages sold under a single SKU. Unlike bundles, kits are counted as single physical items during inventory sweeps.
*   **Configurable / Variable Products**: Parent products containing dynamic, multi-variant combinations (e.g., shirts with multiple color and size variants). Handled using a master-variant relation (`public.product_variants`).
*   **Downloadable Products**: High-security digital delivery files requiring signed URL access tokens, file hash integrity checks, and rate-limited downloads.
*   **Gift Cards**: Electronic or physical monetary credits managed in `public.gift_cards`. Values are encrypted with AES-256 and verified through secure gateway connections.
*   **Rental Products**: High-value items rented over defined periods. Require integration with booking engines and track rental-state histories.
*   **Booking Products**: Service slots or reservations tied to specific dates, times, and zones. Integrates with calendar scheduling engines to prevent overbooking.
*   **Hybrid Products**: Mixed offerings (e.g., buying a physical smart device bundled with a 1-year SaaS subscription and professional on-site setup). Requires multi-system processing across logistics, subscriptions, and services.

---

## 3. THE PRODUCT AGGREGATE BOUNDARY

The product aggregate is a logical consistency boundary containing several database tables. It ensures that updates to a product's variants, options, or attributes are validated as an atomic unit.

```
                      [PRODUCT AGGREGATE BOUNDARY]

    ┌────────────────────────────────────────────────────────┐
    │  public.products (Aggregate Root)                      │
    │  - sku_master, title, status, default_category_id       │
    │                                                        │
    │   ┌────────────────────────────────────────────────┐   │
    │   │  public.product_variants                       │   │
    │   │  - sku_variant, price_base, dimensions         │   │
    │   └───────┬────────────────────────────────────────┘   │
    │           │ (1:N)                                      │
    │           ▼                                            │
    │   ┌────────────────────────────────────────────────┐   │
    │   │  public.product_option_values                  │   │
    │   │  - Size, Color, Custom Swatches                │   │
    │   └────────────────────────────────────────────────┘   │
    │                                                        │
    │  - Attributes, Attributes Values, Specs, Relationships │
    └────────────────────────────────────────────────────────┘
```

### 3.1 Core Components inside the Aggregate Boundary
1.  **Product (Aggregate Root)**: Tracks core fields including `sku_master`, product type, and status (defined in `public.products`). All external systems must reference this root ID.
2.  **Product Variant**: Defines unique transactional SKUs within parent configurations (`public.product_variants`). Variants own unique UPCs, pricing overrides, and dimensional metrics.
3.  **Variant Options & Option Values**: Manages configurable matrix axes (such as "Size: Small, Large", "Color: Crimson, Teal"). Configured across option tables.
4.  **Attributes & Attribute Values**: Standardized metadata schemas (e.g., "Screen Size: 15.6 inches") applied dynamically.
5.  **Specifications**: Technical properties structured for high-density side-by-side product comparisons (`public.product_specifications`).
6.  **Brands & Manufacturers**: Authoritative registries tracing items back to verified production lines.
7.  **Categories & Tags**: Hierarchical and flat taxonomic arrays used for site navigation and search routing.

### 3.2 Product Relationship Mappings
To power commerce recommendation modules, relationship maps connect items across several categories:
*   **Cross-sells**: Complementary accessory recommendations (e.g., offering a camera lens on a camera detail page).
*   **Up-sells**: Premium alternatives within the same product category (e.g., recommending a newer model laptop).
*   **Bundles**: Promotional packages grouping multiple items at discounted rates.
*   **Accessories**: Spare parts or compatible components.
*   **Replacement Products**: Direct replacements for discontinued items.
*   **Successor Products**: Newer, updated releases within product lines.
*   **Compatibility Mappings**: Structural parameters linking items (e.g., verifying car parts fit specific vehicle years and models).

---

## 4. CATALOG HIERARCHY AND MULTI-STORE DESIGN

The system supports complex multi-tenant store structures, regional catalogs, and dynamic product groupings through an advanced category tree structure.

```
                        [MULTI-TENANT CATALOG TREE]

                      ┌──────────────────────────────┐
                      │    public.price_lists        │
                      └──────────────┬───────────────┘
                                     │ (Scoping)
                                     ▼
        Root Catalog ──► Departments ──► Categories ──► Subcategories
                                                          │ (Inheritance)
                                                          ▼
                                                    Collections
```

### 4.1 Hierarchical Path Traversals
The database stores categories in `public.product_categories`, using a flat closure table (`public.category_tree`) to map multi-level parent-child relationships. This design supports fast sub-tree queries:
*   **Path Slug Indexes**: Generates complete URL paths (e.g., `/electronics/computers/laptops`) as unique, pre-indexed slugs.
*   **Category Inheritance**: Subcategories inherit metadata rules and visibility constraints from their parent categories.
*   **Visibility Inheritance**: Marking a category hidden recursively hides its children categories, preventing broken routes on the storefront.
*   **Navigation Nodes**: System components translate active categories into dynamic storefront navigation menus.

### 4.2 Dynamic Collections & Custom Filters
*   **Dynamic Collections**: Automatically aggregates products into collections using set rules (e.g., grouping items with "Stock Status = In Stock" and "Tag = Summer Sale").
*   **Seasonal Collections**: Automates catalog groupings based on seasonal duration rules (e.g., publishing a Holiday collection between Nov 20 and Dec 26).
*   **Featured Collections**: Curated landing collections configured via admin dashboards.

### 4.3 Multi-Store Scoping
*   **Root Catalogs**: Unique catalogs bound to specific platform organizations.
*   **Multi-Store catalogs**: Single tenants can publish distinct storefront catalogs tailored to different regions or business segments (B2B, B2C).
*   **Regional Catalog Routing**: Automatically routes visitors to localized catalogs based on IP addresses and system language parameters.

---

## 5. PRODUCT LIFECYCLE FINITE STATE MACHINE (FSM)

All product writes, updates, and status transitions route through a strict, deterministic state machine.

```
                     [PRODUCT LIFECYCLE STATE MACHINE]

                         ┌───────────────────────┐
                         │         Draft         │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
         ┌──────────────►│    Pending Review     ├────────────────┐
         │               └───────────┬───────────┘                │
         │                           │                            │
   (Rejection)                       ▼                            │ (Direct Pass)
         │               ┌───────────────────────┐                │
         └───────────────┤    Pending Approval   │                │
                         └───────────┬───────────┘                │
                                     │                            ▼
                                     ▼                      ┌───────────┐
                                 Approved ─────────────────►│ Scheduled │
                                     │                      └─────┬─────┘
                                     │                            │
                                     ▼                            ▼
                                 Published ◄──────────────────────┘
                                     │
                     ┌───────────────┴───────────────┐
                     ▼                               ▼
                   Hidden                        Deprecated
                     │                               │
                     ▼                               ▼
                  Archived ──────────────────────► Purged
```

### 5.1 FSM States
1.  **Draft**: Work-in-progress records. Skipping input validations is permitted during editing.
2.  **Incomplete**: Automated scanners mark imported products that miss required fields (such as descriptions or prices) as incomplete.
3.  **Pending Review**: Drafts submitted for review. Triggers automated checks for spelling errors, image layouts, and compliance rules.
4.  **Pending Approval**: Requires editorial sign-off (Maker-Checker policy) before going live.
5.  **Approved**: Passes all verification checks; stands ready for active publishing.
6.  **Scheduled**: Staged products set to publish automatically at a future date (`starts_at` constraint).
7.  **Published**: Live, discoverable products actively displayed on search indices and product pages.
8.  **Hidden**: Products kept active for direct URL access but excluded from search indices and category pages.
9.  **Deprecated**: Discontinued lines. Kept active for past order processing reference but blocked from new checkouts.
10. **Archived**: Permanently hidden from catalogs and storefronts. Re-publishing archived records is blocked.
11. **Deleted**: Soft-deleted entries mapped to `deleted_at` timestamps for historical record retention.

### 5.2 Transition Matrix and System Rules

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Pending Review** | API Submission | Verifies required fields; rejects transitions if attributes or default categories are missing. |
| **Pending Review** | **Pending Approval** | AI Scan Pass | Verifies spelling, runs image compliance checks, and checks for pricing anomalies. |
| **Pending Approval**| **Approved** | Editor Sign-off | Requires authorization from an independent review user (Maker-Checker control). |
| **Approved** | **Scheduled** | Scheduler Engine | Verifies that the future launch timestamp is greater than the current system time. |
| **Approved** | **Published** | Immediate Trigger | Instantly registers items across active search indices and clears edge CDN routes. |
| **Scheduled** | **Published** | Cron Sweep Job | Publishes items automatically once current system times surpass scheduled start dates. |
| **Published** | **Hidden** | Editor Toggle | Excludes products from search indices while preserving active public URL access. |
| **Published** | **Deprecated** | Lifecycle Trigger | Disables cart additions but preserves active product views for past order history references. |
| **Published** | **Archived** | Admin Command | Restricts public views, flags entries as archived, and marks search index records for deletion. |
| **Archived** | **Deleted** | Compliance Run | Sets `deleted_at` soft-delete timestamps, blocking catalog retrievals. |

*   **Rollback Rule**: If publishing pipelines fail, the state machine rolls the product back to its previous state (e.g., returning to `Approved` if a CDN cache purge fails).

---

## 6. PRODUCT VERSIONING AND AUDIT LEDGER

To meet compliance guidelines (such as SOC 2 and ISO 27001), modifications to product records generate immutable history snapshots.

```
                         [PRODUCT VERSION ARCHITECTURE]

    public.products (Active Version: 14)
         │
         ├─── Writes Update ──► Create Version Snapshot 14 ──► Write public.content_versions
         │                                                            (Immutable History)
         ▼
    Update Active Row ──► Increments Version column to 15
```

### 6.1 Snapshots & Revision Histories
*   **Immutable Revision Records**: Edits write full system snapshots to history tables, storing data in structured `JSONB` formats.
*   **Major vs. Minor Versions**: Schema-level updates (such as variant matrix additions) trigger major version shifts, while metadata adjustments generate minor version snapshots.
*   **Optimistic Locking**: Every write query evaluates version keys (`WHERE version = :expected_version`), blocking concurrent overwrites.
*   **Audit Trail Integration**: Version records register user IDs, change sources, IP locations, and previous states.
*   **Historical Rolls**: Administrators can roll back catalog records to previous snapshots, validating changes against active catalog constraint rules before executing.

---

## 7. PRODUCT ATTRIBUTE SPECIFICATION

The catalog handles diverse product specifications across different industries using a hybrid schema that combines standard table structures with flexible JSON validation.

```
                      [DYNAMIC ATTRIBUTE METADATA ENGINE]

   Attribute Meta Schema ──► JSON Schema Validation ──► Write JSONB Column (`meta_properties`)
   (E.g., "Screen Size")       (Application Layer)           (In `public.products` database)
```

### 7.1 Attribute Scopes
*   **Global Attributes**: Standard metadata fields required across all catalog products (e.g., brand, origin country, tariff codes).
*   **Local Attributes**: Tenant-specific attributes configured by individual merchants.
*   **Store Attributes**: Layout rules tied to specific storefront configurations.
*   **Variant Attributes**: Option axes that define variations (e.g., sizes, dimensions).
*   **Localized Attributes**: Field matrices storing translated strings for localized catalogs.
*   **Computed Attributes**: Automatically calculated attributes (e.g., volume-weight metrics computed from package dimensions).
*   **Dynamic Attributes**: Custom attributes added to categories dynamically.

### 7.2 Validation Rules & JSON Schema Verification
*   **JSON Schema Verification**: The application validates custom properties against category schemas before committing writes to JSONB tables.
*   **Validation Rules**: Supports numeric limits (e.g., checking that length is greater than 0), regular expression checks on text inputs, and defined selection lists.
*   **Inheritance Rules**: Subcategories inherit custom attributes defined on their parent categories.

---

## 8. CMS DIGITAL ASSET MANAGEMENT (DAM) INTEGRATION

The catalog integrates with the CMS DAM subsystem, managing media assets through reference links to avoid duplicate files.

```
                           [MEDIA INTEGRATION FLOW]

     public.products ──► Reference CMS asset UUID ──► CMS DAM (`media_assets` Table)
                                                            │
                 ┌──────────────────────────────────────────┘
                 ▼
     Verify Media State ──► Sign Asset Endpoint ──► CDN edge delivery node
```

*   **Asset Reference Links**: Catalog tables store only UUIDv7 keys pointing to files managed in `public.media_assets`.
*   **Image Processing**: The CMS automatically generates responsive images, WebP options, and thumbnail sizes.
*   **Video Delivery**: Supports product video links, 360-degree interactive displays, and spatial files.
*   **Product Documents**: Tracks technical user guides and safety manuals, storing files in secure S3 buckets.
*   **Signed Download Links**: Media deliveries leverage signed Cloud S3 tokens to restrict direct public downloads.

---

## 9. CATALOG PUBLISHING PIPELINES

Publishing catalog updates to global storefronts is automated via robust pipelines to prevent storefront disruptions:

```
                            [CATALOG PUBLISHING PIPELINE]

    Step 1: Check Constraints ──► Step 2: Write Outbox Events ──► Step 3: Run Cache Purges
    (Prices, Tax, Options)         (`product.published.v1`)        (Redis, Edge CDN Nodes)
```

1.  **Constraint Checks**: Validates that all items are approved, prices are active, and product options map to existing variant values.
2.  **Outbox Record**: Commits a `product.published.v1` event record inside the database transaction.
3.  **Outbox Sweeper**: Reads the outbox table, dispatches the event, and triggers CDN cache purges.
4.  **Blue-Green Catalogs**: Stages large catalog updates on draft partitions before updating route links to go live.
5.  **Publishing Rollback**: If a cache purge fails, the pipeline logs the failure, raises system alarms, and rolls back the catalog route to its previous version.

---

## 10. SEARCH INDEX INTEGRATION

Catalog updates automatically update search indices, ensuring storefront search listings are updated in near real-time.

```
                          [SEARCH SYNCHRONIZATION EVENT LOOP]

   Product Status Published ──► Outbox Event ──► Consumer Sweeper ──► Build Search Payload
                                                                            │
             ┌──────────────────────────────────────────────────────────────┘
             ▼
   Generate pgvector embedding ──► Write search_documents ──► Flush dynamic search cache
```

*   **Asynchronous Updates**: Outbox events alert search workers of catalog changes, updating index tables without blocking database writes.
*   **Hybrid Search Indices**: The system maps catalog text to search tables (`search_documents`), generating pgvector embeddings to power semantic searches.
*   **Search Boosting Parameters**: The search engine scales product search rankings using parameters like customer reviews, stock availability, and sales metrics.

---

## 11. AI CO-PILOT INTEGRATION (ADVISORY OVERVIEW)

The Catalog subsystem includes hooks for AI-assisted workflows to optimize listings and improve search visibility:

```
                         [AI CATALOG CO-PILOT INTEGRATION]

   Admin Save Draft ──► Trigger AI Co-Pilot ──► Generate Suggestions ──► Human review approved
                                                                                │
             ┌──────────────────────────────────────────────────────────────────┘
             ▼
   Apply updates to fields (SKU metadata, tags, and category taxonomies)
```

*   **Generative Metadata**: Generates search-optimized descriptions, titles, and localized copy.
*   **Specification Extraction**: Parses unstructured PDF manuals to populate structural technical tables.
*   **Attribute Suggestions**: Analyzes product photos to suggest appropriate category tags and attributes.
*   **Quality Scoring**: Automated scanners evaluate catalog listing quality (e.g., flagging low-resolution images or short descriptions).
*   **Human-in-the-Loop Review**: All AI-generated metadata is saved as drafts, requiring manual approval from editors before publishing.

---

## 12. ROLE-BASED ACCESS CONTROL (RBAC) & DATA SECURITY

The Catalog subsystem implements strict, multi-tenant security layers to protect sensitive product data:

```
                         [SECURITY CONTROL ACCESS GATE]

    Client Request ──► Verify RBAC Role ──► Verify Tenant Isolation (RLS) ──► Table Execute
```

*   **Tenant Isolation**: All queries run against Row-Level Security (RLS) policies, isolating databases using the tenant's `organization_id`.
*   **Maker-Checker Controls**: Prevents authors from self-approving product listings, requiring independent editor validation before publishing.
*   **RBAC Permissions**: Restricts catalog actions to specific roles:
    *   *Merchant Authors*: Can write and edit drafts within their assigned vendor directories.
    *   *Merchant Reviewers*: Can review and approve drafts for publication.
    *   *System Admins*: Can manage platform taxonomy trees, configure global attributes, and moderate reviews.
*   **Compliance Logs**: Audit trails track catalog changes, administrative updates, and deleted listings for security compliance.

---

## 13. BUSINESS EVENT CONTRACTS

The Catalog subsystem interacts with downstream systems using standardized event payloads written to outbox tables:

```
                         [EVENT CONTRACT OUTBOX PATTERN]

    Database State Commit ──► Write Outbox Event Payload ──► Sweep Queue ──► Message Broker
```

### 13.1 `product.created.v1`
*   **Publisher**: Product Catalog Service
*   **Primary Consumers**: Search platform, notification services, analytics dashboards.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033481",
      "event_type": "product.created.v1",
      "timestamp": "2026-06-30T13:00:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033482",
      "product_id": "018f63bb-9ab6-7000-8d59-fc5095033483",
      "sku_master": "PROD-NX-100",
      "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095033484",
      "product_type": "PHYSICAL",
      "created_by": "018f63bb-9ab6-7000-8d59-fc5095033485"
    }
    ```

### 13.2 `product.updated.v1`
*   **Publisher**: Product Catalog Service
*   **Primary Consumers**: Search platform, version archivers, cached route systems.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033486",
      "event_type": "product.updated.v1",
      "timestamp": "2026-06-30T13:05:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033482",
      "product_id": "018f63bb-9ab6-7000-8d59-fc5095033483",
      "sku_master": "PROD-NX-100",
      "updated_by": "018f63bb-9ab6-7000-8d59-fc5095033487",
      "changes": {
        "title": { "old": "Old Title", "new": "New Optimized Title" }
      }
    }
    ```

### 13.3 `product.published.v1`
*   **Publisher**: Publishing Pipeline Service
*   **Primary Consumers**: CDN invalidators, public search indexes, regional pricing databases.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033488",
      "event_type": "product.published.v1",
      "timestamp": "2026-06-30T13:10:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033482",
      "product_id": "018f63bb-9ab6-7000-8d59-fc5095033483",
      "sku_master": "PROD-NX-100",
      "published_by": "018f63bb-9ab6-7000-8d59-fc5095033487",
      "url_slug": "/electronics/laptops/prod-nx-100"
    }
    ```

### 13.4 Resiliency Policies
*   **Transactional Outbox Delivery**: Events are written directly to outbox tables (`public.marketplace_event_outbox`) within parent database transactions to guarantee atomic consistency.
*   **Retry Mechanisms**: Outbox sweepers retry failed event dispatches using exponential backoff schedules (retrying 5 times before shifting to dead-letter queues).
*   **Idempotency Checks**: Downstream systems track unique message keys (`event_id`) to block duplicate message processing.

---

## 14. INTEGRATION MATRIX

Catalog integrations use strictly decoupled APIs and event-driven architectures to prevent direct database locks:

| Target Platform Domain | System of Record | Primary Channel | Data Sync Strategy | Failure Recovery Policy |
| :--- | :--- | :--- | :--- | :--- |
| **Inventory** | Inventory Subsystem | gRPC & Event Bus | Event-driven quantities sync | Fallback to cached quantities; flag low stock alerts |
| **Pricing & Couponing**| Pricing Subsystem | REST API gateway | Real-time pricing calculations | Render base catalog prices; log pricing API exceptions |
| **Orders & Fulfillment**| Orders Bounded Context | Event Bus & Link Tables | Asynchronous outbox events | Log events to DB outbox queues; retry with delays |
| **Finance / Ledger** | Finance Context | Asynchronous Events | Transactional outbox events | Shift failed messages to DLQ; log verification alerts |
| **CRM / Accounts** | Identity / Profiles | JWT Token context | Real-time session verification | Block catalog edit permissions; log auth anomalies |
| **CMS / Assets** | CMS DAM Context | UUID Reference pointers | CDN edge deliveries | Render placeholder images; flag broken links |
| **Search platform** | Search / Embeddings | Event Bus | Asynchronous outbox sweeps | Queue re-indexing runs; preserve cached entries |

---

## 15. PERFORMANCE OPTIMIZATIONS

To scale catalog operations across high-traffic volumes, several performance optimizations are implemented at the database and application levels:

*   **Composite Database Indexes**: Implements multi-column B-Tree indexes on frequently queried fields (such as `organization_id, status, is_searchable`) to speed up product lookups.
*   **Read Model Caching (CQRS Lite)**: Direct storefront routes fetch cached JSON configurations from Redis nodes, bypassing database queries for active catalog browsing.
*   **Materialized Path Views**: Aggregates site routes and category hierarchies into materialized tables (`mv_live_routes`), refreshing records on demand using background tasks.
*   **Query Row Locking**: Time-sensitive updates utilize `FOR UPDATE SKIP LOCKED` database queries to fetch unlocked rows without causing write contention.
*   **Batch Operations**: Large catalog updates or CSV imports are executed inside transactional batches, splitting massive datasets into chunks of 1,000 rows.

---

## 16. ENGINEERING VALIDATION CHECKLIST

The Architecture Review Board evaluates Catalog deployments against this comprehensive checklist to ensure system integrity:

*   [ ] **FSM Correctness**: Verify that all state machine transitions validate fields and block unauthorized state changes.
*   [ ] **Version Snapshotting**: Verify that edits generate immutable version snapshots inside audit history tables.
*   [ ] **Multi-Tenant RLS**: Validate that Row-Level Security policies isolate product queries by tenant `organization_id` at the database layer.
*   [ ] **Maker-Checker Security**: Confirm that product publishing workflows require independent editor approval.
*   [ ] **Outbox Transactionality**: Verify that outbox event writes are committed within the parent database transaction blocks.
*   [ ] **Asset Reference Checks**: Validate that product media entries map to valid CMS assets and bypass direct binary uploads.
*   [ ] **Search Index Synchronization**: Confirm that publishing events trigger asynchronous search index and vector updates.
*   [ ] **API Gateway Authorization**: Ensure that public storefront queries restrict write actions to authorized merchant administrators.
*   [ ] **High-Performance Scaling**: Benchmark database query speeds to verify index performance under high simulated concurrent read loads.
*   [ ] **Disaster Rollback Scenarios**: Test database transaction rollbacks during simulated network and hardware disruptions.

---

*Authorized by the JUANET Technical Review Board & Global Commerce Council.*
