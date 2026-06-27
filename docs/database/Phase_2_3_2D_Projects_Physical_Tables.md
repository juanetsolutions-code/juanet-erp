# JUANET PostgreSQL Projects Table Specifications
## Phase 2.3.2D — Projects Physical Tables
**Document Version:** 1.0  
**Author:** Chief PostgreSQL Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Database Schema Definition  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical table definitions for the Projects domain of the JUANET Enterprise SaaS Platform. All schemas, parameters, and constraints defined herein are binding and must be implemented exactly by future migrations, DDL scripts, or ORM declarations.

These specifications conform strictly to:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`
*   `Phase_2_3_2A_Core_Physical_Tables.md`
*   `Phase_2_3_2B_Authentication_Physical_Tables.md`
*   `Phase_2_3_2C_CRM_Physical_Tables.md`

All Projects domain tables reside within the `public` schema (with some metadata lookups in the `system` schema) as standard multi-tenant business entities. They utilize standard database standards, including Row-Level Security (RLS) keying based on `organization_id` to enforce strict logical tenant isolation.

### 1.1 Mandatory Global Columns Standard
As established in `Phase_2_3_1_PostgreSQL_Physical_Standards.md` (Section 5), **every tenant-owned business entity** within the `public` schema must define the following standard structural block of columns. No table in the `public` schema is exempt from this requirement. To preserve readability and focus on domain-specific attributes, this structural block is represented as **`[MANDATORY GLOBAL COLUMNS]`** in each table catalog:

```sql
id              uuid                        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
organization_id uuid                        NOT NULL REFERENCES system.organizations(id) ON DELETE RESTRICT,
created_at      timestamp with time zone    NOT NULL DEFAULT now(),
updated_at      timestamp with time zone    NOT NULL DEFAULT now(),
deleted_at      timestamp with time zone    NULL DEFAULT NULL,
created_by      uuid                        NULL REFERENCES security.users(id) ON DELETE SET NULL,
updated_by      uuid                        NULL REFERENCES security.users(id) ON DELETE SET NULL,
version         integer                     NOT NULL DEFAULT 1
```

---

## 2. GLOBAL ARCHITECTURAL FRAMEWORKS & DECOUPLING

### 2.1 Low-Coupling Domain Integration Architecture
To prevent tight coupling between Projects and external domains, JUANET enforces an **Asynchronous Outbox Event Pattern**:
1.  **CRM Integration**: When a proposal enters the `'accepted'` state (`public.proposals`), a `proposal.accepted` event is written to `audit.outbound_events`. The integration engine consumes this event and creates a new project using template mapping, with no direct foreign key coupling.
2.  **Finance Integration**: Approved timesheet hours or completed milestone events (`timesheet.approved`, `milestone.completed`) emit transaction records. The billing engine converts these into invoice lines (`public.invoice_items`), decoupled via unique transactional idempotency keys.
3.  **Automation & Notifications**: Mutating Project states produce events (e.g. `task.assigned`). The Automation Engine triggers configured custom workflows, and the Notification Engine dispatches Slack/email alerts based on localized preference states.
4.  **AI Services Engine**: Deep-learning scoring and risk classification models evaluate project and task metrics via read-only replica databases. The models publish risk scores asynchronously to the `risk_register` or `tasks` tables.

### 2.2 Enterprise Performance & Scaling Strategy
At a scale of **millions of projects and tens of millions of tasks**, the following physical database optimizations are mandated:
*   **Kanban Optimization**: High-efficiency drag-and-drop board state tracking utilizes partial composite B-Tree indexes on status and vertical display order.
*   **Gantt & Timeline Rendering**: Chronological range queries (e.g., Gantt charts) use covering composite indexes containing dates, statuses, and identifiers.
*   **Dependency Resolution**: To prevent recursive loop locks when plotting the Critical Path, project schedules utilize `WITH RECURSIVE` CTEs bounded by depth checking limits.
*   **Resource Scheduling & Workload Balancing**: Real-time allocation charts utilize timesheet/allocation range lookups optimized via GIST indexing and range overlap exclusions.
*   **Partitioning**: High-write tables like `public.project_activity_logs` and `public.task_time_logs` are partitioned by range on `created_at` (monthly partitions).

### 2.3 Security, GDPR & Data Retention Framework
*   **Logical Isolation**: Implemented via PostgreSQL Row-Level Security (RLS) on `organization_id`.
*   **GDPR Alignment**: Contains personal information (PII) like names, emails, and comments. Supports the "Right to be Forgotten" via complete physical deletion of soft-deleted records or target cryptographic scrubbing of sensitive text fields upon request.
*   **PII & Encryption**: Column-level encryption using `pgp_sym_encrypt` is used on high-sensitivity corporate files and credential integration tokens.

---

## 3. MASTER PROJECTS DOMAIN TABLES

---

### 3.1 Table Name: `public.project_templates`

#### 3.1.1 Table Overview
*   **Purpose**: Stores reusable project structures, phases, and configurations, allowing tenants to standardize delivery workflows.
*   **Business Responsibility**: Process Standardization & Blueprint Governance
*   **Ownership Domain**: PMO Config Core (Lookup / Low-Write)
*   **Dependencies**: `system.organizations`
*   **Expected Lifetime**: Indefinite
*   **Read / Write Frequency**: 95% Reads / 5% Writes
*   **Expected Growth**: Very Low (~10-100 templates per tenant)
*   **Retention Policy**: Retained indefinitely unless explicitly deleted by a PMO admin.

#### 3.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `name` | `varchar(150)` | NO | None | None | 2-150 characters | None | Public | Identifies the template. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Contextual overview. |
| `estimated_days` | `integer` | NO | `30` | None | Value > 0 | None | Public | Standardized base timeframe. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Controls template visibility. |
| `settings` | `jsonb` | NO | `'{}'`::jsonb | None | Valid JSON | None | Public | Dynamic PMO parameter flags. |

#### 3.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**: Standard `organization_id` reference.
*   **Check Constraints**:
    *   `CONSTRAINT project_templates_name_length CHECK (length(trim(name)) >= 2)`
    *   `CONSTRAINT project_templates_est_days CHECK (estimated_days > 0)`

#### 3.1.4 Index Strategy
*   **`project_templates_pkey`**: Primary Key index.
*   **`project_templates_org_active_idx`**: Partial B-Tree Index on `(organization_id, name)` WHERE `deleted_at IS NULL AND is_active = true`. Optimizes template selectors.

#### 3.1.5 Full-Text Search Strategy
*   **Searchable Entity**: Yes.
*   **Strategy**: Uses `pg_trgm` GIN index on `name` and `description` to allow fuzzy searching.

#### 3.1.6 Row-Level Security (RLS)
*   **Read**: Authorized tenant members.
*   **Insert / Update / Delete**: Tenant PMO / Administrator roles.
*   **Service Account Bypass**: Enabled.

#### 3.1.7 Audit & Locking
*   **Auditing**: INSERT, UPDATE, DELETE actions emit structured events to `audit.security_events`.
*   **Optimistic Locking**: Handled by the `version` column.

#### 3.1.8 Event Matrix
*   **Events Produced**: `project_template.created`, `project_template.updated`, `project_template.deleted`
*   **Events Consumed**: None

#### 3.1.9 Validation Rules
*   Template name must be unique within the tenant space.
*   Estimated days must be greater than zero.

#### 3.1.10 Performance Considerations
*   Low row counts (100 rows per tenant max). Cached in memory.

#### 3.1.11 Relationships
*   **One-to-Many**: `public.project_template_tasks`, `public.projects`

---

### 3.2 Table Name: `public.project_template_tasks`

#### 3.2.1 Table Overview
*   **Purpose**: Stores the skeletal tasks, milestones, and task dependencies associated with a project template.
*   **Business Responsibility**: PMO Task Skeletal Blueprinting
*   **Ownership Domain**: PMO Config Core (Lookup)
*   **Dependencies**: `public.project_templates`
*   **Expected Lifetime**: Indefinite
*   **Read / Write Frequency**: 95% Reads / 5% Writes
*   **Expected Growth**: Low (Linear multiplication of templates)
*   **Retention Policy**: Inherits parent template lifetime.

#### 3.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `template_id` | `uuid` | NO | None | FK -> `project_templates(id)` | Valid UUID | None | Public | Parent template link. |
| `title` | `varchar(200)` | NO | None | None | 1-200 characters | None | Public | Name of skeletal task. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Skeletal instructions. |
| `relative_start_day`| `integer` | NO | `0` | None | Day >= 0 | None | Public | Relative offset to start. |
| `duration_days` | `integer` | NO | `1` | None | Duration > 0 | None | Public | Typical duration window. |
| `is_milestone` | `boolean` | NO | `false` | None | Boolean | None | Public | Marks milestone phases. |
| `sort_order` | `integer` | NO | `10` | None | Sort >= 0 | None | Public | Custom vertical order. |

#### 3.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `template_id REFERENCES public.project_templates(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT template_tasks_duration CHECK (duration_days > 0)`
    *   `CONSTRAINT template_tasks_start_day CHECK (relative_start_day >= 0)`

#### 3.2.4 Index Strategy
*   **`project_template_tasks_pkey`**: Primary Key index.
*   **`project_template_tasks_parent_idx`**: B-Tree Index on `(template_id, sort_order)`. Optimizes sequential building of new projects.

#### 3.2.5 Full-Text Search Strategy
*   None (not a search-heavy entity).

#### 3.2.6 Row-Level Security (RLS)
*   **Read**: Tenant members.
*   **Insert / Update / Delete**: Tenant PMO administrators.

#### 3.2.7 Audit & Locking
*   **Auditing**: Schema mutations audited via outbox logs.
*   **Optimistic Locking**: Tracked via `version`.

#### 3.2.8 Event Matrix
*   **Events Produced**: None (Static schema changes)
*   **Events Consumed**: None

#### 3.2.9 Validation Rules
*   `duration_days` must be greater than zero.

#### 3.2.10 Performance Considerations
*   Low row counts. Loaded in single transaction block during project generation.

#### 3.2.11 Relationships
*   **Many-to-One**: `public.project_templates`

---

### 3.3 Table Name: `public.projects`

#### 3.3.1 Table Overview
*   **Purpose**: The central entity representing a scoped professional engagement, customer contract fulfillment, or internal initiative.
*   **Business Responsibility**: Execution, Profitability & Delivery Governance
*   **Ownership Domain**: Projects Core (High-Read, Medium-Write)
*   **Dependencies**: `system.organizations`, `public.client_accounts`, `public.project_calendars`
*   **Expected Lifetime**: Long-Term (Active during execution, archived post-closure)
*   **Read / Write Frequency**: 80% Reads / 20% Writes
*   **Expected Growth**: Medium (1,000 - 50,000 active projects per tenant)
*   **Retention Policy**: Retained indefinitely for delivery audit and historical P&L analytics.

#### 3.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `client_account_id`| `uuid` | YES | `NULL` | FK -> `client_accounts(id)` | Valid UUID | None | Public | Associated CRM client. |
| `template_id` | `uuid` | YES | `NULL` | FK -> `project_templates(id)` | Valid UUID | None | Public | Blueprint origin. |
| `calendar_id` | `uuid` | NO | None | FK -> `project_calendars(id)` | Valid UUID | None | Public | Defines working schedule. |
| `name` | `varchar(200)` | NO | None | None | 2-200 characters | None | Public | Project name. |
| `code` | `varchar(50)` | NO | None | Unique per Org | Upper, alphanumeric | None | Public | Human-friendly identifier. |
| `status` | `varchar(30)` | NO | `'draft'` | None | Valid statuses | None | Public | Current state of project. |
| `budget` | `numeric(18,2)` | NO | `0.00` | None | Budget >= 0 | None | Financial | Projected delivery budget. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)` | Valid UUID | None | Financial | Currency code. |
| `start_date` | `date` | YES | `NULL` | None | Start date | None | Public | Scheduled start date. |
| `end_date` | `date` | YES | `NULL` | None | End >= Start | None | Public | Scheduled completion date. |
| `actual_start_date`| `date` | YES | `NULL` | None | Date | None | Public | Real-world start timestamp. |
| `actual_end_date` | `date` | YES | `NULL` | None | Date >= Actual Start | None | Public | Real-world closure date. |
| `completion_percentage`|`numeric(5,2)`|NO | `0.00` | None | `0.00` to `100.00` | None | Public | Current completion progress. |
| `search_vector` | `tsvector` | YES | `NULL` | None | None | None | Public | Native search indices. |

#### 3.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `client_account_id REFERENCES public.client_accounts(id) ON DELETE RESTRICT`
    *   `template_id REFERENCES public.project_templates(id) ON DELETE SET NULL`
    *   `calendar_id REFERENCES public.project_calendars(id) ON DELETE RESTRICT`
    *   `currency_id REFERENCES system.currencies(id) ON DELETE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT projects_org_code_key UNIQUE (organization_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT projects_date_order CHECK (end_date >= start_date)`
    *   `CONSTRAINT projects_actual_date_order CHECK (actual_end_date >= actual_start_date)`
    *   `CONSTRAINT projects_completion CHECK (completion_percentage >= 0.00 AND completion_percentage <= 100.00)`
    *   `CONSTRAINT projects_budget_positive CHECK (budget >= 0.00)`
    *   `CONSTRAINT projects_status CHECK (status IN ('draft', 'active', 'on_hold', 'completed', 'canceled', 'archived'))`

#### 3.3.4 Index Strategy
*   **`projects_pkey`**: Primary Key index.
*   **`projects_org_code_uidx`**: Unique Index on `(organization_id, code)`.
*   **`projects_status_idx`**: B-Tree Index on `(organization_id, status)` WHERE `deleted_at IS NULL`. Optimizes executive dashboard KPI tracking.
*   **`projects_timeline_idx`**: Covering B-Tree Index on `(organization_id, start_date, end_date)` INCLUDE `(id, name, status)`. Speeds up chronological timeline list rendering.
*   **`projects_search_gin_idx`**: GIN Index on `search_vector` for full-text queries.

#### 3.3.5 Full-Text Search Strategy
*   **tsvector Column**: `search_vector` is computed via a trigger compiling:
    `to_tsvector('english', coalesce(name, '') || ' ' || coalesce(code, '') || ' ' || coalesce(status, ''))`
*   **Trigrams**: `pg_trgm` on `name` ensures fast partial prefix searches.

#### 3.3.6 Row-Level Security (RLS)
*   **Read**: Tenant users. Users with specific project member status have read permission.
*   **Insert / Update**: PMO/Managers.
*   **Delete**: Soft delete only. Physical delete restricted to Super Admins.

#### 3.3.7 Audit & Locking
*   **Auditing**: Creation, status change, and closure trigger append events in `project_status_history` and `project_activity_logs`.
*   **Locking**: Tracked via `version`.

#### 3.3.8 Event Matrix
*   **Events Produced**: `project.created`, `project.updated`, `project.status_changed`, `project.completed`, `project.archived`
*   **Events Consumed**: `proposal.accepted` (Triggers automatic project provisioning from blueprint)

#### 3.3.9 Validation Rules
*   `end_date` must be equal to or greater than `start_date`.
*   Project `code` must conform to the regular expression `^[A-Z0-9\_]{3,50}$`.

#### 3.3.10 Performance Considerations
*   Dashboard reporting queries utilize Materialized Views (`mv_monthly_tenant_summaries`) to calculate aggregate completion percentages, preventing expensive run-time joins across large tables.

#### 3.3.11 Relationships
*   **Many-to-One**: `public.client_accounts`, `public.project_calendars`
*   **One-to-Many**: `public.milestones`, `public.tasks`, `public.project_members`, `public.project_status_history`

---

### 3.4 Table Name: `public.project_status_history`

#### 3.4.1 Table Overview
*   **Purpose**: Records an immutable chronological audit trail of project state shifts.
*   **Business Responsibility**: Audit Verification & Delivery Velocity Analytics
*   **Ownership Domain**: Projects Core (Immutable / Append-Only)
*   **Dependencies**: `public.projects`
*   **Expected Lifetime**: Indefinite
*   **Read / Write Frequency**: 90% Reads / 10% Writes
*   **Expected Growth**: Linear based on project state transitions.
*   **Retention Policy**: Aligned with the parent project retention period.

#### 3.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | Valid UUID | None | Public | Record primary key. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Valid UUID | None | Public | Multi-tenant boundary. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Associated project. |
| `old_status` | `varchar(30)` | YES | `NULL` | None | Valid status | None | Public | Pre-transition state. |
| `new_status` | `varchar(30)` | NO | None | None | Valid status | None | Public | Post-transition state. |
| `changed_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Timestamp of transition. |
| `changed_by` | `uuid` | NO | None | FK -> `security.users(id)` | Valid UUID | None | Public | Actor responsible. |
| `notes` | `text` | YES | `NULL` | None | None | None | Public | Business context/reasons. |

#### 3.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE`
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
    *   `changed_by REFERENCES security.users(id) ON DELETE RESTRICT`

#### 3.4.4 Index Strategy
*   **`project_status_history_pkey`**: Primary Key index.
*   **`project_status_history_project_idx`**: B-Tree Index on `(project_id, changed_at DESC)`. Used to compile transition history instantly.

#### 3.4.5 Full-Text Search Strategy
*   None.

#### 3.4.6 Row-Level Security (RLS)
*   **Read**: Tenant members.
*   **Insert**: Generated via database trigger on project state mutation.
*   **Update / Delete**: Strictly prohibited (Immutable audit table).

#### 3.4.7 Audit & Locking
*   **Auditing**: Naturally serves as an audit ledger.
*   **Optimistic Locking**: None required (Append-only).

#### 3.4.8 Event Matrix
*   **Events Produced**: `project.status_logged`
*   **Events Consumed**: None

#### 3.4.9 Relationships
*   **Many-to-One**: `public.projects`

---

### 3.5 Table Name: `public.project_members`

#### 3.5.1 Table Overview
*   **Purpose**: Registers users assigned to a project, granting functional project access.
*   **Business Responsibility**: Staffing Authorization & Access Control Governance
*   **Ownership Domain**: Resource Staffing (High-Read, Low-Write)
*   **Dependencies**: `public.projects`, `security.users`, `public.project_roles`
*   **Expected Lifetime**: Persistent
*   **Read / Write Frequency**: 90% Reads / 10% Writes
*   **Expected Growth**: Linear ($O(N)$ multiplier of project count)
*   **Retention Policy**: Retained indefinitely for legal and project billing validation.

#### 3.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Link to project. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | Valid UUID | None | Public | Associated staff member. |
| `role_id` | `uuid` | NO | None | FK -> `project_roles(id)` | Valid UUID | None | Public | Associated project permissions. |
| `allocated_percentage`|`numeric(5,2)`| NO | `100.00` | None | `0.00` to `100.00` | None | Public | Resource bandwidth allocated. |

#### 3.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT`
    *   `role_id REFERENCES public.project_roles(id) ON DELETE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT project_members_unique_assignment UNIQUE (project_id, user_id)`
*   **Check Constraints**:
    *   `CONSTRAINT project_members_allocation CHECK (allocated_percentage >= 0.00 AND allocated_percentage <= 100.00)`

#### 3.5.4 Index Strategy
*   **`project_members_pkey`**: Primary Key index.
*   **`project_members_project_user_uidx`**: Unique Index on `(project_id, user_id)`.
*   **`project_members_user_idx`**: B-Tree Index on `(user_id)`. Optimizes portal routing when loading "My Assigned Projects".

#### 3.5.5 Row-Level Security (RLS)
*   **Read**: Tenant members.
*   **Insert / Update / Delete**: Project Managers or PMO administrators.

#### 3.5.6 Audit & Locking
*   **Auditing**: Staff changes log record insertions and removals to `project_activity_logs`.
*   **Locking**: Optimistic locking via `version`.

#### 3.5.7 Event Matrix
*   **Events Produced**: `project_member.assigned`, `project_member.removed`
*   **Events Consumed**: `user.invited` (Seeds core member profile settings)

#### 3.5.8 Relationships
*   **Many-to-One**: `public.projects`, `security.users`, `public.project_roles`

---

### 3.6 Table Name: `public.project_roles`

#### 3.6.1 Table Overview
*   **Purpose**: Defines specialized permission-scoped positions on projects (e.g., Lead Architect, QA Engineer, Business Analyst, Client Viewer).
*   **Business Responsibility**: Staff Permissions & Billing Grade Governance
*   **Ownership Domain**: PMO Config Core (Lookup)
*   **Dependencies**: `system.organizations`

#### 3.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Title of the role. |
| `code` | `varchar(50)` | NO | None | Unique per Org | snake_case | None | Public | Code lookup value. |
| `billing_rate` | `numeric(18,2)` | NO | `0.00` | None | Rate >= 0 | None | Financial | Default hourly billing grade. |
| `permissions` | `jsonb` | NO | `'{}'`::jsonb | None | Valid JSON | None | Public | Permission scopes list. |

#### 3.6.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraints**:
    *   `CONSTRAINT project_roles_org_code_key UNIQUE (organization_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT project_roles_billing_rate CHECK (billing_rate >= 0.00)`
    *   `CONSTRAINT project_roles_code_format CHECK (code ~ '^[a-z0-9\_]+$')`

#### 3.6.4 Index Strategy
*   **`project_roles_pkey`**: Primary Key index.
*   **`project_roles_org_code_uidx`**: Unique Index on `(organization_id, code)`. Used for authorization checks.

---

### 3.7 Table Name: `public.project_tags`

#### 3.7.1 Table Overview
*   **Purpose**: Polymorphic tagging table linking taxonomy labels to project profiles.
*   **Business Responsibility**: Reporting Taxonomy Categorization
*   **Ownership Domain**: Projects Core (Medium-Write)
*   **Dependencies**: `public.projects`, `public.tags`

#### 3.7.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Target project profile. |
| `tag_id` | `uuid` | NO | None | FK -> `tags(id)` | Valid UUID | None | Public | Target tag lookup. |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Assignment date. |

#### 3.7.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (project_id, tag_id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
    *   `tag_id REFERENCES public.tags(id) ON DELETE CASCADE`

#### 3.7.4 Index Strategy
*   **`project_tags_pkey`**: Primary Key composite index.
*   **`project_tags_tag_idx`**: B-Tree Index on `(tag_id)`. Optimizes grouping reports filter searches by tag.

---

### 3.8 Table Name: `public.milestones`

#### 3.8.1 Table Overview
*   **Purpose**: Manages key delivery deadlines, contractual billing stages, or phase gates.
*   **Business Responsibility**: Delivery Stage Gates & Revenue Phase Unlocking
*   **Ownership Domain**: Projects Core (Medium-Read, Low-Write)
*   **Dependencies**: `public.projects`

#### 3.8.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Parent project target. |
| `name` | `varchar(150)` | NO | None | None | 2-150 characters | None | Public | Milestone title. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Contextual deliverables info. |
| `due_date` | `date` | NO | None | None | Date | None | Public | Scheduled delivery deadline. |
| `completed_at` | `timestamptz` | YES | `NULL` | None | Date | None | Public | Real-world validation date. |
| `billing_release_amount`|`numeric(18,2)`|NO | `0.00` | None | Amount >= 0 | None | Financial | Release payout value. |

#### 3.8.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT milestones_billing CHECK (billing_release_amount >= 0.00)`

#### 3.8.4 Index Strategy
*   **`milestones_pkey`**: Primary Key index.
*   **`milestones_project_due_idx`**: B-Tree Index on `(project_id, due_date)`. Optimizes project roadmap rendering.

#### 3.8.5 Event Matrix
*   **Events Produced**: `milestone.created`, `milestone.completed`, `milestone.overdue`
*   **Events Consumed**: None

---

### 3.9 Table Name: `public.milestone_dependencies`

#### 3.9.1 Table Overview
*   **Purpose**: Ensures project execution logic by preventing milestone completion before its prerequisite is completed.
*   **Business Responsibility**: PMO Execution Path Logic
*   **Ownership Domain**: Projects Core (Junction)

#### 3.9.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `milestone_id` | `uuid` | NO | None | FK -> `milestones(id)` | Valid UUID | None | Public | Dependent milestone. |
| `depends_on_milestone_id`|`uuid`| NO | None | FK -> `milestones(id)` | Valid UUID | None | Public | Prerequisite milestone. |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Link mapping creation. |

#### 3.9.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (milestone_id, depends_on_milestone_id)`
*   **Foreign Keys**:
    *   `milestone_id REFERENCES public.milestones(id) ON DELETE CASCADE`
    *   `depends_on_milestone_id REFERENCES public.milestones(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT milestones_cannot_depend_on_self CHECK (milestone_id <> depends_on_milestone_id)`

---

### 3.10 Table Name: `public.tasks`

#### 3.10.1 Table Overview
*   **Purpose**: Serves as the central transactional table for task lists, tracking all individual assignments, work items, and completion statuses.
*   **Business Responsibility**: Granular Work Breakdown & Active Operations Management
*   **Ownership Domain**: Tasks Module (High-Write, Extremely High-Read)
*   **Dependencies**: `public.projects`, `public.milestones`, `public.sprints`
*   **Expected Lifetime**: Persistent (Archived post project closure)
*   **Read / Write Frequency**: 60% Reads / 40% Writes
*   **Expected Growth**: High (100,000 - 10,000,000+ active rows per year)
*   **Retention Policy**: 5 years active, then moved to range-partitioned historic tables.

#### 3.10.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Validation / Rule |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Target project context. |
| `milestone_id` | `uuid` | YES | `NULL` | FK -> `milestones(id)` | Valid UUID | None | Public | Linked deliverable stage. |
| `sprint_id` | `uuid` | YES | `NULL` | FK -> `sprints(id)` | Valid UUID | None | Public | Associated active sprint sprint. |
| `parent_task_id` | `uuid` | YES | `NULL` | FK -> `tasks(id)` | Valid UUID | None | Public | Supports hierarchical subtasks. |
| `title` | `varchar(250)` | NO | None | None | 1-250 characters | None | Public | Task title. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Detailed specifications. |
| `status` | `varchar(30)` | NO | `'todo'` | None | Valid state | None | Public | Kanban board state tracking. |
| `priority` | `varchar(20)` | NO | `'medium'` | None | Valid priorities | None | Public | Operational urgency value. |
| `estimated_hours` | `numeric(8,2)` | YES | `NULL` | None | Est >= 0 | None | Public | Estimated duration baseline. |
| `start_date` | `date` | YES | `NULL` | None | Date | None | Public | Execution start gate. |
| `due_date` | `date` | YES | `NULL` | None | Due >= Start | None | Public | Deadline tracking. |
| `completed_at` | `timestamptz` | YES | `NULL` | None | Date | None | Public | Completion validation point. |
| `sort_order` | `integer` | NO | `100` | None | Sort >= 0 | None | Public | Kanban drag-and-drop index. |
| `search_vector` | `tsvector` | YES | `NULL` | None | None | None | Public | Native FTS catalog. |

#### 3.10.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
    *   `milestone_id REFERENCES public.milestones(id) ON DELETE SET NULL`
    *   `sprint_id REFERENCES public.sprints(id) ON DELETE SET NULL`
    *   `parent_task_id REFERENCES public.tasks(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT tasks_status CHECK (status IN ('todo', 'in_progress', 'under_review', 'blocked', 'done'))`
    *   `CONSTRAINT tasks_priority CHECK (priority IN ('low', 'medium', 'high', 'critical'))`
    *   `CONSTRAINT tasks_est_hours CHECK (estimated_hours >= 0.00)`
    *   `CONSTRAINT tasks_date_order CHECK (due_date >= start_date)`
    *   `CONSTRAINT tasks_prevent_self_hierarchy CHECK (parent_task_id <> id)`

#### 3.10.4 Index Strategy
*   **`tasks_pkey`**: Primary Key index.
*   **`tasks_kanban_idx`**: Composite B-Tree Index on `(project_id, status, sort_order)`. Optimizes real-time drag-and-drop Kanban card board updates.
*   **`tasks_hierarchy_idx`**: Partial Index on `(parent_task_id)` WHERE `parent_task_id IS NOT NULL`. Speeds up hierarchical subtask tree rendering.
*   **`tasks_due_idx`**: Covering Index on `(due_date, status)` INCLUDE `(id, title)`. Optimizes background task validation runners checking for overdue statuses.
*   **`tasks_search_vector_idx`**: GIN index on `search_vector`.

#### 3.10.5 Full-Text Search Strategy
*   **Generated tsvector**:
    `to_tsvector('english', coalesce(title, '') || ' ' || coalesce(description, '') || ' ' || coalesce(status, ''))`

#### 3.10.6 Row-Level Security (RLS)
*   **Read**: Tenant users who belong to the associated project.
*   **Insert / Update / Delete**: Project members.

#### 3.10.7 Audit & Locking
*   **Auditing**: Status and assignment updates generate records in `project_activity_logs`.
*   **Locking**: Handled by the `version` column.

#### 3.10.8 Event Matrix
*   **Events Produced**: `task.created`, `task.assigned`, `task.completed`, `task.overdue`, `task.updated`
*   **Events Consumed**: None

#### 3.10.9 Relationships
*   **Many-to-One**: `public.projects`, `public.milestones`, `public.sprints`, `public.tasks` (Self referencing)
*   **One-to-Many**: `public.task_comments`, `public.task_attachments`, `public.task_checklists`, `public.task_time_logs`

---

### 3.11 Table Name: `public.task_dependencies`

#### 3.11.1 Table Overview
*   **Purpose**: Prevents tasks from starting before their prerequisites are resolved.
*   **Business Responsibility**: Critical Path Logic Enforcements
*   **Ownership Domain**: PMO Config Core

#### 3.11.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Blocked task ID. |
| `depends_on_task_id`| `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Prerequisite task ID. |
| `dependency_type` | `varchar(30)` | NO | `'FS'` | None | `FS`, `SS`, `FF`, `SF` | None | Public | Standard scheduling dependency logic. |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Dependency link timestamp. |

#### 3.11.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (task_id, depends_on_task_id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`
    *   `depends_on_task_id REFERENCES public.tasks(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT task_dependency_cannot_depend_on_self CHECK (task_id <> depends_on_task_id)`
    *   `CONSTRAINT task_dependency_type CHECK (dependency_type IN ('FS', 'SS', 'FF', 'SF'))` (Finish-to-Start, Start-to-Start, Finish-to-Finish, Start-to-Finish)

---

### 3.12 Table Name: `public.task_comments`

#### 3.12.1 Table Overview
*   **Purpose**: Stores discussion logs on specific task cards.
*   **Business Responsibility**: Contextual Communication Log
*   **Ownership Domain**: Tasks Module (High-Write)
*   **Dependencies**: `public.tasks`, `security.users`

#### 3.12.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Target task connection. |
| `body` | `text` | NO | None | None | Plain or Markdown | None | Public / PII | Actual message text. |

#### 3.12.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`

---

### 3.13 Table Name: `public.task_attachments`

#### 3.13.1 Table Overview
*   **Purpose**: Maps assets, file uploads, and document resources stored in cloud buckets (Google Cloud Storage) to specific operational tasks.
*   **Business Responsibility**: Project Asset Repository
*   **Ownership Domain**: Tasks Module

#### 3.13.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Target task mapping. |
| `file_name` | `varchar(255)` | NO | None | None | 1-255 characters | None | Public | User-facing title. |
| `file_path` | `text` | NO | None | None | Valid bucket path | None | Public | Unique GCS object reference keys. |
| `file_size_bytes` | `bigint` | NO | None | None | Size > 0 | None | Public | File storage size tracking. |
| `mime_type` | `varchar(100)` | NO | None | None | Valid mime string | None | Public | File mime categorization. |

#### 3.13.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT task_attachments_size CHECK (file_size_bytes > 0)`

---

### 3.14 Table Name: `public.task_checklists`

#### 3.14.1 Table Overview
*   **Purpose**: Groups sub-task list groups inside individual task cards.
*   **Business Responsibility**: Structured Progress Checklists
*   **Ownership Domain**: Tasks Module

#### 3.14.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Associated parent task card. |
| `title` | `varchar(150)` | NO | None | None | 1-150 characters | None | Public | Checklist header title. |
| `sort_order` | `integer` | NO | `10` | None | Sort >= 0 | None | Public | Display sequence identifier. |

#### 3.14.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`

---

### 3.15 Table Name: `public.task_checklist_items`

#### 3.15.1 Table Overview
*   **Purpose**: Stores the specific rows of action steps inside a checklist group.
*   **Business Responsibility**: Granular Progress Step Execution
*   **Ownership Domain**: Tasks Module (High-Write)

#### 3.15.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `checklist_id` | `uuid` | NO | None | FK -> `task_checklists(id)`| Valid UUID | None | Public | Parent checklist association. |
| `title` | `varchar(250)` | NO | None | None | 1-250 characters | None | Public | Checklist step description. |
| `is_completed` | `boolean` | NO | `false` | None | Boolean | None | Public | Current status of checklist step. |
| `sort_order` | `integer` | NO | `10` | None | Sort >= 0 | None | Public | Inner-list vertical ordering. |

#### 3.15.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `checklist_id REFERENCES public.task_checklists(id) ON DELETE CASCADE`

---

### 3.16 Table Name: `public.task_labels`

#### 3.16.1 Table Overview
*   **Purpose**: Junction mapping associating categorizing labels (e.g., Bug, Technical Debt, Feature Request) with active task records.
*   **Business Responsibility**: Task Taxonomy Mapping
*   **Ownership Domain**: Tasks Module

#### 3.16.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Target task. |
| `tag_id` | `uuid` | NO | None | FK -> `tags(id)` | Valid UUID | None | Public | Mapped taxonomy label ID. |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Assignment date. |

#### 3.16.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (task_id, tag_id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`
    *   `tag_id REFERENCES public.tags(id) ON DELETE CASCADE`

---

### 3.17 Table Name: `public.task_time_logs`

#### 3.17.1 Table Overview
*   **Purpose**: Captures actual hours logged by workers against a specific task card.
*   **Business Responsibility**: Time Tracking & Billable Hour Captures
*   **Ownership Domain**: Time Core (Append-Only / Partitioned candidate)
*   **Dependencies**: `public.tasks`, `security.users`, `public.timesheets`
*   **Expected Lifetime**: Persistent (Financial audits)
*   **Read / Write Frequency**: 40% Reads / 60% Writes
*   **Expected Growth**: Extremely High ($O(N)$ logs logged daily by thousands of engineers)
*   **Retention Policy**: 7 years, then archived to range partitioned warehouse databases.

#### 3.17.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Task tracked. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | Valid UUID | None | Public | Worker tracking hours. |
| `timesheet_id` | `uuid` | YES | `NULL` | FK -> `timesheets(id)` | Valid UUID | None | Public | Aggregating timesheet record. |
| `logged_hours` | `numeric(5,2)` | NO | None | None | Hours > 0.00 | None | Public | Captured billable fraction. |
| `is_billable` | `boolean` | NO | `true` | None | Boolean | None | Public | Marks billable tracking status. |
| `work_date` | `date` | NO | None | None | Date | None | Public | Specific day work occurred. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Summary of actions executed. |

#### 3.17.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `task_id REFERENCES public.tasks(id) ON DELETE RESTRICT`
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT`
    *   `timesheet_id REFERENCES public.timesheets(id) ON DELETE SET NULL`
*   **Check Constraints**:
    *   `CONSTRAINT task_time_logs_positive CHECK (logged_hours > 0.00 AND logged_hours <= 24.00)`

#### 3.17.4 Index Strategy
*   **`task_time_logs_pkey`**: Primary Key index.
*   **`task_time_logs_work_date_idx`**: B-Tree Index on `(user_id, work_date)`. Optimizes timesheet generation engines.
*   **`task_time_logs_reporting_idx`**: Composite B-Tree Index on `(task_id, is_billable)` INCLUDE `(logged_hours)`. Optimizes PM project budget audits.

---

### 3.18 Table Name: `public.timesheets`

#### 3.18.1 Table Overview
*   **Purpose**: Aggregates time-log sets for a defined period (typically weekly) for management approval and billing payroll processing.
*   **Business Responsibility**: Time Compliance & Contract Billing Verification
*   **Ownership Domain**: Finance Core (Medium-Write)
*   **Dependencies**: `security.users`

#### 3.18.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | Valid UUID | None | Public | Target employee profile. |
| `start_date` | `date` | NO | None | None | Date | None | Public | Beginning day of pay period. |
| `end_date` | `date` | NO | None | None | End >= Start | None | Public | End day of pay period. |
| `status` | `varchar(30)` | NO | `'draft'` | None | Valid state | None | Public | Approval state tracking. |
| `approved_by` | `uuid` | YES | `NULL` | FK -> `users(id)` | Valid UUID | None | Public | Approving manager ID. |
| `approved_at` | `timestamptz` | YES | `NULL` | None | Date | None | Public | Approval timestamp. |

#### 3.18.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT`
    *   `approved_by REFERENCES security.users(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT timesheets_date_order CHECK (end_date >= start_date)`
    *   `CONSTRAINT timesheets_status CHECK (status IN ('draft', 'submitted', 'approved', 'rejected'))`

#### 3.18.4 Index Strategy
*   **`timesheets_pkey`**: Primary Key index.
*   **`timesheets_lookup_idx`**: B-Tree Index on `(user_id, start_date)`. Optimizes profile timesheet lists.

---

### 3.19 Table Name: `public.timesheet_entries`

#### 3.19.1 Table Overview
*   **Purpose**: Explicit rows storing allocations inside an approved timesheet.
*   **Business Responsibility**: Granular Payroll Audits
*   **Ownership Domain**: Finance Core (Append-Only)

#### 3.19.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `timesheet_id` | `uuid` | NO | None | FK -> `timesheets(id)` | Valid UUID | None | Public | Parent timesheet links. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Linked project profile. |
| `work_date` | `date` | NO | None | None | Date | None | Public | Work date. |
| `logged_hours` | `numeric(4,2)` | NO | None | None | Hours > 0 | None | Public | Tracked hours count. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Activity log summary. |

#### 3.19.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `timesheet_id REFERENCES public.timesheets(id) ON DELETE CASCADE`
    *   `project_id REFERENCES public.projects(id) ON DELETE RESTRICT`

---

### 3.20 Table Name: `public.resource_allocations`

#### 3.20.1 Table Overview
*   **Purpose**: Defines staff capacity planning and allocation constraints over a chronological window. Prevents double-booking conflicts and tracks resource availability.
*   **Business Responsibility**: Capacity Planning & Resource Conflict Prevention
*   **Ownership Domain**: Resource Staffing
*   **Dependencies**: `security.users`, `public.projects`

#### 3.20.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | Valid UUID | None | Public | Target worker. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Target project engagement. |
| `start_date` | `date` | NO | None | None | Date | None | Public | Allocation start day. |
| `end_date` | `date` | NO | None | None | End >= Start | None | Public | Allocation release day. |
| `allocation_percentage`|`numeric(5,2)`| NO | `100.00` | None | `0.00` to `100.00` | None | Public | Bandwidth allocated. |

#### 3.20.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE RESTRICT`
    *   `project_id REFERENCES public.projects(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT resource_allocations_date_order CHECK (end_date >= start_date)`
    *   `CONSTRAINT resource_allocations_percent CHECK (allocation_percentage > 0.00 AND allocation_percentage <= 100.00)`

#### 3.20.4 Index Strategy
*   **`resource_allocations_pkey`**: Primary Key index.
*   **`resource_allocations_range_gist_idx`**: GIST index using range type helper:
    `CREATE INDEX resource_allocations_range_gist_idx ON public.resource_allocations USING gist (user_id, daterange(start_date, end_date, '[]'));`
    *Why This Matters*: This index is critical for resource scheduling. It optimizes overlapping range checks to prevent overallocation.

---

### 3.21 Table Name: `public.project_calendars`

#### 3.21.1 Table Overview
*   **Purpose**: Defines distinct working structures, base timezones, and active working hour blocks for project delivery groups.
*   **Business Responsibility**: Scheduling Baseline Framework
*   **Ownership Domain**: PMO Config Core (Lookup)

#### 3.21.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Calendar name. |
| `timezone` | `varchar(50)` | NO | `'UTC'` | None | Valid Timezone | None | Public | Base operations timezone. |
| `working_days` | `integer[]` | NO | `'{1,2,3,4,5}'` | None | Days 0-6 array | None | Public | Active working days. |

#### 3.21.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`

---

### 3.22 Table Name: `public.project_holidays`

#### 3.22.1 Table Overview
*   **Purpose**: Registers non-working days for calendar calculations.
*   **Business Responsibility**: Calendar Compliance & Accurate Milestone Estimates
*   **Ownership Domain**: PMO Config Core (Lookup)

#### 3.22.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `calendar_id` | `uuid` | NO | None | FK -> `project_calendars(id)`| Valid UUID | None | Public | Parent calendar mapping. |
| `holiday_date` | `date` | NO | None | None | Date | None | Public | Specific day non-working. |
| `description` | `varchar(150)` | NO | None | None | 1-150 characters | None | Public | Holiday name (e.g. New Year). |

#### 3.22.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `calendar_id REFERENCES public.project_calendars(id) ON DELETE CASCADE`
*   **Unique Constraints**:
    *   `CONSTRAINT project_holidays_cal_date_key UNIQUE (calendar_id, holiday_date)`

---

### 3.23 Table Name: `public.sprints`

#### 3.23.1 Table Overview
*   **Purpose**: Manages Agile sprint durations, planning structures, and development goals.
*   **Business Responsibility**: Scrum Ceremonies & Velocity Benchmarking
*   **Ownership Domain**: Agile Module
*   **Dependencies**: `public.projects`

#### 3.23.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Parent project link. |
| `name` | `varchar(150)` | NO | None | None | 2-150 characters | None | Public | Sprint name (e.g. Sprint 24). |
| `start_date` | `date` | NO | None | None | Date | None | Public | Active execution start. |
| `end_date` | `date` | NO | None | None | End >= Start | None | Public | Active execution end. |
| `status` | `varchar(30)` | NO | `'planning'` | None | Valid state | None | Public | Sprints state pipeline. |
| `goal` | `text` | YES | `NULL` | None | None | None | Public | Sprint target description. |

#### 3.23.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT sprints_date_order CHECK (end_date >= start_date)`
    *   `CONSTRAINT sprints_status CHECK (status IN ('planning', 'active', 'completed'))`

---

### 3.24 Table Name: `public.sprint_tasks`

#### 3.24.1 Table Overview
*   **Purpose**: Junction mapping associating tasks with specific agile sprint iterations.
*   **Business Responsibility**: Sprint Backlog Allocation
*   **Ownership Domain**: Agile Module (Junction)

#### 3.24.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `sprint_id` | `uuid` | NO | None | FK -> `sprints(id)` | Valid UUID | None | Public | Associated sprint. |
| `task_id` | `uuid` | NO | None | FK -> `tasks(id)` | Valid UUID | None | Public | Associated task card. |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Insertion log timestamp. |

#### 3.24.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (sprint_id, task_id)`
*   **Foreign Keys**:
    *   `sprint_id REFERENCES public.sprints(id) ON DELETE CASCADE`
    *   `task_id REFERENCES public.tasks(id) ON DELETE CASCADE`

---

### 3.25 Table Name: `public.risk_register`

#### 3.25.1 Table Overview
*   **Purpose**: Stores identified delivery threats, probability-impact evaluations, and contingency flags.
*   **Business Responsibility**: Delivery Threat Mitigation & Risk Management Governance
*   **Ownership Domain**: PMO Config Core (Medium-Read, Low-Write)
*   **Dependencies**: `public.projects`

#### 3.25.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Target project mapping. |
| `title` | `varchar(200)` | NO | None | None | 2-200 characters | None | Public | Name of risk. |
| `description` | `text` | NO | None | None | None | None | Public | Core conditions and consequences. |
| `probability` | `varchar(20)` | NO | `'medium'`| None | Valid tokens | None | Public | Probability tier. |
| `impact` | `varchar(20)` | NO | `'medium'`| None | Valid tokens | None | Public | Impact severity level. |
| `score` | `integer` | NO | `9` | None | `1` to `25` | None | Public | Combined risk rating score. |
| `status` | `varchar(30)` | NO | `'identified'`| None | Valid state | None | Public | Risk monitoring state. |

#### 3.25.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT risk_probability CHECK (probability IN ('low', 'medium', 'high'))`
    *   `CONSTRAINT risk_impact CHECK (impact IN ('low', 'medium', 'high'))`
    *   `CONSTRAINT risk_score_range CHECK (score >= 1 AND score <= 25)`
    *   `CONSTRAINT risk_status CHECK (status IN ('identified', 'monitored', 'mitigated', 'triggered', 'closed'))`

#### 3.25.4 Index Strategy
*   **`risk_register_pkey`**: Primary Key index.
*   **`risk_project_score_idx`**: B-Tree Index on `(project_id, score DESC)`. Used to render project risk matrices instantly on PM dashboards.

---

### 3.26 Table Name: `public.risk_mitigation_actions`

#### 3.26.1 Table Overview
*   **Purpose**: Registers mitigation steps, assigned personnel, and execution timelines for threat controls.
*   **Business Responsibility**: Delivery Threat Control Action Execution
*   **Ownership Domain**: PMO Config Core

#### 3.26.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `risk_id` | `uuid` | NO | None | FK -> `risk_register(id)`| Valid UUID | None | Public | Linked risk register ID. |
| `action` | `text` | NO | None | None | 5+ characters | None | Public | Description of step. |
| `assigned_user_id`| `uuid` | YES | `NULL` | FK -> `users(id)` | Valid UUID | None | Public | Assigned action owner. |
| `due_date` | `date` | YES | `NULL` | None | Date | None | Public | Target completion. |
| `is_executed` | `boolean` | NO | `false` | None | Boolean | None | Public | Execution status flag. |

#### 3.26.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `risk_id REFERENCES public.risk_register(id) ON DELETE CASCADE`
    *   `assigned_user_id REFERENCES security.users(id) ON DELETE SET NULL`

---

### 3.27 Table Name: `public.change_requests`

#### 3.27.1 Table Overview
*   **Purpose**: Logs scoping revisions, contract variations, and cost modifications to manage project changes.
*   **Business Responsibility**: Scope Variations Governance & Financial Contract Variations
*   **Ownership Domain**: PMO Config Core (Medium-Write)
*   **Dependencies**: `public.projects`, `security.users`

#### 3.27.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Associated project profile. |
| `title` | `varchar(200)` | NO | None | None | 2-200 characters | None | Public | Change title. |
| `description` | `text` | NO | None | None | None | None | Public | Detailed business reason. |
| `budget_impact` | `numeric(18,2)` | NO | `0.00` | None | None | None | Financial | Cost difference impact. |
| `status` | `varchar(30)` | NO | `'pending'` | None | Valid state | None | Public | Approval state tracking. |
| `reviewed_by` | `uuid` | YES | `NULL` | FK -> `users(id)` | Valid UUID | None | Public | Approving manager ID. |
| `reviewed_at` | `timestamptz` | YES | `NULL` | None | Date | None | Public | Approval timestamp. |

#### 3.27.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`
    *   `reviewed_by REFERENCES security.users(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT change_requests_status CHECK (status IN ('pending', 'approved', 'rejected', 'canceled'))`

---

### 3.28 Table Name: `public.project_documents`

#### 3.28.1 Table Overview
*   **Purpose**: Stores project-level documentation, templates, and specifications.
*   **Business Responsibility**: Unified Project Document Repository
*   **Ownership Domain**: Projects Core

#### 3.28.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | See Section 1.1 | - | Public | Standards compliance. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Associated project. |
| `name` | `varchar(255)` | NO | None | None | 1-255 characters | None | Public | User-facing document name. |
| `file_path` | `text` | NO | None | None | Valid bucket path | None | Public | GCS object reference. |
| `file_size_bytes` | `bigint` | NO | None | None | Size > 0 | None | Public | Document storage size tracking. |
| `mime_type` | `varchar(100)` | NO | None | None | Valid mime string | None | Public | Mime categorization. |

#### 3.28.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `project_id REFERENCES public.projects(id) ON DELETE CASCADE`

---

### 3.29 Table Name: `public.project_activity_logs`

#### 3.29.1 Table Overview
*   **Purpose**: Chronologically records all user activity, configuration updates, and system events within the Projects domain. Partitioned monthly for database health.
*   **Business Responsibility**: Regulatory Compliance, Threat Auditing & Process Diagnostics
*   **Ownership Domain**: Audit Core (Immutable / Range Partitioned / High-Write)
*   **Dependencies**: `public.projects`, `security.users`
*   **Expected Lifetime**: Indefinite
*   **Read / Write Frequency**: 10% Reads / 90% Writes
*   **Expected Growth**: Extremely High ($O(N)$ logs logged on every database mutation)
*   **Retention Policy**: 7 years for compliance, then purged.

#### 3.29.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / FKs | Validation | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | Valid UUID | None | Public | Primary record key. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Valid UUID | None | Public | Multi-tenant separator. |
| `project_id` | `uuid` | NO | None | FK -> `projects(id)` | Valid UUID | None | Public | Associated project. |
| `user_id` | `uuid` | YES | `NULL` | FK -> `users(id)` | Valid UUID | None | Public | Operating actor. |
| `action_type` | `varchar(50)` | NO | None | None | 2-50 characters | None | Public | Category of action (e.g., `task_completed`). |
| `payload` | `jsonb` | NO | `'{}'`::jsonb | None | Valid JSON | None | Public | **JSONB document block detailing the previous and updated values.** |
| `created_at` | `timestamptz` | NO | `now()` | None | Date | None | Public | Log birth timestamp. Used for range partitioning. |

#### 3.29.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id, created_at)`
*   **Foreign Keys**: None (Cannot enforce standard foreign keys across range-partitioned boundary boundaries in PostgreSQL).
*   **Check Constraints**: None.

#### 3.29.4 Index Strategy & Partitioning
*   **Partitioning Pattern**: Range-partitioned on `created_at` in monthly intervals to prevent index bloat and ensure fast execution.
*   **`project_activity_logs_lookup_idx`**: B-Tree Index on `(project_id, created_at DESC)`. Used for task audit feeds.

---

## 4. DOMAIN INTEGRATIONS & COMPREHENSIVE CLASSIFICATION

### 4.1 Table Classification & Properties

The following matrix classifies all 29 Projects domain tables by their primary write characteristics, performance roles, and data classification tags:

| Table Name | Primary Role | Write Profile | Partitioned | JSONB Usage | Encrypted Columns | GDPR PII Scopes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `project_templates` | Lookup / PMO Config | Low-Write | No | Yes (Settings) | None | None |
| `project_template_tasks`| PMO Config Lookup | Low-Write | No | None | None | None |
| `projects` | Operational master | Medium-Write | No | None | None | None |
| `project_status_history`| Immutable audit log | Append-Only | No | None | None | None |
| `project_members` | Operational junction | Low-Write | No | None | None | User Association |
| `project_roles` | Lookup / PMO Config | Low-Write | No | Yes (Permissions) | None | None |
| `project_tags` | Junction tag mapping | Medium-Write | No | None | None | None |
| `milestones` | Operational target | Low-Write | No | None | None | None |
| `milestone_dependencies`| Operational junction | Low-Write | No | None | None | None |
| `tasks` | Core task tracker | High-Write | No | None | None | Title/Description |
| `task_dependencies` | Operational junction | Low-Write | No | None | None | None |
| `task_comments` | Engagement trail | High-Write | No | None | None | Markdown Comment Body |
| `task_attachments` | Resource mapping | Medium-Write | No | None | Yes (`file_path` token) | Filename |
| `task_checklists` | Subtask layout | Medium-Write | No | None | None | None |
| `task_checklist_items` | Subtask execution | High-Write | No | None | None | Step Text |
| `task_labels` | Junction tag mapping | Low-Write | No | None | None | None |
| `task_time_logs` | Timesheet log track | High-Write | Yes | None | None | User Reference |
| `timesheets` | Financial capture | Medium-Write | No | None | None | User Reference |
| `timesheet_entries` | Financial item line | High-Write | No | None | None | Log Text |
| `resource_allocations` | Operational timeline | Medium-Write | No | None | None | User Reference |
| `project_calendars` | Configuration lookup| Low-Write | No | None | None | None |
| `project_holidays` | Configuration lookup| Low-Write | No | None | None | None |
| `sprints` | Agile execution | Low-Write | No | None | None | None |
| `sprint_tasks` | Operational junction | Medium-Write | No | None | None | None |
| `risk_register` | Delivery control | Low-Write | No | None | None | None |
| `risk_mitigation_actions`| Threat control action| Low-Write | No | None | None | User Assignment |
| `change_requests` | Contract variations | Low-Write | No | None | None | Description Text |
| `project_documents` | File register | Low-Write | No | None | Yes (`file_path` token) | Name |
| `project_activity_logs` | Immutable audit log | High-Write | Yes (Monthly) | Yes (Payload) | None | Full Payload Context |

---

## 5. RECONCILIATION & INTRA-DOCUMENT CONSISTENCY

This blueprint guarantees intra-document consistency and strict logical isolation:
1.  **Multi-Tenant Isolation**: Enforced across all tables using logical separation of row states by filtering on `organization_id` under Row-Level Security policies.
2.  **Referential Integrity**: Cascades are applied only on child entities that have no independent lifecycle (e.g. `timesheet_entries` belonging to `timesheets`). Traditional entities require `ON DELETE RESTRICT` or `ON DELETE SET NULL` to maintain operational history.
3.  **Audit Enforcement**: High-write log registers are optimized via partitioned monthly boundaries and stored as immutable, append-only structures, ensuring compliance with global enterprise security regulations.
4.  **Optimistic Concurrency**: Checked on every read/write cycle via the standard monotonic `version` column, protecting against conflicting updates.
