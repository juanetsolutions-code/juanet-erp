# JUANET Marketplace Physical Database Schema Specification
## Phase 2.3.2H — Physical Tables, Columns, Foreign Key Mappings, Indexing Strategies, RLS Policies, and Database Compliance Ledgers
**Document Version:** 1.0  
**Author:** Chief Database Architect, VP of Systems Engineering, and Global Technical Review Board  
**Classification:** Confidential / Enterprise Architecture Standard, Domain Database Ledger, and Security Blueprint  

---

## 1. MARKETPLACE ARCHITECTURE PHILOSOPHY

The **JUANET Marketplace Bounded Context** provides the system of record for multi-vendor, multi-tenant digital and physical commerce. Designed for high throughput and low-latency reads, this subsystem is completely decoupled from adjacent domains such as Finance, Support, and CMS, relying on Domain-Driven Design (DDD) principles, CQRS Lite separation, and Event-Driven Architecture.

```
                         [MARKETPLACE BOUNDED CONTEXT OVERVIEW]

     CRM / Accounts Context ──► [OIDC / JWT Context] ──► Row-Level Security (RLS) Gate
                                                                │
  ┌─────────────────────────────────────────────────────────────┼──────────────────────────────┐
  │ MARKETPLACE DOMAIN BOUNDARY                                 ▼                              │
  │                                                                                            │
  │  ┌──────────────────────┐      ┌──────────────────────┐          ┌──────────────────────┐  │
  │  │ Vendor Aggregate Root│ ◄──► │Product Aggregate Root│ ◄──────► │Inventory Subsystem   │  │
  │  └──────────┬───────────┘      └──────────┬───────────┘          └──────────────────────┘  │
  │             │                             │                                                │
  │             ▼                             ▼                                                │
  │    [Vendor Audit Log]            [FTS & Vector Indexes]                                    │
  │             │                             │                                                │
  │             └─────────────────────────────┼──────────────────┐                             │
  │                                           ▼                  ▼                             │
  │                                   [Transactional Outbox]  [Processed Events]               │
  └───────────────────────────────────────────┬──────────────────┬─────────────────────────────┘
                                              │                  │
                                              ▼ (Asynchronous)   ▼
                                        Event Bus (Redis)  S3 Media Buckets
```

### 1.1 Core Aggregates
*   **Vendor Aggregate Root**: Governs vendor onboarding, profile setup, bank routing configurations, payout schedules, tax residency data, and security controls.
*   **Product Aggregate Root**: Coordinates multi-dimensional catalog structures including dynamic attributes, multi-currency price tables, digital product downloads, and customer-specific discount models.

### 1.2 Subsystem Integration Boundaries
*   **Orders Integration**: The Marketplace tracks catalog, product listings, and vendor assignments, but delegates actual order processing to the Orders domain. It coordinates shipments and returns via immutable reference link tables.
*   **Finance Integration**: Real-time pricing models, coupon validations, and tax-inclusive calculations are performed inside the Marketplace, while transaction logs and ledger settlements are pushed asynchronously to the Finance Bounded Context via outbox events.
*   **CRM & Account Identity**: Customer data and account parameters are verified using JWT claims mapped from OIDC. Row-Level Security (RLS) filters database sessions using verified tenant keys (`organization_id`).
*   **CMS Integration**: Products reference marketing media files stored in the Digital Asset Management (DAM) subsystem using unified content IDs, ensuring catalog consistency without duplicating raw files.
*   **AI Integration**: Catalog optimization pipelines parse product descriptions asynchronously to extract tags, suggest pricing rules, and predict warehouse inventory limits.

---

## 2. GLOBAL DATABASE ENGINE STANDARDS

To maintain uniformity across the entire JUANET database cluster, all tables within the Marketplace domain must implement the following physical column standards and database configurations:

```
                      [GLOBAL TRANS-TABLE COLUMN SCHEMA]
  ┌──────────────────┬──────────────────┬──────────────────┬────────────────────────┐
  │ uuidv7_id (PK)   │ organization_id  │ version (INT)    │ created_at (TIMESTAMPTZ)│
  │ (Time-ordered)   │ (Tenant isolation)│ (Optimistic lock)│ (Record creation)       │
  └──────────────────┴──────────────────┴──────────────────┴────────────────────────┘
```

### 2.1 Standard Primary Columns
*   **`id` UUIDv7 PRIMARY KEY**: Time-ordered, millisecond-precision UUIDv7s are enforced as primary keys for all tables, enabling high B-Tree write speeds and collision-free migrations.
*   **`organization_id` UUIDv7 NOT NULL**: Establishes the multi-tenant isolation boundaries. All client-facing tables must map back to an active organization record.
*   **`version` INTEGER DEFAULT 1 NOT NULL**: Enforces optimistic locking, preventing concurrent edits from overwriting transactional updates.
*   **`created_at` TIMESTAMPTZ DEFAULT clock_timestamp() NOT NULL**: Non-nullable record creation timestamp.
*   **`updated_at` TIMESTAMPTZ DEFAULT clock_timestamp() NOT NULL**: Automatically updated using row modification triggers.
*   **`deleted_at` TIMESTAMPTZ**: Soft delete timestamp. To ensure compliance with legal and analytical retention rules, records are never hard-deleted; instead, soft deletes are filtered using partial indexes.
*   **`created_by` UUIDv7 / `updated_by` UUIDv7**: Operational audit fields tracing database changes back to verified system users.

### 2.2 Security Configuration Defaults
*   **Row-Level Security (RLS)**: Enforced on all tables. Queries default to filtering rows using the `organization_id` mapped from active session variables.
*   **Transaction Isolation**: Defaults to `READ COMMITTED`, while sensitive pricing calculations and inventory allocations utilize `REPEATABLE READ` or `SERIALIZABLE` transactions.

---

## 3. VENDOR MANAGEMENT SUBSYSTEM

This subsystem manages third-party merchant accounts, onboarding workflows, regulatory compliance, bank routing registers, and payout schedules.

```
                         [VENDOR MANAGEMENT RELATIONSHIPS]

               ┌────────────────────────────────────────────────┐
               │                  public.vendors                │
               └────────┬──────────────────────────────┬────────┘
                        │ (1:1)                        │ (1:N)
                        ▼                              ▼
          ┌───────────────────────────┐  ┌───────────────────────────┐
          │  public.vendor_profiles   │  │   public.vendor_contacts  │
          └───────────────────────────┘  └───────────────────────────┘
                        │ (1:1)                        │ (1:N)
                        ▼                              ▼
          ┌───────────────────────────┐  ┌───────────────────────────┐
          │  public.vendor_addresses  │  │   public.vendor_documents │
          └───────────────────────────┘  └───────────────────────────┘
```

### 3.1 `public.vendors`
Primary registry for merchant entities within the marketplace context.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `legal_name` `VARCHAR(255)` NOT NULL.
*   `trade_name` `VARCHAR(255)`.
*   `registration_number` `VARCHAR(100)` UNIQUE.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING_VERIFICATION' CHECK (status IN ('PENDING_VERIFICATION', 'ACTIVE', 'SUSPENDED', 'TERMINATED', 'RESTRICTED')).
*   `risk_score` `NUMERIC(5,2)` DEFAULT 0.00.
*   `onboarding_completed_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `deleted_at` `TIMESTAMPTZ`.

### 3.2 `public.vendor_profiles`
Stores merchant brand details, visual elements, and portal setups.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `description` `TEXT`.
*   `support_email` `VARCHAR(255)` NOT NULL.
*   `support_phone` `VARCHAR(64)`.
*   `logo_asset_id` `UUIDv7` COMMENT 'Reference to DAM CMS Asset'.
*   `banner_asset_id` `UUIDv7`.
*   `social_links` `JSONB` DEFAULT '{}'::jsonb.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.3 `public.vendor_contacts`
Direct registry for merchant personnel and account representatives.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `first_name` `VARCHAR(128)` NOT NULL.
*   `last_name` `VARCHAR(128)` NOT NULL.
*   `email` `VARCHAR(255)` NOT NULL.
*   `phone` `VARCHAR(64)`.
*   `role` `VARCHAR(64)` NOT NULL DEFAULT 'PRIMARY' CHECK (role IN ('PRIMARY', 'BILLING', 'LOGISTICS', 'COMPLIANCE', 'TECHNICAL')).
*   `is_encrypted` `BOOLEAN` DEFAULT TRUE.
*   `encrypted_fields` `BYTEA` COMMENT 'Contains encrypted PII payloads'.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.4 `public.vendor_addresses`
Physical and legal address structures for tax calculations and shipping centers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `address_type` `VARCHAR(32)` NOT NULL DEFAULT 'BUSINESS' CHECK (address_type IN ('LEGAL', 'BUSINESS', 'WAREHOUSE', 'RETURN_CENTER')).
*   `country_code` `CHAR(2)` NOT NULL.
*   `region` `VARCHAR(128)`.
*   `locality` `VARCHAR(128)` NOT NULL.
*   `postal_code` `VARCHAR(32)` NOT NULL.
*   `street_address` `VARCHAR(255)` NOT NULL.
*   `extended_address` `VARCHAR(255)`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.5 `public.vendor_bank_accounts`
Highly secure registry for processing payout transfers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `bank_name` `VARCHAR(255)` NOT NULL.
*   `routing_number_encrypted` `BYTEA` NOT NULL.
*   `account_number_encrypted` `BYTEA` NOT NULL.
*   `iban_encrypted` `BYTEA`.
*   `bic_swift_encrypted` `BYTEA`.
*   `currency` `CHAR(3)` NOT NULL DEFAULT 'USD'.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.6 `public.vendor_tax_information`
Coordinates corporate tax identities, W-8/W-9 registration statuses, and withholding rates.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `tax_identifier_type` `VARCHAR(32)` NOT NULL DEFAULT 'EIN' CHECK (tax_identifier_type IN ('EIN', 'SSN', 'VAT', 'GST', 'OTHER')).
*   `tax_identifier_encrypted` `BYTEA` NOT NULL.
*   `w8_w9_status` `VARCHAR(32)` NOT NULL DEFAULT 'NOT_SUBMITTED' CHECK (w8_w9_status IN ('NOT_SUBMITTED', 'SUBMITTED', 'VERIFIED', 'EXPIRED')).
*   `withholding_rate` `NUMERIC(5,4)` NOT NULL DEFAULT 0.0000.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.7 `public.vendor_documents`
Registry mapping compliance files and verification certificates to object storage.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `document_type` `VARCHAR(64)` NOT NULL CHECK (document_type IN ('REGISTRATION_PROOF', 'TAX_CERTIFICATE', 'BANK_STATEMENT', 'IDENTITY_PROOF', 'BUSINESS_LICENSE')).
*   `storage_file_path` `VARCHAR(512)` NOT NULL.
*   `file_hash_sha256` `CHAR(64)` NOT NULL.
*   `verification_status` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING' CHECK (verification_status IN ('PENDING', 'APPROVED', 'REJECTED', 'EXPIRED')).
*   `reviewer_id` `UUIDv7`.
*   `rejection_reason` `TEXT`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.8 `public.vendor_settings`
Merchant-specific operational preferences and integration toggles.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `auto_payout_enabled` `BOOLEAN` NOT NULL DEFAULT FALSE.
*   `payout_frequency` `VARCHAR(16)` NOT NULL DEFAULT 'MONTHLY' CHECK (payout_frequency IN ('DAILY', 'WEEKLY', 'BIWEEKLY', 'MONTHLY')).
*   `payout_minimum_threshold` `NUMERIC(15,2)` NOT NULL DEFAULT 100.00.
*   `commission_override_rate` `NUMERIC(5,4)`.
*   `meta_settings` `JSONB` DEFAULT '{}'::jsonb.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.9 `public.vendor_status_history`
Append-only log tracking state changes for regulatory compliance.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `old_status` `VARCHAR(32)`.
*   `new_status` `VARCHAR(32)` NOT NULL.
*   `changed_by` `UUIDv7` NOT NULL.
*   `change_reason` `TEXT` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.10 `public.vendor_reviews`
Public customer reviews assessing merchant performance.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `reviewer_id` `UUIDv7` NOT NULL.
*   `rating` `SMALLINT` NOT NULL CHECK (rating BETWEEN 1 AND 5).
*   `comment` `TEXT`.
*   `moderation_status` `VARCHAR(32)` NOT NULL DEFAULT 'APPROVED' CHECK (moderation_status IN ('PENDING', 'APPROVED', 'REJECTED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.11 `public.vendor_payout_accounts`
Payout records routing balances to connected services (Stripe Connect).
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `gateway_provider` `VARCHAR(64)` NOT NULL DEFAULT 'STRIPE_CONNECT' CHECK (gateway_provider IN ('STRIPE_CONNECT', 'PAYPAL_MARKETPLACE', 'DIRECT_DEPOSIT')).
*   `connected_account_id` `VARCHAR(255)` NOT NULL.
*   `payout_status` `VARCHAR(32)` NOT NULL DEFAULT 'ACTIVE' CHECK (payout_status IN ('ACTIVE', 'RESTRICTED', 'PAUSED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.12 `public.vendor_verification_requests`
Onboarding validation logs tracking regulatory compliance (KYC/KYB checks).
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `provider_request_id` `VARCHAR(255)`.
*   `verification_type` `VARCHAR(32)` NOT NULL CHECK (verification_type IN ('KYC', 'KYB', 'TAX_ID_VALIDATION', 'SANCTION_SCREENING')).
*   `provider_response_raw` `JSONB`.
*   `outcome` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING' CHECK (outcome IN ('PENDING', 'PASSED', 'FAILED', 'MANUAL_REVIEW')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 3.13 `public.vendor_contracts`
Tracks legal agreements, terms of service, and commission tiers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `contract_terms_raw` `TEXT` NOT NULL.
*   `signature_hash_sha256` `CHAR(64)` NOT NULL.
*   `signed_at` `TIMESTAMPTZ` NOT NULL.
*   `effective_date` `DATE` NOT NULL.
*   `termination_date` `DATE`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 4. PRODUCT CATALOG SUBSYSTEM

This subsystem coordinates multi-merchant inventories, custom options, category trees, and product specification templates.

```
                           [PRODUCT CATALOG SCHEMA]

                     ┌──────────────────────────────────┐
                     │         public.products          │
                     └──────────────┬───────────────────┘
                                    │ (1:N)
                                    ▼
                     ┌──────────────────────────────────┐
                     │     public.product_variants      │
                     └──────────────┬───────────────────┘
                                    │ (1:N)
                                    ▼
                     ┌──────────────────────────────────┐
                     │   public.product_option_values   │
                     └──────────────────────────────────┘
```

### 4.1 `public.products`
Primary catalog registry defining product aggregates.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `sku_master` `VARCHAR(128)` NOT NULL UNIQUE.
*   `title` `VARCHAR(255)` NOT NULL.
*   `description` `TEXT`.
*   `product_type` `VARCHAR(32)` NOT NULL DEFAULT 'PHYSICAL' CHECK (product_type IN ('PHYSICAL', 'DIGITAL', 'SUBSCRIPTION', 'SERVICE')).
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT', 'PENDING_APPROVAL', 'ACTIVE', 'ARCHIVED', 'OUT_OF_STOCK')).
*   `default_category_id` `UUIDv7` NOT NULL.
*   `brand` `VARCHAR(128)`.
*   `is_searchable` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `meta_properties` `JSONB` DEFAULT '{}'::jsonb.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `deleted_at` `TIMESTAMPTZ`.

### 4.2 `public.product_variants`
Tracks SKU-level items and physical characteristics.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `sku_variant` `VARCHAR(128)` NOT NULL UNIQUE.
*   `upc_ean` `VARCHAR(64)` UNIQUE.
*   `price_base` `NUMERIC(15,4)` NOT NULL.
*   `compare_at_price` `NUMERIC(15,4)`.
*   `cost_per_item` `NUMERIC(15,4)`.
*   `weight_grams` `NUMERIC(12,2)`.
*   `length_mm` `NUMERIC(10,2)`.
*   `width_mm` `NUMERIC(10,2)`.
*   `height_mm` `NUMERIC(10,2)`.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.3 `public.product_options`
Defines variant parameters (e.g., "Size", "Color").
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(128)` NOT NULL.
*   `sort_order` `SMALLINT` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.4 `public.product_option_values`
Defines variant values (e.g., "Medium", "Red").
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_option_id` `UUIDv7` NOT NULL REFERENCES public.product_options(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `value` `VARCHAR(128)` NOT NULL.
*   `sort_order` `SMALLINT` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.5 `public.product_categories`
Catalog taxonomies.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(128)` NOT NULL.
*   `slug_path` `VARCHAR(255)` NOT NULL.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.6 `public.category_tree`
Adjacency path mappings to optimize hierarchical query traversals.
*   `ancestor_category_id` `UUIDv7` NOT NULL REFERENCES public.product_categories(id) ON DELETE CASCADE.
*   `descendant_category_id` `UUIDv7` NOT NULL REFERENCES public.product_categories(id) ON DELETE CASCADE.
*   `path_depth` `SMALLINT` NOT NULL.
*   PRIMARY KEY (ancestor_category_id, descendant_category_id).

### 4.7 `public.product_tags`
Search indexing tags.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(64)` NOT NULL UNIQUE.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.8 `public.product_tag_assignments`
Maps products to indexing tags.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `tag_id` `UUIDv7` NOT NULL REFERENCES public.product_tags(id) ON DELETE CASCADE.
*   PRIMARY KEY (product_id, tag_id).

### 4.9 `public.product_attributes`
Custom metadata structures (e.g., "Screen Size").
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(128)` NOT NULL.
*   `data_type` `VARCHAR(32)` NOT NULL CHECK (data_type IN ('TEXT', 'NUMBER', 'BOOLEAN', 'DATE', 'JSON')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.10 `public.product_attribute_values`
Stores values associated with custom attributes.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `attribute_id` `UUIDv7` NOT NULL REFERENCES public.product_attributes(id) ON DELETE RESTRICT.
*   `value_text` `TEXT`.
*   `value_numeric` `NUMERIC(15,4)`.
*   `value_boolean` `BOOLEAN`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.11 `public.product_specifications`
Technical documentation and specifications matrices.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `spec_group` `VARCHAR(128)` NOT NULL DEFAULT 'GENERAL'.
*   `key` `VARCHAR(128)` NOT NULL.
*   `value` `TEXT` NOT NULL.
*   `sort_order` `SMALLINT` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.12 `public.product_relationships`
Cross-sell, upsell, and bundle mappings.
*   `id` `UUIDv7` PRIMARY KEY.
*   `source_product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `target_product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `relation_type` `VARCHAR(32)` NOT NULL DEFAULT 'CROSS_SELL' CHECK (relation_type IN ('CROSS_SELL', 'UP_SELL', 'BUNDLE', 'VARIANT_COMPATIBILITY')).
*   `sort_order` `SMALLINT` DEFAULT 0.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 4.13 `public.product_visibility_rules`
Tenant access rules and visibility constraints.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `access_level` `VARCHAR(32)` NOT NULL DEFAULT 'PUBLIC' CHECK (access_level IN ('PUBLIC', 'AUTHENTICATED_ONLY', 'VIP_ONLY', 'WHITELIST_ONLY')).
*   `whitelisted_customer_groups` `JSONB` DEFAULT '[]'::jsonb.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 5. PRICING & PROMOTIONS SUBSYSTEM

This subsystem coordinates promotional models, volume discounts, regional taxes, and validation rules.

```
                           [PRICING SYSTEM LOGIC]

                      ┌──────────────────────────────────┐
                      │        public.price_lists        │
                      └────────────────┬─────────────────┘
                                       │ (1:N)
                                       ▼
                      ┌──────────────────────────────────┐
                      │      public.product_prices       │
                      └──────────────────────────────────┘
```

### 5.1 `public.price_lists`
Global and customer-segment price registers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(128)` NOT NULL.
*   `currency` `CHAR(3)` NOT NULL DEFAULT 'USD'.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `starts_at` `TIMESTAMPTZ`.
*   `ends_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.2 `public.product_prices`
SKU-level price listings.
*   `id` `UUIDv7` PRIMARY KEY.
*   `price_list_id` `UUIDv7` NOT NULL REFERENCES public.price_lists(id) ON DELETE CASCADE.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `price_value` `NUMERIC(15,4)` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.3 `public.customer_price_rules`
Dynamic customer segment and B2B contract rates.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `customer_group_id` `UUIDv7` NOT NULL.
*   `price_list_id` `UUIDv7` NOT NULL REFERENCES public.price_lists(id) ON DELETE RESTRICT.
*   `priority` `SMALLINT` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.4 `public.volume_pricing`
Tiered quantity discounts.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `min_quantity` `INTEGER` NOT NULL CHECK (min_quantity > 0).
*   `price_per_unit` `NUMERIC(15,4)` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.5 `public.subscription_pricing`
Recurring billing setups and delivery intervals.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `billing_interval` `VARCHAR(16)` NOT NULL DEFAULT 'MONTHLY' CHECK (billing_interval IN ('WEEKLY', 'MONTHLY', 'ANNUALLY')).
*   `recurring_price` `NUMERIC(15,4)` NOT NULL.
*   `trial_period_days` `SMALLINT` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.6 `public.discount_rules`
Configures system discount logic.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `discount_type` `VARCHAR(32)` NOT NULL CHECK (discount_type IN ('PERCENTAGE', 'FIXED_AMOUNT', 'FREE_SHIPPING', 'BUY_X_GET_Y')).
*   `value` `NUMERIC(15,4)` NOT NULL.
*   `min_order_value` `NUMERIC(15,4)` DEFAULT 0.0000.
*   `starts_at` `TIMESTAMPTZ` NOT NULL.
*   `ends_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.7 `public.promotions`
Tracks marketing campaigns.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(255)` NOT NULL.
*   `description` `TEXT`.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `starts_at` `TIMESTAMPTZ` NOT NULL.
*   `ends_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.8 `public.promotion_products`
Applies promotions to specific catalogs.
*   `promotion_id` `UUIDv7` NOT NULL REFERENCES public.promotions(id) ON DELETE CASCADE.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   PRIMARY KEY (promotion_id, product_id).

### 5.9 `public.coupon_codes`
Customer-redeemable promo codes.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `promotion_id` `UUIDv7` NOT NULL REFERENCES public.promotions(id) ON DELETE CASCADE.
*   `code` `VARCHAR(64)` NOT NULL UNIQUE.
*   `usage_limit` `INTEGER`.
*   `usage_count` `INTEGER` NOT NULL DEFAULT 0.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 5.10 `public.gift_cards`
Redeemable gift cards and balance ledgers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `card_code_encrypted` `BYTEA` NOT NULL UNIQUE.
*   `balance_initial` `NUMERIC(15,4)` NOT NULL.
*   `balance_remaining` `NUMERIC(15,4)` NOT NULL.
*   `currency` `CHAR(3)` NOT NULL DEFAULT 'USD'.
*   `expires_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 6. INVENTORY SUBSYSTEM

This subsystem coordinates multi-warehouse inventories, stock allocations, and audit registers.

```
                           [INVENTORY TO WAREHOUSE RELATION]

               ┌────────────────────────────────────────────────┐
               │              public.warehouses                 │
               └────────┬──────────────────────────────┬────────┘
                        │ (1:N)                        │ (1:N)
                        ▼                              ▼
          ┌───────────────────────────┐  ┌───────────────────────────┐
          │ public.inventory_locations│  │   public.inventory_items  │
          └───────────────────────────┘  └───────────────────────────┘
```

### 6.1 `public.warehouses`
Fulfillment center registers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `name` `VARCHAR(128)` NOT NULL.
*   `is_active` `BOOLEAN` NOT NULL DEFAULT TRUE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.2 `public.inventory_locations`
Fulfillment center layouts.
*   `id` `UUIDv7` PRIMARY KEY.
*   `warehouse_id` `UUIDv7` NOT NULL REFERENCES public.warehouses(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `zone` `VARCHAR(64)` NOT NULL.
*   `aisle` `VARCHAR(64)` NOT NULL.
*   `shelf` `VARCHAR(64)` NOT NULL.
*   `bin` `VARCHAR(64)` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.3 `public.inventory_items`
SKU inventory balances.
*   `id` `UUIDv7` PRIMARY KEY.
*   `warehouse_id` `UUIDv7` NOT NULL REFERENCES public.warehouses(id) ON DELETE RESTRICT.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `quantity_on_hand` `INTEGER` NOT NULL DEFAULT 0.
*   `quantity_allocated` `INTEGER` NOT NULL DEFAULT 0.
*   `quantity_available` `INTEGER` GENERATED ALWAYS AS (quantity_on_hand - quantity_allocated) STORED.
*   `reorder_point` `INTEGER` DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.4 `public.inventory_transactions`
Fulfillment transaction audit ledgers.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `quantity_change` `INTEGER` NOT NULL.
*   `transaction_type` `VARCHAR(32)` NOT NULL CHECK (transaction_type IN ('PURCHASE_RECEIPT', 'CUSTOMER_SHIPMENT', 'RETURN', 'TRANSFER', 'ADJUSTMENT')),
*   `reference_document_id` `UUIDv7` COMMENT 'FKEY referencing other context objects',
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.5 `public.inventory_adjustments`
Inventory adjustments tracking.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `adjusted_by` `UUIDv7` NOT NULL.
*   `quantity_delta` `INTEGER` NOT NULL.
*   `reason_code` `VARCHAR(64)` NOT NULL CHECK (reason_code IN ('DAMAGED', 'THEFT', 'MISCOUNT', 'PROMOTIONAL_SAMPLE')).
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.6 `public.stock_reservations`
Temporary stock reservations (cart holds).
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `quantity_reserved` `INTEGER` NOT NULL CHECK (quantity_reserved > 0).
*   `expires_at` `TIMESTAMPTZ` NOT NULL.
*   `is_committed` `BOOLEAN` NOT NULL DEFAULT FALSE.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.7 `public.inventory_counts`
Physical audit counts.
*   `id` `UUIDv7` PRIMARY KEY.
*   `warehouse_id` `UUIDv7` NOT NULL REFERENCES public.warehouses(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `scheduled_date` `DATE` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'SCHEDULED' CHECK (status IN ('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.8 `public.inventory_movements`
Internal warehouse stock movements.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `source_location_id` `UUIDv7` NOT NULL REFERENCES public.inventory_locations(id) ON DELETE RESTRICT.
*   `destination_location_id` `UUIDv7` NOT NULL REFERENCES public.inventory_locations(id) ON DELETE RESTRICT.
*   `quantity` `INTEGER` NOT NULL CHECK (quantity > 0).
*   `moved_by` `UUIDv7` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.9 `public.inventory_batches`
Inventory track logs.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `batch_number` `VARCHAR(128)` NOT NULL.
*   `manufactured_date` `DATE`.
*   `expiry_date` `DATE`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 6.10 `public.serial_numbers`
Tracks specific items.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `serial_number` `VARCHAR(255)` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'IN_STOCK' CHECK (status IN ('IN_STOCK', 'SOLD', 'DEFECTIVE', 'TRANSIT')).
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 7. ORDERS INTEGRATION REFERENCE SCHEMAS

Direct integrations with adjacent systems use immutable link tables.

```
                           [ORDER INTEGRATION RELATION]

     Orders Bounded Context ──► public.marketplace_order_links ──► public.vendors
                                              │ (1:N)
                                              ▼
                             public.vendor_order_assignments
```

### 7.1 `public.marketplace_order_links`
Integrates with the Orders Bounded Context.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `order_id_reference` `UUIDv7` NOT NULL COMMENT 'Authoritative ID in Orders DB'.
*   `total_marketplace_value` `NUMERIC(15,4)` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 7.2 `public.vendor_order_assignments`
Maps line items to merchants.
*   `id` `UUIDv7` PRIMARY KEY.
*   `order_link_id` `UUIDv7` NOT NULL REFERENCES public.marketplace_order_links(id) ON DELETE CASCADE.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `line_item_id_reference` `UUIDv7` NOT NULL.
*   `split_value` `NUMERIC(15,4)` NOT NULL.
*   `commission_value` `NUMERIC(15,4)` NOT NULL.
*   `fulfillment_status` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING' CHECK (fulfillment_status IN ('PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 7.3 `public.fulfillment_batches`
Consolidates items for shipping.
*   `id` `UUIDv7` PRIMARY KEY.
*   `vendor_id` `UUIDv7` NOT NULL REFERENCES public.vendors(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `carrier` `VARCHAR(128)` NOT NULL.
*   `tracking_number` `VARCHAR(128)`.
*   `shipped_at` `TIMESTAMPTZ`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 7.4 `public.shipment_links`
Maps items to specific shipments.
*   `fulfillment_batch_id` `UUIDv7` NOT NULL REFERENCES public.fulfillment_batches(id) ON DELETE CASCADE.
*   `assignment_id` `UUIDv7` NOT NULL REFERENCES public.vendor_order_assignments(id) ON DELETE RESTRICT.
*   PRIMARY KEY (fulfillment_batch_id, assignment_id).

### 7.5 `public.return_requests`
Processes returns.
*   `id` `UUIDv7` PRIMARY KEY.
*   `assignment_id` `UUIDv7` NOT NULL REFERENCES public.vendor_order_assignments(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `reason` `TEXT` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'SUBMITTED' CHECK (status IN ('SUBMITTED', 'RECEIVED', 'APPROVED', 'REJECTED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 7.6 `public.refund_requests`
Refund approvals.
*   `id` `UUIDv7` PRIMARY KEY.
*   `return_request_id` `UUIDv7` NOT NULL REFERENCES public.return_requests(id) ON DELETE RESTRICT.
*   `organization_id` `UUIDv7` NOT NULL.
*   `amount` `NUMERIC(15,4)` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 8. DIGITAL PRODUCTS SUBSYSTEM

This subsystem coordinates licensing, downlinks, and log audit registers.

```
                           [DIGITAL DOWNLOAD RELATION]

                     ┌──────────────────────────────────┐
                     │     public.digital_assets        │
                     └──────────────┬───────────────────┘
                                    │ (1:N)
                                    ▼
                     ┌──────────────────────────────────┐
                     │      public.download_links       │
                     └──────────────┬───────────────────┘
                                    │ (1:N)
                                    ▼
                     ┌──────────────────────────────────┐
                     │       public.download_logs       │
                     └──────────────────────────────────┘
```

### 8.1 `public.digital_assets`
Metadata registries for digital items.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `storage_file_path` `VARCHAR(512)` NOT NULL.
*   `file_hash_sha256` `CHAR(64)` NOT NULL.
*   `file_size_bytes` `BIGINT` NOT NULL.
*   `mime_type` `VARCHAR(128)` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 8.2 `public.license_keys`
System-generated license keys.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `key_encrypted` `BYTEA` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'AVAILABLE' CHECK (status IN ('AVAILABLE', 'ASSIGNED', 'REVOKED')).
*   `assigned_customer_id` `UUIDv7`.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 8.3 `public.download_links`
Generates transient downlinks.
*   `id` `UUIDv7` PRIMARY KEY.
*   `digital_asset_id` `UUIDv7` NOT NULL REFERENCES public.digital_assets(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `signed_token` `VARCHAR(255)` NOT NULL UNIQUE.
*   `expires_at` `TIMESTAMPTZ` NOT NULL.
*   `max_downloads` `SMALLINT` NOT NULL DEFAULT 5.
*   `download_count` `SMALLINT` NOT NULL DEFAULT 0.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 8.4 `public.subscription_products`
Sets active subscription billing timelines.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `grace_period_days` `SMALLINT` DEFAULT 3.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 8.5 `public.download_logs`
Downlink download logs for auditing.
*   `id` `UUIDv7` PRIMARY KEY.
*   `download_link_id` `UUIDv7` NOT NULL REFERENCES public.download_links(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `ip_address` `INET` NOT NULL.
*   `user_agent` `VARCHAR(512)`.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 9. PUBLIC RATINGS, REVIEWS & DISCUSSIONS

This subsystem manages customer feedback, product rankings, and moderation workflows.

```
                           [PUBLIC REVIEWS RELATION]

                     ┌──────────────────────────────────┐
                     │     public.product_reviews       │
                     └──────────────┬───────────────────┘
                                    │ (1:N)
                                    ▼
                     ┌──────────────────────────────────┐
                     │       public.review_votes        │
                     └──────────────────────────────────┘
```

### 9.1 `public.product_reviews`
Public customer product reviews.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `customer_id` `UUIDv7` NOT NULL.
*   `rating` `SMALLINT` NOT NULL CHECK (rating BETWEEN 1 AND 5).
*   `title` `VARCHAR(128)`.
*   `comment` `TEXT`.
*   `moderation_status` `VARCHAR(32)` NOT NULL DEFAULT 'APPROVED' CHECK (moderation_status IN ('PENDING', 'APPROVED', 'REJECTED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().
*   `updated_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 9.2 `public.review_votes`
Community review utility votes ("Helpful").
*   `id` `UUIDv7` PRIMARY KEY.
*   `review_id` `UUIDv7` NOT NULL REFERENCES public.product_reviews(id) ON DELETE CASCADE.
*   `voter_id` `UUIDv7` NOT NULL.
*   `is_upvote` `BOOLEAN` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 9.3 `public.review_reports`
User reports flagged for moderator review.
*   `id` `UUIDv7` PRIMARY KEY.
*   `review_id` `UUIDv7` NOT NULL REFERENCES public.product_reviews(id) ON DELETE CASCADE.
*   `reporter_id` `UUIDv7` NOT NULL.
*   `reason` `TEXT` NOT NULL.
*   `status` `VARCHAR(32)` NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'REVIEWED', 'DISMISSED')).
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 9.4 `public.questions`
Customer QA forums.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `customer_id` `UUIDv7` NOT NULL.
*   `question_text` `TEXT` NOT NULL.
*   `is_answered` `BOOLEAN` NOT NULL DEFAULT FALSE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 9.5 `public.answers`
Answers posted to customer questions.
*   `id` `UUIDv7` PRIMARY KEY.
*   `question_id` `UUIDv7` NOT NULL REFERENCES public.questions(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `responder_id` `UUIDv7` NOT NULL.
*   `responder_type` `VARCHAR(32)` NOT NULL CHECK (responder_type IN ('VENDOR', 'CUSTOMER', 'STAFF')).
*   `answer_text` `TEXT` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 10. SEARCH OPTIMIZATION REGISTRIES

Supports fast, typo-tolerant keyword indexes.

### 10.1 `public.search_documents`
Denormalized search index tables.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `search_content` `TEXT` NOT NULL.
*   `tsvector_document` `tsvector` GENERATED ALWAYS AS (to_tsvector('english', search_content)) STORED.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 10.2 `public.search_keywords`
Keyword indices for predictive search suggestions.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `keyword` `VARCHAR(128)` NOT NULL UNIQUE.
*   `frequency` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 10.3 `public.search_synonyms`
Maps custom synonyms for search normalization.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `keyword` `VARCHAR(128)` NOT NULL.
*   `synonyms_list` `VARCHAR(128)[]` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 11. ANALYTICAL CONVERSION HISTORIES (OLAP)

Operational analytics tables partitioned by date ranges to isolate analytical queries from transactional locks.

### 11.1 `public.product_statistics`
Monitors product views, clicks, and sales volumes.
*   `product_id` `UUIDv7` NOT NULL.
*   `organization_id` `UUIDv7` NOT NULL.
*   `view_count` `INTEGER` DEFAULT 0.
*   `cart_add_count` `INTEGER` DEFAULT 0.
*   `purchase_count` `INTEGER` DEFAULT 0.
*   `revenue_generated` `NUMERIC(15,4)` DEFAULT 0.0000.
*   `stats_date` `DATE` NOT NULL.
*   PRIMARY KEY (product_id, stats_date).

### 11.2 `public.vendor_statistics`
Tracks merchant processing volumes and performance stats.
*   `vendor_id` `UUIDv7` NOT NULL.
*   `organization_id` `UUIDv7` NOT NULL.
*   `orders_count` `INTEGER` DEFAULT 0.
*   `payout_volume` `NUMERIC(15,4)` DEFAULT 0.0000.
*   `avg_fulfillment_hours` `NUMERIC(6,2)`.
*   `stats_date` `DATE` NOT NULL.
*   PRIMARY KEY (vendor_id, stats_date).

### 11.3 `public.sales_statistics`
Aggregates sales performance indicators over time.
*   `id` `UUIDv7` NOT NULL.
*   `organization_id` `UUIDv7` NOT NULL.
*   `gross_sales` `NUMERIC(15,4)` NOT NULL.
*   `net_sales` `NUMERIC(15,4)` NOT NULL.
*   `tax_sales` `NUMERIC(15,4)` NOT NULL.
*   `payout_sales` `NUMERIC(15,4)` NOT NULL.
*   `stats_date` `DATE` NOT NULL.
*   PRIMARY KEY (id, stats_date);

### 11.4 `public.conversion_statistics`
Measures funnel drop-offs and promotional conversions.
*   `id` `UUIDv7` NOT NULL.
*   `organization_id` `UUIDv7` NOT NULL.
*   `funnel_stage` `VARCHAR(32)` NOT NULL CHECK (funnel_stage IN ('IMPRESSION', 'CLICK', 'ADD_TO_CART', 'CHECKOUT', 'PURCHASE')),
*   `count` `INTEGER` NOT NULL DEFAULT 1.
*   `stats_date` `DATE` NOT NULL.
*   PRIMARY KEY (id, stats_date);

---

## 12. AI OPTIMIZATION INTEGRATIONS

These schemas support automated content optimizations and prediction pipelines.

### 12.1 `public.ai_product_descriptions`
Uses AI to write optimized product descriptions and translate titles.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `prompt_template_name` `VARCHAR(128)` NOT NULL.
*   `generated_text` `TEXT` NOT NULL.
*   `quality_score` `NUMERIC(3,2)`.
*   `is_applied` `BOOLEAN` NOT NULL DEFAULT FALSE.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 12.2 `public.ai_category_predictions`
Automates product catalog categorization.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_id` `UUIDv7` NOT NULL REFERENCES public.products(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `predicted_category_id` `UUIDv7` REFERENCES public.product_categories(id) ON DELETE CASCADE.
*   `confidence_score` `NUMERIC(5,4)` NOT NULL.
*   `version` `INTEGER` NOT NULL DEFAULT 1.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 12.3 `public.ai_pricing_suggestions`
Analyzes competitor pricing trends to suggest price adjustments.
*   `id` `UUIDv7` PRIMARY KEY.
*   `product_variant_id` `UUIDv7` NOT NULL REFERENCES public.product_variants(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `market_price_sampled` `NUMERIC(15,4)` NOT NULL.
*   `suggested_price` `NUMERIC(15,4)` NOT NULL.
*   `pricing_strategy` `VARCHAR(64)` NOT NULL CHECK (pricing_strategy IN ('MAX_PROFIT', 'PENETRATION', 'COMPETITOR_MATCH')),
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 12.4 `public.ai_inventory_forecasts`
Forecasts warehouse stock demands to prevent stockouts.
*   `id` `UUIDv7` PRIMARY KEY.
*   `inventory_item_id` `UUIDv7` NOT NULL REFERENCES public.inventory_items(id) ON DELETE CASCADE.
*   `organization_id` `UUIDv7` NOT NULL.
*   `forecast_demand` `INTEGER` NOT NULL.
*   `target_reorder_date` `DATE` NOT NULL.
*   `confidence_interval` `NUMERIC(5,4)` NOT NULL.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 13. TRANSACTIONAL OUTBOX & EVENT CONTRACTS

Asynchronous outbox systems decouple integrations and prevent transactional locking.

```
                         [TRANSACTIONAL OUTBOX PATTERN]

  Step 1: DB Transaction ──► Write Outbox Record (Commit DB Transaction Atomically)
                                   │
                                   ▼
  Step 2: Event Sweeper  ──► Publish Event Payload ──► Message Broker (Idempotency Check)
```

### 13.1 `public.marketplace_event_outbox`
Atomic database outbox.
*   `id` `UUIDv7` PRIMARY KEY.
*   `organization_id` `UUIDv7` NOT NULL.
*   `event_type` `VARCHAR(128)` NOT NULL.
*   `payload` `JSONB` NOT NULL.
*   `retry_count` `SMALLINT` DEFAULT 0.
*   `processed_at` `TIMESTAMPTZ`.
*   `created_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

### 13.2 `public.processed_marketplace_events`
Idempotent validation ledger.
*   `event_id` `UUID` PRIMARY KEY.
*   `consumer_name` `VARCHAR(128)` NOT NULL.
*   `processed_at` `TIMESTAMPTZ` NOT NULL DEFAULT clock_timestamp().

---

## 14. POSTGRESQL 16 PERFORMANCE TUNING STANDARDS

All Marketplace tables are tuned for high transactional and query performance:

### 14.1 Custom Table Fillfactors
*   High-write operational log and transactional outbox tables enforce a default `FILLFACTOR = 80` to keep update operations fast and prevent page splitting.
*   Read-heavy catalog tables leverage a default `FILLFACTOR = 95` to pack data pages tightly and optimize database memory cache hits.

### 14.2 High-Throughput Indexing Strategies
*   **Composite Index Mappings**: Composite filters (e.g., matching routes or catalogs by both `organization_id` and unique slug keys) use composite indexes instead of index merges.
*   **GIN Trigram Search Indexing**: Fast fuzzy matching queries leverage native GIN indexes with the `pg_trgm` extension on searchable text columns.
*   **Partial Indexing**: Query performance on soft-deleted items is optimized by excluding deleted records from indexes using partial indexing.

---

## 15. ZERO-TRUST SECURITY & COMPLIANCE

Data protection rules secure tenant boundaries at the database engine layer:

*   **Zero-Trust Session Context**: Session handlers retrieve tenant claims from verified JWT tokens, applying Row-Level Security (RLS) configurations to every connection.
*   **Envelope Data Encryption**: Sensitive regulatory and financial records (e.g., bank account routing numbers, SSNs, EINs, gift card balances) are secured using AES-256-GCM envelope encryption.
*   **Cryptographically Signed Audit Trails**: All system and metadata adjustments are recorded in write-only audit logs, verifying row integrity using cryptographically signed hash chains.

---

## 16. TABLE CLASSIFICATION MATRIX

All database tables are classified below by operational write rates, storage retention rules, auditing compliance, and encryption configurations:

| Table Name | Subsystem Module | Write Frequency | Retention Period | Audit Level | Encryption Level | Partitioning Strategy | JSONB Usage | AI Integration |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `vendors` | Vendor | Low | Permanent | High | Tablespace | None | No | No |
| `vendor_profiles` | Vendor | Low | Permanent | Medium | Tablespace | None | Yes | No |
| `vendor_contacts` | Vendor | Low | Permanent | High | AES-256 Envelope| None | No | No |
| `vendor_addresses` | Vendor | Low | Permanent | Medium | Tablespace | None | No | No |
| `vendor_bank_accounts`| Vendor | Low | Permanent | High | AES-256 Envelope| None | No | No |
| `vendor_tax_information`| Vendor | Low | Permanent | High | AES-256 Envelope| None | No | No |
| `vendor_documents` | Vendor | Low | Permanent | High | Binary Signature | None | No | No |
| `vendor_settings` | Vendor | Low | Permanent | Medium | Tablespace | None | Yes | No |
| `vendor_status_history`| Vendor | Low | Permanent | Critical | Tablespace | None | No | No |
| `vendor_reviews` | Vendor | Low | 7 Years | Medium | Tablespace | None | No | No |
| `vendor_payout_accounts`| Vendor | Low | Permanent | Critical | AES-256 Envelope| None | No | No |
| `vendor_contracts` | Vendor | Low | Permanent | Critical | Cryptographic Hash| None | No | No |
| `products` | Catalog | Medium | Permanent | Medium | Tablespace | None | Yes | Yes |
| `product_variants` | Catalog | Medium | Permanent | Medium | Tablespace | None | No | Yes |
| `product_options` | Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_option_values`| Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_categories` | Catalog | Low | Permanent | Low | Tablespace | None | No | Yes |
| `category_tree` | Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_tags` | Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_tag_assignments`| Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_attributes` | Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_attribute_values`| Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_specifications`| Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_relationships`| Catalog | Low | Permanent | Low | Tablespace | None | No | No |
| `product_visibility_rules`| Catalog | Low | Permanent | Medium | Tablespace | None | Yes | No |
| `price_lists` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `product_prices` | Pricing | Medium | 7 Years | Medium | Tablespace | None | No | Yes |
| `customer_price_rules` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `volume_pricing` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `subscription_pricing` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `discount_rules` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `promotions` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `promotion_products` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `coupon_codes` | Pricing | Low | 7 Years | Medium | Tablespace | None | No | No |
| `gift_cards` | Pricing | Low | Permanent | High | AES-256 Envelope| None | No | No |
| `warehouses` | Inventory | Low | Permanent | Medium | Tablespace | None | No | No |
| `inventory_locations` | Inventory | Low | Permanent | Low | Tablespace | None | No | No |
| `inventory_items` | Inventory | High | Permanent | High | Tablespace | None | No | Yes |
| `inventory_transactions`| Inventory | Critical | 7 Years | Critical | Tablespace | Range (Monthly) | No | No |
| `inventory_adjustments`| Inventory | Medium | 7 Years | High | Tablespace | Range (Monthly) | No | No |
| `stock_reservations` | Inventory | Critical | Transient | Low | Tablespace | None | No | No |
| `inventory_counts` | Inventory | Low | 7 Years | High | Tablespace | None | No | No |
| `inventory_movements` | Inventory | Medium | 7 Years | High | Tablespace | Range (Monthly) | No | No |
| `inventory_batches` | Inventory | Low | 7 Years | High | Tablespace | None | No | No |
| `serial_numbers` | Inventory | Medium | Permanent | High | Tablespace | None | No | No |
| `marketplace_order_links`| Order Refs | High | Permanent | High | Tablespace | Range (Monthly) | No | No |
| `vendor_order_assignments`| Order Refs| High | Permanent | Critical | Tablespace | Range (Monthly) | No | No |
| `fulfillment_batches` | Order Refs| High | Permanent | High | Tablespace | Range (Monthly) | No | No |
| `shipment_links` | Order Refs| High | Permanent | High | Tablespace | Range (Monthly) | No | No |
| `return_requests` | Order Refs| Medium | 7 Years | High | Tablespace | None | No | No |
| `refund_requests` | Order Refs| Medium | 7 Years | Critical | Tablespace | None | No | No |
| `digital_assets` | Digital | Low | Permanent | High | Tablespace | None | No | No |
| `license_keys` | Digital | Low | Permanent | High | AES-256 Envelope| None | No | No |
| `download_links` | Digital | High | Transient | Low | Tablespace | None | No | No |
| `subscription_products`| Digital | Low | Permanent | High | Tablespace | None | No | No |
| `download_logs` | Digital | Critical | 3 Years | High | Tablespace | Range (Monthly) | No | No |
| `product_reviews` | Reviews | Medium | 7 Years | Medium | Tablespace | None | No | No |
| `review_votes` | Reviews | High | 7 Years | Low | Tablespace | None | No | No |
| `review_reports` | Reviews | Low | 7 Years | Medium | Tablespace | None | No | No |
| `questions` | Reviews | Medium | 7 Years | Low | Tablespace | None | No | No |
| `answers` | Reviews | Medium | 7 Years | Low | Tablespace | None | No | No |
| `search_documents` | Search | High | Transient | Low | Tablespace | None | No | No |
| `search_keywords` | Search | High | Transient | Low | Tablespace | None | No | No |
| `search_synonyms` | Search | Low | Permanent | Low | Tablespace | None | No | No |
| `product_statistics` | Analytics | Critical | 3 Years | Low | Tablespace | Range (Daily) | No | No |
| `vendor_statistics` | Analytics | High | 3 Years | Low | Tablespace | Range (Daily) | No | No |
| `sales_statistics` | Analytics | High | 3 Years | Low | Tablespace | Range (Daily) | No | No |
| `conversion_statistics`| Analytics | Critical | 3 Years | Low | Tablespace | Range (Daily) | No | No |
| `ai_product_descriptions`| AI Support | Low | Permanent | Low | Tablespace | None | No | Yes |
| `ai_category_predictions`| AI Support | Low | Permanent | Low | Tablespace | None | No | Yes |
| `ai_pricing_suggestions`| AI Support | Medium | 1 Year | Low | Tablespace | None | No | Yes |
| `ai_inventory_forecasts`| AI Support | Medium | 1 Year | Low | Tablespace | None | No | Yes |
| `marketplace_event_outbox`| Outbox | Critical | Transient | High | Tablespace | Range (Monthly) | Yes | No |
| `processed_marketplace_events`| Outbox | Critical | Transient | High | Tablespace | Range (Monthly) | No | No |

---

## 17. ENGINEERING VERIFICATION & COMPLIANCE CHECKLIST

Before promoting the database schema to production environments, SREs and Database Administrators must verify that the following quality checks are fully completed and pass the CI/CD release gate:

*   [ ] **Primary UUIDv7 Validation**: Confirm that every database table uses UUIDv7 primary keys to maintain global uniqueness and order.
*   [ ] **Tenant Isolation Audits**: Confirm that Row-Level Security (RLS) is active and enforced across all client-facing tables.
*   [ ] **Optimistic Lock Verification**: Verify that every update query increments the `version` column to prevent concurrent edit locks.
*   [ ] **Outbox Transaction Compliance**: Verify that outbox event entries are written atomically within the main database transaction.
*   [ ] **Envelope Encryption Checks**: Audit security keys and verify that SSN, bank, and gift card records are encrypted using AES-256-GCM.
*   [ ] **Partitioning Execution Plan**: Review execution plans for partitioned log tables to verify that queries utilize partition pruning.
*   [ ] **Database Schema Traceability**: Verify that every table mapped in the catalog registers to a physical table schema definition.

---

## 18. REFS & MASTER DOCUMENT MAP

This physical table specification aligns with adjacent specifications within the Marketplace bounded context:
*   **Marketplace Physical Tables (`Phase_2_3_2H_Marketplace_Physical_Tables.md`)**: Defines physical table structures, column definitions, database indexes, and Row-Level Security (RLS) policies.
*   **Vendor Aggregate Core (`Phase_2_3_2H_1_Vendor_Aggregate.md`)**: Coordinates merchant registrations, compliance workflows, and payout timelines.
*   **Product Catalog Core (`Phase_2_3_2H_2_Product_Catalog_Aggregate.md`)**: Governs multi-merchant inventories, custom attributes, categories, and tags.
*   **Pricing & Promotions Engine (`Phase_2_3_2H_3_Pricing_and_Promotions.md`)**: Governs promotional systems, regional tax integrations, and volume discounts.
*   **Inventory & Warehousing (`Phase_2_3_2H_4_Inventory_and_Warehousing.md`)**: Coordinates fulfillment layouts, stock allocations, and adjustment logs.
*   **Orders Domain Integrations (`Phase_2_3_2H_5_Orders_and_Fulfillment_Integration.md`)**: Connects catalog purchases to shipping services and returns.
*   **Digital Products & Licensing (`Phase_2_3_2H_6_Digital_Products_and_Licensing.md`)**: Manages licensing, digital asset downlinks, and log audit registries.
*   **Ratings, Reviews & Discussions (`Phase_2_3_2H_7_Ratings_Reviews_and_Discussions.md`)**: Governs customer rating portals, reviews, and moderation flows.
*   **Search Optimizations & FTS (`Phase_2_3_2H_8_Search_Optimizations_and_Query_Tuning.md`)**: Manages denormalized search indices and keyword FTS tuning.
*   **Operational Analytics (`Phase_2_3_2H_9_Marketplace_Analytics_and_Reporting.md`)**: Coordinates aggregated reporting views and partitioned statistics.
*   **AI Integrations & Demands (`Phase_2_3_2H_10_AI_Integrations_and_Demand_Forecasting.md`)**: Governs automated descriptions, price optimization suggestions, and warehouse forecasts.
*   **Integration Events & Outbox (`Phase_2_3_2H_11_Integration_Events_and_Outbox_Contracts.md`)**: Governs transactional outbox tables and external integration payloads.
*   **Performance & Scale Tuning (`Phase_2_3_2H_12_Performance_Scalability_and_Optimizations.md`)**: Documents query optimizations, fillfactors, and cache layers.
*   **Security & Compliance (`Phase_2_3_2H_13_Security_Privacy_and_Regulatory_Compliance.md`)**: Documents zero-trust database security, cryptographic audits, and GDPR workflows.
*   **Testing & CI/CD Release Gates (`Phase_2_3_2H_14_Testing_and_Validation_Strategies.md`)**: Outlines pgTAP unit and integration database test suites.
*   **Architecture Decision Records (`Phase_2_3_2H_15_Marketplace_Architecture_Decision_Records.md`)**: Logs permanent technical design decisions, evaluated alternatives, and trade-off analyses.
*   **Execution Master Roadmap (`Phase_2_3_2H_16_Marketplace_Implementation_Roadmap.md`)**: Orchestrates rollout phases and database migration orders.

---

*Authorized by the JUANET Database Administration Board & Global System Integrity Council.*
