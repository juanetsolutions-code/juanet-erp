# JUANET PostgreSQL Physical Database Standards
## Phase 2.3.1 — PostgreSQL Physical Standards & Implementation Constitution
**Document Version:** 1.0  
**Author:** Chief Database Architect, JUANET Platform  
**Classification:** Technical / Database Architecture Reference  

---

## SECTION 1 — POSTGRESQL VERSION & PLATFORM RUNTIME

### 1.1 Supported Engine & Target Version
The JUANET Enterprise Platform is standardized exclusively on **PostgreSQL 16**. All runtime deployments (local development, staging environments, and high-availability production clusters on Cloud SQL) must execute on this release or subsequent minor patch updates.

#### 1.1.1 Architectural Justifications
*   **Logical Replicas & Bi-Directional Synchronization**: PostgreSQL 16 introduces advanced logical replication parallel workers and bidirectional replication filters, facilitating geographical sharding and seamless multi-region cluster coordination.
*   **Active Query Engine Optimization**: Enhanced optimizer behavior for parallel hash joins, generalized indexes, and partitioned table aggregate operations reduces cold-query latency on high-volume tenant datasets.
*   **Enhanced Row-Level Security (RLS) Execution**: RLS policies execute with optimized plan caches, avoiding nested-loop degradation on highly transactional tables.
*   **Native SIMD-Optimized Performance**: Improved JSONB parser utilization of CPU vector instructions yields high-throughput processing on dynamic payloads.

### 1.2 Required Extensions
Only the following vetted extensions are approved for activation. No migration script may execute commands utilizing extensions not listed below without formal approval from the Database Architecture Council:

1.  **`pgcrypto`**: Used for legacy cryptographic utilities and UUID generations.
2.  **`uuid-ossp`**: Provides industry-standard UUIDv4 algorithm hooks.
3.  **`citext`**: Establishes case-insensitive character string support. Essential for case-preserving, case-insensitive uniqueness constraints (e.g., matching emails across user registries).
4.  **`pg_trgm`**: Trigram matching extension utilized for high-velocity, case-insensitive substring searching on indices where full-text indexes are excessive.
5.  **`btree_gist`**: Integrates standard B-Tree sorting behavior into GIST indexes, supporting multi-column exclusion constraints (e.g., preventing booking overlaps inside scheduling models).

### 1.3 Unsupported & Prohibited Features
To preserve transaction portability, prevent platform lock-in, and maintain absolute resource isolation, the following features are strictly prohibited:

*   **Database-Bound Object-Relational Triggers**: Do not implement business logic inside database triggers. Database triggers are strictly limited to technical auditing, soft delete propagation, or automatic timestamp columns.
*   **Server-Side Execution Languages (other than PL/pgSQL)**: The activation of procedural languages such as `plpythonu`, `plv8` (V8 JavaScript Engine), or `pltcl` is prohibited. Server-side computation must rely strictly on optimized SQL or PL/pgSQL.
*   **Unlogged Tables (`UNLOGGED`)**: Utilizing unlogged tables is prohibited for any transactional domain. Ephemeral cache data must be handled in memory (e.g., Redis) or via transient database partitions, preventing unexpected dataloss upon node crash events.
*   **Implicit Type Casts (`CAST`)**: No migration or query may rely on implicit string-to-numeric or string-to-date casting. All database expressions must use explicit type conversions (`::datatype` or `CAST(expr AS datatype)`) to prevent runtime parse failures.

### 1.4 Compatibility Policy & Upgrade Strategy
*   **Minor Version Upgrades**: Automatically applied within maintenance windows without changing physical schemas.
*   **Major Version Upgrades**: Must undergo extensive staging verification using physical replication tests, measuring execution plans, query latency deltas, and RLS constraint performance prior to production deployment.

---

## SECTION 2 — UUID STANDARDS & KEYING POLICY

To prevent ID-collision security exploits, mask internal customer metrics (such as daily signups or transaction volume), and ensure distributed database node safety, the platform utilizes strict Universally Unique Identifier (UUID) standards.

### 2.1 UUID Version Standard
*   All primary keys, foreign keys, and relational association indices across the public, system, and audit schemas must utilize the **UUIDv4 (Randomly Generated)** format.
*   The system utilizes the native PostgreSQL function **`gen_random_uuid()`** introduced in PostgreSQL 13+ to generate UUIDv4 values. Legacy mechanisms relying on external functions (e.g., `uuid_generate_v4()`) are deprecated.

### 2.2 Primary Keys
*   All tables must define a single column named **`id`** of type `uuid` as their primary key.
*   The default expression on this primary key column must be `gen_random_uuid()`.
*   *Exception*: Junction tables representing pure many-to-many relationships (e.g., `role_permissions`) do not use an independent `id` column. They must define a composite primary key consisting of the related foreign keys (e.g., `PRIMARY KEY (role_id, permission_id)`).

### 2.3 Foreign Keys
*   Foreign keys referencing a UUID primary key must be declared with the exact data type **`uuid`**.
*   The column naming convention must strictly append `_id` to the referenced table's singular name (e.g., `organization_id` referencing `organizations.id`, `user_id` referencing `users.id`).

### 2.4 Prohibited UUID Implementations & Exceptions
UUIDs must not be used for user-facing, high-read, sequential indicators (e.g., Invoice Numbers, Support Ticket IDs, Transaction Receipts, Purchase Orders).
*   *Rationale*: UUIDs are difficult for human support agents and clients to read, communicate, and track over the telephone or chat support.
*   *Alternative Pattern*: User-facing sequential indices must be implemented using a dedicated, human-readable alphanumeric column (e.g., `invoice_number varchar(50)`) mapped to isolated, transaction-safe sequence counters.

---

## SECTION 3 — ENTERPRISE DATA TYPE CATALOG

To preserve maximum database memory alignment, maximize disk I/O efficiency, and enforce rigid data validation, developers must adhere to this definitive datatype specification.

| Relational Data Type | Standardized Platform Purpose | Storage Footprint | Core Architectural Guidelines & Restrictions |
| :--- | :--- | :--- | :--- |
| **`uuid`** | Primary & Foreign Keys | 16 bytes | Used for all object identifiers. Never substitute with integers or VARCHAR. |
| **`citext`** | Case-Insensitive Strings | Variable | Mandatory for emails, usernames, slugs, and domain names. Prevents duplicate lookup vulnerabilities. |
| **`text`** | Variable-Length Content | Variable | Use for descriptions, markdown bodies, logs, and free text comments. Performance is identical to `varchar(N)`. |
| **`varchar(N)`** | Constrained String Fields | Variable | Use only when business logic enforces a strict string length constraint (e.g., ISO codes, phone numbers, state tokens). |
| **`boolean`** | Flag Fields | 1 byte | Use for two-state indicators (e.g., `is_active`). Never use strings (e.g., `"Y"`/`"N"`) or integers (`0`/`1`). |
| **`integer`** | standard Counters | 4 bytes | Use for standard small range integers, tracking version sequences, page counts, or priority scales. |
| **`bigint`** | Large Quantities / Bytes | 8 bytes | Use for file byte sizes, high-velocity metric counters, or event sequences. |
| **`numeric(P,S)`** | Exact Monetary Values | Variable | **Mandatory** for financial ledger entries, tax rates, transaction amounts. Never use floating-point types (`real`/`double precision`). |
| **`jsonb`** | Dynamic Configuration Block | Variable | Use for unstructured configurations, custom metadata, and flexible system logs. Prohibited for core relational structures. |
| **`bytea`** | Binary Payloads | Variable | Used only for short cryptographic signatures or file hashes. Raw file uploads are strictly prohibited. |
| **`inet`** | IP Address Storage | 7 or 19 bytes | Used for client logging, audits, and network connection tracking. Supports IPv4 and IPv6 natively. |
| **`date`** | Calendar Dates | 4 bytes | Use strictly for date values independent of times zones (e.g., birth dates). |
| **`timestamptz`** | Absolute Timestamps | 8 bytes | **Mandatory** for all transaction records, audits, logs, creation dates, and process completions. UTC storage. |
| **`interval`** | Time Durations | 16 bytes | Used for tracking relative offsets, trial durations, or scheduling thresholds. |

### 3.1 Type Selection Guidelines & Performance Rationales

#### 3.1.1 Numeric Precision Policy for Financial Records
All transactional balances, credit definitions, price specifications, and debit entries must be declared as **`numeric(18,4)`** or **`numeric(18,2)`**. 
*   *The Anti-Float Rule*: The use of floating-point types (such as `real` or `double precision`) is strictly prohibited for monetary values due to rounding errors introduced by binary floating-point representation.
*   *Sufficient Scale*: Precision `18` with scale `4` provides secure storage for up to 100 trillion currency units with four decimal places, accommodating high-inflation currencies and partial micro-currency splits.

#### 3.1.2 ENUM types vs. Relational Status Lookup Tables
*   The use of native PostgreSQL database ENUM types (`CREATE TYPE ... AS ENUM`) is **strictly prohibited**.
*   *Rationale*: ENUM values are difficult to alter dynamically in high-volume, multi-tenant databases without locking tables. They also prevent foreign language translations and localized UI labeling.
*   *Standard Pattern*: All state transitions, status fields, and classifications must be mapped to physical status lookup tables (e.g., `invoice_statuses`) using formal foreign keys.

#### 3.1.3 Storage of Array Data Types
*   Native SQL arrays (e.g., `varchar[]` or `integer[]`) are prohibited for core relational associations.
*   *Rationale*: Arrays break First Normal Form (1NF), complicate relational queries, and degrade indexing performance.
*   *Standard Pattern*: Use traditional junction tables or, if tracking non-relational simple strings (e.g., list of permission scope tokens), store them inside structured `jsonb` columns.

---

## SECTION 4 — NAMING CONVENTIONS & SCHEMAS

Consistency in naming simplifies object-relational mapping, eases query writing, and prevents schema conflicts.

### 4.1 Schema Organization
The platform database is structured into four distinct logical namespaces:
1.  **`public`**: Contains standard multi-tenant business entities (e.g., `invoices`, `projects`, `leads`). Row-Level Security is active across this schema.
2.  **`system`**: Stores global lookup tables, configuration presets, currency rates, countries, and feature definitions. This schema is read-accessible globally but write-restricted to system admins.
3.  **`audit`**: Contains the time-partitioned immutable logging structures (`audit_logs`) and the outbox pattern tables (`outbound_events`).
4.  **`security`**: Manages master credential indices, password hashes, and user MFA devices. Highly isolated schema bypassed only by PostgreSQL auth interfaces.

### 4.2 Naming Rules Matrix

| Database Object Type | Naming Rule | Prefix / Suffix Pattern | Standardized Enterprise Example |
| :--- | :--- | :--- | :--- |
| **Table** | lowercase, snake_case, plural | None | `client_accounts`, `payment_intents` |
| **Junction Table** | lowercase, snake_case, singular | None | `user_role`, `role_permission` |
| **Column** | lowercase, snake_case, singular | None | `billing_email`, `total_amount` |
| **Foreign Key Column** | singular referenced table + `_id` | `*_id` | `organization_id`, `created_by_user_id` |
| **Primary Key Constraint** | table name + `_pkey` | `*_pkey` | `organizations_pkey` |
| **Foreign Key Constraint** | source table + column + `_fkey` | `*_fkey` | `projects_organization_id_fkey` |
| **Unique Constraint** | table name + columns + `_key` | `*_key` | `users_email_key` |
| **Check Constraint** | table name + column + `_check` | `*_check` | `invoices_balance_due_check` |
| **B-Tree Index** | table name + column + `_idx` | `*_idx` | `leads_organization_id_idx` |
| **Unique Index** | table name + column + `_uidx` | `*_uidx` | `organizations_slug_uidx` |
| **GIN Index** | table name + column + `_gin_idx` | `*_gin_idx` | `audit_logs_new_values_gin_idx` |
| **GIST Index** | table name + column + `_gist_idx`| `*_gist_idx` | `bookings_range_gist_idx` |
| **Database View** | lowercase, snake_case, plural | `v_*` | `v_active_invoices` |
| **Materialized View** | lowercase, snake_case, plural | `mv_*` | `mv_monthly_tenant_summaries` |
| **PL/pgSQL Function** | lowercase, snake_case, action verb | `fn_*` | `fn_reconcile_invoice_balance` |
| **Trigger Function** | lowercase, snake_case | `tg_fn_*` | `tg_fn_update_timestamp` |
| **Database Trigger** | lowercase, snake_case | `tg_*` | `tg_update_audit_fields` |

---

## SECTION 5 — MANDATORY GLOBAL COLUMNS

To maintain complete tracking, audit compliance, multi-tenant safety, and optimistic concurrency safety, **every tenant-owned business entity** within the `public` schema must define the following standard structural block of columns. No table in the `public` schema is exempt from this requirement.

```sql
id              uuid                        NOT NULL DEFAULT gen_random_uuid(),
organization_id uuid                        NOT NULL,
created_at      timestamp with time zone    NOT NULL DEFAULT now(),
updated_at      timestamp with time zone    NOT NULL DEFAULT now(),
deleted_at      timestamp with time zone    NULL,
created_by      uuid                        NULL,
updated_by      uuid                        NULL,
version         integer                     NOT NULL DEFAULT 1
```

### 5.1 Comprehensive Column Specifications

#### 5.1.1 `id`
*   **Data Type**: `uuid`
*   **Nullability**: `NOT NULL`
*   **Default Value**: `gen_random_uuid()`
*   **Constraint**: `PRIMARY KEY`
*   **Purpose**: Global system-wide unique identifier for the specific row.

#### 5.1.2 `organization_id`
*   **Data Type**: `uuid`
*   **Nullability**: `NOT NULL`
*   **Constraint**: `FOREIGN KEY REFERENCES system.organizations(id) ON DELETE RESTRICT`
*   **Index Requirement**: Must be included as the primary field in compound indices to support multi-tenant isolation routing.
*   **Purpose**: Logical tenant partition key used to enforce database-level RLS policies.

#### 5.1.3 `created_at`
*   **Data Type**: `timestamp with time zone` (TIMESTAMPTZ)
*   **Nullability**: `NOT NULL`
*   **Default Value**: `now()`
*   **Purpose**: Standard record creation timestamp in UTC.

#### 5.1.4 `updated_at`
*   **Data Type**: `timestamp with time zone` (TIMESTAMPTZ)
*   **Nullability**: `NOT NULL`
*   **Default Value**: `now()`
*   **Purpose**: Timestamp of the latest modification. Managed via an automatic database trigger to ensure consistency.

#### 5.1.5 `deleted_at`
*   **Data Type**: `timestamp with time zone` (TIMESTAMPTZ)
*   **Nullability**: `NULL`
*   **Default Value**: `NULL`
*   **Purpose**: Soft delete timestamp. When populated, the row is treated as logically deleted.

#### 5.1.6 `created_by`
*   **Data Type**: `uuid`
*   **Nullability**: `NULL`
*   **Constraint**: `FOREIGN KEY REFERENCES security.users(id) ON DELETE SET NULL`
*   **Purpose**: Audit reference tracking the user who authored the record.

#### 5.1.7 `updated_by`
*   **Data Type**: `uuid`
*   **Nullability**: `NULL`
*   **Constraint**: `FOREIGN KEY REFERENCES security.users(id) ON DELETE SET NULL`
*   **Purpose**: Audit reference tracking the user who last modified the record.

#### 5.1.8 `version`
*   **Data Type**: `integer`
*   **Nullability**: `NOT NULL`
*   **Default Value**: `1`
*   **Purpose**: Optimistic concurrency locking sequence counter.

---

## SECTION 6 — DATABASE CONSTRAINT STANDARDS

Database constraints are the primary line of defense for data integrity. Schema designs must prioritize constraints over application-layer checks.

### 6.1 Primary Key Constraints
*   Must be declared explicitly on table creation.
*   *Naming Standard*: `{table_name}_pkey`.

### 6.2 Foreign Key Constraints
*   All foreign keys must reference valid primary keys.
*   **Explicit Deletions Block Policy**: Deletions on critical configurations, transactions, lookup tables, and parent entities must enforce `ON DELETE RESTRICT` or `ON DELETE NO ACTION`.
*   **Implicit Deletions Cascade Policy**: Cascades are permitted only on child records that have no independent lifecycle (e.g., `invoice_items` belonging to `invoices`). These must declare `ON DELETE CASCADE`.
*   *Naming Standard*: `{table_name}_{column_name}_fkey`.

### 6.3 Unique Constraints
*   Uniqueness constraints must handle case-preservation rules correctly. Fields such as email addresses, slugs, and domain names must be enforced via case-insensitive indices:
    ```sql
    CREATE UNIQUE INDEX users_email_uidx ON security.users (lower(email));
    ```
*   *Naming Standard*: `{table_name}_{columns}_key` or `{table_name}_{columns}_uidx` for indexes.

### 6.4 Check Constraints (Business Invariants)
Check constraints must enforce logical boundaries on numeric and status values directly at the engine level:
*   **Financial Records**: `CHECK (debit_amount >= 0.00)` and `CHECK (credit_amount >= 0.00)`.
*   **Invoices**: `CHECK (total_amount >= 0.00)` and `CHECK (balance_due >= 0.00)`.
*   **Status Codes**: Status values must exist within lookup tables rather than free text strings.
*   *Naming Standard*: `{table_name}_{column_name}_check`.

### 6.5 Exclusion Constraints (Physical Conflict Prevention)
For tables tracking scheduling, booking windows, room assignments, or timesheets, exclusion constraints must prevent double-booking conflicts:
*   Enforced using GIST indexes and range operators (`tsrange` or `daterange`), blocking overlapping timespans for the same resource context.

---

## SECTION 7 — JSONB STORAGE & INDEXING STANDARDS

The `jsonb` data type provides flexibility for storing unstructured data, but unstructured columns can complicate reporting, make indexing inefficient, and bypass relational constraints.

### 7.1 Approved JSONB Use Cases
*   Storing dynamic, provider-specific payment gateway payloads (e.g., M-Pesa API webhook responses).
*   Dynamic, client-side configuration settings (e.g., dashboard themes, dashboard widget placements).
*   Dynamic, user-defined notification template variables.

### 7.2 Prohibited JSONB Use Cases
*   Storing primary keys, foreign keys, or critical relational references.
*   Storing financial transactional balances or credit ledgers that require atomic correctness.
*   Creating structural tables entirely within a single `jsonb` column (EAV database model anti-pattern).

### 7.3 JSONB Indexing Strategy
To prevent full-table scans during queries targeting JSONB properties:
*   **GIN Indexes (Generalized Inverted Index)**: Must be applied to tables where queries search for arbitrary keys or objects inside JSONB payloads:
    ```sql
    CREATE INDEX audit_logs_new_values_gin_idx ON audit.audit_logs USING gin (new_values);
    ```
*   **Functional Indexes**: If queries target a specific, nested parameter within a JSONB column (e.g., `payload->>'transaction_id'`), a B-Tree index must be created on that specific path:
    ```sql
    CREATE INDEX payment_attempts_provider_ref_idx ON payments.payment_attempts ((external_payload->>'provider_reference'));
    ```

---

## SECTION 8 — TIME ZONE & CLOCK STANDARDS

All temporal values must be consistent, timezone-aware, and highly precise across all systems.

### 8.1 Timezone Storage Standard
*   All date and time columns representing absolute time points must use the data type **`timestamp with time zone` (TIMESTAMPTZ)**.
*   The use of standard `timestamp` (without time zone) is strictly prohibited, as it discards timezone offsets and can lead to scheduling and invoicing errors.
*   All datetime values are converted to and stored in **UTC** within the database.

### 8.2 Client-Facing Timezone Conversions
*   The conversion from UTC to a tenant's local timezone must occur in the application layer or within SQL queries using the `AT TIME ZONE` expression:
    ```sql
    SELECT created_at AT TIME ZONE 'Africa/Nairobi' AS local_time FROM public.invoices;
    ```
*   The fallback local timezone for an organization is defined in `system.organization_settings.timezone`.

### 8.3 Creation and Audit Timestamps
*   Creation fields must default to the system timestamp using `now()`:
    ```sql
    created_at timestamp with time zone NOT NULL DEFAULT now()
    ```
*   Database clocks rely entirely on the underlying virtual machine host or cloud database cluster (which must be synchronized via NTP).

---

## SECTION 9 — SOFT DELETE STANDARDS

To preserve historical business records, transaction trails, and prevent accidental data loss, deletions of critical business entities (e.g., clients, projects, support tickets, invoices) must execute as logical "soft deletes."

### 9.1 Column Definition
*   Every table prone to deletion must include a **`deleted_at`** column:
    ```sql
    deleted_at timestamp with time zone NULL DEFAULT NULL
    ```

### 9.2 SQL Query Filtering Standard
*   All SELECT statements executed by user-facing applications must filter out logically deleted records:
    ```sql
    SELECT * FROM public.projects WHERE deleted_at IS NULL;
    ```
*   This filtering must be enforced at the database layer using Row-Level Security (RLS) policies to prevent accidental data leaks.

### 9.3 Indexing Soft Deletes (Partial Indexes)
To minimize index size and keep lookups fast, standard indexes on tables that support soft deletes must be declared as **Partial Indexes** that exclude soft-deleted rows:
```sql
CREATE INDEX projects_organization_id_partial_idx 
ON public.projects (organization_id) 
WHERE deleted_at IS NULL;
```

### 9.4 Soft Delete Cascades (Application Layer Coordination)
Physical database cascades (`ON DELETE CASCADE`) do not trigger during a soft delete. Downstream soft deletions must be coordinated by the application layer or managed via dedicated PL/pgSQL database functions to ensure data consistency across the schema.

---

## SECTION 10 — OPTIMISTIC CONCURRENCY LOCKING

To prevent the "lost update" problem in concurrent multi-user environments, JUANET implements a mandatory optimistic locking mechanism.

### 10.1 The `version` Column Standard
*   Every mutable table in the `public` schema must define a **`version`** column:
    ```sql
    version integer NOT NULL DEFAULT 1
    ```

### 10.2 Database-Level Validation Block
*   When a record is read, the application retrieves its current `version` integer.
*   When performing an update, the SQL statement must verify that the version hasn't changed since it was read, and increment it:
    ```sql
    UPDATE public.tasks 
    SET title = :title, version = version + 1, updated_at = now()
    WHERE id = :id AND version = :read_version;
    ```

### 10.3 Application Handling Expectations
*   If the record was modified by another process in the interim, the `version` check will fail, and the query will return an update count of `0`.
*   The application must detect this condition, roll back any open transactions, and prompt the user to refresh their view and resolve the conflict.

---

## SECTION 11 — DOCUMENTATION & COMMENTS

Maintaining clear database documentation is critical for ongoing development, compliance audits, and onboarding.

### 11.1 COMMENT ON Table and Column Policy
*   All migration files and database schemas must include explicit description comments for tables and columns using the PostgreSQL `COMMENT ON` syntax.
*   No table will be accepted into the repository without these descriptors.

### 11.2 Comment Implementation Example
```sql
COMMENT ON TABLE public.invoices IS 
'Stores customer billing invoices. Isolated per tenant via organization_id. Classified as Restricted-Financial.';

COMMENT ON COLUMN public.invoices.balance_due IS 
'The remaining unpaid balance on the invoice in the specified currency. Checked to ensure it is >= 0.00.';

COMMENT ON COLUMN public.invoices.client_account_id IS 
'Foreign key referencing the target customer account. Managed under ON DELETE RESTRICT.';
```

---

## SECTION 12 — ENTERPRISE DATABASE DESIGN RULES

These mandatory design rules govern all schema structures, table definitions, and migrations within the JUANET platform. Any deviation requires formal review and approval.

### 12.1 Mandatory Relational Integrity Rules

#### 12.1.1 Rule of Absolute Nullability Verification
*   **Rule**: Every column declaration must explicitly state its nullability (`NOT NULL` or `NULL`). Leaving nullability implicit is prohibited.
*   *Rationale*: Eliminates ambiguity for developers and database engines, preventing unexpected null pointer errors in application code.

#### 12.1.2 Rule of Non-Nullable Foreign Keys
*   **Rule**: Foreign keys must be declared as `NOT NULL` unless a nullable relationship is explicitly justified by business requirements (e.g., an optional task assignment field).
*   *Rationale*: Prevents orphaned or disconnected data, keeping the database model clean and consistent.

#### 12.1.3 Rule of Zero Business Logic in Triggers
*   **Rule**: Trigger functions must be used strictly for technical system tasks (such as setting the `updated_at` timestamp or tracking audits). Business calculations and workflow logic are prohibited in triggers.
*   *Rationale*: Keeps business rules centralized in the application layer, simplifies debugging, and prevents silent, unexpected side effects.

#### 12.1.4 Rule of Hardcoded Value Avoidance
*   **Rule**: Never hardcode status codes, categories, or classifications inside application code or check constraints. All options must reside in physical lookup tables.
*   *Rationale*: Allows adding, removing, or translating options without schema changes or redeployments.

#### 12.1.5 Rule of Polymorphic Foreign Key Prohibitions
*   **Rule**: Polymorphic associations (where a single foreign key column can reference multiple different parent tables depending on a second "type" column) are strictly prohibited.
*   *Rationale*: Breaks referential integrity constraints, prevents index optimization, and increases the risk of data corruption.
*   *Standard Pattern*: Use explicit, nullable foreign keys for each possible parent table, or utilize intermediate junction tables.

#### 12.1.6 Rule of Anti-EAV (Entity-Attribute-Value) Tables
*   **Rule**: Creating generic EAV tables (storing all parameters as key-value string rows) is strictly prohibited.
*   *Rationale*: EAV tables bypass SQL type safety, degrade query performance, and complicate indexing.
*   *Standard Pattern*: Use proper relational schemas or leverage structured `jsonb` columns for highly dynamic, non-relational parameters.

#### 12.1.7 Rule of Circular Dependency Prevention
*   **Rule**: Creating circular foreign key dependencies (e.g., Table A requiring a key from Table B, which in turn requires a key from Table A) is prohibited.
*   *Rationale*: Creates deadlock vulnerabilities during insertions and prevents clean table purges or drops.

#### 12.1.8 Rule of Absolute Immutability of Ledger Records
*   **Rule**: Financial transactions and ledger entries must be strictly append-only. Under no circumstances may an application execute an `UPDATE` or `DELETE` on a committed financial ledger row.
*   *Rationale*: Ensures absolute financial audit compliance and audit-trail integrity. Corrections must be handled via formal reversal entries.

---

## SECTION 13 — THE SECTOR-1 AUDIT VALIDATION CHECKLIST

Before any schema migration, table definition, or ORM model is committed to the repository, it must pass this validation checklist.

```
┌────────────────────────────────────────────────────────────────────────┐
│               ENTERPRISE SCHEMATIC VALIDATION CHECKLIST                │
├────────────────────────────────────────────────────────────────────────┤
│  [ ] 1.  Is the table named in plural form and snake_case?             │
│  [ ] 2.  Is the table assigned to a logical schema?                    │
│  [ ] 3.  Does the table define a single UUIDv4 primary key?            │
│  [ ] 4.  Are foreign keys NOT NULL unless explicitly justified?        │
│  [ ] 5.  Do all foreign keys specify explicit ON DELETE behaviors?     │
│  [ ] 6.  Are all temporal columns TIMESTAMPTZ?                         │
│  [ ] 7.  Is the standard structural block of columns present?         │
│  [ ] 8.  Is the version column present for optimistic locking?         │
│  [ ] 9.  Are check constraints defined for numeric and status fields?  │
│  [ ] 10. Are status values mapped to physical lookup tables?           │
│  [ ] 11. Is Row-Level Security (RLS) configured for tenant isolation?   │
│  [ ] 12. Is a partial index configured on deleted_at?                  │
│  [ ] 13. Are comment descriptors written for the table and columns?    │
│  [ ] 14. Has a security classification been assigned?                  │
└────────────────────────────────────────────────────────────────────────┘
```

---

## SECTION 14 — ARCHITECTURAL CONSISTENCY REVIEW

This standards specification has been thoroughly reviewed against the finalized platform specifications to ensure total alignment:
*   **JUANET Master Specification (v1.3)**: Aligns with the multi-tenant isolation, data encryption, and double-entry bookkeeping parameters.
*   **Phase 2.1 Database Blueprint**: Physical standards map directly to the conceptual axioms, optimistic locking, and event outbox patterns.
*   **Phase 2.2 Entity Dictionary**: Schema structures and naming conventions match the documented properties across all twelve core domains.

All PostgreSQL physical standards are now finalized and ready for implementation.
