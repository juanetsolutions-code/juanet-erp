# JUANET PostgreSQL CRM Table Specifications
## Phase 2.3.2C — Customer Relationship Management (CRM) Physical Tables
**Document Version:** 1.0  
**Author:** Chief PostgreSQL Solutions Architect, JUANET Platform  
**Classification:** Technical / Database Schema Definition  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical table definitions for the Customer Relationship Management (CRM) domain of the JUANET Enterprise SaaS Platform. All schemas, parameters, and constraints defined herein are binding and must be implemented exactly by future migrations, DDL scripts, or ORM declarations.

These specifications conform strictly to:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`
*   `Phase_2_3_2A_Core_Physical_Tables.md`
*   `Phase_2_3_2B_Authentication_Physical_Tables.md`

All CRM tables reside within the `public` schema as standard multi-tenant business entities. They utilize standard database standards, including Row-Level Security (RLS) keying based on `organization_id` to enforce strict logical tenant isolation.

---

## 2. SUBDOMAIN 1 — LEAD MANAGEMENT

### 2.1 Table Name: `public.lead_sources`

#### 2.1.1 Table Overview
*   **Purpose**: Manages tenant-specific categories indicating how prospects were acquired (e.g., Google Ads, Organic, LinkedIn Outreach, Referral). Allows customization of channels for ROI tracking.
*   **Business Responsibility**: Lead Source Analytics & Marketing Attribution
*   **Ownership Domain**: Marketing & Customer Acquisition Core
*   **Dependencies**: `system.organizations`
*   **Expected Lifetime**: Persistent
*   **Read Frequency**: Extremely High (Loaded in UI selectors; cached)
*   **Write Frequency**: Very Low (Admin-level configuration edits)
*   **Estimated Row Growth**: Negligible (~10-50 records per tenant)
*   **Retention Policy**: Indefinite (Lookups are never purged unless deprecated, in which case soft deleted)

#### 2.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Primary key for lead source lookup. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `name` | `varchar(100)` | NO | None | None | None | Public | 1-100 chars, trimmed | User-facing display name of lead source. |
| `code` | `varchar(50)` | NO | None | Unique per Org | None | Public | Lowercase, snake_case | Machine-readable identifier for code integration. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Boolean | Controls whether the source appears in active selectors. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Standard record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Dynamic modification audit timestamp. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Optimistic locking sequence manager. |

#### 2.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT lead_sources_org_code_key UNIQUE (organization_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT lead_sources_code_format CHECK (code ~ '^[a-z0-9\_]+$')`
    *   `CONSTRAINT lead_sources_name_length CHECK (length(trim(name)) >= 1)`

#### 2.1.4 Index Strategy
*   **`lead_sources_pkey`**: Primary Key Index (B-Tree) on `id`.
*   **`lead_sources_org_code_uidx`**: Unique Index (B-Tree) on `(organization_id, code)`. Used for tenant-isolated lookups.
*   **`lead_sources_active_idx`**: Partial B-Tree Index on `(organization_id, id)` WHERE `is_active = true`. Optimizes UI dropdown rendering queries.

#### 2.1.5 Row-Level Security (RLS)
*   **Read**: Authorized tenant users.
*   **Insert / Update / Delete**: Tenant administrators.
*   **Service Account Bypass**: Enabled for system data loaders.

#### 2.1.6 Audit & Locking
*   **Auditing**: INSERT, UPDATE, and DELETE generate immutable entries in `audit.security_events`.
*   **Optimistic Locking**: Handled by the `version` column.

#### 2.1.7 Business Events
*   **Events Produced**: `lead_source.created`, `lead_source.updated`
*   **Events Consumed**: `organization.created` (seeds default lookup records)

#### 2.1.8 Relationships
*   **One-to-Many**: `public.leads`

---

### 2.2 Table Name: `public.lead_statuses`

#### 2.2.1 Table Overview
*   **Purpose**: Defines progression status levels for inbound inquiries (e.g., New, Contacted, Qualified, Nurturing). Includes flags for conversion triggers.
*   **Business Responsibility**: Lead Funnel Modeling & Conversion Tracking
*   **Ownership Domain**: Sales Process Configuration Core
*   **Dependencies**: `system.organizations`
*   **Expected Lifetime**: Persistent
*   **Read Frequency**: Extremely High
*   **Write Frequency**: Very Low
*   **Estimated Row Growth**: Negligible (~5-20 rows per tenant)
*   **Retention Policy**: Indefinite

#### 2.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Primary lookup identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `name` | `varchar(100)` | NO | None | None | None | Public | 1-100 chars, trimmed | Public stage display name. |
| `code` | `varchar(50)` | NO | None | Unique per Org | None | Public | Lowercase, snake_case | Machine-readable label for state transitions. |
| `color_hex` | `varchar(7)` | NO | `'#6B7280'`| None | None | Public | `#` followed by 6 hex chars | Hex code used for front-end pipeline cards. |
| `sort_order` | `integer` | NO | `10` | None | None | Public | Positive integer | Controls custom sorting in pipeline columns. |
| `is_converted_state`|`boolean` | NO | `false` | None | None | Public | Boolean | If true, marks that entering this status triggers customer conversion. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Standard record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Standard record update timestamp. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 2.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT lead_statuses_org_code_key UNIQUE (organization_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT lead_statuses_code_format CHECK (code ~ '^[a-z0-9\_]+$')`
    *   `CONSTRAINT lead_statuses_color_format CHECK (color_hex ~ '^#[0-9A-Fa-f]{6}$')`
    *   `CONSTRAINT lead_statuses_sort_positive CHECK (sort_order >= 0)`

#### 2.2.4 Index Strategy
*   **`lead_statuses_pkey`**: Primary Key index.
*   **`lead_statuses_org_order_idx`**: B-Tree Index on `(organization_id, sort_order)`. Optimizes pipeline list queries.

#### 2.2.5 Row-Level Security (RLS)
*   **Read**: Authorized tenant users.
*   **Insert / Update / Delete**: Tenant administrators.

#### 2.2.6 Audit & Locking
*   **Auditing**: INSERT, UPDATE, DELETE generate events.
*   **Optimistic Locking**: Tracked via `version`.

#### 2.2.7 Business Events
*   **Events Produced**: `lead_status.created`, `lead_status.updated`
*   **Events Consumed**: `organization.created` (seeds default lookup statuses)

#### 2.2.8 Relationships
*   **One-to-Many**: `public.leads`

---

### 2.3 Table Name: `public.leads`

#### 2.3.1 Table Overview
*   **Purpose**: Central data storage for prospective customer profiles before they are fully validated or qualified into active client accounts.
*   **Business Responsibility**: Prospect Data Custody, Pipeline Ingestion, and AI Lead Scoring
*   **Ownership Domain**: CRM Core
*   **Dependencies**: `system.organizations`, `public.lead_sources`, `public.lead_statuses`, `security.users`
*   **Expected Lifetime**: Long-term active; archived upon conversion or after 1 year of inactivity
*   **Read Frequency**: Extremely High (Search, dashboards, listing)
*   **Write Frequency**: High (Web-to-lead forms, email interactions, user updates)
*   **Estimated Row Growth**: High (100,000 - 1,000,000+ per month for high-volume consumer/SaaS clients)
*   **Retention Policy**: Standard retention of unconverted leads is 3 years, converted leads retained indefinitely for history. Supports GDPR "Right to be Forgotten" deletions.

#### 2.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Primary record key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `first_name` | `varchar(100)` | YES | `NULL` | None | None | PII | Maximum 100 chars | Lead given name. |
| `last_name` | `varchar(100)` | YES | `NULL` | None | None | PII | Maximum 100 chars | Lead family name. |
| `company_name` | `varchar(200)` | YES | `NULL` | None | None | Public | Maximum 200 chars | Targeted business firm. |
| `email` | `citext` | YES | `NULL` | None | None | PII | Valid email address | Primary communication endpoint. |
| `phone` | `varchar(30)` | YES | `NULL` | None | None | PII | E.164 phone format | Telephone contact. |
| `website` | `text` | YES | `NULL` | None | None | Public | Valid URL format | Prospect web domain. |
| `lead_source_id` | `uuid` | YES | `NULL` | FK -> `public.lead_sources(id)` | None | Public | Valid reference | Identifies source channel. |
| `lead_status_id` | `uuid` | NO | None | FK -> `public.lead_statuses(id)` | None | Public | Valid reference | Current stage index. |
| `assigned_user_id` | `uuid` | YES | `NULL` | FK -> `security.users(id)` | None | Public | Valid reference | Assigned sales representative. |
| `value` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Value >= 0.00 | Estimated deal/opportunity value. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)` | None | Public | Valid reference | Matches currency denomination. |
| `search_vector` | `tsvector` | YES | `NULL` | None | None | Public | None | Managed tsvector column for fast full-text searching. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Generation time tracking. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Dynamic update timestamp. |
| `deleted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Logical soft delete. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 2.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `lead_source_id REFERENCES public.lead_sources(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `lead_status_id REFERENCES public.lead_statuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `assigned_user_id REFERENCES security.users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT leads_email_check CHECK (email IS NULL OR email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')`
    *   `CONSTRAINT leads_value_positive CHECK (value >= 0.00)`
    *   `CONSTRAINT leads_phone_format CHECK (phone IS NULL OR phone ~ '^\+[1-9]\d{1,14}$')`

#### 2.3.4 Index Strategy
*   **`leads_pkey`**: Primary Key index.
*   **`leads_tenant_status_idx`**: B-Tree Index on `(organization_id, lead_status_id, deleted_at)` WHERE `deleted_at IS NULL`. Optimizes active pipeline grid queries.
*   **`leads_email_idx`**: B-Tree Index on `(organization_id, email)`. Used for fast contact lookups and deduplication.
*   **`leads_assigned_user_idx`**: B-Tree Index on `(assigned_user_id)` WHERE `deleted_at IS NULL`. Optimizes individual sales agent queues.
*   **`leads_fts_idx`**: GIN Index on `search_vector`. Used for ultra-fast full-text search.

#### 2.3.5 Full-Text Search Strategy
To allow rapid searching across first name, last name, email, and company name without table scanning:
*   **Generated Vector**: `search_vector` is computed using a trigger or generated column:
    `to_tsvector('english', coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(company_name, '') || ' ' || coalesce(email::text, ''))`
*   **Search**: Supports fuzzy prefix matching via trigrams on raw text elements for search bars using `pg_trgm`.

#### 2.3.6 Row-Level Security (RLS)
*   **Read / Update**: Authorized tenant users (optionally constrained so sales reps can only see self-assigned leads, while sales managers see all).
*   **Insert**: Public (supports Web-to-Lead API) with strict volume rate limits.
*   **Delete**: Admin users only (soft delete threshold; physical purges via background workers).

#### 2.3.7 Events & Auditing
*   **Events Produced**: `lead.created`, `lead.updated`, `lead.converted`, `lead.deleted`
*   **Audit**: High PII sensitivity changes (such as email or phone modification) emit structured records to `audit.security_events`.

#### 2.3.8 Relationships
*   **One-to-Many**: `public.lead_activities`
*   **Many-to-Many**: Polymorphic assignments via `public.tag_assignments`

---

### 2.4 Table Name: `public.lead_activities`

#### 2.4.1 Table Overview
*   **Purpose**: Stores a chronological log of all communications, touchpoints, tasks, and notes relating to a lead.
*   **Business Responsibility**: Activity Logging and Audit Trail
*   **Ownership Domain**: CRM Activity Core (Append-Only)
*   **Dependencies**: `system.organizations`, `public.leads`, `security.users`
*   **Expected Lifetime**: Persistent; archived along with the parent lead
*   **Read / Write Frequency**: 40% Reads / 60% Writes (Heavily updated by automatic email syncs, dialer integrations, and rep logging)
*   **Estimated Row Growth**: Extremely High ($O(N)$ multiplier of active leads; millions of rows expected quickly)
*   **Retention Policy**: 5 years, then moved to secondary warehouse partitions

#### 2.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique activity record. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `lead_id` | `uuid` | NO | None | FK -> `public.leads(id)` | None | Public | Valid UUIDv4 | Target lead association. |
| `user_id` | `uuid` | NO | None | FK -> `security.users(id)` | None | Public | Valid UUIDv4 | Operating agent who created/logged the activity. |
| `activity_type` | `varchar(50)` | NO | None | None | None | Public | Valid enum tokens | E.g., `call`, `email`, `meeting`, `internal_note`. |
| `subject` | `varchar(255)` | NO | None | None | None | Internal | 1-255 characters | Short summary line. |
| `notes` | `text` | YES | `NULL` | None | None | Internal | Plain text or markdown | Detailed logs, transcripts, or summaries. |
| `occurred_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Actual occurrence date of communication. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record database write timestamp. |

#### 2.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `lead_id REFERENCES public.leads(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT lead_activities_type CHECK (activity_type IN ('call', 'email', 'meeting', 'internal_note', 'task_completion'))`

#### 2.4.4 Index Strategy
*   **`lead_activities_pkey`**: Primary Key index.
*   **`lead_activities_lead_occurred_idx`**: B-Tree Index on `(lead_id, occurred_at DESC)`. Used to render lead activity streams instantly in reverse chronological order.

#### 2.4.5 Operational performance & Lifecycles
*   **Partitioning**: Ideal candidate for partitioning by range on `occurred_at` (monthly partitions) due to massive, append-only volume.
*   **Backup Importance**: High (Contains historical touchpoints for compliance).

#### 2.4.6 Event Matrix
*   **Events Produced**: `lead_activity.created`
*   **Events Consumed**: None

---

## 3. SUBDOMAIN 2 — CONTACT & CLIENT MANAGEMENT

### 3.1 Table Name: `public.contacts`

#### 3.1.1 Table Overview
*   **Purpose**: Serves as the master registry of individual business professionals. Contacts may exist as warm targets, individual consumer clients, or personnel mapped to corporate client accounts.
*   **Business Responsibility**: Personal CRM Record Keeping & B2B/B2C Contacts Directory
*   **Ownership Domain**: Client Directory Core
*   **Expected Lifetime**: Persistent (Core customer asset)
*   **Read / Write Frequency**: 80% Reads / 20% Writes
*   **Estimated Row Growth**: Moderate to High (Linear based on client acquisition)
*   **Retention Policy**: Indefinite. Rigorous GDPR "Right to Erasure" controls must be supported via targeted column blanking or physical deletion.

#### 3.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `first_name` | `varchar(100)` | NO | None | None | None | PII | 1-100 characters | Contact's given name. |
| `last_name` | `varchar(100)` | NO | None | None | None | PII | 1-100 characters | Contact's family name. |
| `email` | `citext` | NO | None | None | None | PII | Valid email format | Primary digital communication target. |
| `phone` | `varchar(30)` | YES | `NULL` | None | None | PII | E.164 phone format | Mobile or landline contact number. |
| `job_title` | `varchar(100)` | YES | `NULL` | None | None | Public | Max 100 characters | Corporate role. |
| `search_vector` | `tsvector` | YES | `NULL` | None | None | Public | None | Managed full-text index vector. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record birth. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Latest modification tracking. |
| `deleted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Soft delete boundary. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 3.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT contacts_tenant_email_key UNIQUE (organization_id, email)` (Ensures email is unique inside a single tenant space)
*   **Check Constraints**:
    *   `CONSTRAINT contacts_email_format CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')`
    *   `CONSTRAINT contacts_phone_format CHECK (phone IS NULL OR phone ~ '^\+[1-9]\d{1,14}$')`

#### 3.1.4 Index Strategy
*   **`contacts_pkey`**: Primary Key index.
*   **`contacts_tenant_email_idx`**: Unique B-Tree Index on `(organization_id, email)` WHERE `deleted_at IS NULL`. Primary route for API validations.
*   **`contacts_search_vector_idx`**: GIN Index on `search_vector` for FTS.

#### 3.1.5 Full-Text Search Strategy
*   **Vector Composition**: `search_vector` generated as:
    `to_tsvector('english', coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(email::text, ''))`

#### 3.1.6 Row-Level Security (RLS)
*   **Read / Insert / Update**: Authorized tenant users.
*   **Delete**: Restricted to tenant administrators.

#### 3.1.7 Relationships
*   **One-to-Many**: `public.contact_addresses`
*   **Many-to-Many**: `public.client_accounts` via `public.client_contacts`

---

### 3.2 Table Name: `public.contact_addresses`

#### 3.2.1 Table Overview
*   **Purpose**: Manages multi-location physical or mailing address registers for contacts.
*   **Business Responsibility**: Physical Mail Logistics, Billing Validation, Geocoding
*   **Ownership Domain**: Location Directory Core
*   **Dependencies**: `system.organizations`, `public.contacts`, `system.countries`

#### 3.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique address entry ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `contact_id` | `uuid` | NO | None | FK -> `public.contacts(id)` | None | Public | Valid UUIDv4 | Links back to the contact profile. |
| `address_type` | `varchar(30)` | NO | `'billing'` | None | None | Public | `billing`, `shipping`, `other` | Categorizes address role. |
| `street_address` | `varchar(255)` | NO | None | None | None | PII | Non-empty | Physical street location. |
| `city` | `varchar(100)` | NO | None | None | None | PII | Non-empty | City name. |
| `state_province` | `varchar(100)` | YES | `NULL` | None | None | Public | Non-empty | State or administrative region. |
| `postal_code` | `varchar(20)` | YES | `NULL` | None | None | Public | Alphanumeric postcodes | ZIP / Postal code identifier. |
| `country_id` | `uuid` | NO | None | FK -> `system.countries(id)` | None | Public | Valid UUIDv4 | Geographical country reference. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Creation time. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Modification time. |

#### 3.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `contact_id REFERENCES public.contacts(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `country_id REFERENCES system.countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT address_type_valid CHECK (address_type IN ('billing', 'shipping', 'other'))`

#### 3.2.4 Index Strategy
*   **`contact_addresses_pkey`**: Primary Key index.
*   **`contact_addresses_contact_idx`**: B-Tree Index on `(contact_id)`. Essential for pulling all addresses of a single contact.

---

### 3.3 Table Name: `public.client_accounts`

#### 3.3.1 Table Overview
*   **Purpose**: Tracks corporate, institutional, or enterprise clients who purchase SaaS products, buy custom packages, or interact under localized billing agreements.
*   **Business Responsibility**: Client Portfolio Custody & Financial Credit Management
*   **Ownership Domain**: Client Directory Core
*   **Dependencies**: `system.organizations`, `public.companies`, `system.currencies`
*   **Expected Lifetime**: Long-Term (Spans entire commercial interaction life)
*   **Read / Write Frequency**: 85% Reads / 15% Writes
*   **Estimated Row Growth**: Moderate (Grows linearly based on target corporate client signups)
*   **Retention Policy**: Indefinite (Financial records require persistent linking to these accounts)

#### 3.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique client account key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `company_id` | `uuid` | YES | `NULL` | FK -> `public.companies(id)` | None | Public | Valid UUIDv4 | Associated corporate profile (optional). |
| `account_name` | `varchar(200)` | NO | None | None | None | Public | 2-200 characters | Primary business name of account. |
| `account_code` | `varchar(50)` | NO | None | Unique per Org | None | Public | Lowercase, alphanumeric | Human-friendly customer code (e.g., `ACC-GLOBEX`). |
| `status` | `varchar(30)` | NO | `'active'` | None | None | Public | `active`, `suspended`, `inactive` | Current state of trading account. |
| `billing_contact_id`|`uuid` | YES | `NULL` | FK -> `public.contacts(id)` | None | Public | Valid UUIDv4 | Links to primary recipient for billing. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)` | None | Public | Valid UUIDv4 | Master billing currency of client account. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record initialization date. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Dynamic modification audit timestamp. |
| `deleted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Soft delete threshold. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 3.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `company_id REFERENCES public.companies(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `billing_contact_id REFERENCES public.contacts(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT client_accounts_org_code_key UNIQUE (organization_id, account_code)`
*   **Check Constraints**:
    *   `CONSTRAINT client_account_status_valid CHECK (status IN ('active', 'suspended', 'inactive'))`

#### 3.3.4 Index Strategy
*   **`client_accounts_pkey`**: Primary Key index.
*   **`client_accounts_org_code_uidx`**: Unique Index (B-Tree) on `(organization_id, account_code)` WHERE `deleted_at IS NULL`.
*   **`client_accounts_company_idx`**: B-Tree Index on `(company_id)`. Resolves client records mapped to specific businesses.

#### 3.3.5 Event Matrix
*   **Events Produced**: `client.created`, `client.status_changed`, `client.deleted`
*   **Events Consumed**: None

#### 3.3.6 Relationships
*   **One-to-Many**: `public.proposals`, `public.opportunities`
*   **Many-to-Many**: `public.contacts` via `public.client_contacts`

---

### 3.4 Table Name: `public.client_contacts`

#### 3.4.1 Table Overview
*   **Purpose**: Junction table implementing the B2B relationships between client accounts and their authorized contact persons.
*   **Business Responsibility**: Corporate Relationship Tree Definition
*   **Ownership Domain**: Client Directory Core (Junction Table)
*   **Dependencies**: `public.client_accounts`, `public.contacts`

#### 3.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `client_account_id`|`uuid` | NO | None | FK -> `public.client_accounts(id)` | None | Public | Valid UUIDv4 | Matches parent corporate account. |
| `contact_id` | `uuid` | NO | None | FK -> `public.contacts(id)` | None | Public | Valid UUIDv4 | Matches associated professional contact. |
| `relationship_role`|`varchar(100)` | NO | `'Primary'` | None | None | Public | Max 100 characters | E.g., `Billing Contact`, `Decision Maker`, `Technical Lead`. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record creation timestamp. |

#### 3.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (client_account_id, contact_id)`
*   **Foreign Keys**:
    *   `client_account_id REFERENCES public.client_accounts(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `contact_id REFERENCES public.contacts(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

#### 3.4.4 Index Strategy
*   **`client_contacts_pkey`**: Composite Primary Key index.
*   **`client_contacts_contact_idx`**: B-Tree Index on `(contact_id)`. Crucial for reverse-indexing (verifying which client accounts a person represents).

---

### 3.5 Table Name: `public.companies`

#### 3.5.1 Table Overview
*   **Purpose**: Represents corporate structures and firmographic entities, allowing structured organization profile management within the SaaS workspace.
*   **Business Responsibility**: B2B Firmographic Custody & Enterprise Profiling
*   **Ownership Domain**: Client Directory Core
*   **Dependencies**: `system.organizations`

#### 3.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant separator. |
| `name` | `varchar(200)` | NO | None | None | None | Public | 2-200 characters | Legal corporate business name. |
| `registration_number`|`varchar(100)`| YES | `NULL` | None | None | Public | Alphanumeric business IDs | Commercial registrar identifier (e.g., KRA PIN). |
| `website` | `varchar(255)` | YES | `NULL` | None | None | Public | Valid URL string | Corporate home URL. |
| `industry` | `varchar(100)` | YES | `NULL` | None | None | Public | Max 100 characters | Vertical segment category. |
| `search_vector` | `tsvector` | YES | `NULL` | None | None | Public | None | Managed tsvector column. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Initial log. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Modification. |
| `deleted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Soft delete threshold. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Optimistic locking manager. |

#### 3.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT companies_registration_org_key UNIQUE (organization_id, registration_number)`
*   **Check Constraints**:
    *   `CONSTRAINT companies_website_format CHECK (website IS NULL OR website ~* '^https?://[A-Za-z0-9.-]+')`

#### 3.5.4 Index Strategy
*   **`companies_pkey`**: Primary Key index.
*   **`companies_org_reg_idx`**: B-Tree Index on `(organization_id, registration_number)` WHERE `deleted_at IS NULL`.
*   **`companies_fts_idx`**: GIN Index on `search_vector`.

#### 3.5.5 Full-Text Search Strategy
*   **Generated Vector**: `search_vector` generated as:
    `to_tsvector('english', coalesce(name, '') || ' ' || coalesce(registration_number, '') || ' ' || coalesce(website, ''))`

---

### 3.6 Table Name: `public.company_addresses`

#### 3.6.1 Table Overview
*   **Purpose**: Holds physical and registered addresses for corporate profiles.
*   **Business Responsibility**: Physical Corporate Location Management
*   **Ownership Domain**: Location Directory Core
*   **Dependencies**: `system.organizations`, `public.companies`, `system.countries`

#### 3.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique address entry ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `company_id` | `uuid` | NO | None | FK -> `public.companies(id)` | None | Public | Valid UUIDv4 | Links back to corporate profile. |
| `address_type` | `varchar(30)` | NO | `'registered'` | None | None | Public | `registered`, `billing`, `shipping` | Addresses categorizing role. |
| `street_address` | `varchar(255)` | NO | None | None | None | Public | Non-empty | Building, road, suite info. |
| `city` | `varchar(100)` | NO | None | None | None | Public | Non-empty | Municipality name. |
| `state_province` | `varchar(100)` | YES | `NULL` | None | None | Public | Non-empty | State or administrative region. |
| `postal_code` | `varchar(20)` | YES | `NULL` | None | None | Public | Alphanumeric postcodes | Postal block mapping. |
| `country_id` | `uuid` | NO | None | FK -> `system.countries(id)` | None | Public | Valid UUIDv4 | International reference. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Database capture date. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | System change date. |

#### 3.6.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `company_id REFERENCES public.companies(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `country_id REFERENCES system.countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT company_address_type_valid CHECK (address_type IN ('registered', 'billing', 'shipping'))`

#### 3.6.4 Index Strategy
*   **`company_addresses_pkey`**: Primary Key index.
*   **`company_addresses_lookup_idx`**: B-Tree Index on `(company_id)`. Resolves addresses linked to specific businesses.

---

## 4. SUBDOMAIN 3 — PROPOSALS & CONTRACT QUOTING

```
┌─────────────────────────┐
│     public.proposals    ├──────────────┐
└───────────┬─────────────┘              │
            │ 1:N                        │ 1:N
┌───────────▼─────────────┐    ┌─────────▼─────────────────┐
│  public.proposal_items  │    │  public.proposal_versions │
└─────────────────────────┘    └───────────────────────────┘
```

### 4.1 Table Name: `public.proposals`

#### 4.1.1 Table Overview
*   **Purpose**: Stores sales quotations, detailed proposals, and master service agreements issued to clients. Represents legal financial promises.
*   **Business Responsibility**: Revenue Quoting, Bid Tracking & Commercial Approvals
*   **Ownership Domain**: Sales Estimations Core
*   **Dependencies**: `system.organizations`, `public.client_accounts`, `system.currencies`
*   **Expected Lifetime**: Persistent
*   **Read / Write Frequency**: 70% Reads / 30% Writes
*   **Estimated Row Growth**: Moderate (Corresponds to quoting frequency)
*   **Retention Policy**: 7 years for regulatory tax/audits; never deleted if accepted.

#### 4.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant isolation boundary. |
| `client_account_id`|`uuid` | NO | None | FK -> `public.client_accounts(id)`| None | Public | Valid UUIDv4 | Target prospective buyer account. |
| `title` | `varchar(255)` | NO | None | None | None | Public | 3-255 characters | Name of bidding project. |
| `proposal_number` | `varchar(50)` | NO | None | Unique per Org | None | Public | Alphanumeric pattern | User-facing sequential code (e.g., `PRP-2026-0034`). |
| `status` | `varchar(30)` | NO | `'draft'` | None | None | Public | Valid status tokens | Pipeline step (`draft`, `under_review`, `sent`, `accepted`, `declined`, `expired`). |
| `total_amount` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Positive numeric | Aggregated line items sum. |
| `discount_percentage`|`numeric(5,2)`| NO | `0.00` | None | None | Public | `0.00` to `100.00` | Global deduction parameter. |
| `tax_amount` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Positive numeric | Calculated sum of taxes. |
| `net_amount` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Positive numeric | Total payable amount (`total_amount - discount + tax`). |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)` | None | Public | Valid UUIDv4 | Denominated currency index. |
| `valid_until` | `timestamptz` | NO | None | None | None | Public | Date > created_at | Legal bid expiration boundary. |
| `sent_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Date sent to client. |
| `accepted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Date accepted (legal close). |
| `declined_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Date declined. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Initial database logging. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Update audit trigger tracking. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 4.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `client_account_id REFERENCES public.client_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT proposals_org_number_key UNIQUE (organization_id, proposal_number)`
*   **Check Constraints**:
    *   `CONSTRAINT proposal_status_valid CHECK (status IN ('draft', 'under_review', 'sent', 'accepted', 'declined', 'expired'))`
    *   `CONSTRAINT proposal_totals_positive CHECK (total_amount >= 0.00 AND tax_amount >= 0.00 AND net_amount >= 0.00)`
    *   `CONSTRAINT proposal_discount_range CHECK (discount_percentage >= 0.00 AND discount_percentage <= 100.00)`
    *   `CONSTRAINT proposal_dates_ordered CHECK (valid_until >= created_at)`

#### 4.1.4 Index Strategy
*   **`proposals_pkey`**: Primary Key index.
*   **`proposals_org_num_uidx`**: Unique Index (B-Tree) on `(organization_id, proposal_number)`. Used for resolving individual bids.
*   **`proposals_client_lookup_idx`**: B-Tree Index on `(client_account_id, status)`. Used to compile customer history dashboards.
*   **`proposals_expiry_check_idx`**: Partial Index (B-Tree) on `(valid_until)` WHERE `status IN ('draft', 'under_review', 'sent')`. Evaluated by hourly expiration cron daemons.

#### 4.1.5 Business Rules & Transitions
*   **Status Transitions**: Only allows valid workflows:
    *   `draft` -> `under_review` or `sent`
    *   `under_review` -> `sent` or `declined`
    *   `sent` -> `accepted`, `declined`, or `expired`
*   **Conversion**: On entering `accepted`, triggers financial ledger events creating draft invoices automatically.

#### 4.1.6 Row-Level Security (RLS)
*   **Read**: Authorized tenant users, or authenticated external clients querying their own issued bids.
*   **Insert / Update**: Authorized tenant users.
*   **Delete**: Restricted strictly to Super Admin roles (Draft soft delete is permitted for tenant users).

#### 4.1.7 Events & Audits
*   **Events Produced**: `proposal.created`, `proposal.sent`, `proposal.accepted`, `proposal.declined`, `proposal.expired`
*   **Audit**: State shifts (e.g., `accepted_at` modification) must write audit events into `audit.security_events`.

---

### 4.2 Table Name: `public.proposal_items`

#### 4.2.1 Table Overview
*   **Purpose**: Maintains the line items included in a proposal, tracking standard services, pricing structures, and volumes.
*   **Business Responsibility**: Bid Line Valuation
*   **Ownership Domain**: Sales Estimations Core
*   **Dependencies**: `system.organizations`, `public.proposals`

#### 4.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique row key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant separator. |
| `proposal_id` | `uuid` | NO | None | FK -> `public.proposals(id)` | None | Public | Valid UUIDv4 | Links back to the master bid. |
| `sort_order` | `integer` | NO | `1` | None | None | Public | Positive integer | Controls custom row sorting. |
| `title` | `varchar(200)` | NO | None | None | None | Public | 1-200 characters | Name of service or product. |
| `description` | `text` | YES | `NULL` | None | None | Public | Plain text/markdown | Specifications of bidded item. |
| `quantity` | `numeric(18,4)` | NO | `1.0000` | None | None | Public | Quantity > 0.0000 | Ordered units count. |
| `unit_price` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Price >= 0.00 | Cost per unit. |
| `discount_amount` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Discount >= 0.00 | Value deduction per item. |
| `tax_rate_percentage`|`numeric(5,2)` | NO | `0.00` | None | None | Public | `0.00` to `100.00` | Standard localized tax rate. |
| `total_amount` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Total >= 0.00 | Calculated final value. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Log timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Log modification. |

#### 4.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `proposal_id REFERENCES public.proposals(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT proposal_items_qty CHECK (quantity > 0.0000)`
    *   `CONSTRAINT proposal_items_unit_price CHECK (unit_price >= 0.00)`
    *   `CONSTRAINT proposal_items_tax CHECK (tax_rate_percentage >= 0.00 AND tax_rate_percentage <= 100.00)`
    *   `CONSTRAINT proposal_items_discount CHECK (discount_amount >= 0.00)`

#### 4.2.4 Index Strategy
*   **`proposal_items_pkey`**: Primary Key index.
*   **`proposal_items_master_idx`**: B-Tree Index on `(proposal_id, sort_order)`. Optimizes sequential rendering of document item tables.

---

### 4.3 Table Name: `public.proposal_versions`

#### 4.3.1 Table Overview
*   **Purpose**: Stores a historical audit trail of proposal content as structural revisions occur, using frozen JSONB snapshots to guarantee historical tracking.
*   **Business Responsibility**: Version Control and Historical Negotiation Archiving
*   **Ownership Domain**: Sales Estimations Core (Immutable Table)
*   **Dependencies**: `system.organizations`, `public.proposals`, `security.users`
*   **Expected Lifetime**: Persistent (Regulatory/historical audits)
*   **Read / Write Frequency**: 95% Reads (Review history) / 5% Writes (Triggered on revision saves)
*   **Estimated Row Growth**: High ($O(N)$ multiplier of proposals depending on edit frequency)
*   **Retention Policy**: Identical to parent `public.proposals`

#### 4.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant separator. |
| `proposal_id` | `uuid` | NO | None | FK -> `public.proposals(id)` | None | Public | Valid UUIDv4 | Target proposal record. |
| `version_number` | `integer` | NO | None | Unique per Proposal | None | Public | Positive integer | Version ordinal indicator (e.g., v1, v2, v3). |
| `proposal_data` | `jsonb` | NO | None | None | None | Internal | Valid JSON format | **JSONB document block freezing proposal metadata, items, and values** at version snapshot. |
| `created_by_user_id`|`uuid` | NO | None | FK -> `security.users(id)` | None | Public | Valid UUIDv4 | Identifies user responsible for revision. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record timestamp. |

#### 4.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `proposal_id REFERENCES public.proposals(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `created_by_user_id REFERENCES security.users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT proposal_versions_number_key UNIQUE (proposal_id, version_number)`
*   **Check Constraints**:
    *   `CONSTRAINT proposal_versions_num_positive CHECK (version_number >= 1)`

#### 4.3.4 Index Strategy
*   **`proposal_versions_pkey`**: Primary Key index.
*   **`proposal_versions_lookup_idx`**: Unique B-Tree Index on `(proposal_id, version_number DESC)`. Primary route for tracking or reverting proposal content.

#### 4.3.5 Row-Level Security (RLS)
*   **Read**: Authorized tenant users.
*   **Insert**: Security subsystem triggered on proposal update.
*   **Update / Delete**: Strictly prohibited (Immutable audit data block).

---

### 4.4 Table Name: `public.proposal_comments`

#### 4.4.1 Table Overview
*   **Purpose**: Houses negotiations, internal notes, and discussion streams between sales agents and client decision makers.
*   **Business Responsibility**: Collaborative Sales Negotiation Logs
*   **Ownership Domain**: CRM Engagement Core
*   **Dependencies**: `system.organizations`, `public.proposals`, `security.users`, `public.contacts`

#### 4.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique log ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant isolator. |
| `proposal_id` | `uuid` | NO | None | FK -> `public.proposals(id)` | None | Public | Valid UUIDv4 | Associated quote. |
| `user_id` | `uuid` | YES | `NULL` | FK -> `security.users(id)` | None | Public | Valid UUIDv4 | Operating agent author (if internal comment). |
| `client_contact_id`|`uuid` | YES | `NULL` | FK -> `public.contacts(id)` | None | Public | Valid UUIDv4 | Client decision maker author (if external comment). |
| `comment_body` | `text` | NO | None | None | None | Internal | Non-empty text | Actual comment text. |
| `is_internal_only` | `boolean` | NO | `false` | None | None | Public | Boolean | If true, remains hidden from external clients (internal notes). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Submission timestamp. |

#### 4.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `proposal_id REFERENCES public.proposals(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `client_contact_id REFERENCES public.contacts(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT proposal_comment_author_check CHECK ((user_id IS NOT NULL AND client_contact_id IS NULL) OR (user_id IS NULL AND client_contact_id IS NOT NULL))` (Guarantees exactly one author type is set)

#### 4.4.4 Index Strategy
*   **`proposal_comments_pkey`**: Primary Key index.
*   **`proposal_comments_stream_idx`**: B-Tree Index on `(proposal_id, is_internal_only, created_at ASC)`. Used to compile the comments feed in chronological order.

---

### 4.5 Table Name: `public.proposal_attachments`

#### 4.5.1 Table Overview
*   **Purpose**: Junction table binding support materials, custom system architectures, blueprints, or legal certificates to proposals.
*   **Business Responsibility**: Supporting Document Tracking
*   **Ownership Domain**: Sales Estimations Core
*   **Dependencies**: `system.organizations`, `public.proposals`, `public.files`

#### 4.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique attachment key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant separator. |
| `proposal_id` | `uuid` | NO | None | FK -> `public.proposals(id)` | None | Public | Valid UUIDv4 | Associated master quote record. |
| `file_id` | `uuid` | NO | None | FK -> `public.files(id)` | None | Public | Valid UUIDv4 | Associated system core file reference. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record upload timestamp. |

#### 4.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `proposal_id REFERENCES public.proposals(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `file_id REFERENCES public.files(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT proposal_attachments_prop_file_key UNIQUE (proposal_id, file_id)`

#### 4.5.4 Index Strategy
*   **`proposal_attachments_pkey`**: Primary Key index.
*   **`proposal_attachments_proposal_idx`**: B-Tree Index on `(proposal_id)`. Pulls all files for a specific bid.

---

### 4.6 Table Name: `public.proposal_templates`

#### 4.6.1 Table Overview
*   **Purpose**: Configures standard proposal frameworks, boilerplate conditions, layout blocks, and terms, enabling rapid, standardized proposal creation.
*   **Business Responsibility**: Document Standardization & Legal Quality Management
*   **Ownership Domain**: Document Templates Core
*   **Dependencies**: `system.organizations`

#### 4.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Primary key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `title` | `varchar(255)` | NO | None | None | None | Public | 3-255 characters | Template identification title. |
| `description` | `text` | YES | `NULL` | None | None | Public | Plain text/markdown | Brief explanation of purpose. |
| `terms_and_conditions`|`text` | YES | `NULL` | None | None | Internal | Standard legal clauses | Default contract terms. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Boolean | Activation state. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Date established. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Date modified. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 4.6.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`

#### 4.6.4 Index Strategy
*   **`proposal_templates_pkey`**: Primary Key index.
*   **`proposal_templates_lookup_idx`**: B-Tree Index on `(organization_id, is_active)`. Retrieves active templates for the user.

---

### 4.7 Table Name: `public.proposal_template_items`

#### 4.7.1 Table Overview
*   **Purpose**: Holds standard boilerplate service lines attached to a proposal template.
*   **Business Responsibility**: Quoting Component Library Management
*   **Ownership Domain**: Document Templates Core
*   **Dependencies**: `system.organizations`, `public.proposal_templates`

#### 4.7.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique item ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant isolator. |
| `proposal_template_id`|`uuid` | NO | None | FK -> `public.proposal_templates(id)`| None | Public | Valid UUIDv4 | Parent template reference. |
| `sort_order` | `integer` | NO | `1` | None | None | Public | Positive integer | UI ordering. |
| `title` | `varchar(200)` | NO | None | None | None | Public | 1-200 characters | Name of bidded item. |
| `description` | `text` | YES | `NULL` | None | None | Public | Plain text/markdown | Specifications of bidded item. |
| `quantity` | `numeric(18,4)` | NO | `1.0000` | None | None | Public | Quantity > 0.0000 | Default quantity multiplier. |
| `unit_price` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Price >= 0.00 | Default base cost of item. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Log timestamp. |

#### 4.7.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `proposal_template_id REFERENCES public.proposal_templates(id) ON DELETE CASCADE ON UPDATE RESTRICT`

---

## 5. SUBDOMAIN 4 — SALES PIPELINES & OPPORTUNITIES

```
┌─────────────────────────┐
│  public.sales_pipelines │
└───────────┬─────────────┘
            │ 1:N
┌───────────▼─────────────┐
│  public.pipeline_stages ◄──────┐
└───────────┬─────────────┘      │
            │ 1:N                │ 1:N (From/To)
┌───────────▼─────────────┐    ┌─┴─────────────────────────┐
│    public.opportunities ├───►│  public.pipeline_history  │
└─────────────────────────┘    └───────────────────────────┘
```

### 5.1 Table Name: `public.sales_pipelines`

#### 5.1.1 Table Overview
*   **Purpose**: Supports multiple separate sales funnels per tenant (e.g., Enterprise Sales, Partner Channels, Self-Serve), tracking pipeline-specific conversion flows.
*   **Business Responsibility**: Pipeline Partitioning & Funnel Separation
*   **Ownership Domain**: Pipeline Management Core
*   **Dependencies**: `system.organizations`

#### 5.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `name` | `varchar(100)` | NO | None | None | None | Public | 2-100 characters | Name of the specific sales funnel. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Boolean | Controls pipeline visibility. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record birth. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record update. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 5.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`

#### 5.1.4 Index Strategy
*   **`sales_pipelines_pkey`**: Primary Key index.
*   **`sales_pipelines_lookup_idx`**: B-Tree Index on `(organization_id, is_active)`. Resolves active channels.

---

### 5.2 Table Name: `public.pipeline_stages`

#### 5.2.1 Table Overview
*   **Purpose**: Defines distinct commercial milestones within a specific pipeline (e.g., Discovery, Presentation, Proposal Sent, Negotiation, Closed Won, Closed Lost).
*   **Business Responsibility**: Funnel Step Definition & Forecasting Probability Management
*   **Ownership Domain**: Pipeline Management Core
*   **Dependencies**: `system.organizations`, `public.sales_pipelines`

#### 5.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique stage key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant separator. |
| `sales_pipeline_id`|`uuid` | NO | None | FK -> `public.sales_pipelines(id)`| None | Public | Valid UUIDv4 | Associated sales funnel. |
| `name` | `varchar(100)` | NO | None | None | None | Public | 1-100 characters | User-facing display name of milestone. |
| `code` | `varchar(50)` | NO | None | Unique per Pipeline | None | Public | Lowercase, snake_case | Machine-readable label for transitions. |
| `sort_order` | `integer` | NO | `10` | None | None | Public | Positive integer | Controls custom sorting in pipeline columns. |
| `win_probability` | `numeric(5,2)` | NO | `10.00` | None | None | Public | `0.00` to `100.00` | Default conversion probability for forecasting. |
| `is_closed` | `boolean` | NO | `false` | None | None | Public | Boolean | If true, marks terminal stages of the funnel. |
| `is_won` | `boolean` | NO | `false` | None | None | Public | Boolean | If true, marks successful commercial conversion. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record creation. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record modification. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 5.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `sales_pipeline_id REFERENCES public.sales_pipelines(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT pipeline_stages_pipe_code_key UNIQUE (sales_pipeline_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT pipeline_stages_code_format CHECK (code ~ '^[a-z0-9\_]+$')`
    *   `CONSTRAINT pipeline_stages_probability CHECK (win_probability >= 0.00 AND win_probability <= 100.00)`
    *   `CONSTRAINT pipeline_stages_won_closed CHECK (is_won = false OR is_closed = true)` (If won, it must also be closed)

#### 5.2.4 Index Strategy
*   **`pipeline_stages_pkey`**: Primary Key index.
*   **`pipeline_stages_sort_idx`**: B-Tree Index on `(sales_pipeline_id, sort_order)`. Essential for pipeline sequencing and Kanban layouts.

---

### 5.3 Table Name: `public.pipeline_history`

#### 5.3.1 Table Overview
*   **Purpose**: Tracks historical stage changes as opportunities progress through pipeline milestones, providing audit logs and tracking stage velocity.
*   **Business Responsibility**: Pipeline Velocity Audit, Conversion Funnel Diagnostics
*   **Ownership Domain**: Pipeline Management Core (Append-Only Table)
*   **Dependencies**: `system.organizations`, `public.opportunities`, `public.pipeline_stages`, `security.users`
*   **Expected Lifetime**: Persistent
*   **Read / Write Frequency**: 98% Reads / 2% Writes
*   **Estimated Row Growth**: High ($O(N)$ multiplier of opportunity movements)
*   **Retention Policy**: 5 years, then moved to archival partitions.

#### 5.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique audit ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant boundary. |
| `opportunity_id` | `uuid` | NO | None | FK -> `public.opportunities(id)`| None | Public | Valid UUIDv4 | Associated sales opportunity. |
| `from_stage_id` | `uuid` | YES | `NULL` | FK -> `public.pipeline_stages(id)`| None | Public | Valid UUIDv4 | Previous step context (null if initial). |
| `to_stage_id` | `uuid` | NO | None | FK -> `public.pipeline_stages(id)`| None | Public | Valid UUIDv4 | Entered milestone step. |
| `user_id` | `uuid` | NO | None | FK -> `security.users(id)` | None | Public | Valid UUIDv4 | Operator who changed the stage. |
| `days_in_previous_stage`|`integer`| YES | `NULL` | None | None | Public | Positive integer | Calculated duration in the previous step. |
| `occurred_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Timestamp of transition. |

#### 5.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `opportunity_id REFERENCES public.opportunities(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `from_stage_id REFERENCES public.pipeline_stages(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `to_stage_id REFERENCES public.pipeline_stages(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT pipeline_history_days CHECK (days_in_previous_stage IS NULL OR days_in_previous_stage >= 0)`

#### 5.3.4 Index Strategy
*   **`pipeline_history_pkey`**: Primary Key index.
*   **`pipeline_history_opportunity_idx`**: B-Tree Index on `(opportunity_id, occurred_at DESC)`. Used to track change histories and audit opportunity progress.

---

### 5.4 Table Name: `public.opportunities`

#### 5.4.1 Table Overview
*   **Purpose**: Central table for managing potential revenue-generating deals, linking prospects, companies, pipelines, stages, and owners.
*   **Business Responsibility**: Pipeline Valuation, Forecast Planning, Sales Strategy Coordination
*   **Ownership Domain**: CRM Core
*   **Dependencies**: `system.organizations`, `public.client_accounts`, `public.contacts`, `public.sales_pipelines`, `public.pipeline_stages`, `system.currencies`, `security.users`
*   **Expected Lifetime**: Persistent
*   **Read / Write Frequency**: 80% Reads / 20% Writes
*   **Estimated Row Growth**: High (10,000 - 100,000+ per month for enterprise deployments)
*   **Retention Policy**: Indefinite (Essential for multi-year sales forecasting and accounting data)

#### 5.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Primary key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant separator. |
| `client_account_id`|`uuid` | NO | None | FK -> `public.client_accounts(id)`| None | Public | Valid UUIDv4 | Associated corporate client account. |
| `contact_id` | `uuid` | YES | `NULL` | FK -> `public.contacts(id)` | None | Public | Valid UUIDv4 | Primary buyer contact person. |
| `name` | `varchar(200)` | NO | None | None | None | Public | 3-200 characters | Name of the deal. |
| `sales_pipeline_id`|`uuid` | NO | None | FK -> `public.sales_pipelines(id)`| None | Public | Valid UUIDv4 | Assigned sales pipeline funnel. |
| `pipeline_stage_id`|`uuid` | NO | None | FK -> `public.pipeline_stages(id)`| None | Public | Valid UUIDv4 | Associated active milestone step. |
| `value` | `numeric(18,2)` | NO | `0.00` | None | None | Public | Value >= 0.00 | Expected transaction revenue value. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)` | None | Public | Valid UUIDv4 | Valuation currency. |
| `close_date` | `date` | YES | `NULL` | None | None | Public | Date >= today | Expected closure date. |
| `win_probability` | `numeric(5,2)` | NO | `10.00` | None | None | Public | `0.00` to `100.00` | Custom win probability overrides. |
| `assigned_user_id` | `uuid` | YES | `NULL` | FK -> `security.users(id)` | None | Public | Valid UUIDv4 | Account executive owner. |
| `loss_reason` | `text` | YES | `NULL` | None | None | Public | Non-empty | Logs reason for loss if marked Closed Lost. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record birth. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record update. |
| `deleted_at` | `timestamptz` | YES | `NULL` | None | None | Public | UTC timestamp | Soft delete boundary. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 5.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `client_account_id REFERENCES public.client_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `contact_id REFERENCES public.contacts(id) ON DELETE SET NULL ON UPDATE RESTRICT`
    *   `sales_pipeline_id REFERENCES public.sales_pipelines(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `pipeline_stage_id REFERENCES public.pipeline_stages(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
    *   `assigned_user_id REFERENCES security.users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT opportunities_value_positive CHECK (value >= 0.00)`
    *   `CONSTRAINT opportunities_win_probability CHECK (win_probability >= 0.00 AND win_probability <= 100.00)`

#### 5.4.4 Index Strategy
*   **`opportunities_pkey`**: Primary Key index.
*   **`opportunities_pipeline_stage_idx`**: B-Tree Index on `(organization_id, pipeline_stage_id, deleted_at)` WHERE `deleted_at IS NULL`. Essential for active Kanban rendering.
*   **`opportunities_forecast_idx`**: B-Tree Index on `(organization_id, close_date, win_probability DESC)` WHERE `deleted_at IS NULL`. Used to render dynamic sales projections.

#### 5.4.5 Row-Level Security (RLS)
*   **Read / Insert / Update**: Authorized tenant users.
*   **Delete**: Restricted strictly to tenant administrators (Soft delete only).

#### 5.4.6 Event Matrix
*   **Events Produced**: `opportunity.created`, `opportunity.stage_changed`, `opportunity.won`, `opportunity.lost`, `opportunity.deleted`
*   **Events Consumed**: None

---

### 5.5 Table Name: `public.opportunity_products`

#### 5.5.1 Table Overview
*   **Purpose**: Junction table linking opportunities with specific product inventories or custom services, allowing detailed revenue breakdowns.
*   **Business Responsibility**: Deal Pricing Details, Product Catalog Attribution
*   **Ownership Domain**: CRM Core
*   **Dependencies**: `system.organizations`, `public.opportunities`

#### 5.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique row key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `opportunity_id` | `uuid` | NO | None | FK -> `public.opportunities(id)`| None | Public | Valid UUIDv4 | Associated master deal record. |
| `product_id` | `uuid` | YES | `NULL` | None | None | Public | Valid UUIDv4 | Reference to standard product table (if matching catalog). |
| `name` | `varchar(200)` | NO | None | None | None | Public | 1-200 characters | Name of bidded item. |
| `quantity` | `numeric(18,4)` | NO | `1.0000` | None | None | Public | Quantity > 0.0000 | Ordered product units count. |
| `unit_price` | `numeric(18,2)` | NO | None | None | None | Public | Price >= 0.00 | Cost per unit. |
| `total_price` | `numeric(18,2)` | NO | None | None | None | Public | Total >= 0.00 | Aggregated line items sum (`qty * unit_price`). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record modification timestamp. |

#### 5.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `opportunity_id REFERENCES public.opportunities(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT opportunity_product_qty CHECK (quantity > 0.0000)`
    *   `CONSTRAINT opportunity_product_price CHECK (unit_price >= 0.00 AND total_price >= 0.00)`

#### 5.5.4 Index Strategy
*   **`opportunity_products_pkey`**: Primary Key index.
*   **`opportunity_products_opp_idx`**: B-Tree Index on `(opportunity_id)`. Pulls all product lines for a deal.

---

## 6. SUBDOMAIN 5 — CUSTOMIZATION, TAXONOMY & EXTENSIBILITY

### 6.1 Table Name: `public.tags`

#### 6.1.1 Table Overview
*   **Purpose**: Enables tenant users to define lightweight keywords/labels, supporting flexible categorization of leads, contacts, opportunities, and bills.
*   **Business Responsibility**: Taxonomy Standardization, Workspace Custom Tagging
*   **Ownership Domain**: Taxonomy Core
*   **Dependencies**: `system.organizations`

#### 6.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique tag key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant separator. |
| `name` | `varchar(50)` | NO | None | Unique per Org | None | Public | 1-50 chars, trimmed | User-facing display name of tag. |
| `color_hex` | `varchar(7)` | NO | `'#3B82F6'`| None | None | Public | `#` followed by 6 hex chars | Color hex for rendering. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Creation log. |

#### 6.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT tags_org_name_key UNIQUE (organization_id, name)`
*   **Check Constraints**:
    *   `CONSTRAINT tags_color_format CHECK (color_hex ~ '^#[0-9A-Fa-f]{6}$')`
    *   `CONSTRAINT tags_name_length CHECK (length(trim(name)) >= 1)`

#### 6.1.4 Index Strategy
*   **`tags_pkey`**: Primary Key index.
*   **`tags_lookup_idx`**: Unique B-Tree Index on `(organization_id, name)`. Primary route for resolving tags during assignments.

---

### 6.2 Table Name: `public.tag_assignments`

#### 6.2.1 Table Overview
*   **Purpose**: Poly-junction table mapping tags to various CRM entities (e.g., Leads, Contacts, Opportunities) dynamically.
*   **Business Responsibility**: Polymorphic Taxonomy Matrix
*   **Ownership Domain**: Taxonomy Core
*   **Dependencies**: `system.organizations`, `public.tags`

#### 6.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique assignment ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant separator. |
| `tag_id` | `uuid` | NO | None | FK -> `public.tags(id)` | None | Public | Valid UUIDv4 | Associated tag key. |
| `entity_type` | `varchar(50)` | NO | None | None | None | Public | Valid type codes | E.g., `Lead`, `Contact`, `Opportunity`, `Proposal`. |
| `entity_id` | `uuid` | NO | None | None | None | Public | Valid UUIDv4 | Target record primary key. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Date linked. |

#### 6.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `tag_id REFERENCES public.tags(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT tag_assignments_composite_key UNIQUE (tag_id, entity_type, entity_id)`
*   **Check Constraints**:
    *   `CONSTRAINT tag_assignments_entity_type CHECK (entity_type IN ('Lead', 'Contact', 'Opportunity', 'Proposal'))`

#### 6.2.4 Index Strategy
*   **`tag_assignments_pkey`**: Primary Key index.
*   **`tag_assignments_entity_idx`**: B-Tree Index on `(organization_id, entity_type, entity_id)`. Used constantly to render all assigned tags for specific cards or records.
*   **`tag_assignments_reverse_idx`**: B-Tree Index on `(tag_id)`. Resolves all items matching a tag.

---

### 6.3 Table Name: `public.custom_fields`

#### 6.3.1 Table Overview
*   **Purpose**: Defines metadata schemas for custom fields, enabling tenants to extend standard entities (e.g., Leads, Contacts, Opportunities) with custom attributes (e.g., "Favorite Color", "Region ID").
*   **Business Responsibility**: Metadata Schema Extensibility
*   **Ownership Domain**: Custom Schema Core
*   **Dependencies**: `system.organizations`

#### 6.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique field definition ID. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Tenant boundary. |
| `entity_type` | `varchar(50)` | NO | None | None | None | Public | Valid type codes | Identifies target entity (`Lead`, `Contact`, `Opportunity`). |
| `name` | `varchar(100)` | NO | None | None | None | Public | 1-100 characters | User-facing display name of custom field. |
| `code` | `varchar(50)` | NO | None | Unique per Org & Entity | None | Public | Lowercase, snake_case | Machine identifier (e.g., `partner_id`). |
| `field_type` | `varchar(30)` | NO | None | None | None | Public | Valid types | E.g., `text`, `number`, `boolean`, `date`, `select`. |
| `is_required` | `boolean` | NO | `false` | None | None | Public | Boolean | Controls input requirements. |
| `options` | `jsonb` | YES | `NULL` | None | None | Public | Valid JSON array | **JSONB block defining choices for drop-down `select` types** (e.g. `["A", "B", "C"]`). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record birth. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record modification. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 6.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT custom_fields_org_entity_code UNIQUE (organization_id, entity_type, code)`
*   **Check Constraints**:
    *   `CONSTRAINT custom_fields_entity_type CHECK (entity_type IN ('Lead', 'Contact', 'Opportunity'))`
    *   `CONSTRAINT custom_fields_code_format CHECK (code ~ '^[a-z0-9\_]+$')`
    *   `CONSTRAINT custom_fields_type CHECK (field_type IN ('text', 'number', 'boolean', 'date', 'select'))`

#### 6.3.4 Index Strategy
*   **`custom_fields_pkey`**: Primary Key index.
*   **`custom_fields_lookup_idx`**: B-Tree Index on `(organization_id, entity_type)`. Resolves custom fields defined for specific interfaces.

---

### 6.4 Table Name: `public.custom_field_values`

#### 6.4.1 Table Overview
*   **Purpose**: Stores the actual data values mapped to custom fields for specific entity records, keeping dynamic values isolated from standard relational columns.
*   **Business Responsibility**: Dynamic Attribute Value Management
*   **Ownership Domain**: Custom Schema Core
*   **Dependencies**: `system.organizations`, `public.custom_fields`
*   **Expected Lifetime**: Matches associated entity record
*   **Read / Write Frequency**: 60% Reads / 40% Writes
*   **Estimated Row Growth**: High ($O(N)$ of dynamic field configurations)
*   **Retention Policy**: Propagates deletions from parent entities.

#### 6.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation Rules | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | None | Public | Valid UUIDv4 | Unique row key. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)` | None | Public | Valid UUIDv4 | Multi-tenant boundary. |
| `custom_field_id` | `uuid` | NO | None | FK -> `public.custom_fields(id)` | None | Public | Valid UUIDv4 | Associated custom field definition. |
| `entity_id` | `uuid` | NO | None | None | None | Public | Valid UUIDv4 | Target entity record key (e.g., Leads ID). |
| `value` | `text` | YES | `NULL` | None | None | Public | Validated per custom field rules | Plain-text representation of custom data values. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record creation. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | UTC timestamp | Record modification. |
| `version` | `integer` | NO | `1` | None | None | Public | Positive integer | Concurrency manager. |

#### 6.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `custom_field_id REFERENCES public.custom_fields(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT custom_field_values_unique UNIQUE (custom_field_id, entity_id)` (Ensures only one custom value exists per field and entity instance)

#### 6.4.4 Index Strategy
*   **`custom_field_values_pkey`**: Primary Key index.
*   **`custom_field_values_entity_idx`**: B-Tree Index on `(entity_id)`. Pulls all custom attributes assigned to a specific record.

---

## 7. CROSS-DOMAIN SYSTEM INTEGRATIONS

To avoid database pollution and maintain a modular architecture, CRM entities integrate with secondary domains using decoupled event-driven patterns:

1.  **Finance Integration (Invoices & Billing)**:
    *   On a `proposal.accepted` event, the finance listener asynchronously reads the proposal items and provisions a corresponding draft invoice in the billing ledger. CRM tables never reference accounting ledger states directly.
2.  **Project Integration (Execution Tracking)**:
    *   Once a deal enters the `Closed Won` stage, a project provisioning worker creates a project container, mapping the client account details across domains asynchronously.
3.  **Support and Tickets Integration**:
    *   Customer contacts link to service tickets via `contact_id`. This allows service desks to view historical communication logs without tight coupling of tables.
4.  **AI Analysis (Smart Scoring & Proposals)**:
    *   AI components read lead descriptions, company scales, and histories asynchronously to compute scores. These scores write to independent telemetry registers, avoiding performance penalties on core transactions.
5.  **Outbound Webhooks and Automation**:
    *   Standard database updates produce events in `public.outbound_events` to notify external endpoints (e.g., Slack notifications on `lead.created`).

---

## 8. GDPR COMPLIANCE & PII ARCHITECTURE

Because CRM databases house sensitive Personally Identifiable Information (PII), several strict operational compliance safeguards are built-in:

1.  **Strict GDPR "Right to Be Forgotten" (Erasure)**:
    *   Physical deletions of contacts must be propagated across `contact_addresses` and associated lookups.
    *   Alternatively, an anonymization routine clears specific columns (e.g., setting `first_name`, `last_name`, `email`, and `phone` to `NULL` or generic placeholder hashes) while retaining transaction histories for tax auditing.
2.  **Encryption at Rest**:
    *   All customer data disk stores, WAL logs, and physical backups utilize enterprise AES-256 block encryption.
3.  **Audited Access Controls**:
    *   Any export or batch SELECT query targeting contacts or leads must write an audit log entry containing the user identity, targeted records count, and access purpose to `audit.security_events`.
