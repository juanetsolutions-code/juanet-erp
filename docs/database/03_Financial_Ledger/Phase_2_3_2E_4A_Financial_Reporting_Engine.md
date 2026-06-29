# JUANET ERP Financial Reporting Engine Specification
## Phase 2.3.2E.4A — Financial Reporting Engine Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Financial Statements, Embedded Analytics, KPI Engine, and Reporting Cache Core  

---

## SECTION 1: REPORTING PHILOSOPHY & PRINCIPLES

In a high-integrity, multi-tenant enterprise ERP, the Financial Reporting Engine must adhere to rigorous architectural boundaries. Financial reports and analytics represent the primary mechanism for stakeholders to assess compliance, profitability, and liquidity. Consequently, the generation, storage, and auditing of reporting data are governed by five absolute principles:

```
                  [REPORTING DATA SEPARATION LAYERS]

 ┌───────────────────────────┐         ┌───────────────────────────┐
 │   Posted Ledger Entries   │ ──────► │   Reporting Snapshots &   │
 │   (Immutable GL Source)   │         │    Materialized Caches    │
 └───────────────────────────┘         └─────────────┬─────────────┘
                                                     │
                                                     ▼
 ┌───────────────────────────┐         ┌───────────────────────────┐
 │   Operational Databases   │ ◄───────│  Interactive BI & Reports │
 │   (CRM, AP, Billing Logs) │ (Drill) │  (PDF, CSV, Dashboards)   │
 └───────────────────────────┘         └───────────────────────────┘
```

1.  **General Ledger is the Absolute Source of Truth**: Every financial report, trial balance, and KPI calculation must derive strictly from posted journal and ledger entries (`public.journal_entries`, `public.ledger_entries`). Raw operational data (such as pending invoices, unapproved payment drafts, or cart totals) is excluded from statutory reports.
2.  **Strict Read-Only Non-Interference**: Generating financial reports is a strictly read-only operation. The Reporting Engine is physically separated from transaction entry pipelines. Under no circumstances can report execution insert, update, or delete records in operational transaction tables.
3.  **Reproducibility of Historical Reporting**: A reporting run executed today for a prior period (e.g., Q1 2025) must yield the exact same figures, balances, and allocations as a report run executed on the day that period closed. This requires using historic exchange rates, frozen ledger states, and immutable accounting period logs.
4.  **Decoupled Analytical Engine**: Executing complex multi-dimensional queries (e.g., historical year-over-year balance trends or cross-departmental cost allocations) directly on transactional tables is strictly prohibited. Heavy analytical operations utilize pre-aggregated summary tables, materialized views, or snapshot caches to protect transaction throughput.
5.  **Multi-Tenant and Departmental Boundary Isolation**: Data security is embedded in the reporting engine. Multi-tenant RLS guarantees that tenant organizations cannot access other tenants' snapshot data. Furthermore, row- and field-level permissions restrict users to authorized cost centers, departments, or project scopes within their own organization.

---

## SECTION 2: CORE REPORTING DATABASE OBJECTS

The physical database schema below models the storage layer for reporting definitions, sections, calculation snapshots, dashboard widgets, and execution logs.

### 2.1 Table Name: `public.financial_reports`
Stores top-level statutory and managerial financial report definitions.

*   **Purpose**: Stores top-level statutory and managerial financial report configurations.
*   **Ownership**: Financial Controller / CFO.
*   **Lifecycle**: Active -> Deprecated.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `report_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Descriptive name of report. |
| `report_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'trial_balance'`, `'balance_sheet'`, `'profit_and_loss'`, `'cash_flow'`, `'custom'` | Category classification. |
| `is_statutory` | `boolean` | NO | `true` | None | - | Public | Valid boolean | Distinguishes tax/legal filings.|
| `base_currency` | `varchar(3)` | NO | `'USD'` | Check Constraint | - | Public | ISO Code | Currency of compiled outputs. |
| `version` | `integer` | NO | `1` | None | - | Public | `>= 1` | Optimistic locking field. |

---

### 2.2 Table Name: `public.financial_report_templates`
Pre-configured structural layouts representing standard reporting outlines (e.g., US GAAP Balance Sheet, IFRS Profit & Loss).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `template_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Outline template label. |
| `standard` | `varchar(30)` | NO | `'US_GAAP'` | Check Constraint | - | Public | `'US_GAAP'`, `'IFRS'`, `'LOCAL'` | Accounting regulatory standard.|
| `structure_definition`|`jsonb` | NO | `'{}'` | None | - | Public | Valid JSON | Structural layout hierarchy. |

---

### 2.3 Table Name: `public.financial_report_sections`
Individual hierarchical section nodes within a report template (e.g., "Current Assets", "Operating Expenses").

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `report_id` | `uuid` | NO | None | FK -> `financial_reports(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent report container. |
| `parent_section_id`|`uuid` | YES | `NULL` | FK -> `financial_report_sections(id)` | - | Public | UUIDv4 | Parent section for trees. |
| `section_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Category display header. |
| `display_order` | `integer` | NO | `0` | None | - | Public | `>= 0` | Determines UI rendering order. |

---

### 2.4 Table Name: `public.financial_report_lines`
Individual rows representing specific data points or account groupings on a report.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `section_id` | `uuid` | NO | None | FK -> `financial_report_sections(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent display section. |
| `line_code` | `varchar(50)` | NO | None | Unique per report | - | Public | Alphanumeric | Variable reference code (e.g., 'L100'). |
| `line_label` | `varchar(150)`| NO | None | None | - | Public | Standard string | Display text on statement. |
| `line_type` | `varchar(30)` | NO | `'account'` | Check Constraint | - | Public | `'account'`, `'formula'`, `'text'` | Row source type. |
| `account_filter` | `jsonb` | YES | `'{}'` | None | - | Public | Valid JSON | Mapping rules (e.g., `[1000..1099]`). |

---

### 2.5 Table Name: `public.financial_report_formulas`
Calculated operations linked to formula-type report lines (e.g., calculating Gross Profit as `L100 - L200`).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `line_id` | `uuid` | NO | None | FK -> `financial_report_lines(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Target report line. |
| `formula_expression`|`varchar(255)`| NO | None | None | - | Public | Validated string | Expression (e.g., `SUM(L101:L105)`). |
| `parameters` | `jsonb` | NO | `'[]'` | None | - | Public | Valid JSON | Variable parameters list. |

---

### 2.6 Table Name: `public.financial_report_runs`
An execution record logging specific report queries submitted to the background processing queue.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `report_id` | `uuid` | NO | None | FK -> `financial_reports(id)` | - | Public | UUIDv4 | Definition reference. |
| `parameters` | `jsonb` | NO | `'{}'` | None | - | Public | Valid JSON | Execution filters (Period, Entity). |
| `run_status` | `varchar(30)` | NO | `'requested'`| Check Constraint | - | Public | `'requested'`, `'queued'`, `'running'`, `'completed'`, `'failed'` | Async execution state. |
| `executed_by_user_id`|`uuid`| NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Initiating user profile ID. |
| `started_at` | `timestamp with time zone`| YES | `NULL` | None | - | Public | Valid timestamp | Start of generation run. |
| `completed_at` | `timestamp with time zone`| YES | `NULL` | None | - | Public | Valid timestamp | Generation run complete. |

---

### 2.7 Table Name: `public.financial_report_snapshots`
The serialized, frozen output data generated by a report execution run.

*   **Purpose**: Caches frozen report output tables for rapid retrieval and audit archiving.
*   **Ownership**: Internal Auditing.
*   **Lifecycle**: Created once and permanently locked (immutable).
*   **Retention**: Permanent (7+ years).
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `report_run_id` | `uuid` | NO | None | FK -> `financial_report_runs(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Source execution run. |
| `period_id` | `uuid` | NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target financial period. |
| `snapshot_data` | `jsonb` | NO | None | None | - | Financial | Valid JSON | Serialized reporting table. |
| `created_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Timestamp of creation. |
| `signature_hash` | `varchar(64)` | NO | None | None | - | Public | SHA-256 Hash | Integrity verification. |

---

### 2.8 Table Name: `public.financial_report_exports`
Logs downloads and exports generated from report runs.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `snapshot_id` | `uuid` | NO | None | FK -> `financial_report_snapshots(id)`| - | Public | UUIDv4 | Source snapshot data. |
| `export_format` | `varchar(10)` | NO | None | Check Constraint | - | Public | `'PDF'`, `'EXCEL'`, `'CSV'`, `'JSON'`, `'XML'` | Export format type. |
| `file_checksum` | `varchar(64)` | NO | None | None | - | Public | SHA-256 Hash | Verify export file integrity. |
| `downloaded_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | User who downloaded the file. |
| `downloaded_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Timestamp of download. |

---

### 2.9 Table Name: `public.financial_kpis`
Configures calculated metrics used to monitor operational and financial performance.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `kpi_name` | `varchar(100)`| NO | None | None | - | Public | Standard string | Metric identification name. |
| `category` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'liquidity'`, `'profitability'`, `'efficiency'`, `'growth'` | KPI classification. |
| `formula_expression`|`varchar(255)`| NO | None | None | - | Public | Standard formula | KPI calculation formula. |
| `alert_threshold_red`|`numeric(18,4)`|YES | `NULL` | None | - | Public | Standard numeric | Lower warning threshold. |
| `alert_threshold_yellow`|`numeric(18,4)`|YES| `NULL` | None | - | Public | Standard numeric | Upper warning threshold. |

---

### 2.10 Table Name: `public.financial_kpi_results`
Stores the results of calculated KPIs over defined periods.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `kpi_id` | `uuid` | NO | None | FK -> `financial_kpis(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent KPI configuration. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `calculated_value`|`numeric(18,4)`| NO | None | None | - | Financial | Standard numeric | Calculated KPI value. |
| `calculated_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Timestamp of calculation. |

---

### 2.11 Table Name: `public.financial_dashboard_widgets`
Visual card components displayed on the user dashboard.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `widget_title` | `varchar(100)`| NO | None | None | - | Public | Standard string | Widget display title. |
| `widget_type` | `varchar(30)` | NO | None | Check Constraint | - | Public | `'bar_chart'`, `'line_chart'`, `'kpi_metric'`, `'table'` | Render target display layout.|
| `data_source` | `varchar(100)`| NO | None | None | - | Public | Standard source | Query target indicator. |
| `configuration` | `jsonb` | NO | `'{}'` | None | - | Public | Valid JSON | UI configurations. |

---

### 2.12 Table Name: `public.financial_dashboard_layouts`
Stores the layout configurations of financial dashboards for users.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Target user profile owner. |
| `layout_definition`|`jsonb` | NO | `'{}'` | None | - | Public | Valid JSON | Grids coordinates configuration. |

---

### 2.13 Table Name: `public.trial_balance_snapshots`
Immutable historical trial balance records.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Fiscal period target. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Ledger account referenced. |
| `debits_total` | `numeric(18,2)`| NO | None | None | - | Financial | `>= 0.00` | Period debit total. |
| `credits_total` | `numeric(18,2)`| NO | None | None | - | Financial | `>= 0.00` | Period credit total. |
| `closing_balance` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Final frozen value. |

---

### 2.14 Table Name: `public.balance_sheet_snapshots`
Frozen balance sheet classifications for audit records.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `assets_total` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Sum of asset accounts. |
| `liabilities_total`|`numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Sum of liability accounts. |
| `equity_total` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Sum of equity accounts. |

---

### 2.15 Table Name: `public.income_statement_snapshots`
Frozen profit and loss reporting values for audit records.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `revenue_total` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Total earned revenue. |
| `cogs_total` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Cost of Goods Sold. |
| `expenses_total` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Operating expenses. |
| `net_income` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Net margin profit value. |

---

### 2.16 Table Name: `public.cash_flow_snapshots`
Frozen cash flow statement allocations.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `operating_activities`|`numeric(18,2)`|NO | None | None | - | Financial | Standard numeric | Operating cash movements. |
| `investing_activities`|`numeric(18,2)`|NO | None | None | - | Financial | Standard numeric | Investing cash movements. |
| `financing_activities`|`numeric(18,2)`|NO | None | None | - | Financial | Standard numeric | Financing cash movements. |
| `net_cash_change` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Net change in cash. |

---

### 2.17 Table Name: `public.retained_earnings_snapshots`
Tracks accumulated equity and distributions across reporting periods.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `opening_balance` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Starting earnings balance. |
| `net_income_allocated`|`numeric(18,2)`|NO| None | None | - | Financial | Standard numeric | Income added to earnings. |
| `dividends_declared`|`numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Dividend payouts distributed. |
| `closing_balance` | `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Ending earnings balance. |

---

### 2.18 Table Name: `public.report_execution_logs`
Detailed performance and traceability logs for report execution runs.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `run_id` | `uuid` | NO | None | FK -> `financial_report_runs(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Associated execution run. |
| `log_level` | `varchar(10)` | NO | `'INFO'` | Check Constraint | - | Public | `'INFO'`, `'WARN'`, `'ERROR'`| Diagnostic category. |
| `message` | `text` | NO | None | None | - | Public | Standard string | Diagnostic log text. |
| `recorded_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Log entry timestamp. |

---

## SECTION 3: SUPPORTED FINANCIAL STATEMENTS

The Reporting Engine generates standard statutory financial statements, managerial analysis reports, and multi-dimensional performance views.

```
                         [STATUTORY BALANCING SEQUENCE]
                         
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 1. Trial Balance Run                                                   │
   │    - Verifies double-entry: Total Debits == Total Credits              │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 2. Profit & Loss Run                                                   │
   │    - Summarizes: Revenues - COGS - Expenses == Net Income              │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 3. Retained Earnings Rollforward                                       │
   │    - Opening Retained Earnings + Net Income - Dividends == Closing RE  │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 4. Balance Sheet Run                                                   │
   │    - Verifies accounting equation: Assets == Liabilities + Equity      │
   └────────────────────────────────────────────────────────────────────────┘
```

### 3.1 Report Generation Rule Matrix

| Report Name | Source Tables | Standard Filters | Aggregation Rules | Real-Time Balancing Checks |
| :--- | :--- | :--- | :--- | :--- |
| **Trial Balance** | `ledger_balances`, `ledger_entries` | Period, Org | Group by Account Code, aggregate balances. | Sum of debits must equal sum of credits. |
| **Balance Sheet** | `ledger_balances`, `ledger_entries` | Period, Org, Segment | Group by Account Class (Asset, Liability, Equity). | Assets = Liabilities + Equity (including Net Income). |
| **Profit & Loss** | `ledger_balances`, `ledger_entries` | Period, Org, Dept | Group by Revenue and Expense types. | Net Income = Revenue - Expenses. |
| **Cash Flow (Indirect)**| `ledger_balances`, `bank_transactions`| Period, Org | Adjust Net Income for non-cash transactions and working capital changes. | Ending cash balance must equal bank account balances. |
| **Aged Receivables** | `invoices`, `payment_allocations` | Org, Date | Buckets: 0-30, 31-60, 61-90, 91+ days past due. | Total aged outstanding must equal AR GL control balance. |
| **Aged Payables** | `bills`, `payment_allocations` | Org, Date | Buckets: 0-30, 31-60, 61-90, 91+ days past due. | Total aged outstanding must equal AP GL control balance. |
| **Budget vs Actual** | `ledger_balances`, `budget_lines` | Period, Org, Cost Center | Aggregate ledger balances and compare against budget configurations. | Variance = Budget - Actual. |
| **Tax Summary** | `tax_ledgers`, `ledger_entries` | Period, Org, Region | Group transactions by tax rate and location code. | Tax due must equal Tax Liability GL control balance. |

---

### 3.2 Dynamic Drill-Down Framework

Every aggregated cell on financial statements generated in the UI includes metadata mapping it to its source transactions. Clicking an aggregated balance (e.g., $14,000 in Marketing Expenses) triggers a drill-down pipeline:
1.  **Read Aggregation Metadata**: Retrieves the source account IDs and dimensions (e.g., Account `6200`, Cost Center `MKT-01`).
2.  **Execute Sub-ledger Queries**: Queries `public.ledger_entries` to retrieve the underlying transaction lines that make up the balance.
3.  **Cross-reference Source Events**: Resolves the parent journal entry and links back to the originating operational event (e.g., vendor bill, credit card receipt), providing full audit traceability.

---

## SECTION 4: CONFIGURABLE REPORTING TREES & HIERARCHIES

To support complex multi-entity structures and matrix organizations, the Reporting Engine defines hierarchical organizational trees using recursive relationships.

```
                          [REPORTING TREE STRUCTURE]
                          
                          [ Global Enterprise (Level 0) ]
                                         │
                    ┌────────────────────┴────────────────────┐
                    ▼                                         ▼
         [ Europe Region (Level 1) ]             [ Americas Region (Level 1) ]
                    │                                         │
           ┌────────┴────────┐                       ┌────────┴────────┐
           ▼                 ▼                       ▼                 ▼
     [ Germany Org ]   [ UK Subsidiary ]       [ US Corporation ]  [ Canada Org ]
```

### 4.1 Recursive Parent-Child PostgreSQL Query Execution

The database maps reporting trees using parent-child reference keys. The query below recursively walks organizational hierarchies to consolidate financial balances up to a target parent node:

```sql
WITH RECURSIVE org_hierarchy AS (
    -- Anchor member: Select target parent entity
    SELECT id, parent_organization_id, organization_name, 1 AS hierarchy_level
    FROM public.organizations
    WHERE id = '99182a20-8192-4911-b118-0416001865af'::uuid
    
    UNION ALL
    
    -- Recursive member: Select child entities
    SELECT o.id, o.parent_organization_id, o.organization_name, h.hierarchy_level + 1
    FROM public.organizations o
    JOIN org_hierarchy h ON o.parent_organization_id = h.id
)
SELECT 
    h.hierarchy_level,
    h.organization_name,
    b.account_id,
    COALESCE(SUM(b.closing_balance), 0.00) AS consolidated_balance
FROM org_hierarchy h
JOIN public.ledger_balances b ON b.organization_id = h.id
WHERE b.accounting_period_id = '11029e20-8192-4911-b118-0416001865ff'::uuid
GROUP BY h.hierarchy_level, h.organization_name, b.account_id
ORDER BY h.hierarchy_level ASC;
```

---

## SECTION 5: FINANCIAL FORMULA CALCULATOR ENGINE

Rather than hardcoding calculation logic within application code, the Reporting Engine utilizes a deterministic formula engine to evaluate calculated rows. Formulas reference unique report lines (`line_code`) instead of hardcoded account numbers, making layouts highly configurable.

### 5.1 Supported Mathematical Expressions

*   `SUM(x:y)`: Aggregates balances from lines `x` through `y` inclusively.
*   `SUBTRACT(x, y)`: Computes the difference between line `x` and line `y`.
*   `PERCENTAGE(x, y)`: Calculates the ratio of line `x` relative to line `y`, multiplied by 100.
*   `RATIO(x, y)`: Computes the proportion of line `x` relative to line `y`.
*   `RUNNING_TOTAL(x)`: Computes a cumulative running total of line `x` across consecutive reporting periods.
*   `MONTH_OVER_MONTH(x)`: Computes the percentage change in line `x` compared to the preceding month.
*   `YEAR_OVER_YEAR(x)`: Computes the percentage change in line `x` compared to the same period in the preceding fiscal year.

---

### 5.2 Circular Reference and Validation Logic

Before executing a formula layout, the engine constructs a Directed Acyclic Graph (DAG) of line dependencies to validate the layout structure:

```
    L100 (Revenues) ◄────┐
                         ├───── L300 (Gross Profit: L100 - L200)
    L200 (COGS)     ◄────┘
                         │
                         ├───── L500 (Net Income: L300 - L400)
    L400 (Expenses) ◄────┘
```

*   **Circular Reference Detection**: The engine analyzes the DAG for dependency loops (e.g., Line A depends on Line B, which depends on Line A). If a loop is detected, the engine blocks report execution and logs a validation error.
*   **Formula Dependency Sorting**: The engine applies topological sorting to the DAG, determining the exact sequence in which to evaluate lines to ensure all dependencies are computed before their parent rows.

---

## SECTION 6: STANDARDIZED FINANCIAL KPI SPECIFICATIONS

To monitor business performance, the system calculates and stores key performance indicators (KPIs) across four core financial categories.

### 6.1 KPI Formula Catalog

#### 6.1.1 Liquidity Metrics
*   **Current Ratio**: Measures the company's ability to cover short-term liabilities with short-term assets.
    $$\text{Current Ratio} = \frac{\text{Total Current Assets (GL: 1100..1299)}}{\text{Total Current Liabilities (GL: 2100..2299)}}$$
*   **Quick Ratio**: Measures short-term liquidity using highly liquid assets (excluding inventory).
    $$\text{Quick Ratio} = \frac{\text{Cash \& Cash Equivalents} + \text{Accounts Receivable}}{\text{Total Current Liabilities}}$$

#### 6.1.2 Profitability Metrics
*   **Gross Margin %**: Measures profitability after accounting for direct production costs.
    $$\text{Gross Margin \%} = \frac{\text{Sales Revenue} - \text{Cost of Goods Sold}}{\text{Sales Revenue}} \times 100$$
*   **Net Profit Margin**: Measures bottom-line profitability relative to total sales.
    $$\text{Net Profit Margin} = \frac{\text{Net Income}}{\text{Total Sales Revenue}} \times 100$$

#### 6.1.3 Efficiency Metrics
*   **Receivable Days (DSO)**: Measures the average number of days it takes to collect payments from customers.
    $$\text{Receivable Days} = \frac{\text{Average Accounts Receivable}}{\text{Total Credit Sales}} \times 365$$
*   **Payable Days (DPO)**: Measures the average number of days it takes to pay vendors.
    $$\text{Payable Days} = \frac{\text{Average Accounts Payable}}{\text{Total Vendor Purchases}} \times 365$$

#### 6.1.4 SaaS Growth Metrics
*   **Monthly Recurring Revenue (MRR)**: The total predictable monthly subscription revenue.
    $$\text{MRR} = \sum(\text{Active Monthly Subscription Value})$$
*   **Annual Recurring Revenue (ARR)**: The annualized value of recurring subscription contracts.
    $$\text{ARR} = \text{MRR} \times 12$$
*   **Churn Rate %**: The percentage of subscription value lost over a given period.
    $$\text{Churn Rate \%} = \frac{\text{MRR Lost in Period}}{\text{MRR at Start of Period}} \times 100$$

---

## SECTION 7: MATERIALIZED VIEW & QUERY ACCELERATION STRATEGY

To maintain high query performance under heavy transactional loads, the Reporting Engine utilizes targeted database structures, materialized views, and pre-computed summaries.

```
 [ ledger_entries ] ──► [ nightly materialized run ] ──► [ mv_trial_balance_summary ] ──► [ High-speed UI Reports ]
```

### 7.1 Materialized Views Schema Configurations

```sql
-- 1. Materialized view summarizing account balances by period and dimension
CREATE MATERIALIZED VIEW public.mv_trial_balance_summary AS
SELECT 
    organization_id,
    accounting_period_id,
    account_id,
    cost_center_id,
    department_id,
    COALESCE(SUM(debit_amount), 0.00) AS total_debits,
    COALESCE(SUM(credit_amount), 0.00) AS total_credits,
    COALESCE(SUM(debit_amount - credit_amount), 0.00) AS net_period_change
FROM public.ledger_entries
GROUP BY organization_id, accounting_period_id, account_id, cost_center_id, department_id;

-- Create unique index to support concurrent refreshes
CREATE UNIQUE INDEX mv_trial_balance_summary_uid 
  ON public.mv_trial_balance_summary(organization_id, accounting_period_id, account_id, cost_center_id, department_id);
```

---

### 7.2 Incremental Refresh and Synchronization Policies

*   **Refresh Frequency**: The system triggers incremental materialized view refreshes nightly during low-traffic periods.
*   **On-Demand Invalidation**: Critical accounting actions (such as closing a period or posting manual adjustments) flag the corresponding period's cache as dirty, triggering an on-demand background refresh.
*   **Locking and Non-Blocking Refreshes**: Refreshes utilize the `CONCURRENTLY` keyword:
    ```sql
    REFRESH MATERIALIZED VIEW CONCURRENTLY public.mv_trial_balance_summary;
    ```
    This allows users to continue querying old reporting data while the refresh completes, preventing system lockups.

---

## SECTION 8: ASYNCHRONOUS REPORT EXECUTION PIPELINE

Generating large reports over extensive timelines or complex hierarchies is executed asynchronously to prevent request timeouts and maintain system responsiveness.

```
                          [ASYNC EXECUTION PIPELINE]
                          
                          [ Report Run Requested ]
                                     │
                                     ▼
                          Validate Run Parameters:
                          Verify period and entity status.
                                     │
                                     ▼
                            Queue Reporting Job
                        (Pushes to background worker queue)
                                     │
                                     ▼
                             Background Worker:
                             - Pulls job from queue
                             - Compiles balances
                             - Evaluates formulas
                                     │
                    ┌────────────────┴────────────────┐
                    ▼                                 ▼
           [ Run Completed ]                    [ Run Failed ]
            Write snapshot data                  Log error details
            and update job status                and alert admins
```

### 8.1 Error Recovery and Retry Policies
*   **Timeouts and Boundaries**: Report execution runs are limited to a maximum execution window (default: 180 seconds). Jobs exceeding this limit are terminated, flagged as `failed`, and log diagnostic details.
*   **Transient Error Retries**: If a job fails due to transient database issues (e.g., serialization failures or deadlock rollbacks), the engine automatically retries execution up to three times with exponential backoff before marking the run as failed.

---

## SECTION 9: REPORT EXPORT & DISTRIBUTION ENGINE

The Export Engine converts generated snapshots into multiple download formats, applying security watermarks and integrity verification hashes.

### 9.1 Export Invariants and Distribution Workflows

*   **Static Snapshot Binding**: Exports must bind to a static, verified report snapshot (`public.financial_report_snapshots`). Regenerating data directly during export runs is prohibited to prevent inconsistencies.
*   **Cryptographic Verification**: Generated files include SHA-256 integrity verification hashes stored in the export log, preventing tampering with downloadable documents.
*   **Security Watermarking**: Exported documents (especially PDF and Excel) support security watermarking (e.g., printing "CONFIDENTIAL" or the requesting user's ID as a background layer) to prevent unauthorized leaks.
*   **Download Audit Logs**: Every file download generates an entry in `public.financial_report_exports`, tracking the requesting user, timestamp, file format, and IP address for compliance.

---

## SECTION 10: ROLE-BASED ACCESS CONTROL & SECURITY

To protect sensitive corporate data and satisfy compliance audits, the Reporting Engine enforces rigorous security rules.

### 10.1 Security Roles and Operational Matrix

| Operations Role | View Dashboard | Run Standard Reports | Export Data | Adjust Report Layouts | Edit Formulas | Access Auditing Logs |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Report Viewer** | Yes | No | No | No | No | No |
| **Accountant** | Yes | Yes | Yes | No | No | No |
| **Financial Manager** | Yes | Yes | Yes | Yes | No | Yes |
| **Controller** | Yes | Yes | Yes | Yes | Yes | Yes |
| **CFO / Director** | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 10.2 Row-Level Security (RLS) & Masking
*   **Row-Level Security (RLS)**: Enforced on all reporting and snapshot tables, isolating tenant data using the organization context.
*   **Field-Level Masking**: Masking rules obscure sensitive details (such as specific employee salaries on payroll detail reports or encrypted bank account numbers) for unauthorized roles.
*   **Departmental Filters**: Managers' visibility is restricted to cost centers or department codes within their authorized operational scope.

---

## SECTION 11: PERFORMANCE ENGINEERING STRATEGIES

To scale reporting capabilities across high transaction volumes and complex enterprise models, the engine utilizes targeted performance designs.

### 11.1 Indexing and Query Optimization

```sql
-- 1. Covering index to accelerate period balances lookup
CREATE INDEX ledger_balances_perf_idx 
  ON public.ledger_balances(organization_id, accounting_period_id, account_id)
  INCLUDE (opening_balance, closing_balance);

-- 2. Index to optimize report snapshot lookup
CREATE INDEX report_snapshots_lookup_idx 
  ON public.financial_report_snapshots(organization_id, period_id)
  INCLUDE (created_at, signature_hash);
```

---

### 11.2 Architectural Optimizations
*   **Partition Pruning**: Transaction tables are partitioned by `organization_id`, allowing the query planner to bypass unrelated tenant partitions and accelerate report execution times.
*   **Pre-Aggregated Balance Lookup**: The engine prioritizes querying pre-computed summaries (`public.ledger_balances`) rather than executing resource-intensive aggregation queries over raw transaction rows.
*   **Database Read Replicas**: Heavy analytical and export queries are routed to dedicated read replicas, protecting transaction throughput on the primary write database.

---

## SECTION 12: REAL-TIME REPORTING EVENTS

The Reporting Engine is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 12.1 System Events

#### `financial.report.generated`
Emitted immediately upon successfully compiling a report snapshot.

```json
{
  "event_id": "evt_rep_01A9400201",
  "event_type": "financial.report.generated",
  "organization_id": "org_771829",
  "correlation_id": "corr_run_9918",
  "payload": {
    "report_run_id": "run_449210",
    "report_type": "profit_and_loss",
    "snapshot_id": "snp_229103",
    "period_id": "per_11029",
    "compiled_by_user": "usr_3391",
    "execution_duration_ms": 1240
  },
  "timestamp": "2026-06-28T23:00:00Z"
}
```

#### `report.exported`
Emitted immediately upon converting and downloading a report file.

```json
{
  "event_id": "evt_rep_01A9400255",
  "event_type": "report.exported",
  "organization_id": "org_771829",
  "correlation_id": "corr_dl_44921",
  "payload": {
    "snapshot_id": "snp_229103",
    "export_format": "PDF",
    "file_checksum": "sha256_ab44ef3381...7c",
    "downloaded_by_user": "usr_3391"
  },
  "timestamp": "2026-06-28T23:05:00Z"
}
```

---

## SECTION 13: PRODUCTION REPORTING VALIDATION CHECKLIST

Before deploying the Financial Reporting Engine to production, verify that the following configurations and controls are in place.

- [ ] **Balancing Equations Confirmed**: Balancing checks are active, blocking the completion of reports where assets do not equal liabilities plus equity.
- [ ] **Audit Trait Traceability Verified**: Transaction links exist across all snapshot rows, enabling deep drill-down validation.
- [ ] **Materialized Refreshes Scheduled**: Nightly incremental refreshes for materialized reporting views are configured and tested.
- [ ] **Formula DAG Validation Active**: Directed Acyclic Graph validation checks are active, blocking layouts containing circular references.
- [ ] **Retry Engine Tested**: Transient database connection failure retry rules are verified.
- [ ] **WORM Snapshots Active**: Report snapshots are verified as immutable, blocking subsequent modification or deletion attempts.
- [ ] **Export Verification Active**: Watermarking and integrity hashing rules are active on downloaded documents.
- [ ] **Event Delivery Confirmed**: Real-time event generation and consumption flows are validated.

---
**End of Specification.**
