# JUANET Enterprise Database Blueprint
## Phase 2.1 — Authoritative Database Constitution
**Document Version:** 1.0  
**Author:** Chief Database Architect, JUANET Platform  
**Classification:** Technical / Architecture Reference  

---

## SECTION 1: DATABASE PHILOSOPHY

The database architecture of the JUANET Platform is designed to support a highly performant, scalable, and secure multi-tenant software-as-a-service (SaaS) operating system. Decoupling application logic from raw persistence and maintaining rigorous data integrity are the core objectives of this design. The system operates on the following foundational axioms:

### 1. PostgreSQL as the Authoritative Transactional Database
PostgreSQL is designated as the sole transactional source of truth for all business domains across the enterprise. Its advanced relational engine, extensive constraint mechanisms, support for complex JSONB data types, transactional isolation properties, and mature Row-Level Security (RLS) policies are essential to the platform's execution model. 
*   **Structured Core, Dynamic Margins**: Core relational objects (e.g., ledgers, users, organizations, invoices, projects) utilize strict schema definitions with explicit types and constraints. Auxiliary or high-velocity parameters (e.g., gateway-specific credentials, customizable system configurations, dynamically loaded third-party API payloads) are isolated within optimized `jsonb` columns.
*   **Zero NoSQL Exception**: Relational tables must store all data with transaction-level integrity. No external NoSQL datastores shall be introduced for transactional records.

### 2. UUID Primary Keys
To prevent key-collision vulnerabilities, ensure data portability across distributed environments, and mask internal metrics from external observers, all primary key fields across the database must utilize universally unique identifiers (UUIDv4) generated via `gen_random_uuid()`. 
*   No auto-incrementing integer sequences shall be used for primary identifiers. 
*   If user-facing sequential indices are required (e.g., invoice numbers, receipt codes, support ticket IDs), they must be generated through isolated tracking columns managed by custom transactional sequence counters.

### 3. Absolute ACID Compliance
All financial and administrative operations must satisfy complete Atomicity, Consistency, Isolation, and Durability guarantees:
*   **Isolation Level**: The platform defaults to the `READ COMMITTED` transaction isolation level. Financial ledger reconciliations and complex inventory or scheduling transactions must escalate to `REPEATABLE READ` or `SERIALIZABLE` isolation levels where double-booking or racing conditions present financial risk.
*   **Explicit Transaction Boundaries**: Write operations involving multiple logical state transitions (e.g., invoice marking, balance updating, payment receipt logging) must be executed in a single transaction block. If any phase of the block fails, the entire transaction is rolled back.

### 4. Native Multi-Tenancy
The database implements a shared-database, shared-schema tenancy model. Multiple organizations (tenants) reside within the same physical tables, separated logically by an `organization_id` column.
*   Logical isolation is enforced at the database engine level via PostgreSQL Row-Level Security (RLS). All queries executed on behalf of a tenant must be forced through RLS filters matching the caller’s authenticated identity context.

### 5. Soft Deletes
The destruction of business-critical information is prohibited. Destructive operations (e.g., deleting a client, project, invoice, or ticket) must execute as logical "soft deletes."
*   Every table prone to deletion must include a nullable `deleted_at` timestamp.
*   A query returning datasets to normal users must filter out rows where `deleted_at IS NOT NULL`.
*   Physical deletion is strictly restricted to ephemeral cache records or executing purging scripts during schema maintenance windows, governed by administrative permission overrides.

### 6. Immutable Audit Trails
Any modification (INSERT, UPDATE, DELETE) to sensitive administrative or financial entities must trigger an immutable audit record.
*   Audit logs are written to an isolated table partitioned by time.
*   No update or delete privileges are granted on audit tables to any application role, ensuring audit log immutability.

### 7. Event-Driven Database Architecture
Data modification events act as triggers for asynchronous business processes. Rather than relying on long-running synchronous database hooks, modifications to target records commit an event record to an outbound event log inside the same transaction. This is picked up by the system's event workers and processed asynchronously.

### 8. Double-Entry Accounting Compatibility
The finance module is constructed around double-entry ledger bookkeeping.
*   The system operates on an immutable ledger model: transactions are never updated or deleted once posted. 
*   Corrections are executed purely through reversal transactions, keeping an audit trail of every credit and debit modification.

### 9. Provider-Agnostic Payment Design
The database strictly separates the generic payment transactional status from individual external payment service provider (PSP) properties. A generic payment intent acts as the core interface, which links to specialized metadata storage containing specific API results from providers such as Safaricom Daraja, Paystack, Stripe, or PayPal.

---

## SECTION 2: NAMING STANDARDS

Consistency in database naming patterns is vital to minimize cognitive load, simplify ORM mapping, and prevent naming-collision errors. The entire JUANET schema must conform to the following standards:

### 1. General Conventions
*   **Snake Case Only**: All names of tables, columns, indexes, constraints, views, materialized views, triggers, functions, and schemas must be written in lowercase alphanumeric characters separated by underscores (e.g., `invoice_items`). No CamelCase, kebab-case, or spaces are permitted.
*   **Plural Table Names**: Relational tables must be named in the plural form, representing collections of entities (e.g., `organizations`, `users`, `projects`, `support_tickets`). 
*   **Singular Junction Tables**: Junction tables representing many-to-many associations must be named using singular nouns representing the combined domains (e.g., `user_role`, `role_permission`).
*   **Singular Column Names**: Columns representing individual attributes must be named in the singular form (e.g., `first_name`, `amount`, `status`).

### 2. Foreign Key Naming Conventions
Foreign key columns must explicitly reference the target entity in the singular form, appended with `_id` (e.g., `organization_id` referencing `organizations.id`, `user_id` referencing `users.id`). 
*   If a table has multiple foreign keys referencing the same parent table, descriptive prefixes must be applied (e.g., `created_by_user_id` and `assigned_to_user_id` both referencing `users.id`).

### 3. Constraint Naming Conventions
Every constraint defined in the database must be named explicitly to avoid system-generated names that obscure debugging. The standard suffix naming convention is:
*   **Primary Key**: `{table_name}_pkey` (e.g., `organizations_pkey`).
*   **Foreign Key**: `{table_name}_{column_name}_fkey` (e.g., `projects_organization_id_fkey`).
*   **Unique Constraint**: `{table_name}_{column_name}_key` (e.g., `users_email_key` or `payments_receipt_number_key` if multi-column: `{table_name}_{col1}_{col2}_key`).
*   **Check Constraint**: `{table_name}_{column_name}_check` (e.g., `invoices_status_check`).

### 4. Index Naming Conventions
Every database index must use a explicit naming format reflecting its scope and columns:
*   **Standard Index (B-Tree)**: `{table_name}_{column_name}_idx` (e.g., `projects_organization_id_idx`).
*   **Unique Index**: `{table_name}_{column_name}_uidx` (e.g., `payment_receipts_receipt_number_uidx`).
*   **Compound Index**: `{table_name}_{col1}_{col2}_idx` (e.g., `invoices_organization_id_status_idx`).
*   **Partial/Filtered Index**: `{table_name}_{column_name}_partial_idx` (e.g., `users_email_active_partial_idx` for active users).

### 5. Schema Structures
*   **The public Schema**: Contains standard operational tables used in daily client workflows.
*   **The audit Schema**: Houses logging tables and event store archives.
*   **The system Schema**: Contains global lookup configurations, localized tax standards, global currencies, and platform-wide monitoring logs.

---

## SECTION 3: GLOBAL COLUMNS

To maintain consistency and ease of tracking, every tenant-owned table in the JUANET platform must include a standardized block of structural columns. This standard block must be defined identically across all models:

| Column Name | Data Type | Nullability | Default Value | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **`id`** | `uuid` | NOT NULL | `gen_random_uuid()` | System-wide unique primary identifier. |
| **`organization_id`** | `uuid` | NOT NULL | None | Foreign key linking the row to its owning multi-tenant container. |
| **`created_at`** | `timestamp with time zone` | NOT NULL | `now()` | The exact UTC date and time the record was committed. |
| **`updated_at`** | `timestamp with time zone` | NOT NULL | `now()` | The exact UTC date and time the record was last modified. |
| **`deleted_at`** | `timestamp with time zone` | NULL | `NULL` | Nullable timestamp marking logical soft deletes. |
| **`created_by`** | `uuid` | NULL | None | Foreign key to `users.id` representing the user who authored the record. |
| **`updated_by`** | `uuid` | NULL | None | Foreign key to `users.id` representing the user who last edited the record. |
| **`version`** | `integer` | NOT NULL | `1` | Incrementing version counter used for optimistic concurrency locking. |

### Optimistic Concurrency Control with the `version` Column
To prevent overlapping modifications (the "lost update" problem) during concurrent client interactions, the JUANET database enforces optimistic concurrency control:
1.  When a record is read, the client application retrieves its current `version` integer (e.g., `version = 4`).
2.  When the client sends an update, the SQL statement must explicitly append a version verification clause:
    ```sql
    UPDATE table_name 
    SET attribute = 'new_value', version = version + 1, updated_at = now()
    WHERE id = :id AND version = :read_version;
    ```
3.  If another process updated the record in the interim, the `version` column will have advanced, causing the `WHERE` condition to fail. The database returns an update count of `0`.
4.  The application detects this mismatch, aborts the transaction, and prompts the client to refresh stale data, avoiding silent data overwrites.

---

## SECTION 4: CORE DOMAIN BOUNDARIES

The JUANET relational model is segregated into twelve distinct domains. Each domain maintains explicit boundary parameters, owns a isolated set of relational tables, and interfaces with neighboring domains through tightly constrained foreign keys or decoupled event models.

```
┌────────────────────────────────────────────────────────────────────────────┐
│                              1. CORE DOMAIN                                │
│        Organizations | Settings | Currencies | Exchange Rates | Audits     │
└─────────────────────────────────────┬──────────────────────────────────────┘
                                      │
       ┌──────────────────────────────┼──────────────────────────────┐
       ▼                              ▼                              ▼
┌──────────────┐              ┌──────────────┐              ┌──────────────┐
│  2. AUTH &   │              │    3. CRM    │              │ 4. PROJECTS  │
│  Staff RBAC  │              │  Leads, Orgs │              │ Milestones   │
└──────┬───────┘              └──────┬───────┘              └──────┬───────┘
       │                             │                             │
       └─────────────────────────────┼─────────────────────────────┘
                                     ▼
                      ┌──────────────────────────────┐
                      │    5. FINANCE & PAYMENTS     │
                      │  Invoices, Receipts, Ledgers │
                      └──────────────┬───────────────┘
                                     │
       ┌─────────────────────────────┴─────────────────────────────┐
       ▼                                                           ▼
┌──────────────┐                                            ┌──────────────┐
│  6. AUTOMATION & NOTIFICATION                             │ 7. SUPPORT   │
│  Workflows, Triggers, Outbox, SMTP                        │ Tickets      │
└───────────────────────────────────────────────────────────┴──────────────┘
```

### 1. Core Domain
*   **Purpose**: Manages global multi-tenant organization instances, platform-wide configurations, system-level lookups, localized parameters, and metadata standards.
*   **Owned Entities**: `organizations`, `organization_settings`, `currencies`, `exchange_rates`.
*   **Dependencies**: None. Acts as the root dependency for all other domains.
*   **Events Produced**: `organization.created`, `exchange_rates.updated`.
*   **Events Consumed**: None.

### 2. Authentication & Staff Security Domain (RBAC)
*   **Purpose**: Handles identity verification, security credentials, user roles, structural permission assignments, and organizational staff membership configurations.
*   **Owned Entities**: `users`, `profiles`, `roles`, `permissions`, `role_permissions`, `user_roles`, `organization_members`.
*   **Dependencies**: `Core Domain` (for organization mapping).
*   **Events Produced**: `user.authenticated`, `user_role.modified`, `membership.revoked`.
*   **Events Consumed**: `organization.created` (automatically initializes default administrator role for the new tenant).

### 3. CRM Domain
*   **Purpose**: Records external client interactions, manages prospect pipeline funnels, logs incoming enterprise leads, and structures customizable client service proposals.
*   **Owned Entities**: `leads`, `contacts`, `client_accounts`, `proposals`, `proposal_items`.
*   **Dependencies**: `Core Domain`, `Authentication Domain` (tracking account managers).
*   **Events Produced**: `lead.status_changed`, `proposal.sent`, `proposal.accepted`.
*   **Events Consumed**: `proposal.accepted` (automatically notifies the Finance Domain to generate pre-filled invoices).

### 4. Projects Domain
*   **Purpose**: Tracks client deliverables, schedules agile development sprints, tracks technical tasks, logs billable timesheets, and coordinates structural milestones.
*   **Owned Entities**: `projects`, `milestones`, `tasks`, `timesheets`, `project_members`.
*   **Dependencies**: `Core Domain`, `CRM Domain` (linking projects to active client accounts).
*   **Events Produced**: `project.created`, `milestone.completed`, `task.overdue`.
*   **Events Consumed**: `invoice.marked_paid` (triggers initialization of linked milestone phases).

### 5. Finance Domain
*   **Purpose**: Manages invoicing, maintains transactional ledgers, structures chart accounts, and processes payment allocations.
*   **Owned Entities**: `invoices`, `invoice_line_items`, `ledger_accounts`, `ledger_entries`, `tax_configurations`.
*   **Dependencies**: `Core Domain`, `CRM Domain` (billing clients), `Projects Domain` (billing structural milestones).
*   **Events Produced**: `invoice.created`, `invoice.past_due`, `ledger_entry.committed`.
*   **Events Consumed**: `payment.completed` (triggers automatic payment settlement on targeted invoices).

### 6. Payments Domain
*   **Purpose**: Standardizes multi-gateway configurations, records active payment sessions, processes inbound webhook callbacks, and decouples payment operations from core bookkeeping.
*   **Owned Entities**: `payment_gateways`, `payment_intents`, `payment_receipts`, `payment_health_metrics`.
*   **Dependencies**: `Core Domain`, `Finance Domain` (reconciling outstanding invoices).
*   **Events Produced**: `payment.initiated`, `payment.completed`, `payment.failed`, `gateway.health_degraded`.
*   **Events Consumed**: None.

### 7. Support Domain
*   **Purpose**: Manages post-delivery client communications, tickets, internal staff support assignments, and client feedback logging.
*   **Owned Entities**: `tickets`, `ticket_replies`, `support_categories`, `satisfaction_surveys`.
*   **Dependencies**: `Core Domain`, `Authentication Domain` (tracking assigned support agents).
*   **Events Produced**: `ticket.created`, `ticket.replied`, `ticket.closed`.
*   **Events Consumed**: None.

### 8. Marketplace Domain
*   **Purpose**: Supports extensions, localized module addons, template storefronts, and third-party service listings within the ecosystem.
*   **Owned Entities**: `marketplace_modules`, `installed_modules`, `module_reviews`, `module_usage_metrics`.
*   **Dependencies**: `Core Domain`.
*   **Events Produced**: `module.installed`, `module.uninstalled`.
*   **Events Consumed**: None.

### 9. CMS Domain
*   **Purpose**: Coordinates public-facing promotional websites, writes SEO blog articles, compiles client-facing documentation portals, and publishes knowledge bases.
*   **Owned Entities**: `blog_posts`, `categories`, `post_tags`, `kb_articles`, `site_pages`.
*   **Dependencies**: `Core Domain`, `Authentication Domain` (tracking authors).
*   **Events Produced**: `post.published`, `kb_article.updated`.
*   **Events Consumed**: `ticket.closed` (can trigger automated suggestions to knowledge base authors if ticket patterns match).

### 10. Automation Domain
*   **Purpose**: Formulates corporate workflow logic, handles conditional triggers, executes cron-scheduled logic, and integrates third-party webhooks.
*   **Owned Entities**: `workflows`, `workflow_triggers`, `workflow_actions`, `workflow_runs`, `workflow_run_logs`.
*   **Dependencies**: `Core Domain`.
*   **Events Produced**: `workflow.triggered`, `workflow.failed`.
*   **Events Consumed**: *All System Events* (acts as the core engine listening to and processing any outbound event).

### 11. AI Domain
*   **Purpose**: Indexes context parameters for Gemini model grounding, logs AI operations, handles model routing, and tracks token consumption limits.
*   **Owned Entities**: `ai_context_items`, `ai_prompts`, `ai_generations_audit`, `token_quotas`.
*   **Dependencies**: `Core Domain`, `Authentication Domain` (user context mapping).
*   **Events Produced**: `ai_generation.completed`, `quota_limit.reached`.
*   **Events Consumed**: None.

### 12. Notifications Domain
*   **Purpose**: Coordinates unified communication queues (SMS via Twilio, transactional Email via SMTP/Resend, and push events) to stakeholders.
*   **Owned Entities**: `notification_templates`, `notification_outbox`, `notification_logs`, `user_notification_settings`.
*   **Dependencies**: `Core Domain`, `Authentication Domain`.
*   **Events Produced**: `notification.dispatched`, `notification.bounce`.
*   **Events Consumed**: *Multiple Business Events* (e.g., `invoice.created`, `ticket.created`, `project.created` triggering automated notifications).

---

## SECTION 5: CORE ENTITIES

This section provides the conceptual design of the foundational Core and Authentication entities. Each entity is configured to support multi-tenant security, high scale, and audit compliance.

### 1. `organizations`
*   **Purpose**: Represents the root corporate tenant instance. Acts as the parent container for all tenant data.
*   **Responsibilities**: Records organizational brand details, isolates data namespaces, and governs tenant lifecycle states.
*   **Relationships**: 
    *   One-to-Many: `organization_settings` (1:1 active, 1:N history), `organization_members`.
    *   One-to-Many: All business entities across the public schema (e.g., invoices, projects, leads).
*   **Lifecycle**: Created on client signup. States transition through: `pending_setup` $\rightarrow$ `active` $\rightarrow$ `suspended` (e.g., non-payment) $\rightarrow$ `archived` (soft-deleted).
*   **Security Considerations**: Strict RLS policies restrict cross-tenant visibility. Direct data modifications must be authenticated with the `Super Admin` role or tenant-level `Owner` status.

### 2. `organization_settings`
*   **Purpose**: Controls tenant-specific dynamic interface parameters and runtime variables.
*   **Responsibilities**: Stores operational business parameters (e.g., custom domains, brand colors, localized language preferences, decimal formatting standards).
*   **Relationships**: 
    *   Many-to-One: `organizations`.
*   **Lifecycle**: Generated automatically with a default configuration block upon organization initialization. Remains coupled to the lifecycle of the organization.
*   **Security Considerations**: Non-encrypted values are accessible to team members. Sensitive variables (e.g., external API webhook verify hashes) are isolated to write-only admin views.

### 3. `organization_payment_gateways`
*   **Purpose**: Junction table registering tenant-specific credentials for payment providers.
*   **Responsibilities**: Maps specific organizations to active, verified payment gateway adapters.
*   **Relationships**: 
    *   Many-to-One: `organizations`, `payment_gateways`.
*   **Lifecycle**: Instantiated when an administrator configures a payment gateway. Remains active unless marked disabled or unlinked.
*   **Security Considerations**: **CRITICAL**. High-security entity. API keys, secrets, and private cert hashes must be encrypted using AES-256-GCM. Never store credentials in plain text.

### 4. `users`
*   **Purpose**: Central security principal identity registry for the platform.
*   **Responsibilities**: Manages master email credentials, system-level login state, password hash mappings, MFA parameters, and active authentication statuses.
*   **Relationships**:
    *   One-to-One: `profiles`.
    *   One-to-Many: `organization_members`, `user_roles`, `audit_logs`.
*   **Lifecycle**: Provisioned upon invitation or public signup. Security states transition through: `invited` $\rightarrow$ `pending_verification` $\rightarrow$ `active` $\rightarrow$ `locked_out` (due to threat detection) $\rightarrow$ `suspended`.
*   **Security Considerations**: High security isolation. No plain-text passwords are saved. Security records are written in an isolated schema bypassable only by specific PostgreSQL auth handlers.

### 5. `profiles`
*   **Purpose**: Stores non-security personal user information.
*   **Responsibilities**: Holds personal metadata (e.g., full name, telephone, profile image, localized time zone).
*   **Relationships**:
    *   One-to-One: `users`.
*   **Lifecycle**: Created concurrently with the related `users` record. Removed or anonymized upon GDPR "Right to be Forgotten" triggers.
*   **Security Considerations**: Accessible to authorized organization colleagues. Shared globally only where public listings are required (e.g., public blog author profile).

### 6. `roles`
*   **Purpose**: Defines a organizational role template for access control.
*   **Responsibilities**: Groups functional access rules into logical designations (e.g., `Owner`, `Project Manager`, `Finance Lead`, `Support Agent`).
*   **Relationships**:
    *   One-to-Many: `role_permissions`, `user_roles`.
*   **Lifecycle**: Standard platform roles are read-only and immutable. Custom roles can be authored by organization admins and soft-deleted when retired.
*   **Security Considerations**: RLS isolates custom roles to their originating tenant. Standard system roles (e.g., `Super Admin`) are defined globally.

### 7. `permissions`
*   **Purpose**: Granular permission definition library.
*   **Responsibilities**: Maps technical capabilities to specific modules and actions (e.g., `invoice:create`, `project:delete`, `system_settings:write`).
*   **Relationships**:
    *   One-to-Many: `role_permissions`.
*   **Lifecycle**: Immutable. Managed strictly by database migrations during platform upgrades. No runtime creation or modification of raw permission tokens is allowed.
*   **Security Considerations**: Read-only lookup data. Hardcoded to protect against unauthorized modifications that could compromise RBAC security.

### 8. `role_permissions`
*   **Purpose**: Junction table defining which permissions are granted to a role.
*   **Responsibilities**: Forms the core mapping structure of the platform's RBAC system.
*   **Relationships**:
    *   Many-to-One: `roles`, `permissions`.
*   **Lifecycle**: Created and modified by organization admins when configuring custom roles.
*   **Security Considerations**: Changes are audited. Incorrect modification can result in system-wide privilege escalation.

### 9. `user_roles`
*   **Purpose**: Junction table assigning roles to specific team users within an organization.
*   **Responsibilities**: Maps human users to their active authorization roles.
*   **Relationships**:
    *   Many-to-One: `users`, `roles`, `organizations`.
*   **Lifecycle**: Created when a user is invited to an organization. Revoked when membership is terminated.
*   **Security Considerations**: Tenant-isolated. Users cannot possess roles belonging to other organizations.

### 10. `currencies`
*   **Purpose**: System-wide lookup table containing active currency configurations.
*   **Responsibilities**: Defines international currencies (ISO codes, symbols, precision decimal parameters, formatting standards).
*   **Relationships**:
    *   One-to-Many: `exchange_rates` (as base or target), `invoices`, `payment_intents`.
*   **Lifecycle**: Populated globally during database bootstrap. Immutable.
*   **Security Considerations**: Read-only configuration. Read access is permitted system-wide.

### 11. `exchange_rates`
*   **Purpose**: Stores foreign currency exchange coefficients.
*   **Responsibilities**: Holds conversion values relative to the base reference currency (e.g., USD to KES conversion rates).
*   **Relationships**:
    *   Many-to-One: `currencies` (base currency), `currencies` (target currency).
*   **Lifecycle**: Dynamic. Updated daily via automated integration workers consuming exchange rate APIs. Historic rates are archived.
*   **Security Considerations**: Accessible globally for finance conversions. Only system integration accounts can write exchange rate data.

### 12. `payment_gateways`
*   **Purpose**: Global catalog of supported payment gateway providers.
*   **Responsibilities**: Registers system-wide payment adapters (e.g., Safaricom Daraja, PayHero, Stripe, PayPal, Paystack, Pesapal).
*   **Relationships**:
    *   One-to-Many: `organization_payment_gateways`, `payment_intents`.
*   **Lifecycle**: Created during system bootstrapping or software upgrades.
*   **Security Considerations**: Read-only globally. Credentials for specific tenants are kept in the isolated junction table.

### 13. `payment_health_metrics`
*   **Purpose**: Real-time performance tracker for payment integrations.
*   **Responsibilities**: Logs gateway response latency, webhook delays, and transaction failure rates to calculate automated health indices.
*   **Relationships**:
    *   Many-to-One: `payment_gateways`, `organizations` (optional, for tenant-specific gateways).
*   **Lifecycle**: High-frequency telemetry. Records are pruned periodically and summarized into historical logs.
*   **Security Considerations**: Internal diagnostic telemetry. Restricted to Super Admin dashboards.

### 14. `audit_logs`
*   **Purpose**: Immutable history of database modifications.
*   **Responsibilities**: Records transaction details, caller identities, IP addresses, old/new states, and execution contexts.
*   **Relationships**:
    *   Many-to-One: `users`, `organizations`.
*   **Lifecycle**: Immutable. Retained for compliance. Partitioned by date.
*   **Security Considerations**: **CRITICAL**. Access restricted. No UPDATE or DELETE privileges are granted on audit tables to any application role, ensuring logs are write-once, read-only.

---

## SECTION 6: RELATIONSHIP PRINCIPLES

Relational model safety is governed by strict structural patterns. Foreign key associations must satisfy the following principles:

### 1. One-to-One (1:1) Relationships
Used to extend base entities without cluttering main tables.
*   *Implementation Pattern*: The extension table (e.g., `profiles`) must define its foreign key as the primary key of the table (e.g., `id uuid PRIMARY KEY REFERENCES public.users(id)`).
*   *Cascade Rule*: Standardized to `ON DELETE CASCADE`. If the parent identity is deleted, the dependent profile must be destroyed immediately to avoid orphaned records.

### 2. One-to-Many (1:N) Relationships
The foundational building block of the relational model (e.g., an invoice contains multiple line items).
*   *Cascade Rule (Dependent Entities)*: If the child entity represents a structural component that cannot exist independently (e.g., `invoice_line_items` belonging to `invoices`), the cascade must set `ON DELETE CASCADE`.
*   *Restrict Rule (Critical Records)*: If the child entity represents a ledger or reference record (e.g., `payment_receipts` referencing a `payment_intent`), the relationship must enforce `ON DELETE RESTRICT`. This blocks deletions that would compromise financial records.

### 3. Many-to-Many (N:M) Relationships
Engineered exclusively via explicit junction tables containing a composite primary key.
*   *Implementation Pattern*: Junction tables (e.g., `role_permissions`) must define primary keys using the dual foreign keys:
    ```sql
    ALTER TABLE public.role_permissions 
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (role_id, permission_id);
    ```
*   *Cascade Rule*: Cascade deletes must be set on both foreign keys: `ON DELETE CASCADE`. If a role or a permission is removed, the junction mapping is cleared automatically.

### 4. Self-Referential Relationships
Used for hierarchical data (e.g., subtasks, parent support tickets, organizational hierarchy).
*   *Implementation Pattern*: A foreign key column referencing the primary key of the same table (e.g., `parent_task_id uuid REFERENCES public.tasks(id)`).
*   *Cascade Rule*: Enforced as `ON DELETE SET NULL` or `ON DELETE RESTRICT`. Under no circumstances is `ON DELETE CASCADE` permitted on self-referencing fields, preventing accidental recursive deletions.

### 5. Soft Delete Propagation
Soft deleting a parent entity does not physical trigger CASCADE deletes in child tables. Instead, business logic or soft delete handlers propagate logical soft deletions downward:
*   When an organization is soft-deleted, downstream triggers must flag all contained records (e.g., projects, invoices) as logical soft-deleted:
    ```sql
    UPDATE public.projects SET deleted_at = now() WHERE organization_id = :org_id;
    ```
*   Query models must account for soft deletion cascades by ensuring filters verify deletion states across the ownership tree.

---

## SECTION 7: MULTI-TENANT ISOLATION

Security inside JUANET is based on logical multi-tenant isolation. The platform maintains data partition guarantees using Row-Level Security (RLS) in a shared database schema.

```
                  ┌─────────────────────────────────────┐
                  │          CLIENT CONNECTION          │
                  │   - Claims Organization Context     │
                  │   - Sends JWT Authentication Token  │
                  └──────────────────┬──────────────────┘
                                     │
                                     ▼
                  ┌─────────────────────────────────────┐
                  │      POSTGRESQL SECURITY ENGINE     │
                  │   Evaluates Client JWT / app.tenant │
                  └──────────────────┬──────────────────┘
                                     │
                  ┌──────────────────┴──────────────────┐
                  │          ROW-LEVEL SECURITY         │
                  │   Does organization_id match context?│
                  └────────┬───────────────────────┬────┘
                           │ (Yes)                 │ (No)
                           ▼                       ▼
               ┌───────────────────────┐  ┌─────────────────┐
               │    Permit Read/Write  │  │  Access Denied  │
               └───────────────────────┘  └─────────────────┘
```

### 1. Data Isolation Philosophy
The core architecture prevents the leakage of tenant data across organizational boundaries. Multi-tenant isolation is enforced through the following design:
*   **Organization-Specific Namespace**: Every table containing tenant-owned data must define an `organization_id` foreign key referencing `public.organizations(id)`.
*   **Row-Level Security (RLS)**: PostgreSQL Row-Level Security is enabled on all tenant-owned tables. The database engine filters all read/write queries based on the caller's active organizational context:
    ```sql
    ALTER TABLE public.invoices ENABLE ROW LEVEL SECURITY;
    
    CREATE POLICY invoices_tenant_isolation_policy ON public.invoices
    USING (organization_id = (SELECT current_setting('app.current_organization_id', true)::uuid));
    ```

### 2. Context Initialization
During the execution of any API call or backend worker thread:
1.  The application opens a secure database transaction connection.
2.  Before executing any query, the application initializes the session-level tenant variables within the transaction context:
    ```sql
    SET LOCAL app.current_user_id = 'user-uuid-here';
    SET LOCAL app.current_organization_id = 'organization-uuid-here';
    ```
3.  The database engine enforces RLS policies for all subsequent statements in the transaction block. This isolates tenant data even if backend application code omits explicit tenant filters.

### 3. Reference and Global Tables
Certain tables are defined globally and excluded from tenant isolation filters:
*   **Shared Reference Tables**: Tables such as `currencies` and `payment_gateways` store static system metadata. These are globally readable by all tenants but writable only by system administrators or migrations.
*   **Global Configuration Tables**: Lookups like `exchange_rates` contain platform-wide conversion parameters. These are read-accessible globally without tenant filters.

### 4. Cross-Tenant Interaction Limits
*   Under no circumstances may a user read or modify data belonging to another organization unless they have active memberships in both tenants.
*   If a user has memberships in multiple organizations, they must establish an active tenant context during their API session. Cross-tenant queries are blocked, requiring the user to explicitly switch contexts.

---

## SECTION 8: AUDIT STRATEGY

Compliance, accountability, and diagnostic tracking require an immutable audit trail. The database records structural mutations on sensitive records in an audit log.

### 1. Captured Audit Dimensions
For every INSERT, UPDATE, or DELETE operation executed on audited tables, the audit record must capture the following metadata:

| Metadata Field | DataType | Captured Description |
| :--- | :--- | :--- |
| **`id`** | `uuid` | Unique audit log tracking identifier. |
| **`executed_by_user_id`** | `uuid` | The identity of the authenticated user who initiated the mutation. |
| **`organization_id`** | `uuid` | The organization context within which the change occurred. |
| **`action_type`** | `varchar(10)` | The SQL event category: `INSERT`, `UPDATE`, `DELETE`. |
| **`target_table`** | `varchar(100)`| The name of the table undergoing modification. |
| **`record_id`** | `uuid` | The primary key identifier of the modified row. |
| **`old_state`** | `jsonb` | The complete JSON representation of the row prior to the update (NULL on INSERT). |
| **`new_state`** | `jsonb` | The complete JSON representation of the row after the update (NULL on DELETE). |
| **`change_reason`** | `text` | An optional explanation provided by the actor (e.g., support ticket override explanation). |
| **`request_id`** | `uuid` | A tracking correlation ID tying the database change back to a specific HTTP request. |
| **`client_ip_address`**| `inet` | The source network IP of the initiating client. |
| **`user_agent`** | `text` | The browser or API user-agent signature of the client device. |
| **`service_origin`** | `varchar(100)`| The name of the service that executed the query (e.g., `api-gateway`, `billing-worker`). |

### 2. Partitioning Strategy
*   To maintain query performance, the `audit_logs` table is partitioned by month.
*   Historical logs older than a configured compliance duration (e.g., 7 years) are archived to secure, compressed cold storage and purged from the active database.

### 3. Audit Log Security
*   The `audit_logs` table is write-once, read-only.
*   No application roles are granted `UPDATE` or `DELETE` privileges on the `audit_logs` schema.
*   Security rules block modifications to audit logs, protecting the integrity of the system's tracking history.

---

## SECTION 9: CONCURRENCY STRATEGY

High-velocity environments with multiple concurrent users are prone to race conditions, overlapping updates, and deadlocks. The JUANET platform implements a structured concurrency strategy to maintain transactional integrity.

### 1. Optimistic Locking
Optimistic locking is the default concurrency pattern for standard user workflows:
*   Every table prone to overlapping edits (e.g., project details, task assignments, CRM contact records) must define an integer-based `version` column initialized to `1`.
*   Updates must increment this version and verify its previous state in the `WHERE` clause.
*   If the update fails, the application rolls back the change and prompts the user to refresh their view and resolve conflicts.

### 2. Pessimistic Locking
Used for high-integrity, short-duration financial operations (e.g., bank ledger entries, credit balance reductions):
*   Pessimistic locking is implemented via the `SELECT ... FOR UPDATE` pattern.
*   This locks targeted rows, forcing competing processes to wait until the active transaction block completes.
*   *Strict Execution Timeout*: Pessimistic locks must be wrapped in transactions that resolve within 2000ms. Long-running locks are blocked to maintain system responsiveness.

### 3. Deadlock Prevention Guidelines
To prevent circular waiting scenarios, all database queries must conform to these design rules:
*   **Consistent Object Lock Ordering**: Transactions modifying multiple tables must lock those tables in a consistent order (e.g., always modify `invoices` first, then write to `ledger_entries`).
*   **No Interactive Block Waiting**: Transactions must not wait for external network actions or user input while holding open database locks. All external API requests must be completed before opening locked database transactions.

### 4. Idempotent API Execution Keys
To prevent duplicate processing of critical administrative actions (such as invoice generation or billing payouts):
*   Write requests must include a unique client-generated UUID `idempotency_key` header.
*   The backend records this key in a schema table (`system.idempotency_keys`) at the start of the transaction.
*   If a duplicate request is received, the system returns the cached response, preventing duplicate executions.

---

## SECTION 10: EVENT INTEGRATION

JUANET uses an event outbox pattern to integrate transactional database changes with asynchronous event workers. This pattern decouples critical database updates from slow downstream processes (such as email dispatch, file indexing, or analytics tracking).

```
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      APPLICATION WRITE TRANSACTION                     │
 │  1. Commits business changes (e.g., invoice marked "paid")             │
 │  2. Appends event record to "outbound_events" table in same database  │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │ (Atomically Committed)
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      OUTBOUND EVENT STORE TABLE                        │
 │  - Serves as an immutable transactional event log                      │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │ (Polled by)
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                         EVENT CONSUMER SERVICE                         │
 │  - Reads pending events, dispatches notifications, and triggers       │
 │    downstream workflows asynchronously                                │
 └────────────────────────────────────────────────────────────────────────┘
```

### 1. Outbox Pattern Implementation
*   When a business entity is modified, the database transaction performs the business write and inserts an event record into `audit.outbound_events` in the same transaction block.
*   This ensures event publishing is atomic with the underlying database change.
*   Event consumers poll the outbox table, execute downstream workflows, and mark events as processed.

### 2. Master System Event Catalog
The following table outlines the transactional events emitted by core entities, detailing their triggers and downstream integrations:

| Emitting Entity | Triggering Database Event | Downstream Systems Integrated |
| :--- | :--- | :--- |
| **`organizations`** | `organization.created` | Initializes default charts, triggers billing registration. |
| **`users`** | `user.registered` | Dispatches verification mail, sets up empty security profile. |
| **`leads`** | `lead.created` | Initializes CRM pipeline, matches AI scoring criteria. |
| **`proposals`** | `proposal.accepted` | Generates draft invoice, creates client delivery project. |
| **`invoices`** | `invoice.created` | Registers notification workflow, queues email invoice. |
| **`invoices`** | `invoice.past_due` | Triggers collections notifications, flags account standing. |
| **`payment_intents`**| `payment.initiated` | Configures monitoring metrics, logs gateway checkout activity. |
| **`payment_receipts`**| `payment.completed` | Reconciles invoice status, posts to double-entry ledger. |
| **`tickets`** | `ticket.created` | Assigns agent, dispatches client confirmation notification. |
| **`workflows`** | `workflow.executed` | Logs run metrics, notifies administrators of automation errors. |

---

## SECTION 11: SECURITY STRATEGY

The database is the final line of defense for user and organizational data. Security strategy inside JUANET focuses on defensive configuration across multiple layers.

### 1. Row-Level Security (RLS) Philosophy
*   RLS is enabled by default across all operational schemas.
*   RLS policies verify that the authenticated caller’s security identifier (`app.current_user_id`) possesses valid membership in the targeted organization (`organization_id`).
*   **Implicit Default Deny**: If no policy is explicitly defined, access to the table is denied by default.

### 2. Permission Inheritance & Role Hierarchy
*   Access control does not assign individual permissions directly to users.
*   Permissions (e.g., `invoice:write`) are assigned to Roles, and Roles are assigned to Users via the junction table `user_roles`.
*   RLS policies match permission capabilities by querying role assignments within the active organization:
    ```sql
    CREATE POLICY invoices_write_policy ON public.invoices
    FOR UPDATE
    USING (
      organization_id = (SELECT current_setting('app.current_organization_id', true)::uuid)
      AND EXISTS (
        SELECT 1 FROM public.user_roles ur
        JOIN public.role_permissions rp ON rp.role_id = ur.role_id
        JOIN public.permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = (SELECT current_setting('app.current_user_id', true)::uuid)
          AND ur.organization_id = invoices.organization_id
          AND p.token = 'invoice:write'
      )
    );
    ```

### 3. Service Account and Administrator Bypass
*   **System Workers**: System processes (such as automated billing workers) run under dedicated PostgreSQL service roles.
*   These service roles are granted specific schemas access, bypassing tenant-level RLS policies to perform batch operations (e.g., global exchange rate updates or automated nightly invoicing).
*   **Super Admin Access**: Platform administrators can toggle a global configuration parameter to bypass standard RLS filters for support and debugging, with all admin actions logged to the audit trail.

### 4. Data Encryption Boundaries
*   **Data-at-Rest**: Handled natively by the cloud storage provider using enterprise-grade AES-256 encryption.
*   **Application-Level Field Encryption**: Highly sensitive fields (such as gateway credentials, private keys, and API tokens) are encrypted before database commit using AES-256-GCM, with keys managed by a secure Key Management Service (KMS).

---

## SECTION 12: FUTURE EXPANSION

A core requirement of the JUANET architecture is the ability to adapt as the business expands. The database schema must support new modules, payment gateways, and global configurations without requiring destructive changes to existing tables.

### 1. Extensible Gateway Adapter Framework
Adding a new payment provider (e.g., Flutterwave) does not require altering core schemas:
*   Add the provider code (e.g., `flutterwave`) to the database lookup tables.
*   Create a new payment gateway configuration record in `payment_gateways` containing the necessary credentials and endpoint references in its `jsonb` fields.
*   No changes are required to `payment_intents` or `payment_receipts`, as these tables are decoupled from specific provider attributes.

### 2. Multi-Currency and Multi-Country Scalability
*   All financial and balance tables store currency-agnostic amounts accompanied by a foreign key referencing the `currencies` table.
*   This allows the system to support new currencies, multi-currency invoicing, and cross-border payments natively without schema modification.

### 3. Modular System Schema Extensions
*   New platform modules (e.g., an advanced HR timesheet tracker or subscription billing engine) must be designed within separate database schemas (e.g., `hr_module`).
*   These new tables reference core entities (`users.id`, `organizations.id`) via foreign keys but remain logically isolated, preventing schema clutter in the core namespaces.

---

## SECTION 13: ENTERPRISE STRUCTURAL ENHANCEMENTS (PHASE 2.2-A)

To support high-growth global SaaS operations, this section outlines the core design principles of our 10 new database structural expansions.

### 1. Hardened Gateway Credentials Isolation
Storing sensitive API secrets, merchant codes, and OAuth tokens in loose, unstructured `jsonb` columns presents a high security and decryption surface.
*   **The Credentials Vault**: The physical separation of `gateway_credentials` from `organization_payment_gateways` ensures that decryption routines can target specific narrow rows rather than parsing entire records.
*   **Encrypted Fields**: Values are encrypted using AES-256-GCM in the application layer prior to insertion. Each credential can have an independent expiration, enabling automated token rotation flows.

### 2. Autonomous Dynamic Payment Routing
A standard hardcoded routing switch-case in application logic degrades system adaptability and lacks high-availability failover features.
*   **Rule-Based Ingestion**: The `payment_routing_rules` engine matches the currency, country, and transaction threshold limits to select the highest-scoring gateway.
*   **Seamless Integration**: If Safaricom's Daraja gateway degrades (based on telemetry), the application evaluates the routing rules to fall back to PayHero automatically, ensuring zero transactional drops.

### 3. Geographical Localization and Global Growth
Multi-currency setups require localized definitions of country boundaries, phone routing prefixes, and legal jurisdictions.
*   **National Core**: The `countries` table serves as a relational bridge connecting currency lookups with tax jurisdictions and timezone specifications, providing a firm foundation for VAT, GST, and localized SMS routing.

### 4. Advanced Multi-Jurisdictional Tax Compliance
Simple tax percentages fail to address cross-border B2B scenarios, zero-rated entities, or complex regional exemptions.
*   **Jurisdiction Mapping**: The system separates tax rates from operational rules. The combination of `tax_jurisdictions`, `tax_rules`, and `invoice_tax_lines` enables precise compliance with Kenya VAT (16%), UK VAT, EU Reverse-Charge, or international zero-rating guidelines without schema redesigns.

### 5. Strict Type-Safe Status Enumerations
Free-text status values in database columns are prone to typo bugs, make indexing inefficient, and complicate translation engines.
*   **Physical Lookup Boundaries**: Translating text statuses to explicit foreign key references (e.g. `invoice_statuses`) guarantees complete referential integrity.
*   **Enhanced Reporting**: Status lookups enable clean multi-lingual labels, custom visual themes, and efficient join-based reporting indices.

### 6. Dynamic SaaS Feature Gating
To support plan tiering and selective module activation (e.g., activating AI prompts or complex bookkeeping ledgers for specific premium accounts).
*   **Granular Flag Gating**: The `organization_features` table stores individual active flags mapped to specific modules. Feature gates support trial parameters and subscription grace periods without requiring code modifications.

### 7. Core SaaS Subscription and Metered Billing
SaaS operating models require scalable billing architectures to manage packages, billing cycles, multi-item add-ons, and metered usage counters.
*   **Hierarchical Tiering**: Binds organizations to plans via `plans`, `subscriptions`, and `subscription_items`.
*   **Metered Counters**: The `subscription_usage` table logs resource consumption (e.g., total processed AI tokens or outbound SMS counts) to facilitate usage-based billing structures.

### 8. Hardened Client API Integration Keys
Exposing backend routes requires cryptographically secure, token-based authentication mechanisms.
*   **One-Way Hashing**: The `api_keys` table stores SHA-256 hashes of client-generated API tokens. Applications verify incoming requests against these hashes, enforcing granular scope limitations (e.g., `invoice:read`).

### 9. Outbound Webhook Integration Registry
To enable external developer ecosystems, tenant applications must be able to subscribe to real-time, event-driven webhooks.
*   **Reliable Delivery**: The `organization_webhooks` table stores endpoint targets, signature verification secrets, and subscribed event categories. Outbound event loops dispatch payloads to these endpoints asynchronously, with built-in retry and audit logs.

### 10. Centralized Secure Document Storage
Scattering file and attachment metadata across independent CRM, support, or project modules makes global audits and security checks impossible.
*   **Storage Registry**: The `files` table centralizes all document metadata across modules.
*   **Hardened Security**: Includes storage provider routes, file sizes, MIME types, SHA-256 checksums to verify integrity, and explicit `virus_scan_status` parameters to quarantine malicious uploads before client delivery.

---

### Verification and Master Specification Alignment
This Database Blueprint has been designed in strict alignment with the `JUANET_Master_Specification.md` (Version 1.3):
*   **Payment Hub Decoupling**: Conforms to the provider-agnostic payment gateway specifications defined in Phase 1.
*   **Multi-Tenancy**: Aligns with the core multitenancy definitions established in the master architecture.
*   **Audit Compliance**: Adheres to the auditing, logging, and financial ledger guidelines of the core specifications.
*   **Enterprise Extensions**: The 10 SaaS improvements (Phase 2.2-A Amendments) have been integrated into the central blueprint, establishing a highly scalable, enterprise-grade architecture.
*   **Conflicts Found**: None. All entity mappings, relationship rules, and isolation strategies are consistent with the platform's architectural standards.

---
**End of Enterprise Database Blueprint Document**
