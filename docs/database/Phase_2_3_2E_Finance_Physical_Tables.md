# JUANET PostgreSQL Finance Table Specifications
## Phase 2.3.2E — Finance Domain Physical Tables
**Document Version:** 1.0  
**Author:** Chief Database Architect, JUANET Platform  
**Classification:** Technical / Database Schema Definition  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical table definitions for the Finance domain of the JUANET Enterprise SaaS Platform. All schemas, parameters, and constraints defined herein are binding and must be implemented exactly by future migrations, DDL scripts, or ORM declarations.

These specifications conform strictly to:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`
*   `Phase_2_3_2A_Core_Physical_Tables.md`
*   `Phase_2_3_2B_Authentication_Physical_Tables.md`
*   `Phase_2_3_2C_CRM_Physical_Tables.md`
*   `Phase_2_3_2D_Projects_Physical_Tables.md`

All Finance domain tables reside within the `public` schema (with core currencies in the global `system` schema) as standard multi-tenant business entities. They utilize Row-Level Security (RLS) keying based on `organization_id` to enforce strict logical tenant isolation.

### 1.1 Mandatory Global Columns Standard
As established in the global database blueprint, **every tenant-owned business entity** within the `public` schema must define the following standard structural block of columns. No table in the `public` schema is exempt from this requirement. To preserve readability and focus on domain-specific attributes, this structural block is represented as **`[MANDATORY GLOBAL COLUMNS]`** in each table catalog:

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

## 2. CORE CONCEPTUAL FRAMEWORKS & SPECIAL FINANCE REQUIREMENTS

To support standard multi-tenant SaaS accounting, the database schema implements high-level physical requirements designed for absolute data correctness, SOC2 and GAAP/IFRS auditability, and lightning-fast aggregate reporting.

### 2.1 Double-Entry Bookkeeping & Out-of-Balance Prevention
Double-entry bookkeeping mandates that for every transaction, total debits must exactly equal total credits. To enforce this physically and avoid runtime drifts, we enforce the following:
1.  **Transaction Boundary**: Ledger entries are grouped under a single parent `journal_entries` record.
2.  **Out-of-Balance Check**: A database trigger on `ledger_entries` verifies that for any modification (INSERT, UPDATE, DELETE) inside a transaction, the sum of all debits (`debit_amount`) minus the sum of all credits (`credit_amount`) for that specific `journal_entry_id` is exactly zero. Any transaction that fails this invariant is physically aborted.
3.  **Positive Numeric Values Only**: Both `debit_amount` and `credit_amount` are strictly non-negative. Zero entries are restricted.

### 2.2 Financial Immutability & Ledger Hardening
In accordance with SOC2, GAAP, and IFRS, financial transactions must never be modified or deleted once posted.
1.  **Append-Only Rule**: `journal_entries` and `ledger_entries` have RLS rules and database triggers that block `UPDATE` and `DELETE` operations once the batch status is marked as `'posted'`.
2.  **Reversing Entries**: Error correction is performed exclusively by posting a new, reversing journal entry. No existing record is physically or logically deleted.
3.  **Period Closing**: Once a fiscal or accounting period is closed, its status changes to `'closed'`, triggering a hard lock via check constraints and triggers, blocking any new transactions or adjustments from being posted with transaction dates within that period.

### 2.3 Multi-Currency Accounting & Historical Rate Preservation
1.  **Transactional Currency**: Every invoice, bill, and transaction record specifies its original `currency_id`.
2.  **Base Currency**: Each tenant organization is configured with a base (reporting) currency in `system.organizations`.
3.  **Rate Snapshots**: All monetary postings store a historical snapshot of the exchange rate used (`exchange_rate_at_posting`) and the calculated value in the base currency (`amount_in_base`). This prevents historic balance drift when exchange rates change.

### 2.4 Revenue Recognition & Deferred Revenue Hooks
1.  **Accrual Accounting**: Invoices emit events that trigger debit postings to Accounts Receivable and credit postings to Revenue or Deferred Revenue (for pre-paid subscriptions).
2.  **Deferred Revenue Engine**: Subscriptions and multi-period invoices use the `deferred_revenue` schedules. A nightly job processes recognition schedules, creating reversing debit entries to Deferred Revenue and credit entries to recognized Subscription Revenue.

---

## SECTION 3: CHART OF ACCOUNTS & ORGANIZATIONAL ALLOCATIONS

---

### 3.1 Table Name: `public.account_categories`

#### 3.1.1 Purpose & Business Overview
*   **Purpose**: Manages the master classifications for the Chart of Accounts (e.g., Assets, Liabilities, Equity, Revenue, Cost of Goods Sold, Operating Expenses). Ensures standardized categorization for Balance Sheet and Income Statement generation.
*   **Business Responsibility**: Financial Taxonomy Governance & Financial Statement Standardization.
*   **Ownership Domain**: Finance Core Configuration.
*   **Dependencies**: `system.organizations`.
*   **Lifecycle**: Persistent, created during tenant onboarding and rarely mutated.
*   **Expected Read/Write Ratio**: 99.9% Reads / 0.1% Writes.
*   **Retention Policy**: Retained indefinitely.

#### 3.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | None | Public | 2-100 characters | Display name of the category. |
| `code` | `varchar(50)` | NO | None | Unique per Org | None | Public | uppercase, Alphanumeric | Machine-readable taxonomy code. |
| `report_type` | `varchar(30)` | NO | None | None | None | Public | `balance_sheet`, `income_statement` | Mapping to financial reports. |
| `normal_balance` | `varchar(10)` | NO | `'debit'` | None | None | Public | `'debit'` or `'credit'` | Standard accounting normal balance. |

#### 3.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**: Standard `organization_id` reference.
*   **Check Constraints**:
    *   `CONSTRAINT account_categories_report_type CHECK (report_type IN ('balance_sheet', 'income_statement'))`
    *   `CONSTRAINT account_categories_normal_balance CHECK (normal_balance IN ('debit', 'credit'))`
    *   `CONSTRAINT account_categories_code_format CHECK (code ~ '^[A-Z0-9\_]+$')`
*   **Unique Constraints**:
    *   `CONSTRAINT account_categories_org_code_key UNIQUE (organization_id, code)`

#### 3.1.4 Indexes
*   `account_categories_pkey`: Primary Key index.
*   `account_categories_org_code_uidx`: Unique Index on `(organization_id, code)`.

#### 3.1.5 Concurrency
*   **Optimistic Locking**: Tracked via `version`.
*   **Deadlock Avoidance**: Low-write lookup table; standard read-locks.

#### 3.1.6 Security & RLS
*   **RLS Policy**: Read allowed for authorized tenant users. Write allowed only for Tenant Financial Controllers and Administrators.
*   **Financial write restrictions**: Changes to normal balance block historical adjustments.

#### 3.1.7 Ledger Posting Rules
*   None (static classification lookup).

#### 3.1.8 Financial Immutability
*   Hard locked if any associated chart of accounts has posted ledger entries.

#### 3.1.9 Produced & Consumed Events
*   **Produced Events**: `account_category.created`, `account_category.updated`
*   **Consumed Events**: `organization.created` (seeds standard GAAP template categories).

#### 3.1.10 Partition & Archival Strategy
*   No partitioning (low volume). Retained indefinitely.

#### 3.1.11 GDPR & Performance Expectations
*   No PII. Reads optimized by database index caching.

#### 3.1.12 Relationships
*   **One-to-Many**: `public.chart_of_accounts`.

---

### 3.2 Table Name: `public.chart_of_accounts`

#### 3.2.1 Purpose & Business Overview
*   **Purpose**: Stores the unique ledger accounts used by a tenant to log all financial postings (e.g., 1010-Cash, 1200-Accounts Receivable, 4010-Subscription Revenue).
*   **Business Responsibility**: General Ledger Definition, Out-of-Balance Protection, Audit Readiness.
*   **Ownership Domain**: Finance Core Configuration.
*   **Dependencies**: `system.organizations`, `public.account_categories`.
*   **Lifecycle**: Long-Term, highly integrated with operational flows.
*   **Expected Read/Write Ratio**: 98% Reads / 2% Writes.
*   **Retention Policy**: Retained indefinitely for legal and historic auditing.

#### 3.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `category_id` | `uuid` | NO | None | FK -> `account_categories(id)` | None | Public | Valid UUIDv4 | Relates accounts to balance sheet/income sheets. |
| `parent_account_id`| `uuid` | YES | `NULL` | FK -> `chart_of_accounts(id)`| None | Public | Valid UUIDv4 | Supports nested sub-accounts. |
| `account_number` | `varchar(30)` | NO | None | Unique per Org | None | Public | Alphanumeric characters | Primary business reference number (e.g., '1200'). |
| `name` | `varchar(150)` | NO | None | None | None | Public | 2-150 characters | User-facing title. |
| `description` | `text` | YES | `NULL` | None | None | Public | None | Explains purpose of the account. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Boolean | Controls posting capability. |
| `is_reconciliation` | `boolean` | NO | `false` | None | None | Public | Boolean | If true, subject to monthly bank reconciliations. |

#### 3.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `category_id REFERENCES public.account_categories(id) ON DELETE RESTRICT`
    *   `parent_account_id REFERENCES public.chart_of_accounts(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT coa_account_number_length CHECK (length(trim(account_number)) >= 3)`
    *   `CONSTRAINT coa_prevent_self_hierarchy CHECK (parent_account_id <> id)`
*   **Unique Constraints**:
    *   `CONSTRAINT coa_org_account_number_key UNIQUE (organization_id, account_number)`

#### 3.2.4 Indexes
*   `coa_pkey`: Primary Key index.
*   `coa_org_number_uidx`: Unique Index on `(organization_id, account_number)`.
*   `coa_active_idx`: Partial B-Tree on `(organization_id, id)` WHERE `is_active = true`.

#### 3.2.5 Concurrency
*   **Optimistic Locking**: Tracked via `version`.
*   **Deadlock Avoidance**: Modified infrequently; updates acquire row-level locks on target accounts to prevent concurrent metadata alterations.

#### 3.2.6 Security & RLS
*   **RLS Policy**: Read allowed for authorized users. Insert/Update restricted to Finance Controllers. Deletion blocked if active postings exist.

#### 3.2.7 Ledger Posting Rules
*   Each ledger entry (`ledger_entries`) must reference an active, valid `chart_of_accounts` row.

#### 3.2.8 Financial Immutability
*   Accounts with transaction history cannot be deleted or have their currency/number changed. They must be deactivated (`is_active = false`) instead.

#### 3.2.9 Produced & Consumed Events
*   **Produced Events**: `chart_of_accounts.created`, `chart_of_accounts.updated`, `chart_of_accounts.deactivated`
*   **Consumed Events**: `organization.created` (seeds standard local Charts of Accounts based on GAAP/IFRS).

#### 3.2.10 Partition & Archival Strategy
*   No partitioning. Kept in main database for reporting and historic analytics.

#### 3.2.11 GDPR & Performance Expectations
*   No PII. Indexed heavily to allow rapid tree rendering.

#### 3.2.12 Relationships
*   **Many-to-One**: `public.account_categories`.
*   **One-to-Many**: `public.ledger_entries`, `public.budget_lines`, `public.chart_of_accounts` (Sub-accounts).

---

### 3.3 Table Name: `public.cost_centers`

#### 3.3.1 Purpose & Business Overview
*   **Purpose**: Defines physical business units, divisions, or projects designed to track internal corporate costs (e.g., Marketing-Europe, R&D-SaaS).
*   **Business Responsibility**: Corporate cost management, profit & loss allocation audits.
*   **Ownership Domain**: Finance & Management Accounting.
*   **Dependencies**: `system.organizations`.
*   **Lifecycle**: Long-Term, updated during re-orgs or project creations.
*   **Expected Read/Write Ratio**: 95% Reads / 5% Writes.
*   **Retention Policy**: Retained indefinitely.

#### 3.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(150)` | NO | None | None | None | Public | 2-150 characters | Name of cost center. |
| `code` | `varchar(50)` | NO | None | Unique per Org | None | Public | uppercase, alphanumeric | Lookup code used in accounting splits. |
| `description` | `text` | YES | `NULL` | None | None | Public | None | Purpose and scope. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Boolean | Controls eligibility for expense lines. |

#### 3.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check Constraints**:
    *   `CONSTRAINT cost_centers_code_format CHECK (code ~ '^[A-Z0-9\_]+$')`
*   **Unique Constraints**:
    *   `CONSTRAINT cost_centers_org_code_key UNIQUE (organization_id, code)`

#### 3.3.4 Indexes
*   `cost_centers_pkey`: Primary Key index.
*   `cost_centers_org_code_uidx`: Unique Index on `(organization_id, code)`.

#### 3.3.5 Concurrency & Security
*   **Optimistic Locking**: Tracked via `version`.
*   **RLS Policy**: Read allowed for authorized users. Write allowed for Finance Controllers and HR managers.

#### 3.3.6 Ledger Posting Rules
*   Expense entries can specify a `cost_center_id` to route expenditures for departmental P&L statements.

#### 3.3.7 Produced & Consumed Events
*   **Produced Events**: `cost_center.created`, `cost_center.updated`
*   **Consumed Events**: `project.created` (optionally provisions corresponding cost center for the project).

#### 3.3.8 Relationships
*   **One-to-Many**: `public.ledger_entries`, `public.budget_lines`.

---

### 3.4 Table Name: `public.departments`

#### 3.4.1 Purpose & Business Overview
*   **Purpose**: Manages formal corporate organizational divisions (e.g., Engineering, Sales, Human Resources) to facilitate employee grouping, payroll routing, and operational budgeting.
*   **Business Responsibility**: Organizational Hierarchy & Staffing Cost Groupings.
*   **Ownership Domain**: HR & Corporate Governance.
*   **Dependencies**: `system.organizations`.
*   **Lifecycle**: Long-Term.
*   **Expected Read/Write Ratio**: 98% Reads / 2% Writes.
*   **Retention Policy**: Retained indefinitely.

#### 3.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | - | Public | 2-100 characters | Department name. |
| `code` | `varchar(50)` | NO | None | Unique per Org | - | Public | uppercase, snake_case | System routing code. |
| `manager_id` | `uuid` | YES | `NULL` | FK -> `security.users(id)`| - | Public | Valid UUIDv4 | Identifies departmental approver. |

#### 3.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `manager_id REFERENCES security.users(id) ON DELETE SET NULL`
*   **Unique Constraints**:
    *   `CONSTRAINT departments_org_code_key UNIQUE (organization_id, code)`

#### 3.4.4 Indexes
*   `departments_pkey`: Primary Key index.
*   `departments_org_code_uidx`: Unique Index on `(organization_id, code)`.

#### 3.4.5 Relationships
*   **One-to-Many**: `public.ledger_entries`, `public.budgets`.

---

## SECTION 4: GENERAL LEDGER & PERIOD CONTROLS

---

### 4.1 Table Name: `public.fiscal_periods`

#### 4.1.1 Purpose & Business Overview
*   **Purpose**: Manages the formal financial reporting years of an organization (e.g., Fiscal Year 2026: Jan 1 to Dec 31, or customized fiscal years).
*   **Business Responsibility**: Fiscal Governance, Taxes, GAAP/IFRS Year-End Close compliance.
*   **Ownership Domain**: General Ledger Management.
*   **Dependencies**: `system.organizations`.
*   **Lifecycle**: Active until Year-End closing, then archived into closed state.
*   **Expected Read/Write Ratio**: 99% Reads / 1% Writes.
*   **Retention Policy**: Retained indefinitely for historic financial tax audits.

#### 4.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `year_name` | `varchar(10)` | NO | None | Unique per Org | - | Public | e.g., 'FY2026' | Identifies the fiscal period name. |
| `start_date` | `date` | NO | None | None | - | Public | Date format | Beginning of physical fiscal year. |
| `end_date` | `date` | NO | None | None | - | Public | Date format | End of physical fiscal year. |
| `status` | `varchar(30)` | NO | `'open'` | None | - | Public | `'open'`, `'closed'`, `'locked'`| Controls state of entries in this period. |

#### 4.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check Constraints**:
    *   `CONSTRAINT fiscal_periods_dates CHECK (end_date >= start_date)`
    *   `CONSTRAINT fiscal_periods_status CHECK (status IN ('open', 'closed', 'locked'))`

#### 4.1.4 Indexes
*   `fiscal_periods_pkey`: Primary Key index.
*   `fiscal_periods_timeline_idx`: B-Tree on `(organization_id, start_date, end_date)`.

#### 4.1.5 Security & RLS
*   Only Financial Directors can execute Year-End closing procedures (`status = 'closed'`). Closing is irreversible unless an audit override code is applied.

#### 4.1.6 Relationships
*   **One-to-Many**: `public.accounting_periods`, `public.trial_balances`.

---

### 4.2 Table Name: `public.accounting_periods`

#### 4.2.1 Purpose & Business Overview
*   **Purpose**: Sub-periods of the fiscal period (typically monthly or quarterly) where operational postings are tracked and closed (e.g., January 2026).
*   **Business Responsibility**: Monthly Close Management, Expense Accrual Locking.
*   **Ownership Domain**: General Ledger.
*   **Dependencies**: `public.fiscal_periods`.
*   **Expected Read/Write Ratio**: 98% Reads / 2% Writes.

#### 4.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `fiscal_period_id` | `uuid` | NO | None | FK -> `fiscal_periods(id)`| - | Public | Valid UUIDv4 | Links sub-period to main fiscal year. |
| `period_name` | `varchar(20)` | NO | None | None | - | Public | e.g. 'JAN-2026' | Public label. |
| `start_date` | `date` | NO | None | None | - | Public | Date | Start of monthly period. |
| `end_date` | `date` | NO | None | None | - | Public | Date >= Start | End of monthly period. |
| `status` | `varchar(30)` | NO | `'open'` | None | - | Public | `'open'`, `'closed'`, `'locked'`| Operational state. |

#### 4.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `fiscal_period_id REFERENCES public.fiscal_periods(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT acc_period_dates CHECK (end_date >= start_date)`
    *   `CONSTRAINT acc_period_status CHECK (status IN ('open', 'closed', 'locked'))`

#### 4.2.4 Indexes
*   `acc_periods_pkey`: Primary Key index.
*   `acc_periods_lookup_idx`: B-Tree on `(organization_id, start_date, end_date)`.

#### 4.2.5 Financial Immutability
*   When `status` is `'closed'` or `'locked'`, any `ledger_entries` or `journal_entries` attempted with a posting date inside `[start_date, end_date]` are physically blocked by a database trigger.

#### 4.2.6 Relationships
*   **Many-to-One**: `public.fiscal_periods`.
*   **One-to-Many**: `public.journal_entries`, `public.financial_snapshots`.

---

### 4.3 Table Name: `public.ledger_batches`

#### 4.3.1 Purpose & Business Overview
*   **Purpose**: Groups related journal entries into a single verifiable set before posting to the ledger (e.g., Weekly Payroll Batch, Month-End Accruals Batch).
*   **Business Responsibility**: Verification before ledger commitment.
*   **Ownership Domain**: Journal Verification.
*   **Dependencies**: `system.organizations`.

#### 4.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(150)` | NO | None | None | - | Public | 2-150 characters | Name of the batch. |
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | `'draft'`, `'pending_review'`, `'posted'`| Lifecycle of the batch. |
| `total_debits` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Non-negative | Total debits inside batch. |
| `total_credits` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Non-negative | Total credits inside batch. |

#### 4.3.3 Relational & Integrity Constraints
*   **Check Constraints**:
    *   `CONSTRAINT ledger_batches_status CHECK (status IN ('draft', 'pending_review', 'posted'))`
    *   `CONSTRAINT ledger_batches_balances CHECK (total_debits >= 0.00 AND total_credits >= 0.00)`

#### 4.3.4 Relationships
*   **One-to-Many**: `public.journal_entries`.

---

### 4.4 Table Name: `public.journal_entries`

#### 4.4.1 Purpose & Business Overview
*   **Purpose**: The central transactional ledger record representing an accounting event. Contains a header with a description, date, status, and currency context.
*   **Business Responsibility**: Financial Transaction Integrity & Event Auditing.
*   **Ownership Domain**: General Ledger (High-Read, Medium-Write).
*   **Dependencies**: `system.organizations`, `public.accounting_periods`, `public.ledger_batches`.
*   **Lifecycle**: Transition from Draft -> Posted. Immutable once posted.
*   **Expected Read/Write Ratio**: 80% Reads / 20% Writes.
*   **Retention Policy**: Retained indefinitely (Legally required).

#### 4.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `batch_id` | `uuid` | YES | `NULL` | FK -> `ledger_batches(id)` | - | Public | Valid UUIDv4 | Optional batch group. |
| `accounting_period_id`|`uuid` | NO | None | FK -> `accounting_periods(id)`| - | Public | Valid UUIDv4 | Forces period allocation context. |
| `entry_number` | `varchar(50)` | NO | None | Unique per Org | - | Public | Alphanumeric | e.g. 'JE-2026-00045'. |
| `posting_date` | `date` | NO | None | None | - | Public | Date | Effective posting date. |
| `description` | `text` | YES | `NULL` | None | - | Public | None | Details about transaction context. |
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | `'draft'`, `'posted'`, `'reversed'`| State of the ledger entry. |
| `source_document_ref`| `varchar(100)`| YES | `NULL` | None | - | Public | None | Cross-ref to bills/invoices. |
| `reversal_entry_id` | `uuid` | YES | `NULL` | FK -> `journal_entries(id)` | - | Public | Valid UUIDv4 | If reversed, links to correct reversing JE. |

#### 4.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `batch_id REFERENCES public.ledger_batches(id) ON DELETE SET NULL`
    *   `accounting_period_id REFERENCES public.accounting_periods(id) ON DELETE RESTRICT`
    *   `reversal_entry_id REFERENCES public.journal_entries(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT je_status CHECK (status IN ('draft', 'posted', 'reversed'))`
    *   `CONSTRAINT je_prevent_self_reversal CHECK (reversal_entry_id <> id)`
*   **Unique Constraints**:
    *   `CONSTRAINT je_org_entry_number_key UNIQUE (organization_id, entry_number)`

#### 4.4.4 Indexes
*   `je_pkey`: Primary Key.
*   `je_org_number_uidx`: Unique Index on `(organization_id, entry_number)`.
*   `je_posting_date_idx`: B-Tree on `(organization_id, posting_date)`.

#### 4.4.5 Financial Immutability & Double-Entry Check
1.  **Immutability Trigger**: Once `status` is modified to `'posted'`, a database trigger blocks further updates or deletions on both `journal_entries` and its related `ledger_entries` child records.
2.  **Out-of-Balance Guard**: Prior to moving `status` from `'draft'` to `'posted'`, a database trigger checks:
    ```sql
    IF (SELECT SUM(debit_amount) - SUM(credit_amount) FROM public.ledger_entries WHERE journal_entry_id = NEW.id) <> 0.00 THEN
      RAISE EXCEPTION 'Journal Entry out of balance. Debits must equal Credits.';
    END IF;
    ```

#### 4.4.6 Relationships
*   **One-to-Many**: `public.ledger_entries`, `public.journal_entries` (Self-referential reversal).

---

### 4.5 Table Name: `public.ledger_entries`

#### 4.5.1 Purpose & Business Overview
*   **Purpose**: Individual debit and credit rows belonging to a journal entry.
*   **Business Responsibility**: Physical double-entry line ledger records.
*   **Ownership Domain**: General Ledger.
*   **Dependencies**: `public.journal_entries`, `public.chart_of_accounts`.
*   **Lifecycle**: Immutable once parent `journal_entries` status is `'posted'`.
*   **Expected Read/Write Ratio**: 90% Reads / 10% Writes.
*   **Retention Policy**: Retained indefinitely.

#### 4.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `journal_entry_id` | `uuid` | NO | None | FK -> `journal_entries(id)`| - | Public | Valid UUIDv4 | Parent header. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | Valid UUIDv4 | Ledger account context. |
| `cost_center_id` | `uuid` | YES | `NULL` | FK -> `cost_centers(id)` | - | Public | Valid UUIDv4 | Split cost tracking. |
| `department_id` | `uuid` | YES | `NULL` | FK -> `departments(id)` | - | Public | Valid UUIDv4 | Department cost routing. |
| `debit_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Positive or `0.00` | Debit value in transaction currency. |
| `credit_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Positive or `0.00` | Credit value in transaction currency. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Original currency used. |
| `exchange_rate_at_posting`|`numeric(18,6)`|NO|`1.000000`| None | - | Public | Rate > 0 | Historical exchange rate. |
| `amount_in_base` | `numeric(18,2)`| NO | None | None | - | Financial | Calculated field | Base currency equivalent. |
| `memo` | `text` | YES | `NULL` | None | - | Public | None | Line-level audit detail. |

#### 4.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `journal_entry_id REFERENCES public.journal_entries(id) ON DELETE CASCADE`
    *   `account_id REFERENCES public.chart_of_accounts(id) ON DELETE RESTRICT`
    *   `cost_center_id REFERENCES public.cost_centers(id) ON DELETE RESTRICT`
    *   `department_id REFERENCES public.departments(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT ledger_entries_debit_positive CHECK (debit_amount >= 0.00)`
    *   `CONSTRAINT ledger_entries_credit_positive CHECK (credit_amount >= 0.00)`
    *   `CONSTRAINT ledger_entries_rate_positive CHECK (exchange_rate_at_posting > 0.000000)`
    *   `CONSTRAINT ledger_entries_prevent_double_posting CHECK (NOT (debit_amount > 0.00 AND credit_amount > 0.00))`
    *   `CONSTRAINT ledger_entries_prevent_zero_posting CHECK (debit_amount > 0.00 OR credit_amount > 0.00)`

#### 4.5.4 Indexes
*   `ledger_entries_pkey`: Primary Key index.
*   `ledger_entries_journal_idx`: B-Tree on `(journal_entry_id)`.
*   `ledger_entries_reporting_idx`: Composite covering index on `(account_id, organization_id)` INCLUDE `(debit_amount, credit_amount, amount_in_base)`.

---

## SECTION 5: BILLING & INVOICING ENGINE

---

### 5.1 Table Name: `public.invoices`

#### 5.1.1 Purpose & Business Overview
*   **Purpose**: Manages outward billing documents issued to client accounts for products delivered or services rendered.
*   **Business Responsibility**: Revenue capture, payment tracking, compliance monitoring.
*   **Ownership Domain**: Accounts Receivable / Billing.
*   **Dependencies**: `system.organizations`, `public.client_accounts`.
*   **Lifecycle**: Draft -> Sent -> Paid / Overdue / Cancelled.
*   **Expected Read/Write Ratio**: 70% Reads / 30% Writes.
*   **Retention Policy**: Retained indefinitely (7 years minimum required for regulatory tax audits).

#### 5.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)`| - | Public | Valid UUIDv4 | CRM link. |
| `invoice_number` | `varchar(50)` | NO | None | Unique per Org | - | Public | e.g. 'INV-2026-0001' | Public document identifier. |
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | Valid statuses | Logical invoice lifespans. |
| `issue_date` | `date` | NO | None | None | - | Public | Date | Date issued to customer. |
| `due_date` | `date` | NO | None | None | - | Public | Date >= issue_date | Payment expectations limit. |
| `subtotal` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Subtotal >= 0.00 | Net total before discounts/taxes. |
| `discount_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Discount >= 0.00 | Aggregate discount deductions. |
| `tax_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Tax >= 0.00 | Aggregate calculated tax liabilities. |
| `total_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Total >= 0.00 | Net payable invoice liability. |
| `balance_due` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Balance >= 0.00 | Outstanding collection target. |
| `currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Transaction currency. |

#### 5.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `client_account_id REFERENCES public.client_accounts(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT invoices_dates CHECK (due_date >= issue_date)`
    *   `CONSTRAINT invoices_amounts CHECK (total_amount >= 0.00 AND balance_due >= 0.00)`
    *   `CONSTRAINT invoices_status CHECK (status IN ('draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'))`
*   **Unique Constraints**:
    *   `CONSTRAINT invoices_org_number_key UNIQUE (organization_id, invoice_number)`

#### 5.1.4 Ledger Posting Rules
*   **Invoice Sent (Posting Event)**:
    *   **Debit**: Accounts Receivable (`total_amount`)
    *   **Credit**: Revenue (`subtotal`)
    *   **Credit**: Tax Liability (`tax_amount`)

#### 5.1.5 Produced & Consumed Events
*   **Produced Events**: `invoice.created`, `invoice.sent`, `invoice.overdue`, `invoice.cancelled`, `invoice.paid`
*   **Consumed Events**: `payment.completed` (Allocates and updates `balance_due` to zero).

#### 5.1.6 Relationships
*   **One-to-Many**: `public.invoice_line_items`, `public.invoice_adjustments`, `public.invoice_discounts`, `public.invoice_tax_lines`, `public.invoice_comments`, `public.invoice_attachments`, `public.invoice_history`.

---

### 5.2 Table Name: `public.invoice_line_items`

#### 5.2.1 Purpose & Business Catalog
*   **Purpose**: Specific billing lines inside an invoice representing services, tasks, or items sold.
*   **Business Responsibility**: Granular line-item audit detail.
*   **Ownership Domain**: Billing.

#### 5.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Parent invoice reference. |
| `description` | `text` | NO | None | None | - | Public | 2+ characters | Line item details. |
| `quantity` | `numeric(12,4)`| NO | `1.0000` | None | - | Public | > 0.0000 | Number of items. |
| `unit_price` | `numeric(18,4)`| NO | `0.0000` | None | - | Financial | Unit Price >= 0.00 | Net unit cost. |
| `line_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Net line value | Quantity * Unit Price. |

#### 5.2.3 Relational & Integrity Constraints
*   **Foreign Keys**:
    *   `invoice_id REFERENCES public.invoices(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT inv_line_qty CHECK (quantity > 0.0000)`
    *   `CONSTRAINT inv_line_price CHECK (unit_price >= 0.0000)`

---

### 5.3 Table Name: `public.invoice_adjustments`

#### 5.3.1 Purpose & Business Overview
*   **Purpose**: Stores physical late fee additions, service charges, or post-billing corrections to invoice headers.
*   **Business Responsibility**: Post-Invoice Fee Allocations.
*   **Ownership Domain**: Billing.

#### 5.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Parent invoice. |
| `type` | `varchar(30)` | NO | None | None | - | Public | `'late_fee'`, `'service_charge'`| Category of charge. |
| `amount` | `numeric(18,2)`| NO | None | None | - | Financial | Amount > 0.00 | Valuation of charge. |

#### 5.3.3 Relational & Integrity Constraints
*   **Foreign Keys**:
    *   `invoice_id REFERENCES public.invoices(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT inv_adj_type CHECK (type IN ('late_fee', 'service_charge'))`
    *   `CONSTRAINT inv_adj_amount CHECK (amount > 0.00)`

---

### 5.4 Table Name: `public.invoice_discounts`

#### 5.4.1 Purpose & Business Overview
*   **Purpose**: Tracks individual discounts (percentage or flat amount) deducted from parent invoices.
*   **Business Responsibility**: Campaign discounting or early-payment incentives tracking.

#### 5.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Parent invoice context. |
| `discount_type` | `varchar(20)` | NO | `'flat'` | None | - | Public | `'flat'`, `'percentage'`| Defines discount type. |
| `rate` | `numeric(10,4)`| NO | `0.0000` | None | - | Public | Rate >= 0.0000 | Percentage rate or flat currency value. |
| `calculated_amount`|`numeric(18,2)`|NO | `0.00` | None | - | Financial | Amount >= 0.00 | Net calculated deduction. |

#### 5.4.3 Relational & Integrity Constraints
*   **Foreign Keys**:
    *   `invoice_id REFERENCES public.invoices(id) ON DELETE CASCADE`
*   **Check Constraints**:
    *   `CONSTRAINT inv_disc_type CHECK (discount_type IN ('flat', 'percentage'))`
    *   `CONSTRAINT inv_disc_rate CHECK (rate >= 0.0000)`

---

### 5.5 Table Name: `public.invoice_tax_lines`

#### 5.5.1 Purpose & Business Overview
*   **Purpose**: Records individual tax calculations on an invoice (e.g., VAT, GST, State Taxes) based on jurisdictional rules.
*   **Business Responsibility**: Tax liability ledger calculations.

#### 5.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Parent invoice. |
| `tax_jurisdiction_id`|`uuid`| NO | None | FK -> `tax_jurisdictions(id)`| - | Public | Valid UUIDv4 | Tax authority connection. |
| `tax_rate_id` | `uuid` | NO | None | FK -> `tax_rates(id)` | - | Public | Valid UUIDv4 | Active rate used. |
| `taxable_amount` | `numeric(18,2)`| NO | None | None | - | Financial | Taxable base | Net subtotal subject to tax. |
| `tax_amount` | `numeric(18,2)`| NO | None | None | - | Financial | Tax value | Calculated tax line. |

---

### 5.6 Table Name: `public.invoice_comments`

#### 5.6.1 Purpose & Business Overview
*   **Purpose**: Immutable chronological comments thread between financial controllers or clients regarding disputes, payment promises, or status adjustments.

#### 5.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public / PII | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Target invoice card. |
| `body` | `text` | NO | None | None | - | Public / PII | Plain text / markdown | Actual message body. |

---

### 5.7 Table Name: `public.invoice_attachments`

#### 5.7.1 Purpose & Business Overview
*   **Purpose**: Stores file metadata (PDFs, receipts, timesheet summaries) uploaded to confirm work backing the invoice.

#### 5.7.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Parent invoice. |
| `file_name` | `varchar(255)` | NO | None | None | - | Public | 1-255 characters | Display name of the file. |
| `file_path` | `text` | NO | None | None | - | Public | Google Storage URL | Storage locator reference. |
| `file_size` | `bigint` | NO | None | None | - | Public | Size > 0 | Tracking storage metrics. |

---

### 5.8 Table Name: `public.invoice_history`

#### 5.8.1 Purpose & Business Overview
*   **Purpose**: Append-only audit log tracking every state mutation, delivery, dispute, or payment event associated with an invoice.
*   **Lifecycle**: Immutable.

#### 5.8.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | - | Public | Valid UUIDv4 | Unique row identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)`| - | Public | Valid UUIDv4 | Multi-tenant separator. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Target invoice audited. |
| `actor_id` | `uuid` | YES | `NULL` | FK -> `security.users(id)` | - | Public | Valid UUIDv4 | Responsible user. |
| `action_type` | `varchar(50)` | NO | None | None | - | Public | e.g. `'sent'`, `'disputed'`| Event action descriptor. |
| `notes` | `text` | YES | `NULL` | None | - | Public | None | Audit descriptions. |
| `created_at` | `timestamptz` | NO | `now()` | None | - | Public | UTC timestamp | Chronological log point. |

---

## SECTION 6: CREDIT & DEBIT NOTES

---

### 6.1 Table Name: `public.credit_notes`

#### 6.1.1 Purpose & Business Overview
*   **Purpose**: Accounts receivable credit adjustments issued to reduce an invoice outstanding balance or grant future credits to a client (e.g., for returned products, billing corrections).
*   **Business Responsibility**: Accounts Receivable Credits Management.
*   **Expected Read/Write Ratio**: 90% Reads / 10% Writes.

#### 6.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)`| - | Public | Valid UUIDv4 | Target customer. |
| `invoice_id` | `uuid` | YES | `NULL` | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Optional related invoice. |
| `credit_note_number`|`varchar(50)`|NO | None | Unique per Org | - | Public | e.g. 'CN-2026-0001' | Unique identifier. |
| `amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Amount > 0.00 | Net valuation. |
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | Valid statuses | Draft, Approved, Allocated, Void. |

#### 6.1.3 Ledger Posting Rules
*   **Credit Note Issued (Posting Event)**:
    *   **Debit**: Sales Returns / Revenue Adjustment (`amount`)
    *   **Credit**: Accounts Receivable (`amount`)

---

### 6.2 Table Name: `public.credit_note_items`

#### 6.2.1 Purpose & Business Overview
*   **Purpose**: Specific line rows detailing deductions inside the credit note.

#### 6.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `credit_note_id` | `uuid` | NO | None | FK -> `credit_notes(id)` | - | Public | Valid UUIDv4 | Parent credit note. |
| `description` | `text` | NO | None | None | - | Public | None | Reason for line. |
| `amount` | `numeric(18,2)`| NO | None | None | - | Financial | > 0.00 | Allocated deduction value. |

---

### 6.3 Table Name: `public.debit_notes`

#### 6.3.1 Purpose & Business Overview
*   **Purpose**: Accounts payable credit corrections issued to increase an invoice's payable liability or adjust pricing in favour of the organization.

#### 6.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `vendor_id` | `uuid` | NO | None | FK -> `vendors(id)` | - | Public | Valid UUIDv4 | Target vendor. |
| `bill_id` | `uuid` | YES | `NULL` | FK -> `bills(id)` | - | Public | Valid UUIDv4 | Associated bill reference. |
| `debit_note_number`|`varchar(50)`| NO | None | Unique per Org | - | Public | e.g. 'DN-2026-0001' | Identifies the debit note. |
| `amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Amount > 0.00 | Payable valuation. |
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | `'draft'`, `'approved'`| Administrative state. |

---

### 6.4 Table Name: `public.debit_note_items`

#### 6.4.1 Purpose & Business Overview
*   **Purpose**: Specific lines inside the debit note.

#### 6.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `debit_note_id` | `uuid` | NO | None | FK -> `debit_notes(id)` | - | Public | Valid UUIDv4 | Parent debit note. |
| `description` | `text` | NO | None | None | - | Public | None | Itemized details. |
| `amount` | `numeric(18,2)`| NO | None | None | - | Financial | > 0.00 | Debit value. |

---

## SECTION 7: ACCOUNTS RECEIVABLE & PAYABLES

---

### 7.1 Table Name: `public.accounts_receivable`

#### 7.1.1 Purpose & Business Overview
*   **Purpose**: Chronological ledger mapping customer outstanding invoice balances (Receivables Ledger).
*   **Expected Read/Write Ratio**: 90% Reads / 10% Writes.

#### 7.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)`| - | Public | Valid UUIDv4 | Mapped client debtor. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Base outstanding document. |
| `original_amount` | `numeric(18,2)`| NO | None | None | - | Financial | > 0.00 | Net historical liability. |
| `remaining_balance`|`numeric(18,2)`|NO | None | None | - | Financial | >= 0.00 | Outstanding collection target. |

#### 7.1.3 Ledger Posting Rules
*   Synchronized with Invoice creation and Payment receipt triggers.

---

### 7.2 Table Name: `public.receivable_allocations`

#### 7.2.1 Purpose & Business Overview
*   **Purpose**: Specific transaction rows matching cash receipts (or credit notes) to accounts receivable lines.

#### 7.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `receivable_id` | `uuid` | NO | None | FK -> `accounts_receivable(id)`| - | Public | Valid UUIDv4 | Target receivable item. |
| `allocated_amount` | `numeric(18,2)`| NO | None | None | - | Financial | > 0.00 | Portion of invoice paid. |
| `allocation_date` | `date` | NO | `now()` | None | - | Public | Date | Date allocated. |

---

### 7.3 Table Name: `public.vendors`

#### 7.3.1 Purpose & Business Overview
*   **Purpose**: Manages business suppliers and vendors contracted for outsourcing, hardware acquisitions, or recurring services.
*   **Business Responsibility**: Supply Chain Custody, Outbound Payables Accountability.
*   **Expected Read/Write Ratio**: 95% Reads / 5% Writes.

#### 7.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(200)` | NO | None | None | - | Public | 2-200 characters | Legal vendor name. |
| `tax_identifier` | `varchar(50)` | YES | `NULL` | None | - | Private / PII | Tax registration | Corporate Tax/VAT Registration. |
| `payment_terms_days`|`integer` | NO | `30` | None | - | Public | >= 0 | Net standard payback window. |
| `is_active` | `boolean` | NO | `true` | None | - | Public | Boolean | Controls billing state. |

---

### 7.4 Table Name: `public.vendor_contacts`

#### 7.4.1 Purpose & Business Overview
*   **Purpose**: Stores PII contact profiles of account representatives inside vendor organizations.

#### 7.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public / PII | See Section 1.1 | Standards compliance. |
| `vendor_id` | `uuid` | NO | None | FK -> `vendors(id)` | - | Public | Valid UUIDv4 | Parent vendor profile. |
| `first_name` | `varchar(100)` | NO | None | None | - | Public / PII | Name string | Contact name. |
| `last_name` | `varchar(100)` | NO | None | None | - | Public / PII | Name string | Contact name. |
| `email` | `varchar(150)` | NO | None | None | - | Confidential | Email address | Contact email. |
| `phone_number` | `varchar(50)` | YES | `NULL` | None | - | Confidential | Phone format | Direct telephone extension. |

---

### 7.5 Table Name: `public.bills`

#### 7.5.1 Purpose & Business Overview
*   **Purpose**: Stores incoming invoices issued to the organization by external vendors (Payable Bills).
*   **Expected Read/Write Ratio**: 80% Reads / 20% Writes.

#### 7.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `vendor_id` | `uuid` | NO | None | FK -> `vendors(id)` | - | Public | Valid UUIDv4 | Supplier account profile. |
| `bill_number` | `varchar(50)` | NO | None | Unique per Org | - | Public | Alphanumeric | Invoice identifier from vendor. |
| `issue_date` | `date` | NO | None | None | - | Public | Date | Date vendor issued bill. |
| `due_date` | `date` | NO | None | None | - | Public | Date >= issue_date | Payment deadline. |
| `subtotal` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Subtotal >= 0.00 | Net pre-tax value. |
| `tax_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Tax >= 0.00 | Net tax value. |
| `total_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Total >= 0.00 | Total liability payable. |
| `status` | `varchar(30)` | NO | `'unpaid'` | None | - | Public | Valid states | unpaid, partially_paid, paid, void. |

#### 7.5.3 Ledger Posting Rules
*   **Bill Posted (Posting Event)**:
    *   **Debit**: Expense or Asset Account (`subtotal`)
    *   **Debit**: Input Tax Credit (`tax_amount`)
    *   **Credit**: Accounts Payable (`total_amount`)

---

### 7.6 Table Name: `public.bill_line_items`

#### 7.6.1 Purpose & Business Overview
*   **Purpose**: Specific transaction rows detailing expenditures inside a vendor bill.

#### 7.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `bill_id` | `uuid` | NO | None | FK -> `bills(id)` | - | Public | Valid UUIDv4 | Parent bill link. |
| `description` | `text` | NO | None | None | - | Public | None | Audit descriptions. |
| `quantity` | `numeric(12,4)`| NO | `1.0000` | None | - | Public | > 0.0000 | Item quantity. |
| `unit_price` | `numeric(18,4)`| NO | `0.0000` | None | - | Financial | >= 0.0000 | Item unit price. |
| `line_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Quantity * Price | Line cost. |

---

### 7.7 Table Name: `public.accounts_payable`

#### 7.7.1 Purpose & Business Overview
*   **Purpose**: Chronological outstanding balances scorecard tracking total amounts owed to physical vendors.

#### 7.7.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `vendor_id` | `uuid` | NO | None | FK -> `vendors(id)` | - | Public | Valid UUIDv4 | Creditor link. |
| `bill_id` | `uuid` | NO | None | FK -> `bills(id)` | - | Public | Valid UUIDv4 | Base outstanding bill. |
| `original_amount` | `numeric(18,2)`| NO | None | None | - | Financial | > 0.00 | Net historical debt. |
| `remaining_balance`|`numeric(18,2)`|NO | None | None | - | Financial | >= 0.00 | Total remaining debt. |

---

## SECTION 8: PAYMENTS ALLOCATION ENGINE

---

### 8.1 Table Name: `public.payment_allocations`

#### 8.1.1 Purpose & Business Overview
*   **Purpose**: Standard matching database engine which tracks the receipt of funds and allocates them across one or multiple Accounts Receivable invoice lines (or outward payments to Accounts Payable bill lines).
*   **Business Responsibility**: Operational Cash Application, Cash Flow tracking.
*   **Ownership Domain**: Payments Core.
*   **Dependencies**: `system.organizations`, `public.invoices` (or `public.bills`).
*   **Expected Read/Write Ratio**: 70% Reads / 30% Writes.
*   **Retention Policy**: Retained indefinitely.

#### 8.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `payment_reference`| `varchar(100)`| NO | None | None | - | Public | e.g. bank wire ref | Identifies bank transaction. |
| `allocated_amount` | `numeric(18,2)`| NO | None | None | - | Financial | Amount > 0.00 | Applied cash portion. |
| `allocation_date` | `date` | NO | `now()` | None | - | Public | Date | Date applied. |
| `direction` | `varchar(10)` | NO | `'inbound'` | None | - | Public | `'inbound'`, `'outbound'`| Applied to AR or AP. |
| `invoice_id` | `uuid` | YES | `NULL` | FK -> `invoices(id)` | - | Public | Valid UUIDv4 | Target invoice if inbound. |
| `bill_id` | `uuid` | YES | `NULL` | FK -> `bills(id)` | - | Public | Valid UUIDv4 | Target bill if outbound. |

#### 8.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `invoice_id REFERENCES public.invoices(id) ON DELETE RESTRICT`
    *   `bill_id REFERENCES public.bills(id) ON DELETE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT payment_allocations_amount CHECK (allocated_amount > 0.00)`
    *   `CONSTRAINT payment_allocations_direction CHECK (direction IN ('inbound', 'outbound'))`
    *   `CONSTRAINT payment_allocations_context CHECK ((direction = 'inbound' AND invoice_id IS NOT NULL AND bill_id IS NULL) OR (direction = 'outbound' AND bill_id IS NOT NULL AND invoice_id IS NULL))`

#### 8.1.4 Ledger Posting Rules
*   **Inbound Payment Applied (Posting Event)**:
    *   **Debit**: Cash / Bank Account (`allocated_amount`)
    *   **Credit**: Accounts Receivable (`allocated_amount`)
*   **Outbound Payment Settled (Posting Event)**:
    *   **Debit**: Accounts Payable (`allocated_amount`)
    *   **Credit**: Cash / Bank Account (`allocated_amount`)

#### 8.1.5 Produced & Consumed Events
*   **Produced Events**: `payment.allocated`
*   **Consumed Events**: `payment.completed` (Starts the cash application matching logic).

---

## SECTION 9: TAX & CURRENCY LOCALIZATION ENGINES

---

### 9.1 Table Name: `public.tax_jurisdictions`

#### 9.1.1 Purpose & Business Overview
*   **Purpose**: Defines geographical regions or tax authorities requiring tax compliance mapping (e.g., UK HMRC, California Franchise Tax Board).
*   **Business Responsibility**: Legal and Tax Authority mapping.

#### 9.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `name` | `varchar(150)` | NO | None | None | - | Public | Name string | Authority name. |
| `country_code` | `varchar(2)` | NO | None | None | - | Public | ISO 2-character | e.g. 'GB', 'US', 'DE'. |
| `state_province` | `varchar(100)` | YES | `NULL` | None | - | Public | None | Regional division. |

---

### 9.2 Table Name: `public.tax_rules`

#### 9.2.1 Purpose & Business Overview
*   **Purpose**: Logical tax evaluation rules mapping operational categories to tax percentage triggers.

#### 9.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `jurisdiction_id` | `uuid` | NO | None | FK -> `tax_jurisdictions(id)`| - | Public | Valid UUIDv4 | Local tax authority. |
| `name` | `varchar(150)` | NO | None | None | - | Public | None | Rule description. |
| `rule_code` | `varchar(50)` | NO | None | Unique per Org | - | Public | e.g. 'UK_VAT_STANDARD'| Machine code. |

---

### 9.3 Table Name: `public.tax_rates`

#### 9.3.1 Purpose & Business Overview
*   **Purpose**: Specific tax percentage rates mapped to active tax rules with scheduling dates.

#### 9.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `tax_rule_id` | `uuid` | NO | None | FK -> `tax_rules(id)` | - | Public | Valid UUIDv4 | Parent rule link. |
| `rate_percentage` | `numeric(6,4)` | NO | None | None | - | Public | >= 0.0000 | tax rate (e.g., 20.0000 for 20%). |
| `start_date` | `date` | NO | None | None | - | Public | Date | Activation date. |
| `end_date` | `date` | YES | `NULL` | None | - | Public | Date >= start_date | Deprecation date. |

---

### 9.4 Table Name: `public.tax_exemptions`

#### 9.4.1 Purpose & Business Overview
*   **Purpose**: Certificates or exemptions held by clients or projects mapping tax-exempt overrides.

#### 9.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `client_account_id`| `uuid` | YES | `NULL` | FK -> `client_accounts(id)`| - | Public | Valid UUIDv4 | Exempt client. |
| `exemption_certificate`|`varchar(100)`|NO|None| None | - | Confidential | Alphanumeric | Legal certificate number. |
| `reason` | `varchar(255)` | NO | None | None | - | Public | None | Legal exemption reason. |

---

### 9.5 Table Name: `system.currencies`

#### 9.5.1 Purpose & Business Overview
*   **Purpose**: Master system-wide global lookup table containing currency metadata (e.g., USD, EUR, GBP, KES).
*   **Business Responsibility**: Global Currency Standardization.
*   **Ownership Domain**: Global Core System (Lookup / Read-Only for tenants).
*   **Expected Read/Write Ratio**: 99.9% Reads / 0.1% Writes.

#### 9.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | - | Public | Valid UUIDv4 | Primary key lookup. |
| `code` | `varchar(3)` | NO | None | Unique globally | - | Public | 3-char UPPERCASE | ISO code (e.g., 'USD'). |
| `name` | `varchar(100)` | NO | None | None | - | Public | Name | Full name (e.g. 'US Dollar').|
| `symbol` | `varchar(10)` | NO | None | None | - | Public | Symbol char | e.g. '$', '€'. |
| `decimal_places` | `integer` | NO | `2` | None | - | Public | Positive integer | Base precision mapping. |

---

### 9.6 Table Name: `public.exchange_rates`

#### 9.6.1 Purpose & Business Overview
*   **Purpose**: Dynamic exchange rates mapping specific currencies back to the tenant's organization base currency.

#### 9.6.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `from_currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Original currency. |
| `to_currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Org base currency. |
| `rate` | `numeric(18,6)`| NO | None | None | - | Public | > 0.000000 | Multiplier exchange rate. |
| `last_updated_at` | `timestamptz` | NO | `now()` | None | - | Public | UTC timestamp | Latest rate retrieval sync. |

---

### 9.7 Table Name: `public.exchange_rate_history`

#### 9.7.1 Purpose & Business Overview
*   **Purpose**: Chronological lookup tables auditing dynamic rate changes over time, facilitating accurate historical translations.

#### 9.7.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PRIMARY KEY | - | Public | Valid UUIDv4 | Unique row. |
| `organization_id` | `uuid` | NO | None | FK -> `system.organizations(id)`| - | Public | Valid UUIDv4 | Multi-tenant border. |
| `from_currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Source currency. |
| `to_currency_id` | `uuid` | NO | None | FK -> `system.currencies(id)`| - | Public | Valid UUIDv4 | Destination currency. |
| `rate` | `numeric(18,6)`| NO | None | None | - | Public | > 0.000000 | Historic rate. |
| `effective_date` | `date` | NO | None | None | - | Public | Date | Active date of this rate. |

---

## SECTION 10: BUDGETING CORE

---

### 10.1 Table Name: `public.budgets`

#### 10.1.1 Purpose & Business Overview
*   **Purpose**: Outlines corporate spending targets and projected revenues associated with cost centers, departments, or specific corporate years.
*   **Business Responsibility**: Corporate Financial planning and budgeting control.
*   **Expected Read/Write Ratio**: 90% Reads / 10% Writes.

#### 10.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `fiscal_period_id` | `uuid` | NO | None | FK -> `fiscal_periods(id)`| - | Public | Valid UUIDv4 | Parent reporting year. |
| `name` | `varchar(150)` | NO | None | None | - | Public | 2-150 characters | e.g. '2026 Marketing Budget'.|
| `status` | `varchar(30)` | NO | `'draft'` | None | - | Public | Valid statuses | Draft, Approved, Archived. |

---

### 10.2 Table Name: `public.budget_lines`

#### 10.2.1 Purpose & Business Overview
*   **Purpose**: Itemized spending thresholds mapped to specific chart of accounts rows.

#### 10.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `budget_id` | `uuid` | NO | None | FK -> `budgets(id)` | - | Public | Valid UUIDv4 | Parent budget link. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | Valid UUIDv4 | Ledger account context. |
| `cost_center_id` | `uuid` | YES | `NULL` | FK -> `cost_centers(id)` | - | Public | Valid UUIDv4 | Mapped center tracking. |
| `allocated_amount` | `numeric(18,2)`| NO | None | None | - | Financial | Amount >= 0.00 | Authorized budget cap. |

---

## SECTION 11: FINANCIAL REPORTING & AUDIT CORE

---

### 11.1 Table Name: `public.trial_balances`

#### 11.1.1 Purpose & Business Overview
*   **Purpose**: Consolidated aggregate balances representing total debit and credit balances for all chart of accounts rows in a given period. Used for year-end checks.
*   **Expected Read/Write Ratio**: 95% Reads / 5% Writes (Triggered on close).

#### 11.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `fiscal_period_id` | `uuid` | NO | None | FK -> `fiscal_periods(id)`| - | Public | Valid UUIDv4 | Parent fiscal year. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | Valid UUIDv4 | Ledger account evaluated. |
| `total_debits` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | >= 0.00 | Accumulated debits. |
| `total_credits` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | >= 0.00 | Accumulated credits. |
| `final_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Net result | Debits minus Credits. |

---

### 11.2 Table Name: `public.financial_snapshots`

#### 11.2.1 Purpose & Business Overview
*   **Purpose**: Materialized financial results (Balance Sheet, Profit & Loss) generated periodically for static reporting and analytics.

#### 11.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `accounting_period_id`|`uuid` | NO | None | FK -> `accounting_periods(id)`| - | Public | Valid UUIDv4 | Base period for snapshot. |
| `snapshot_type` | `varchar(50)` | NO | None | None | - | Public | e.g. 'balance_sheet' | Financial report categorizer.|
| `payload` | `jsonb` | NO | None | None | - | Financial | Valid JSON | Full serialized report tree.|

---

### 11.3 Table Name: `public.financial_adjustments`

#### 11.3.1 Purpose & Business Overview
*   **Purpose**: Stores non-standard adjustment mappings authorized by auditors (e.g. write-offs, depreciation adjustments, corrections of historic periods).
*   **Lifecycle**: Immutable once authorized.

#### 11.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `journal_entry_id` | `uuid` | NO | None | FK -> `journal_entries(id)`| - | Public | Valid UUIDv4 | Parent transaction linked. |
| `auditor_id` | `uuid` | NO | None | FK -> `security.users(id)` | - | Public | Valid UUIDv4 | Auditor signature. |
| `reason` | `text` | NO | None | None | - | Public | Min 10 chars | Audit adjustment rationale. |

---

### 11.4 Table Name: `public.reconciliation_runs`

#### 11.4.1 Purpose & Business Overview
*   **Purpose**: Tracks bank reconciliation procedures matching ledger records back to real-world bank statements.
*   **Expected Read/Write Ratio**: 60% Reads / 40% Writes.

#### 11.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY GLOBAL]`| Multiple | - | - | See Section 1.1 | - | Public | See Section 1.1 | Standards compliance. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | Valid UUIDv4 | Bank account reconciled. |
| `statement_date` | `date` | NO | None | None | - | Public | Date | Statement closing date. |
| `statement_balance`|`numeric(18,2)`|NO | None | None | - | Financial | Any number | Balance on physical doc. |
| `ledger_balance` | `numeric(18,2)`| NO | None | None | - | Financial | Any number | Balance on internal ledger. |
| `status` | `varchar(30)` | NO | `'unreconciled'`| None | - | Public | Valid statuses | unreconciled, reconciled, flag. |

---

## SECTION 12: CONCURRENCY, LOCKING & DEADLOCK AVOIDANCE

Accounting operations require rigorous transactional controls to protect the integrity of financial balances under heavy write loads.

### 12.1 Optimistic Concurrency Controls
*   Every table implements the standard `version` column.
*   Operational interfaces (e.g., invoice updates, journal editing) must check `version` on save:
    ```sql
    UPDATE public.invoices 
    SET status = 'sent', version = version + 1 
    WHERE id = :id AND version = :expected_version;
    ```
    If 0 rows are updated, the transaction is aborted to prevent overwriting concurrent edits.

### 12.2 Pessimistic Ledger Locking
During financial batch posting or year-end closures, we enforce **pessimistic locking** on related master records to prevent modifications while balance aggregation occurs.
*   **Bank Reconciliations**: Acquires a `SELECT ... FOR UPDATE` lock on matching `ledger_entries` records to block modification during reconciliation runs.
*   **Double-Entry Posting**: The posting engine locks parent `journal_entries` rows:
    ```sql
    SELECT id FROM public.journal_entries WHERE id = :je_id FOR UPDATE;
    ```

### 12.3 Deadlock Avoidance Standards
To avoid relational loop deadlocks (e.g., Transaction A locks Account 1 then Account 2, while Transaction B locks Account 2 then Account 1), all ledger transaction postings must:
1.  **Enforce Ordering**: Always sort and lock accounts by `account_id` (or `account_number`) in ascending alphanumeric order.
2.  **Short-Lived Transactions**: Financial batches must perform database calculations inside highly localized transactions to minimize lock holding times.

---

## SECTION 12B: DETAILED CONCURRENCY, SECURITY & POLICIES BY TABLE

Below is a tabular reference outlining specific Row-Level Security (RLS) rules, period locks, and concurrency configurations for each Finance table:

| Table Name | Concurrency Strategy | RLS Read Policy | RLS Write Policy | Write / Post Restrictions | Period Lock Check Required? |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `account_categories` | Version, Read-Lock | Tenant Members | Admin, Controller | None once COA entries exist | No |
| `chart_of_accounts` | Version, Alphanumeric Sort | Tenant Members | Finance Controller | Block edits if ledger history exists | No |
| `cost_centers` | Version, Read-Lock | Tenant Members | HR, Controller | None | No |
| `departments` | Version, Read-Lock | Tenant Members | HR, Admin | None | No |
| `fiscal_periods` | Version, Row-Lock | Tenant Members | CFO, Controller | Block edit if status is Closed | No |
| `accounting_periods` | Version, Row-Lock | Tenant Members | Controller, Admin | Block insertions if Fiscal is Closed | No |
| `ledger_batches` | Version, Alphanumeric Sort | Tenant Members | Finance staff | Block edits if status is Posted | Yes |
| `journal_entries` | Version, `FOR UPDATE` Row-Lock | Tenant Members | Finance Controller | Trigger blocks updates if Posted | Yes |
| `ledger_entries` | Row-Lock on parent JE | Tenant Members | Finance Controller | Block edits if parent JE is Posted | Yes |
| `invoices` | Version, Optimistic | Tenant Members | Billing Specialist | Block delete unless Draft status | Yes |
| `invoice_line_items` | Cascaded from Invoices | Tenant Members | Billing Specialist | Block modifications if Invoice Sent/Paid | Yes |
| `invoice_adjustments` | Cascaded from Invoices | Tenant Members | Billing Specialist | Only permitted on Unpaid/Overdue | Yes |
| `invoice_discounts` | Cascaded from Invoices | Tenant Members | Billing Specialist | Only permitted in Draft stage | Yes |
| `invoice_tax_lines` | Cascaded from Invoices | Tenant Members | Billing Specialist | Automatically calculated; read-only | Yes |
| `invoice_comments` | Append-Only | Tenant Members | Author | Cannot edit once inserted | No |
| `invoice_attachments` | Append-Only | Tenant Members | Billing Specialist | Allowed only before Invoice closure | No |
| `invoice_history` | Immutable (Insert-Only) | Tenant Members | Trigger Only | Write block via DB rules | No |
| `credit_notes` | Version, Row-Lock | Tenant Members | Controller, CFO | Block edit if status is Allocated | Yes |
| `credit_note_items` | Cascaded from CN | Tenant Members | Controller | Block edit if CN status is Allocated | Yes |
| `debit_notes` | Version, Row-Lock | Tenant Members | Controller | Block edit if CN status is Approved | Yes |
| `debit_note_items` | Cascaded from DN | Tenant Members | Controller | Block edit if DN status is Approved | Yes |
| `accounts_receivable` | Row-Lock on reconciliation| Tenant Members | Billing System | Read-Only for standard users | Yes |
| `receivable_allocations`| Append-Only | Tenant Members | Payments System | Immutable ledger rows | Yes |
| `vendors` | Version, Optimistic | Tenant Members | Procurement Staff | None | No |
| `vendor_contacts` | Version, Optimistic | Tenant Members | Procurement Staff | None | No |
| `bills` | Version, Optimistic | Tenant Members | Accounts Payable Staff | Block edits if status is Paid | Yes |
| `bill_line_items` | Cascaded from Bills | Tenant Members | Accounts Payable Staff | Block edits if Bill is Paid | Yes |
| `accounts_payable` | Row-Lock on reconciliation| Tenant Members | AP System | Read-Only ledger metrics | Yes |
| `payment_allocations` | Version, Optimistic | Tenant Members | Cash App Specialist | Block edit once matched to bank statement | Yes |
| `tax_jurisdictions` | Version, Lookup-Lock | Tenant Members | Admin | None | No |
| `tax_rules` | Version, Lookup-Lock | Tenant Members | Admin | Block delete if applied to tax lines | No |
| `tax_rates` | Version, Row-Lock | Tenant Members | Admin | Block edit if overlap with other rates | No |
| `tax_exemptions` | Version, Row-Lock | Tenant Members | Compliance Officer | None | No |
| `currencies` | Lookup-Lock (System Schema) | All users | System Super Admin | Complete write block for tenants | No |
| `exchange_rates` | Version, Row-Lock | Tenant Members | System Integration | Updated via webhook triggers | No |
| `exchange_rate_history`| Immutable (Insert-Only) | Tenant Members | System Integration | Write blocked | No |
| `budgets` | Version, Optimistic | Tenant Members | Finance Manager | Block edits if status is Archived | No |
| `budget_lines` | Cascaded from Budgets | Tenant Members | Finance Manager | Block edits if Budget is Approved | No |
| `trial_balances` | Materialized snapshot rows | Tenant Members | Accounting Engine | Read-Only rows | Yes |
| `financial_snapshots` | Materialized jsonb | Tenant Members | Controller, CFO | Read-Only rows | Yes |
| `financial_adjustments`| Immutable (Audit block) | Tenant Members | Certified Auditor | Authorized auditor signature required | Yes |
| `reconciliation_runs` | Version, Row-Lock | Tenant Members | Controller | Locked once reconciliation closes | Yes |

---

## SECTION 13: MASTER LEDGER POSTING RULES (LEDGER POSTING MATRIX)

To ensure double-entry alignment, every operational event in the system is mapped to a strict debit-and-credit posting matrix. Each event must correspond to a balanced journal transaction:

```
[Operational Event] ---> Triggers General Ledger Hook ---> Creates balanced journal_entry ---> Adds debit and credit ledger_entries
```

### 13.1 General Ledger Posting Matrix

| Transaction Event | Debit Account (Category) | Credit Account (Category) | Purpose / Description |
| :--- | :--- | :--- | :--- |
| **Invoice Sent** | Accounts Receivable (Asset) | Revenue (Revenue) | Recognizes customer billing liability. |
| **Invoice Sent (Tax Lines)** | Accounts Receivable (Asset) | Tax Liability (Liability) | Records sales tax collected on behalf of tax authority. |
| **Cash Inbound Received** | Cash / Bank Account (Asset) | Accounts Receivable (Asset) | Offsets receivable on invoice receipt. |
| **Vendor Bill Received** | Expense / COGS (Expense) | Accounts Payable (Liability) | Records corporate debt to supplier. |
| **Vendor Bill Tax Credit**| Input Tax Credit (Asset) | Accounts Payable (Liability) | Captures deductible tax offsets. |
| **Vendor Bill Settled** | Accounts Payable (Liability) | Cash / Bank Account (Asset) | Offsets liability on invoice settlement. |
| **Credit Note Issued** | Sales Returns / Adjustments (Revenue) | Accounts Receivable (Asset) | Reduces outstanding receivable. |
| **Debit Note Received** | Accounts Payable (Liability) | Purchase Returns / Offsets (Expense) | Reduces organization payable liability. |
| **Deferred Revenue Posted** | Accounts Receivable (Asset) | Deferred Revenue (Liability) | Captures pre-paid long-term customer billing. |
| **Revenue Recognized** | Deferred Revenue (Liability) | Subscription Revenue (Revenue) | Periodic recognition of deferred income. |
| **Accrued Expense Accrual**| Expense Account (Expense) | Accrued Liabilities (Liability) | Month-end matching principle booking. |
| **Accrued Expense Reversal**| Accrued Liabilities (Liability)| Expense Account (Expense) | Reverses accrual in opening of new period. |
| **Depreciation Booking** | Depreciation Expense (Expense) | Accumulated Depreciation (Asset Contra)| Records periodic asset depreciation. |
| **Write-Off Processing** | Bad Debt Expense (Expense) | Accounts Receivable (Asset) | Writes off uncollectible customer balance. |
| **Intercompany Transfer** | Intercompany Clearing (Asset) | Cash / Bank Account (Asset) | Transfers liquidity to affiliate division. |

---

## SECTION 14: PLATFORM INTERACTION & EVENT BUS INTEGRATION

The Finance domain operates in a loosely coupled, event-driven architecture to coordinate ledger updates, CRM billing, project timesheet billing, and real-time alerts.

### 14.1 Outbox Pattern Integration
All finance mutations write events to the audit outbox (`audit.outbound_events`) as part of the database transaction. A background scheduler consumes these messages asynchronously, dispatching them to external systems (such as Slack, email servers, or stripe webhooks).

### 14.2 Produced Events Matrix
The finance module is responsible for publishing the following canonical business events:

| Event Name | Source Table | Trigger Condition | Payload Details |
| :--- | :--- | :--- | :--- |
| `invoice.created` | `public.invoices` | Invoice transitions to Draft state | `invoice_id, client_account_id, currency, total_amount` |
| `invoice.sent` | `public.invoices` | Invoice status marked Sent | `invoice_id, invoice_number, total_amount, due_date` |
| `invoice.overdue` | `public.invoices` | Nightly check finds overdue invoice | `invoice_id, client_account_id, balance_due, days_overdue` |
| `invoice.cancelled` | `public.invoices` | Invoice marked Cancelled | `invoice_id, cancel_reason, reversal_je_id` |
| `journal.posted` | `public.journal_entries` | Status changes to Posted | `journal_entry_id, entry_number, posting_date, total_val` |
| `payment.allocated` | `public.payment_allocations` | Payment application completed | `allocation_id, payment_ref, invoice_id, allocated_amount` |
| `credit_note.created` | `public.credit_notes` | Credit note approved and issued | `credit_note_id, credit_note_number, amount, invoice_id` |
| `period.closed` | `public.accounting_periods` | Period status marked Closed | `period_id, period_name, closed_by_user_id` |

### 14.3 Consumed Events Matrix
The finance module subscribes to external domain events to initiate actions:

| Event Name | Subscribing Module | Action Triggered | Payload Expected |
| :--- | :--- | :--- | :--- |
| `payment.completed` | Payments Allocator | Creates `payment_allocations` row | `payment_ref, amount, currency, invoice_id` |
| `proposal.accepted` | Billing Engine | Provisions draft Invoice based on CRM terms | `proposal_id, organization_id, line_items_json` |
| `subscription.renewed`| Billing Engine | Generates monthly recurring Invoice | `subscription_id, organization_id, total_due, currency`|
| `organization.created`| COA Provisioner | Seeds GAAP/IFRS template chart of accounts | `organization_id, country_code, default_currency` |

---

## SECTION 15: HIGH-SCALE DATABASE PARTITIONING & ARCHIVAL

High-transaction tables (millions of journal lines, hundreds of thousands of ledger entries monthly) must utilize native database partitioning to prevent indexing decay.

### 15.1 Range Partitioning Scheme
*   **Target Tables**: `public.ledger_entries`, `public.exchange_rate_history`, `public.invoice_history`.
*   **Key**: Partitioned by RANGE on `created_at` (TIMESTAMPTZ) in monthly increments.
*   **Partition Naming**: `{table_name}_y{year}m{month}` (e.g. `ledger_entries_y2026m01`).

```
[ledger_entries] (Parent Table)
    |---> [ledger_entries_y2026m01] (Range: 2026-01-01 to 2026-01-31)
    |---> [ledger_entries_y2026m02] (Range: 2026-02-01 to 2026-02-28)
    |---> [ledger_entries_y2026m03] (Range: 2026-03-01 to 2026-03-31)
```

### 15.2 Archival Strategy
*   **Hot Storage**: Contains partitions for the current and prior fiscal years (24 months of data).
*   **Warm Storage (Cold Partitioning)**: Partitions older than 2 years are detached from the main logical tables and moved to low-cost read-only Google Cloud SQL replicas, or compressed via pg_dump into Parquet files in cold cloud storage.
*   **Legal Hold Retention**: Financial documents cannot be physically purged. Archives must remain accessible for 7-10 years based on country-specific GAAP/IFRS rules.

---

## SECTION 16: GDPR, PII COMPLIANCE & SOC2 TRACEABILITY

Accounting software must balance legal financial record-keeping constraints with GDPR privacy rights.

### 16.1 Right to be Forgotten vs. Financial Record retention
*   **Conflict**: GDPR allows users to request profile deletion, but tax regulations mandate keeping transaction/invoice logs.
*   **Physical Reconciliation Strategy**:
    *   **Invoices, ledger records, and billing lines**: Must remain fully intact to preserve financial balance sheets.
    *   **PII Fields**: Personal identifier fields (such as client address, phone numbers, contact names) in `public.vendor_contacts` are cryptographically scrubbed or replaced with `"GDPR Obliterated Customer"` placeholders, while preserving transaction amounts and UUID keys.

### 16.2 SOC2 Compliance Audit Traceability
1.  **Immutable System Logs**: Modifications to security configurations, tax rate overrides, or closed-period reopenings emit mandatory logs to `audit.security_events`.
2.  **No Direct Manual DB Edits**: RLS blocks direct manual queries on transactional tables by administrators unless a signed change request ticket identifier is provided via session variables.
3.  **Audit Trail Fields**: `created_by` and `updated_by` are always linked to actual verified corporate staff user records to ensure perfect accountability.

---

## SECTION 17: PERFORMANCE ESTIMATES & QUERY OPTIMIZATIONS

To guarantee snappy UI rendering on high-volume tenant dashboards:

### 17.1 Materialized Views for Financial Statements
Calculating Profit & Loss or Balance Sheets in real-time by summing millions of `ledger_entries` lines causes heavy CPU and IO bottlenecks. Instead, JUANET relies on **Materialized Views**:
*   **`mv_monthly_trial_balances`**: Pre-aggregates debits/credits per account per month. Refreshed daily or on-demand when a period closes.
*   **`mv_organization_revenue_snapshots`**: Pre-aggregates monthly gross revenue and receivable balances per tenant.

### 17.2 Covering Index Index-Only Scans
*   To speed up invoice pipeline dashboards, the index `invoices_timeline_idx` covers all necessary return fields.
*   To compile general ledger balances, `ledger_entries_reporting_idx` allows the engine to complete balance inquiries exclusively inside index memory, completely bypassing raw page lookups.
