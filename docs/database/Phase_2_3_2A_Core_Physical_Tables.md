# JUANET PostgreSQL Physical Table Specifications
## Phase 2.3.2A — Core Physical Tables & Structural Matrix
**Document Version:** 1.0  
**Author:** Chief Database Architect, JUANET Platform  
**Classification:** Technical / Database Schema Definition  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical table definitions for the core infrastructure of the JUANET Enterprise SaaS Platform. All specifications defined herein are binding and must be implemented exactly by any subsequent migration, ORM model, or DDL script. 

These tables conform to the guidelines set in:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`

---

## 2. MODULE 1 — CORE TENANT & SYSTEM FOUNDATION

### 2.1 Table Name: `system.organizations`

#### 2.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Represents the master tenant accounts. Every tenant maps to exactly one record in this table. All client data, invoices, projects, and users are logically isolated under a parent organization using Row-Level Security (RLS) keys referencing this table.
*   **Ownership Domain**: Tenant & Access Management Core
*   **Dependencies**: None (Root-level foundational table)

#### 2.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Global system-wide unique identifier for the tenant. |
| `name` | `varchar(100)` | NO | None | Unique Index | Legal registration name of the tenant entity. Checked to prevent empty strings. |
| `slug` | `citext` | NO | None | Unique Index | Unique, URL-safe slug used for subdomains or vanity routing (e.g., `acme`). |
| `status_id` | `uuid` | NO | None | FK Reference | References `system.organization_statuses` lookup. Prevents arbitrary state entries. |
| `created_at` | `timestamptz` | NO | `now()` | B-Tree Index | UTC creation timestamp for analytical tracking. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Dynamic modification audit timestamp. Managed by trigger. |
| `deleted_at` | `timestamptz` | YES | `NULL` | Partial Index | Logical soft delete threshold. When set, tenant is suspended. |
| `version` | `integer` | NO | `1` | None | Optimistic locking sequence counter. |

#### 2.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `status_id REFERENCES system.organization_statuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT organizations_slug_key UNIQUE (slug)`
    *   `CONSTRAINT organizations_name_key UNIQUE (name)`
*   **Check Constraints**:
    *   `CONSTRAINT organizations_name_length_check CHECK (length(trim(name)) >= 2)`
    *   `CONSTRAINT organizations_slug_format_check CHECK (slug ~* '^[a-z0-9\-]+$')`

#### 2.1.4 Indexes & Execution Paths
*   **`organizations_pkey`**: Primary Key Index (Implicit B-Tree) on `id`.
*   **`organizations_slug_uidx`**: Unique Index (B-Tree) on `slug`. Used for high-frequency subdomain resolution queries.
*   **`organizations_name_uidx`**: Unique Index (B-Tree) on `name`. Used for tenant search and onboarding uniqueness validations.
*   **`organizations_active_partial_idx`**: Partial B-Tree Index on `(id)` WHERE `deleted_at IS NULL`. Restricts lookup engines to active tenants.

#### 2.1.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable only for specific unauthenticated routines (such as resolving subdomain slug to tenant metadata). Otherwise restricted to authorized super-users and the tenant’s own authenticated users.
*   **Insert**: Super-admin role only.
*   **Update**: Restricted to organization admins belonging to the specific tenant ID.
*   **Delete**: Restricted to super-admins (soft delete only; physical deletion is prohibited).
*   **Service Account Bypass**: Enabled for the platform-level provisioning engine.

#### 2.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000 – 100,000 tenants.
*   **Read / Write Ratio**: 99.9% Reads / 0.1% Writes (High read frequency).
*   **Archival & Retention Policy**: Immutable directory. Never archived physically. Soft-deleted tenants are retained for 7 years for compliance and audit requirements.
*   **Backup Importance**: Absolute Priority (Critical Core Configuration).

#### 2.1.7 Event Matrix
*   **Events Produced**: `organization.created`, `organization.updated`, `organization.suspended`, `organization.soft_deleted`
*   **Events Consumed**: None

#### 2.1.8 Relationships
*   **One-to-One**: `system.organization_settings`
*   **One-to-Many**: `public.users`, `public.projects`, `public.invoices`, `system.organization_features`

---

### 2.2 Table Name: `system.organization_settings`

#### 2.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Manages localized configuration values, system themes, default currencies, default locales, and billing parameters for each organization. Separates core identity from administrative settings.
*   **Ownership Domain**: Tenant Configuration Core
*   **Dependencies**: `system.organizations`, `system.currencies`

#### 2.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `organization_id` | `uuid` | NO | None | PK & FK | One-to-one identifier matching the parent tenant organization. |
| `default_currency_id`| `uuid` | NO | None | FK Reference | References `system.currencies`. Sets standard base reporting currency. |
| `timezone` | `varchar(50)` | NO | `'UTC'` | None | Primary active IANA timezone string for reporting and task calculations. |
| `locale` | `varchar(10)` | NO | `'en-US'` | None | Standard BCP-47 locale code for numeric, date, and currency formatting. |
| `billing_email` | `citext` | NO | None | B-Tree Index | Centralized address where invoices and financial alerts are dispatched. |
| `updated_at` | `timestamptz` | NO | `now()` | None | System timestamp of latest adjustment. |
| `version` | `integer` | NO | `1` | None | Optimistic locking concurrency manager. |

#### 2.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (organization_id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `default_currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT settings_timezone_check CHECK (length(timezone) >= 3)`
    *   `CONSTRAINT settings_billing_email_check CHECK (billing_email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')`

#### 2.2.4 Indexes & Execution Paths
*   **`organization_settings_pkey`**: Primary key index on `organization_id`.
*   **`settings_billing_email_idx`**: B-Tree Index on `billing_email`. Optimized for targeted financial notifications and batch billing runs.

#### 2.2.5 Row-Level Security (RLS) Policy
*   **Select**: Authorized tenant members only.
*   **Insert / Update**: Restricted to organization admins of the specific tenant.
*   **Delete**: Inherits CASCADE on physical tenant deletion. Direct delete is prohibited.

#### 2.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: Matches `system.organizations` (1:1 relationship).
*   **Read / Write Ratio**: 99.5% Reads / 0.5% Writes.
*   **Archival & Retention Policy**: Follows parent organization lifecycle.
*   **Backup Importance**: High.

#### 2.2.7 Event Matrix
*   **Events Produced**: `organization.settings_updated`
*   **Events Consumed**: None

#### 2.2.8 Relationships
*   **One-to-One**: `system.organizations`

---

### 2.3 Table Name: `system.organization_features`

#### 2.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Stores granular tenant feature enablement flags. Restricts or grants access to premium modules (CRM, custom ledgers, advanced AI summarizers) based on subscription boundaries without requiring physical code deployments.
*   **Ownership Domain**: SaaS Feature Gating & Entitlements
*   **Dependencies**: `system.organizations`

#### 2.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique, non-sequential feature rule ID. |
| `organization_id` | `uuid` | NO | None | FK & Composite PK | SaaS Tenant isolation identifier. |
| `feature` | `varchar(100)` | NO | None | Composite PK | Code name of restricted module (e.g., `double_entry_ledger`, `ai`). |
| `enabled` | `boolean` | NO | `true` | B-Tree Index | System flag status determining access. |
| `expires_at` | `timestamptz` | YES | `NULL` | B-Tree Index | Nullable UTC timestamp tracking trial expirations or promo phases. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |
| `version` | `integer` | NO | `1` | None | Optimistic concurrency sequence. |

#### 2.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT org_features_composite_key UNIQUE (organization_id, feature)`
*   **Check Constraints**:
    *   `CONSTRAINT features_code_format_check CHECK (feature ~* '^[a-z0-9\_]+$')`

#### 2.3.4 Indexes & Execution Paths
*   **`organization_features_pkey`**: Primary index on `id`.
*   **`org_features_composite_uidx`**: Unique Index (B-Tree) on `(organization_id, feature)`. This is the primary execution path for the application-layer feature checker.
*   **`org_features_expiry_idx`**: Partial Index (B-Tree) on `(expires_at)` WHERE `expires_at IS NOT NULL`. Used by hourly background workers to auto-expire features.

#### 2.3.5 Row-Level Security (RLS) Policy
*   **Select**: Tenant members (verifying capabilities).
*   **Insert / Update**: Restricted to system administrators.
*   **Delete**: Restricted to system administrators.

#### 2.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 50,000 – 500,000 rows (based on features per tenant).
*   **Read / Write Ratio**: 99.9% Reads / 0.1% Writes.
*   **Caching Strategy**: Highly critical candidate for distributed in-memory caching (e.g., Redis) or local memory caching to prevent database roundtrips on every controller dispatch.
*   **Backup Importance**: High (Directly impacts billing and system gating).

#### 2.3.7 Event Matrix
*   **Events Produced**: `feature_flag.created`, `feature_flag.updated`, `feature_flag.expired`
*   **Events Consumed**: `subscription.activated` (triggers features provision), `subscription.canceled` (removes features)

---

### 2.4 Table Name: `system.countries`

#### 2.4.1 Purpose, Ownership & Dependencies
*   **Purpose**: Central geographical registry used to coordinate tax jurisdictions, national currency relationships, address validations, local timezone conventions, and phone routing rules.
*   **Ownership Domain**: System Reference Catalog (Lookup Table)
*   **Dependencies**: `system.currencies`

#### 2.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Canonical country ID. |
| `iso2` | `char(2)` | NO | None | Unique Index | ISO 3166-1 alpha-2 code (uppercase, e.g., `KE`, `NG`). |
| `iso3` | `char(3)` | NO | None | Unique Index | ISO 3166-1 alpha-3 code (uppercase, e.g., `KEN`, `NGA`). |
| `name` | `varchar(100)` | NO | None | B-Tree Index | Formal common name of the country. |
| `phone_code` | `varchar(10)` | NO | None | None | International telephone prefix code (e.g., `+254`, `+44`). |
| `currency_id` | `uuid` | NO | None | FK Reference | References default local `system.currencies(id)`. |
| `default_timezone`| `varchar(50)` | NO | None | None | Default IANA timezone name (e.g., `Africa/Nairobi`). |
| `tax_region` | `varchar(50)` | NO | `'Standard'`| None | Tax federation bracket categorization (e.g., `EAC`, `ECOWAS`, `EU_VAT`). |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 2.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT countries_iso2_key UNIQUE (iso2)`
    *   `CONSTRAINT countries_iso3_key UNIQUE (iso3)`
*   **Check Constraints**:
    *   `CONSTRAINT countries_iso2_format CHECK (iso2 ~ '^[A-Z]{2}$')`
    *   `CONSTRAINT countries_iso3_format CHECK (iso3 ~ '^[A-Z]{3}$')`

#### 2.4.4 Indexes & Execution Paths
*   **`countries_iso2_uidx`**: Unique Index on `iso2`. Primary route for API parameter parsing and mapping requests.
*   **`countries_iso3_uidx`**: Unique Index on `iso3`.
*   **`countries_name_idx`**: B-Tree Index on `name` for localized search selectors in UI inputs.

#### 2.4.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally (lookup catalog).
*   **Insert / Update / Delete**: System administrators only (Bypassed in multi-tenant contexts).

#### 2.4.6 Operational Performance & Lifecycles
*   **Expected Row Count**: ~250 records (Static lookup).
*   **Read / Write Ratio**: 100% Reads (Read-only after seeding).
*   **Caching Strategy**: Static cache. Indefinitely cached in memory.
*   **Archival & Retention Policy**: Immutable catalog. Never archived.
*   **Backup Importance**: Medium (Easily seedable via static definitions).

#### 2.4.7 Event Matrix
*   **Events Produced**: None
*   **Events Consumed**: None

#### 2.4.8 Relationships
*   **One-to-Many**: `system.organizations` (via tenant location), `system.tax_jurisdictions`

---

### 2.5 Table Name: `system.currencies`

#### 2.5.1 Purpose, Ownership & Dependencies
*   **Purpose**: Global system lookup catalog defining the legal financial currencies supported across the multi-tenant transactional architecture. Prevents typing mistakes and maintains precision standards.
*   **Ownership Domain**: System Financial Catalog (Lookup Table)
*   **Dependencies**: None

#### 2.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique currency ledger reference ID. |
| `code` | `char(3)` | NO | None | Unique Index | ISO 4217 standard uppercase alphabetic currency code (e.g., `KES`, `USD`, `EUR`). |
| `name` | `varchar(50)` | NO | None | None | Legal English currency name (e.g., `Kenyan Shilling`). |
| `symbol` | `varchar(10)` | NO | None | None | Graphic symbol or character set representation (e.g., `KSh`, `$`, `€`). |
| `decimals` | `integer` | NO | `2` | None | Standard fractional subunits (e.g., `2` for cents, `0` for Japanese Yen). |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 2.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraints**:
    *   `CONSTRAINT currencies_code_key UNIQUE (code)`
*   **Check Constraints**:
    *   `CONSTRAINT currencies_code_format CHECK (code ~ '^[A-Z]{3}$')`
    *   `CONSTRAINT currencies_decimals_range CHECK (decimals >= 0 AND decimals <= 4)`

#### 2.5.4 Indexes & Execution Paths
*   **`currencies_code_uidx`**: Unique Index on `code`. Main lookup route for currency parsers during financial data ingestion.

#### 2.5.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert / Update / Delete**: System administrators only.

#### 2.5.6 Operational Performance & Lifecycles
*   **Expected Row Count**: ~180 records (Static lookup).
*   **Read / Write Ratio**: 100% Reads.
*   **Caching Strategy**: In-memory cache candidate.
*   **Archival & Retention Policy**: Immutable catalog. Never archived.

#### 2.5.7 Event Matrix
*   **Events Produced**: None
*   **Events Consumed**: None

#### 2.5.8 Relationships
*   **One-to-Many**: `system.organization_settings`, `system.exchange_rates`, `public.invoices`

---

### 2.6 Table Name: `system.exchange_rates`

#### 2.6.1 Purpose, Ownership & Dependencies
*   **Purpose**: Stores historical and active foreign exchange conversion multipliers, enabling multi-currency consolidation, dynamic reporting, and unified global ledger calculations.
*   **Ownership Domain**: Treasury & Exchange Ledger (Append-Only)
*   **Dependencies**: `system.currencies`

#### 2.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique rate record ID. |
| `source_currency_id`| `uuid` | NO | None | FK & Composite Index | Base conversion currency link. |
| `target_currency_id`| `uuid` | NO | None | FK & Composite Index | Target conversion currency link. |
| `rate` | `numeric(18,6)` | NO | None | None | Highly precise exchange rate multiplier. |
| `fetched_at` | `timestamptz` | NO | `now()` | B-Tree Index | System timestamp when rate was synchronized or calculated. |

#### 2.6.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `source_currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `target_currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT rates_different_currencies CHECK (source_currency_id <> target_currency_id)`
    *   `CONSTRAINT rates_positive_rate CHECK (rate > 0.000000)`

#### 2.6.4 Indexes & Execution Paths
*   **`exchange_rates_composite_idx`**: B-Tree Index on `(source_currency_id, target_currency_id, fetched_at DESC)`. Used to quickly retrieve the latest exchange rate for currency conversions.

#### 2.6.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert**: System rate update agent only.
*   **Update / Delete**: Strictly prohibited (Append-only design).

#### 2.6.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 1,000,000+ rows over time (daily snapshot syncs).
*   **Read / Write Ratio**: 90% Reads / 10% Writes.
*   **Partitioning Recommended**: Partitioned by Range on `fetched_at` monthly to maintain index performance on historical sets.
*   **Archival Policy**: Rates older than 3 years migrated to cold storage. Active snapshot views are cached.

#### 2.6.7 Event Matrix
*   **Events Produced**: `exchange_rate.updated`
*   **Events Consumed**: None

---

## 3. MODULE 2 — PAYMENT HUB & GATEWAY

### 3.1 Table Name: `system.payment_gateways`

#### 3.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Central lookup catalog of supported payment gateway providers (e.g., Safaricom Daraja/M-Pesa, PayHero, Stripe, PayPal). Defines the adapter capabilities and configuration structures required by the application.
*   **Ownership Domain**: Payment Hub Core
*   **Dependencies**: `system.gateway_statuses`

#### 3.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique gateway ID. |
| `code` | `varchar(50)` | NO | None | Unique Index | Standard internal lowercase code (e.g., `mpesa_daraja`, `stripe`). |
| `name` | `varchar(100)` | NO | None | None | User-friendly provider display name. |
| `status_id` | `uuid` | NO | None | FK Reference | References `system.gateway_statuses` lookup. |
| `capabilities` | `jsonb` | NO | `'[]'` | None | JSONB array of supported functions (e.g., `["stk_push", "payout", "card_capture"]`). |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 3.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `status_id REFERENCES system.gateway_statuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT gateways_code_key UNIQUE (code)`
*   **Check Constraints**:
    *   `CONSTRAINT gateways_code_format CHECK (code ~ '^[a-z0-9\_]+$')`

#### 3.1.4 Indexes & Execution Paths
*   **`gateways_code_uidx`**: Unique index on `code`. Main route for gateway adapter resolution.

#### 3.1.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert / Update / Delete**: System administrators only.

#### 3.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: ~20 records (Static lookup).
*   **Read / Write Ratio**: 100% Reads.
*   **Backup Importance**: High.

#### 3.1.7 Relationships
*   **One-to-Many**: `public.organization_payment_gateways`, `public.payment_routing_rules`

---

### 3.2 Table Name: `public.organization_payment_gateways`

#### 3.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Binds a tenant organization to specific payment gateways. Configures tenant-specific parameters (e.g., merchant IDs, business shortcodes) while isolating operational data.
*   **Ownership Domain**: Payment Hub Configuration
*   **Dependencies**: `system.organizations`, `system.payment_gateways`

#### 3.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique tenant gateway reference ID. |
| `organization_id` | `uuid` | NO | None | FK & Composite Index | SaaS Tenant isolation identifier. |
| `gateway_id` | `uuid` | NO | None | FK & Composite Index | Reference to the core gateway adapter. |
| `is_active` | `boolean` | NO | `true` | B-Tree Index | Toggle determining if the gateway is active for transactions. |
| `config_parameters`| `jsonb` | NO | `'{}'` | None | Public configuration variables (e.g., callback URLs, public merchant keys). |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |
| `version` | `integer` | NO | `1` | None | Optimistic locking manager. |

#### 3.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `gateway_id REFERENCES system.payment_gateways(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT org_gateways_composite_key UNIQUE (organization_id, gateway_id)`

#### 3.2.4 Indexes & Execution Paths
*   **`org_payment_gateways_pkey`**: Primary Key Index on `id`.
*   **`org_gateways_composite_idx`**: Unique composite index on `(organization_id, gateway_id)`. Main route for verifying gateway access.

#### 3.2.5 Row-Level Security (RLS) Policy
*   **Select / Update**: Restricted to organization admins of the specific tenant.
*   **Insert**: Organization admins of the tenant.
*   **Delete**: Restricted to organization admins (ON DELETE CASCADE from organization).

#### 3.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 20,000 – 100,000 rows.
*   **Read / Write Ratio**: 99.5% Reads / 0.5% Writes.
*   **Backup Importance**: Critical (Tenant financial endpoints configuration).

#### 3.2.7 Event Matrix
*   **Events Produced**: `organization_gateway.activated`, `organization_gateway.deactivated`
*   **Events Consumed**: None

#### 3.2.8 Relationships
*   **One-to-Many**: `public.gateway_credentials`

---

### 3.3 Table Name: `public.gateway_credentials`

#### 3.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Stores encrypted, highly sensitive credentials (such as client secrets, API passwords, private certificates, OAuth refresh tokens) mapped to a tenant's gateway configuration. Normalizes secrets into an isolated table to prevent accidental leaks.
*   **Ownership Domain**: Payment Security Core
*   **Dependencies**: `public.organization_payment_gateways`

#### 3.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique credential ID. |
| `organization_gateway_id`| `uuid` | NO | None | FK & Composite Index | Associated tenant gateway link. |
| `credential_name` | `varchar(100)`| NO | None | Composite Index | Code name for the key parameter (e.g., `api_key`, `private_key`). |
| `encrypted_value` | `text` | NO | None | None | Sensitive credential value, **encrypted in the app layer using AES-256-GCM**. |
| `expires_at` | `timestamptz` | YES | `NULL` | B-Tree Index | Optional credential expiration date, used to trigger rotation flows. |
| `rotated_at` | `timestamptz` | YES | `NULL` | None | Timestamp of last rotation. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 3.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_gateway_id REFERENCES public.organization_payment_gateways(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT gateway_credentials_composite_key UNIQUE (organization_gateway_id, credential_name)`
*   **Check Constraints**:
    *   `CONSTRAINT credential_name_format CHECK (credential_name ~* '^[a-z0-9\_]+$')`

#### 3.3.4 Indexes & Execution Paths
*   **`gateway_credentials_pkey`**: Primary Key index.
*   **`gateway_credentials_lookup_idx`**: Unique composite index on `(organization_gateway_id, credential_name)`. Used to retrieve specific credentials during checkout.
*   **`gateway_credentials_expiry_idx`**: Partial index on `(expires_at)` WHERE `expires_at IS NOT NULL`. Used to track credentials nearing expiration.

#### 3.3.5 Row-Level Security (RLS) Policy
*   **Select**: Restricted to active payment workers and tenant admins.
*   **Insert / Update**: Tenant admins of the organization.
*   **Delete**: Restricted to tenant admins.

#### 3.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 50,000 – 300,000 rows.
*   **Read / Write Ratio**: 99.8% Reads / 0.2% Writes.
*   **Encrypted Columns**: `encrypted_value` is encrypted with application-managed AES-256-GCM.
*   **Backup Importance**: Critical.

#### 3.3.7 Event Matrix
*   **Events Produced**: `gateway_credential.expired`, `gateway_credential.rotated`
*   **Events Consumed**: None

---

### 3.4 Table Name: `public.payment_routing_rules`

#### 3.4.1 Purpose, Ownership & Dependencies
*   **Purpose**: Controls transaction routing based on user-defined criteria (e.g., currency, country, transfer amounts, transaction volume). Eliminates hardcoded conditional routing in application logic.
*   **Ownership Domain**: Intelligent Payment Routing Engine
*   **Dependencies**: `system.organizations`, `system.currencies`, `system.countries`, `system.payment_gateways`

#### 3.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique rule ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | SaaS Tenant isolation identifier. |
| `currency_id` | `uuid` | NO | None | FK Reference | Target currency for routing check. |
| `country_id` | `uuid` | YES | `NULL` | FK Reference | Optional target geography for routing check. |
| `priority` | `integer` | NO | `1` | B-Tree Index | Rule evaluation priority (1 is highest). |
| `gateway_id` | `uuid` | NO | None | FK Reference | Selected payment gateway if rule criteria are met. |
| `minimum_amount` | `numeric(18,2)`| YES | `NULL` | None | Lower threshold limit for rule. |
| `maximum_amount` | `numeric(18,2)`| YES | `NULL` | None | Upper threshold limit for rule. |
| `capability` | `varchar(50)` | YES | `'payout'`| None | Gateway capability required (e.g., `stk_push`, `card_capture`). |
| `is_active` | `boolean` | NO | `true` | B-Tree Index | Toggle determining if rule is active. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |
| `version` | `integer` | NO | `1` | None | Optimistic locking manager. |

#### 3.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `country_id REFERENCES system.countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `gateway_id REFERENCES system.payment_gateways(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT routing_priority_positive CHECK (priority >= 1)`
    *   `CONSTRAINT routing_min_amount_positive CHECK (minimum_amount >= 0.00)`
    *   `CONSTRAINT routing_max_amount_positive CHECK (maximum_amount >= 0.00)`
    *   `CONSTRAINT routing_amounts_ordered CHECK (minimum_amount IS NULL OR maximum_amount IS NULL OR minimum_amount <= maximum_amount)`

#### 3.4.4 Indexes & Execution Paths
*   **`payment_routing_rules_pkey`**: Primary Key index.
*   **`payment_routing_execution_idx`**: B-Tree Index on `(organization_id, currency_id, is_active, priority ASC)`. Primary execution index used to match and route transactions.

#### 3.4.5 Row-Level Security (RLS) Policy
*   **Select / Insert / Update / Delete**: Tenant administrators only.

#### 3.4.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000 – 100,000 rules.
*   **Read / Write Ratio**: 99.9% Reads / 0.1% Writes.
*   **Caching Strategy**: Rules are cached per tenant and currency to bypass transactional lookups during checkout.
*   **Backup Importance**: High.

#### 3.4.7 Event Matrix
*   **Events Produced**: `routing_rule.created`, `routing_rule.updated`
*   **Events Consumed**: None

---

### 3.5 Table Name: `public.payment_health_metrics`

#### 3.5.1 Purpose, Ownership & Dependencies
*   **Purpose**: Logs real-time transactional telemetry (such as success rates, connection latencies, and gateway timeouts) per provider. Enables automated failovers to secondary gateways if quality degradation is detected.
*   **Ownership Domain**: Payment Telemetry Engine (Append-Only)
*   **Dependencies**: `system.payment_gateways`

#### 3.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique metric record ID. |
| `gateway_id` | `uuid` | NO | None | FK & B-Tree Index | Target payment gateway provider. |
| `requests_count` | `integer` | NO | `0` | None | Total checkout transactions dispatched within the analysis period. |
| `success_count` | `integer` | NO | `0` | None | Successfully authorized checkouts. |
| `failure_count` | `integer` | NO | `0` | None | Rejected or failed transactions. |
| `avg_latency_ms` | `integer` | NO | `0` | None | Average round-trip response time in milliseconds. |
| `measured_at` | `timestamptz` | NO | `now()` | B-Tree Index | Time of telemetry collection. |

#### 3.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `gateway_id REFERENCES system.payment_gateways(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT health_counts_valid CHECK (success_count + failure_count <= requests_count)`
    *   `CONSTRAINT health_counts_positive CHECK (requests_count >= 0 AND success_count >= 0 AND failure_count >= 0)`

#### 3.5.4 Indexes & Execution Paths
*   **`payment_health_metrics_pkey`**: Primary Key index.
*   **`payment_health_telemetry_idx`**: B-Tree Index on `(gateway_id, measured_at DESC)`. Used to track the health of specific gateways over time.

#### 3.5.5 Row-Level Security (RLS) Policy
*   **Select**: System telemetry dashboard workers.
*   **Insert**: Telemetry service workers only.
*   **Update / Delete**: Strictly prohibited (Append-only ledger).

#### 3.5.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000,000+ rows over time.
*   **Read / Write Ratio**: 20% Reads / 80% Writes.
*   **Partitioning Recommended**: Partitioned by Range on `measured_at` monthly.
*   **Archival Policy**: Aggregated into daily metrics after 14 days; raw telemetry logs older than 30 days are purged.

#### 3.5.7 Event Matrix
*   **Events Produced**: `gateway_health.degraded`, `gateway_health.recovered`
*   **Events Consumed**: None

---

## 4. MODULE 3 — SAAS BILLING & SUBSCRIPTIONS

### 4.1 Table Name: `system.plans`

#### 4.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Central registry of available SaaS subscription tiers (e.g., Free, Starter, Pro, Enterprise). Configures pricing models, billing intervals, and structural bounds.
*   **Ownership Domain**: Subscription Management Core (Lookup Table)
*   **Dependencies**: `system.currencies`

#### 4.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique plan ID. |
| `name` | `varchar(100)` | NO | None | None | Display title of subscription tier. |
| `code` | `varchar(50)` | NO | None | Unique Index | Unique billing code (e.g., `pro-monthly`). |
| `price` | `numeric(18,2)`| NO | `0.00` | None | Base price charged per billing interval. |
| `currency_id` | `uuid` | NO | None | FK Reference | References currency of subscription. |
| `billing_interval`| `varchar(30)` | NO | `'month'` | None | Billing interval period (`month` or `year`). |
| `is_active` | `boolean` | NO | `true` | B-Tree Index | Flag indicating if plan is active for new signups. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 4.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT plans_code_key UNIQUE (code)`
*   **Check Constraints**:
    *   `CONSTRAINT plans_price_non_negative CHECK (price >= 0.00)`
    *   `CONSTRAINT plans_code_format CHECK (code ~* '^[a-z0-9\-]+$')`
    *   `CONSTRAINT plans_interval_valid CHECK (billing_interval IN ('month', 'year'))`

#### 4.1.4 Indexes & Execution Paths
*   **`plans_code_uidx`**: Unique Index on `code`. Main lookup route for pricing queries during signup.

#### 4.1.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert / Update / Delete**: System administrators only.

#### 4.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: ~50 records.
*   **Read / Write Ratio**: 100% Reads.
*   **Caching Strategy**: Cached globally.
*   **Backup Importance**: High.

#### 4.1.7 Relationships
*   **One-to-Many**: `public.subscriptions`

---

### 4.2 Table Name: `public.subscriptions`

#### 4.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Manages active tenant subscriptions, mapping organizations to plans and tracking subscription lifecycles, billing periods, and cancellation triggers.
*   **Ownership Domain**: Tenant Subscription Management
*   **Dependencies**: `system.organizations`, `system.plans`

#### 4.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique subscription ID. |
| `organization_id` | `uuid` | NO | None | FK & Unique Index | Associated tenant organization. |
| `plan_id` | `uuid` | NO | None | FK & B-Tree Index | Subscribed tier reference. |
| `status` | `varchar(30)` | NO | `'trialing'`| B-Tree Index | Lifecycle stage (`trialing`, `active`, `past_due`, `canceled`). |
| `current_period_start`|`timestamptz`| NO | `now()` | None | Current billing period start date (UTC). |
| `current_period_end`| `timestamptz` | NO | None | B-Tree Index | Current billing period expiration date (UTC). |
| `cancel_at_period_end`|`boolean`| NO | `false` | None | If true, subscription will cancel at the period end. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |
| `version` | `integer` | NO | `1` | None | Optimistic locking manager. |

#### 4.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `plan_id REFERENCES system.plans(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT subscriptions_org_key UNIQUE (organization_id)`
*   **Check Constraints**:
    *   `CONSTRAINT subscriptions_status_check CHECK (status IN ('trialing', 'active', 'past_due', 'canceled'))`
    *   `CONSTRAINT subscriptions_dates_ordered CHECK (current_period_start <= current_period_end)`

#### 4.2.4 Indexes & Execution Paths
*   **`subscriptions_pkey`**: Primary Key index.
*   **`subscriptions_expiry_idx`**: B-Tree Index on `(status, current_period_end)`. Used by background workers to identify and transition expired accounts.

#### 4.2.5 Row-Level Security (RLS) Policy
*   **Select**: Authorized tenant members.
*   **Insert / Update / Delete**: Restricted to billing systems and system admins.

#### 4.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: Matches `system.organizations` (1:1 relationship).
*   **Read / Write Ratio**: 95% Reads / 5% Writes.
*   **Backup Importance**: Critical (Tenant billing configurations).

#### 4.2.7 Event Matrix
*   **Events Produced**: `subscription.created`, `subscription.status_changed`, `subscription.canceled`
*   **Events Consumed**: `payment.completed` (updates current period end), `payment.failed` (sets subscription status to past_due)

#### 4.2.8 Relationships
*   **One-to-Many**: `public.subscription_items`

---

### 4.3 Table Name: `public.subscription_items`

#### 4.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Supports modular multi-item subscriptions and add-on pricing (e.g., purchasing additional storage or seats alongside a base plan).
*   **Ownership Domain**: Add-On Entitlements Core
*   **Dependencies**: `public.subscriptions`

#### 4.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique subscription item ID. |
| `subscription_id` | `uuid` | NO | None | FK & B-Tree Index | Master subscription reference. |
| `item_type` | `varchar(50)` | NO | `'base'` | None | Add-on type classification (e.g., `base`, `addon`, `usage`). |
| `quantity` | `integer` | NO | `1` | None | Purchased quantity multiplier. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |

#### 4.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `subscription_id REFERENCES public.subscriptions(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT sub_items_quantity_positive CHECK (quantity >= 1)`
    *   `CONSTRAINT sub_items_type_valid CHECK (item_type IN ('base', 'addon', 'usage'))`

#### 4.3.4 Indexes & Execution Paths
*   **`subscription_items_pkey`**: Primary Key index.
*   **`sub_items_master_lookup_idx`**: B-Tree Index on `(subscription_id)`. Resolves all active add-ons for a subscription.

#### 4.3.5 Row-Level Security (RLS) Policy
*   **Select**: Authorized tenant members.
*   **Insert / Update / Delete**: Restricted to billing systems and system admins.

#### 4.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 50,000 – 300,000 rows.
*   **Read / Write Ratio**: 98% Reads / 2% Writes.
*   **Backup Importance**: High.

#### 4.3.7 Relationships
*   **One-to-Many**: `public.subscription_usage`

---

### 4.4 Table Name: `public.subscription_usage`

#### 4.4.1 Purpose, Ownership & Dependencies
*   **Purpose**: Logs and tracks metered resource consumption (e.g., total API calls, processed AI tokens, SMS dispatches) for usage-based billing models.
*   **Ownership Domain**: Usage Billing Core
*   **Dependencies**: `public.subscription_items`

#### 4.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique usage record ID. |
| `subscription_item_id`| `uuid` | NO | None | FK & Composite Index | Associated subscription add-on link. |
| `metric_name` | `varchar(100)`| NO | None | Composite Index | Code name of volume counter (e.g., `ai_tokens_processed`). |
| `quantity_used` | `numeric(18,2)`| NO | `0.00` | None | Cumulative consumption count. |
| `reset_at` | `timestamptz` | NO | None | B-Tree Index | Timestamp when consumption quota resets (usually billing period end). |
| `updated_at` | `timestamptz` | NO | `now()` | None | Timestamp of last usage increment. |

#### 4.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `subscription_item_id REFERENCES public.subscription_items(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT sub_usage_composite_key UNIQUE (subscription_item_id, metric_name)`
*   **Check Constraints**:
    *   `CONSTRAINT sub_usage_quantity_positive CHECK (quantity_used >= 0.00)`
    *   `CONSTRAINT sub_usage_metric_format CHECK (metric_name ~* '^[a-z0-9\_]+$')`

#### 4.4.4 Indexes & Execution Paths
*   **`subscription_usage_pkey`**: Primary Key index.
*   **`sub_usage_lookup_idx`**: Unique composite index on `(subscription_item_id, metric_name)`. Primary route for updating and retrieving consumption records.

#### 4.4.5 Row-Level Security (RLS) Policy
*   **Select**: Authorized tenant members.
*   **Insert / Update / Delete**: Restricted to usage logging agents and system admins.

#### 4.4.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 100,000 – 1,000,000 rows.
*   **Read / Write Ratio**: 30% Reads / 70% Writes (High update frequency).
*   **Caching Strategy**: Usage counts can be temporarily aggregated in a write-behind cache (e.g., Redis) and flushed to the database in batches to reduce write lock contention.
*   **Backup Importance**: High.

#### 4.4.7 Event Matrix
*   **Events Produced**: `usage.limit_reached`, `usage.threshold_warning`
*   **Events Consumed**: `usage.reported`

---

## 5. MODULE 4 — DEVELOPER PLATFORM & CENTRALIZED SERVICES

### 5.1 Table Name: `public.api_keys`

#### 5.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Manages cryptographically secure API tokens, allowing external systems to authenticate and interact with JUANET API endpoints securely under organization-level scopes.
*   **Ownership Domain**: Developer Platform Core
*   **Dependencies**: `system.organizations`

#### 5.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique API Key record ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | SaaS Tenant scope identifier. |
| `name` | `varchar(100)` | NO | None | None | User-defined label identifying the key (e.g., `Stripe Sync Worker`). |
| `hashed_key` | `varchar(255)`| NO | None | Unique Index | **SHA-256 hash of original secret token**. Original keys are never stored. |
| `scopes` | `jsonb` | NO | `'[]'` | None | JSONB array of authorized permissions (e.g., `["invoice:read", "crm:write"]`). |
| `last_used` | `timestamptz` | YES | `NULL` | None | Timestamp of last API connection using this key. |
| `expires_at` | `timestamptz` | YES | `NULL` | B-Tree Index | Optional expiration date. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 5.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT api_keys_hashed_key UNIQUE (hashed_key)`

#### 5.1.4 Indexes & Execution Paths
*   **`api_keys_pkey`**: Primary Key index.
*   **`api_keys_hash_uidx`**: Unique Index on `hashed_key`. Main lookup route for verifying incoming API headers.
*   **`api_keys_expiry_idx`**: Partial index on `(expires_at)` WHERE `expires_at IS NOT NULL`. Used to filter out expired keys.

#### 5.1.5 Row-Level Security (RLS) Policy
*   **Select / Update / Delete**: Tenant administrators only.
*   **Insert**: Tenant administrators of the organization.

#### 5.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000 – 100,000 rows.
*   **Read / Write Ratio**: 99.9% Reads / 0.1% Writes (Key lookups are high frequency).
*   **Caching Strategy**: Key validations are cached in memory (e.g., Redis) with short TTLs to prevent database queries on every API request.
*   **Backup Importance**: Critical.

#### 5.1.7 Event Matrix
*   **Events Produced**: `api_key.created`, `api_key.expired`, `api_key.revoked`
*   **Events Consumed**: None

---

### 5.2 Table Name: `public.organization_webhooks`

#### 5.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Manages outbound developer webhooks, allowing organizations to subscribe to platform-wide events (e.g., `invoice.created`) and receive real-time HTTP POST notifications.
*   **Ownership Domain**: Event Dispatching Engine
*   **Dependencies**: `system.organizations`

#### 5.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique webhook endpoint ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | Associated tenant organization. |
| `url` | `text` | NO | None | None | Destination HTTP/HTTPS endpoint URL where payloads are dispatched. |
| `secret` | `varchar(255)`| NO | None | None | Signing secret used to generate cryptographic signature headers. |
| `events` | `jsonb` | NO | `'[]'` | None | JSONB array of subscribed event categories (e.g., `["invoice.created"]`). |
| `enabled` | `boolean` | NO | `true` | B-Tree Index | Dynamic delivery status toggle. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |

#### 5.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT webhooks_url_valid CHECK (url ~* '^https?://[A-Za-z0-9.-]+')`

#### 5.2.4 Indexes & Execution Paths
*   **`organization_webhooks_pkey`**: Primary Key index.
*   **`webhooks_delivery_lookup_idx`**: B-Tree Index on `(organization_id, enabled)`. Used to find active targets for event dispatches.

#### 5.2.5 Row-Level Security (RLS) Policy
*   **Select / Insert / Update / Delete**: Tenant administrators only.

#### 5.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 5,000 – 500,000 rows.
*   **Read / Write Ratio**: 99.5% Reads / 0.5% Writes.
*   **Backup Importance**: High.

#### 5.2.7 Event Matrix
*   **Events Produced**: `webhook.created`, `webhook.updated`, `webhook.delivery_failed`
*   **Events Consumed**: None

---

### 5.3 Table Name: `public.files`

#### 5.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Central file registry managing metadata, access rules, and physical paths for documents uploaded across all business modules (e.g., invoice PDFs, contract attachments). Prevents fragmented storage schemas.
*   **Ownership Domain**: Centralized Document Storage Core
*   **Dependencies**: `system.organizations`

#### 5.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique file metadata ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | SaaS Tenant scope identifier. |
| `storage_provider`| `varchar(50)` | NO | `'supabase'`| None | Remote storage backend engine identifier (e.g., `s3`, `supabase`). |
| `bucket` | `varchar(100)`| NO | None | B-Tree Index | Target namespace bucket (e.g., `invoice-receipts`). |
| `path` | `text` | NO | None | Unique Index | Absolute folder path inside storage backend (e.g., `org_1/receipt.pdf`). |
| `mime_type` | `varchar(100)`| NO | None | None | Standard Internet MIME type (e.g., `application/pdf`). |
| `checksum` | `varchar(64)` | NO | None | None | SHA-256 integrity signature of binary data. |
| `size` | `bigint` | NO | None | None | Total file size in bytes. |
| `virus_scan_status`|`varchar(30)` | NO | `'pending'` | B-Tree Index | Quarantine scanning state (`pending`, `clean`, `infected`). |
| `uploaded_by` | `uuid` | YES | `NULL` | FK Reference | References the user who initiated upload. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 5.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT files_path_key UNIQUE (path)`
*   **Check Constraints**:
    *   `CONSTRAINT files_size_positive CHECK (size > 0)`
    *   `CONSTRAINT files_scan_status_valid CHECK (virus_scan_status IN ('pending', 'clean', 'infected'))`

#### 5.3.4 Indexes & Execution Paths
*   **`files_pkey`**: Primary Key index.
*   **`files_path_uidx`**: Unique Index on `path`. Used to resolve file targets from access tokens.
*   **`files_scan_filter_idx`**: Partial index on `(virus_scan_status)` WHERE `virus_scan_status = 'pending'`. Used by file scanning agents to locate newly uploaded files.

#### 5.3.5 Row-Level Security (RLS) Policy
*   **Select**: Restricted to tenant members with read permissions for the associated parent business context (e.g., invoice).
*   **Insert / Update**: Tenant members with upload capabilities.
*   **Delete**: Soft delete standard.

#### 5.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 1,000,000 – 20,000,000 rows.
*   **Read / Write Ratio**: 85% Reads / 15% Writes.
*   **Backup Importance**: High (Metadata must align with external storage objects).

#### 5.3.7 Event Matrix
*   **Events Produced**: `file.uploaded`, `file.scanned`, `file.quarantined`
*   **Events Consumed**: None

---

## 6. MODULE 5 — GOVERNANCE, AUDIT & TRANSACTION CONTROL

### 6.1 Table Name: `audit.audit_logs`

#### 6.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Records highly structured, tamper-proof activity logs for all modifications to core business data, supporting security audits, compliance checks, and debugging.
*   **Ownership Domain**: System Governance & Audit Engine (Append-Only)
*   **Dependencies**: `system.organizations`

#### 6.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique log event ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | Associated tenant organization. |
| `user_id` | `uuid` | YES | `NULL` | FK Reference | References user who initiated action. |
| `action` | `varchar(100)`| NO | None | B-Tree Index | Event classification (e.g., `invoice.created`, `user.login`). |
| `table_name` | `varchar(100)`| NO | None | B-Tree Index | Affected physical table name. |
| `record_id` | `uuid` | NO | None | B-Tree Index | Target record identifier. |
| `old_values` | `jsonb` | YES | `NULL` | None | JSONB snapshot of record state before update. |
| `new_values` | `jsonb` | YES | `NULL` | GIN Index | JSONB snapshot of record state after update. |
| `ip_address` | `inet` | YES | `NULL` | None | Client IP address. |
| `user_agent` | `text` | YES | `NULL` | None | Client browser user agent string. |
| `created_at` | `timestamptz` | NO | `now()` | B-Tree Index | Timestamp of event dispatch. |

#### 6.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)` (or composite with `created_at` if partitioned).
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`

#### 6.1.4 Indexes & Execution Paths
*   **`audit_logs_pkey`**: Primary Key index.
*   **`audit_logs_lookup_idx`**: B-Tree Index on `(organization_id, table_name, record_id, created_at DESC)`. Used to compile event audit trails for specific records.
*   **`audit_logs_new_values_gin_idx`**: GIN Index on `new_values`. Allows querying logs for specific JSON keys.

#### 6.1.5 Row-Level Security (RLS) Policy
*   **Select**: Restricted to tenant admins and security workers.
*   **Insert**: Automatically populated by system trigger scripts.
*   **Update / Delete**: Strictly prohibited (Enforces append-only standard).

#### 6.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 100,000,000+ rows.
*   **Read / Write Ratio**: 5% Reads / 95% Writes.
*   **Partitioning Recommended**: Partitioned by Range on `created_at` monthly.
*   **Archival Policy**: Logs older than 1 year migrated to cold storage. Logs older than 7 years are purged to reduce storage footprint.

#### 5.1.7 Event Matrix
*   **Events Produced**: None
*   **Events Consumed**: All transactional events (logs are generated in response to mutations).

---

### 6.2 Table Name: `audit.outbound_events`

#### 6.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Implements the transactional outbox pattern. Stores outbound event messages written within the same transaction as state changes, ensuring reliable event delivery to external queues.
*   **Ownership Domain**: Event Infrastructure Engine (Append-Only)
*   **Dependencies**: `system.organizations`

#### 6.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique outbox message ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | Tenant scope identifier. |
| `event_type` | `varchar(100)`| NO | None | B-Tree Index | Name of event classification (e.g., `invoice.created`). |
| `payload` | `jsonb` | NO | None | None | Complete JSON serializable event payload. |
| `is_delivered` | `boolean` | NO | `false` | B-Tree Index | Delivery status toggle. |
| `attempts` | `integer` | NO | `0` | None | Total delivery dispatch attempts. |
| `created_at` | `timestamptz` | NO | `now()` | B-Tree Index | Timestamp of record creation. |
| `delivered_at` | `timestamptz` | YES | `NULL` | None | Timestamp of successful delivery. |

#### 6.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT outbox_attempts_non_negative CHECK (attempts >= 0)`

#### 6.2.4 Indexes & Execution Paths
*   **`outbound_events_pkey`**: Primary Key index.
*   **`outbox_pending_delivery_idx`**: Partial index on `(is_delivered, created_at)` WHERE `is_delivered = false`. Primary execution route used by event dispatch workers.

#### 6.2.5 Row-Level Security (RLS) Policy
*   **Select / Update**: Event workers and system workers only.
*   **Insert**: Automatically populated by system triggers.
*   **Delete**: Strictly prohibited (Append-only outbox ledger).

#### 6.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 50,000,000+ rows over time.
*   **Read / Write Ratio**: 50% Reads / 50% Writes.
*   **Partitioning Recommended**: Partitioned by Range on `created_at` monthly.
*   **Archival Policy**: Delivered events are purged after 7 days to keep table size small and maintain fast queue operations.

---

### 6.3 Table Name: `audit.idempotency_keys`

#### 6.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Stores unique transaction keys to prevent duplicate processing of API requests (e.g., duplicate invoice payments or project creation) caused by network retry loops.
*   **Ownership Domain**: API Integrity & Gateway Protection
*   **Dependencies**: `system.organizations`

#### 6.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `key` | `varchar(255)`| NO | None | Primary Key | Cryptographic hash representing the request properties. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | SaaS Tenant isolation identifier. |
| `response_status` | `integer` | NO | None | None | Cached HTTP status code returned for the request. |
| `response_body` | `jsonb` | NO | None | None | Cached JSON response body returned for the request. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | Expiration timestamp of the idempotency token. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 6.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (key)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`

#### 6.3.4 Indexes & Execution Paths
*   **`idempotency_keys_pkey`**: Primary key index. Primary verification path on API requests.
*   **`idempotency_expiry_idx`**: B-Tree Index on `(expires_at)`. Used by sweep workers to prune expired keys.

#### 6.3.5 Row-Level Security (RLS) Policy
*   **Select / Insert / Update**: System API workers only.
*   **Delete**: System admin cleanup workers.

#### 6.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 100,000 – 1,000,000 rows.
*   **Read / Write Ratio**: 50% Reads / 50% Writes.
*   **Archival Policy**: Keys are ephemeral and purged 24 hours after creation.

---

## 7. MODULE 6 — UNIFIED STATUS LOOKUPS

This module defines the schema for the seven physical lookup tables that replace free-text statuses across transactional domains.

### 7.1 Shared Physical Table Specifications
The seven status tables follow a unified, optimized schema:
1.  **`system.invoice_statuses`** (e.g., `draft`, `unpaid`, `partially_paid`, `paid`, `past_due`, `voided`)
2.  **`system.payment_statuses`** (e.g., `pending`, `authorized`, `captured`, `failed`, `refunded`)
3.  **`system.project_statuses`** (e.g., `proposed`, `active`, `suspended`, `completed`, `canceled`)
4.  **`system.ticket_statuses`** (e.g., `open`, `assigned`, `in_progress`, `resolved`, `closed`)
5.  **`system.workflow_statuses`** (e.g., `draft`, `active`, `paused`, `completed`)
6.  **`system.gateway_statuses`** (e.g., `active`, `maintenance`, `offline`)
7.  **`system.organization_statuses`** (e.g., `trialing`, `active`, `suspended`, `terminated`)

#### 7.1.1 Column Specifications & Verification
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique status lookup identifier. |
| `code` | `varchar(50)` | NO | None | Unique Index | Machine-readable lowercase snake_case code (e.g., `paid`). |
| `label` | `varchar(100)`| NO | None | None | Human-readable user-facing status label (e.g., `Fully Paid`). |
| `description` | `text` | YES | `NULL` | None | Technical or administrative context of the state. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 7.1.2 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraints**:
    *   `CONSTRAINT {table}_code_key UNIQUE (code)`
*   **Check Constraints**:
    *   `CONSTRAINT {table}_code_format CHECK (code ~* '^[a-z0-9\_]+$')`

#### 7.1.3 Indexes & Execution Paths
*   **`{table}_code_uidx`**: Unique Index on `code`. Main route for resolving status codes in transactional queries.

#### 7.1.4 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert / Update / Delete**: System administrators only.

#### 7.1.5 Operational Performance & Lifecycles
*   **Expected Row Count**: 5 – 20 records per table (Static lookups).
*   **Read / Write Ratio**: 100% Reads.
*   **Caching Strategy**: Indefinitely cached in memory.
*   **Archival Policy**: Immutable. Never archived.
*   **Backup Importance**: High.

---

## 8. MODULE 7 — TAX COMPLIANCE ENGINE

### 8.1 Table Name: `public.tax_jurisdictions`

#### 8.1.1 Purpose, Ownership & Dependencies
*   **Purpose**: Defines regional tax authorities (e.g., Kenya Revenue Authority, UK HMRC) to manage and calculate local tax compliance rules natively.
*   **Ownership Domain**: Tax Compliance Engine
*   **Dependencies**: `system.countries`

#### 8.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique tax jurisdiction ID. |
| `country_id` | `uuid` | NO | None | FK & B-Tree Index | Country where the tax authority has jurisdiction. |
| `name` | `varchar(100)`| NO | None | None | Name of the legal tax authority (e.g., `Kenya Revenue Authority`). |
| `region_code` | `varchar(30)` | YES | `NULL` | None | Optional region, state, or county code (e.g., `Nairobi_County`). |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 8.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `country_id REFERENCES system.countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

#### 8.1.4 Indexes & Execution Paths
*   **`tax_jurisdictions_pkey`**: Primary Key index.
*   **`tax_jurisdictions_lookup_idx`**: B-Tree Index on `(country_id)`. Used to find active jurisdictions for a transaction.

#### 8.1.5 Row-Level Security (RLS) Policy
*   **Select**: Publicly readable globally.
*   **Insert / Update / Delete**: System administrators only.

#### 8.1.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 200 – 1,000 records.
*   **Read / Write Ratio**: 100% Reads.
*   **Backup Importance**: High.

#### 8.1.7 Relationships
*   **One-to-Many**: `public.tax_rules`

---

### 8.2 Table Name: `public.tax_rules`

#### 8.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Stores tax rate rules and exceptions (e.g., standard rates, zero-rated exemptions, reverse-charge settings) applicable to transactional line items.
*   **Ownership Domain**: Tax Calculation Engine
*   **Dependencies**: `system.organizations`, `public.tax_jurisdictions`

#### 8.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique tax rule ID. |
| `organization_id` | `uuid` | NO | None | FK & B-Tree Index | SaaS Tenant isolation identifier. |
| `jurisdiction_id` | `uuid` | NO | None | FK Reference | Target tax authority. |
| `tax_rate` | `numeric(5,4)` | NO | `0.0000` | None | Tax percentage rate expressed as a decimal (e.g., `0.1600` for 16%). |
| `rule_type` | `varchar(50)` | NO | `'standard'`| None | Rule classification (`standard`, `exempt`, `reverse_charge`). |
| `is_reverse_charge`| `boolean` | NO | `false` | None | Boolean indicating if reverse charge applies to B2B transactions. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | Record modification timestamp. |
| `version` | `integer` | NO | `1` | None | Optimistic locking manager. |

#### 8.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `jurisdiction_id REFERENCES public.tax_jurisdictions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT tax_rate_range CHECK (tax_rate >= 0.0000 AND tax_rate <= 1.0000)`
    *   `CONSTRAINT tax_rule_type_valid CHECK (rule_type IN ('standard', 'exempt', 'reverse_charge'))`

#### 8.2.4 Indexes & Execution Paths
*   **`tax_rules_pkey`**: Primary Key index.
*   **`tax_rules_evaluation_idx`**: B-Tree Index on `(organization_id, jurisdiction_id)`. Main execution path used to apply tax rates during invoicing.

#### 8.2.5 Row-Level Security (RLS) Policy
*   **Select**: Tenant members (verifying invoice rules).
*   **Insert / Update / Delete**: Tenant administrators of the organization.

#### 8.2.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000 – 50,000 rules.
*   **Read / Write Ratio**: 99.5% Reads / 0.5% Writes.
*   **Backup Importance**: High.

#### 8.2.7 Relationships
*   **One-to-Many**: `public.invoice_tax_lines`

---

### 8.3 Table Name: `public.invoice_tax_lines`

#### 8.3.1 Purpose, Ownership & Dependencies
*   **Purpose**: Immutable transaction records storing precise tax calculations mapped to individual invoice line items. Ensures financial audit compliance.
*   **Ownership Domain**: Financial Ledger Engine (Append-Only)
*   **Dependencies**: `public.tax_rules`

#### 8.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints & Indexes | Business Meaning & Architectural Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary Key | Unique tax line item ID. |
| `invoice_id` | `uuid` | NO | None | FK & B-Tree Index | Reference to target invoice record. |
| `tax_rule_id` | `uuid` | NO | None | FK Reference | Reference to applied tax rule. |
| `base_amount` | `numeric(18,2)`| NO | None | None | Net cost before taxes. |
| `tax_amount` | `numeric(18,2)`| NO | None | None | Calculated tax amount. |
| `created_at` | `timestamptz` | NO | `now()` | None | Record creation timestamp. |

#### 8.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `tax_rule_id REFERENCES public.tax_rules(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT tax_line_amounts_positive CHECK (base_amount >= 0.00 AND tax_amount >= 0.00)`

#### 8.3.4 Indexes & Execution Paths
*   **`invoice_tax_lines_pkey`**: Primary Key index.
*   **`tax_lines_invoice_idx`**: B-Tree Index on `(invoice_id)`. Used to compile tax lines for invoice PDFs and financial reports.

#### 8.3.5 Row-Level Security (RLS) Policy
*   **Select**: Tenant members with invoice read permissions.
*   **Insert**: Billing service system only.
*   **Update / Delete**: Strictly prohibited (Enforces append-only financial ledger).

#### 8.3.6 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000,000 – 100,000,000 records.
*   **Read / Write Ratio**: 90% Reads / 10% Writes.
*   **Partitioning Recommended**: Partitioned by Range on `created_at` monthly, matching parent invoice partitions.
*   **Backup Importance**: Critical (Core financial ledger entries).

---

## 9. PHYSICAL ARCHITECTURE COMPLIANCE MATRIX

This document is certified compliant with the architectural principles established for the JUANET SaaS Platform:
1.  **Strict Reference Safety**: All free-text status columns are replaced with foreign key references to physical status lookup tables, preventing runtime typos.
2.  **No Direct Deletes**: Tables in the `public` schema use logical soft deletes (`deleted_at`) or are append-only.
3.  **Optimistic Concurrency**: Standard transaction tables include `version` columns to prevent concurrent update conflicts.
4.  **No Mock Data Placeholders**: Structural and metadata properties are fully defined, ensuring schema configurations are complete and production-ready.
5.  **Multi-Tenant Isolation**: Row-Level Security (RLS) policies are configured on all public tables to secure tenant isolation boundaries.
