# JUANET ERP Budgeting, Forecasting & Financial Planning Engine Specification
## Phase 2.3.2E.4B — Budgeting, Forecasting & Financial Planning Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Budgeting, Forecasting, Enterprise Performance Management (EPM), and Scenario Modeling Subsystem  

---

## SECTION 1: PLANNING PHILOSOPHY & PRINCIPLES

In a modern, tier-1 enterprise resource planning (ERP) environment (equivalent to SAP Analytics Cloud Planning, Oracle Enterprise Performance Management Cloud, and Workday Adaptive Planning), the **Planning Engine** governs future-facing corporate intentions. This subsystem is architecturally segregated from transaction-processing engines, operating under five foundational financial principles:

```
    [ Operational Ledger ] ──────────────► [ Actuals Source ]
    (Immutable Posted GL Entries)                 │
                                                  ▼
    [ Planning Engine ] ◄───────────────── [ Drivers & Assumptions ]
    - Coexisting Budget & Forecast Versions       │
    - What-if Scenario Modeling                   │
                                                  ▼
    [ Management Reports ] ◄────────────── [ Variance Engine ]
    (Budget vs. Actual, Forecast vs. Actual, Trends)
```

1.  **Budgets are Plans, Not Ledger Records**: Budgets represent authorized fiscal boundaries and strategic limits. They are operational targets, not transactional ledger records. Under no circumstances do budgets alter or write transactions to general ledger posting tables (`public.ledger_entries`).
2.  **Forecasts are Projections, Not Historical Reality**: Forecasts represent dynamic, rolling calculations of future performance based on operational trends, financial drivers, and macroeconomic assumptions. They are strictly informational and never modify historic operational or accounting balances.
3.  **Actuals Originate Solely from the General Ledger**: All historical actuals utilized in variance reporting, trend analysis, or AI training algorithms must derive exclusively from immutable posted ledger records. Direct ingestion of unposted sub-ledger estimates is strictly prohibited in formal financial reporting.
4.  **Planning Data Versioning and Immutability**: Budgets and forecasts are version-controlled. Once a budget version is formally approved and "Locked" by the Chief Financial Officer (CFO) or Board of Directors, it becomes permanently immutable. Subsequent adjustments, mid-year revisions, or re-allocations are written as audit-logged "Budget Adjustments" or modeled as new "Forecast Versions" without modifying the original approved baseline.
5.  **Multi-Scenario Coexistence**: The Planning Engine supports the concurrent execution of multiple, isolated scenario branches (e.g., "Worst Case", "Expected", "Best Case", "Aggressive Growth"). This isolation guarantees that "what-if" modeling in one workspace does not distort the active budget of another organizational unit.

---

## SECTION 2: CORE PLANNING TABLES

This section defines the physical database schema for the budgeting, forecasting, scenario planning, and executive scorecard modules.

### 2.1 Table Name: `public.budgets`
Top-level container for a fiscal year's corporate budget.

*   **Purpose**: Stores top-level metadata for an overall budgeting cycle (e.g., Fiscal Year 2026 Consolidated Budget).
*   **Ownership**: CFO Office / Corporate Controller.
*   **Lifecycle**: Draft -> Under_Review -> Approved -> Locked -> Archived.
*   **Retention**: Permanent (7+ years).
*   **Optimistic Locking**: Yes, via `version` column.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Descriptive budget name. |
| `fiscal_year` | `integer` | NO | None | None | - | Public | `fiscal_year >= 2000` | Target financial year. |
| `base_currency` | `varchar(3)` | NO | `'USD'` | Check Constraint | - | Public | ISO Code | Currency of consolidated views. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'under_review'`, `'approved'`, `'locked'`, `'archived'` | State-machine flag. |
| `version` | `integer` | NO | `1` | None | - | Public | `>= 1` | Optimistic locking field. |

*   **Indexes**:
    *   `CREATE UNIQUE INDEX budgets_year_tenant_idx ON public.budgets(organization_id, fiscal_year) WHERE status = 'locked';`

---

### 2.2 Table Name: `public.budget_versions`
Branches of a budget representing draft iterations, re-forecasts, or board amendments.

*   **Purpose**: Manages multiple concurrent budget revisions for scenario comparison and auditing.
*   **Ownership**: Finance Department.
*   **Lifecycle**: Draft -> Active -> Superseded.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_id` | `uuid` | NO | None | FK -> `budgets(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent budget container. |
| `version_code` | `varchar(30)` | NO | None | None | - | Public | Unique per budget | Code (e.g., 'V1.0_Base', 'V2.1_Rev'). |
| `is_active` | `boolean` | NO | `false` | None | - | Public | Valid boolean | Denotes current baseline plan. |
| `description` | `text` | YES | `NULL` | None | - | Public | Standard string | Change summary notes. |
| `created_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Creator profile. |
| `version` | `integer` | NO | `1` | None | - | Public | `>= 1` | Optimistic locking field. |

---

### 2.3 Table Name: `public.budget_lines`
Atomic row-level planning targets distributed by ledger accounts and financial dimensions.

*   **Purpose**: Granular budget amounts mapped to specific cost centers, accounts, and periods.
*   **Ownership**: Department Managers / Budget Owners.
*   **Lifecycle**: Active (editable when parent version is 'draft').
*   **Retention**: Permanent.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_version_id`|`uuid` | NO | None | FK -> `budget_versions(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Linked budget revision. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Period target constraint. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Chart of Accounts target. |
| `budget_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `budget_amount >= 0.00` | Target expenditure limit. |
| `cost_center_id` | `uuid` | YES | `NULL` | FK -> `cost_centers(id)` | - | Public | UUIDv4 | Cost center dimension. |
| `department_id` | `uuid` | YES | `NULL` | FK -> `departments(id)` | - | Public | UUIDv4 | Department dimension. |
| `project_id` | `uuid` | YES | `NULL` | FK -> `projects(id)` | - | Public | UUIDv4 | Project dimension. |

*   **Indexes**:
    *   `CREATE INDEX budget_lines_search_idx ON public.budget_lines(organization_id, budget_version_id, account_id) INCLUDE (budget_amount);`

---

### 2.4 Table Name: `public.budget_adjustments`
Formal revision entries recorded against approved budgets to track amendments.

*   **Purpose**: Records approved budget adjustments, preserving the original baseline.
*   **Ownership**: Corporate Controller.
*   **Lifecycle**: Pending -> Approved -> Posted.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_line_id` | `uuid` | NO | None | FK -> `budget_lines(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Target budget line row. |
| `adjustment_amount`|`numeric(18,2)`| NO | None | None | - | Financial | `adjustment_amount <> 0.00`| Positive or negative revision. |
| `reason_code` | `varchar(50)` | NO | None | None | - | Public | Standard string | Justification audit code. |
| `status` | `varchar(30)` | NO | `'pending'` | Check Constraint | - | Public | `'pending'`, `'approved'`, `'rejected'` | State machine. |
| `approved_by` | `uuid` | YES | `NULL` | FK -> `users(id)` | - | Public | UUIDv4 | Authorized CFO user. |

---

### 2.5 Table Name: `public.budget_approvals`
Tracks workflow signatures and review approvals for a budget version.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_version_id`|`uuid` | NO | None | FK -> `budget_versions(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Reference version. |
| `workflow_step` | `varchar(50)` | NO | None | None | - | Public | Standard string | Step (e.g., 'Finance_Review'). |
| `approver_id` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Reviewing user profile ID. |
| `decision` | `varchar(30)` | NO | `'pending'` | Check Constraint | - | Public | `'pending'`, `'approved'`, `'rejected'` | Decision status. |
| `comments` | `text` | YES | `NULL` | None | - | Public | Standard string | Review notes. |

---

### 2.6 Table Name: `public.budget_templates`
Defines standard structural layouts and dimensions for budget entries.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `template_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Display name. |
| `dimensions_config`|`jsonb` | NO | `'{}'` | None | - | Public | Valid JSON | Pre-mapped inputs layout. |

---

### 2.7 Table Name: `public.forecasts`
Top-level metadata container for dynamically updated forecasts.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Descriptive forecast label. |
| `start_period_id` | `uuid` | NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Start period of forecast. |
| `end_period_id` | `uuid` | NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | End period of forecast. |
| `version` | `integer` | NO | `1` | None | - | Public | `>= 1` | Optimistic locking field. |

---

### 2.8 Table Name: `public.forecast_versions`
Historical snapshot drafts of dynamic forecasts.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_id` | `uuid` | NO | None | FK -> `forecasts(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent forecast run. |
| `version_code` | `varchar(30)` | NO | None | None | - | Public | Standard string | Branch label (e.g. 'FC_2026_Q1'). |
| `is_approved` | `boolean` | NO | `false` | None | - | Public | Valid boolean | Status confirmation flag. |

---

### 2.9 Table Name: `public.forecast_lines`
Individual projected values by accounts, dimensions, and accounting periods.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_version_id`|`uuid`| NO | None | FK -> `forecast_versions(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Parent forecast revision. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target fiscal period. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Target account. |
| `projected_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `projected_amount >= 0.00`| Projected balance value. |
| `cost_center_id` | `uuid` | YES | `NULL` | FK -> `cost_centers(id)` | - | Public | UUIDv4 | Target cost center dimension.|

---

### 2.10 Table Name: `public.rolling_forecasts`
Defines active, rolling forecast schedules (e.g., 3 Months Actual + 9 Months Forecast).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_id` | `uuid` | NO | None | FK -> `forecasts(id)` | - | Public | UUIDv4 | Target forecast settings. |
| `actuals_cutoff_date`|`date` | NO | None | None | - | Public | Valid date | Cuts off actuals transition. |
| `rolling_periods_count`|`integer`| NO | `12` | None | - | Public | `>= 1` | Timeline span (e.g., 12 periods).|

---

### 2.11 Table Name: `public.forecast_adjustments`
Stores manual overrides or adjustments applied to forecast lines.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_line_id` | `uuid` | NO | None | FK -> `forecast_lines(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Target forecast line row. |
| `override_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `>= 0.00` | Applied manual value. |
| `justification` | `text` | NO | None | None | - | Public | Standard string | Auditor override explanation. |

---

### 2.12 Table Name: `public.planning_scenarios`
Configures isolated "what-if" models representing strategic alternatives.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `scenario_name` | `varchar(100)`| NO | None | None | - | Public | Standard string | Scenario name (e.g., 'Worst Case'). |
| `is_active` | `boolean` | NO | `true` | None | - | Public | Valid boolean | Status flag. |
| `created_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Record timestamp. |

---

### 2.13 Table Name: `public.scenario_assumptions`
Stores driver rates and assumptions applied within an active scenario model.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `scenario_id` | `uuid` | NO | None | FK -> `planning_scenarios(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Linked what-if scenario. |
| `assumption_key` | `varchar(100)`| NO | None | None | - | Public | Standard key | Key (e.g., 'inflation_rate'). |
| `assumption_value` | `numeric(12,6)`| NO | None | None | - | Public | Standard numeric | Numeric value (e.g., 0.045000). |

---

### 2.14 Table Name: `public.variance_reports`
Header logging variance calculations (Actuals vs. Budgets/Forecasts).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `comparison_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'actual_vs_budget'`, `'actual_vs_forecast'`, `'budget_vs_forecast'` | Analysis classification. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target period analyzed. |
| `calculated_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Generation run timestamp. |

---

### 2.15 Table Name: `public.variance_lines`
Detailed variance computations for individual accounts and cost centers.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `variance_report_id`|`uuid` | NO | None | FK -> `variance_reports(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Parent report container. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Target account. |
| `actual_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | GL actuals value. |
| `planned_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | Target planned value. |
| `variance_absolute`|`numeric(18,2)`| NO | None | None | - | Financial | `actual_amount - planned_amount`| Absolute variance. |
| `variance_percent` | `numeric(8,4)` | YES | `NULL` | None | - | Public | Standard numeric | Percentage variance. |
| `root_cause_class` | `varchar(100)`| YES | `NULL` | None | - | Public | Standard string | Classification (e.g., 'Delay'). |

---

### 2.16 Table Name: `public.financial_targets`
Corporate performance targets set for a planning cycle (e.g., Target Gross Margin of 75%).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `target_name` | `varchar(100)`| NO | None | None | - | Public | Standard string | Target label. |
| `metric_key` | `varchar(50)` | NO | None | None | - | Public | Standard key | Target metric identifier. |
| `target_value` | `numeric(18,4)`| NO | None | None | - | Public | Standard numeric | Target goal. |

---

### 2.17 Table Name: `public.financial_objectives`
Managerial strategic goals linked to target metrics.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `target_id` | `uuid` | NO | None | FK -> `financial_targets(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent target profile. |
| `objective_text` | `text` | NO | None | None | - | Public | Standard string | Qualitative objective text. |
| `due_date` | `date` | NO | None | None | - | Public | Valid date | Due date for completion. |

---

### 2.18 Table Name: `public.executive_scorecards`
Consolidated corporate scoreboard definitions.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `scorecard_name` | `varchar(100)`| NO | None | None | - | Public | Standard string | Dashboard layout title. |
| `owner_role` | `varchar(50)` | NO | `'CFO'` | Check Constraint | - | Public | `'CEO'`, `'CFO'`, `'BOARD'` | Access role filter. |

---

### 2.19 Table Name: `public.executive_scorecard_metrics`
Dynamic widget metrics mapped to scorecards.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `scorecard_id` | `uuid` | NO | None | FK -> `executive_scorecards(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent scorecard container. |
| `metric_name` | `varchar(100)`| NO | None | None | - | Public | Standard string | Metric title (e.g., 'CAC'). |
| `current_value` | `numeric(18,4)`| NO | None | None | - | Public | Standard numeric | Current value. |
| `target_value` | `numeric(18,4)`| NO | None | None | - | Public | Standard numeric | Planned target value. |

---

### 2.20 Table Name: `public.planning_comments`
Discussion threads appended to planning lines or targets to coordinate decisions.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `context_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'budget_line'`, `'forecast_line'`, `'target'` | Identifies target module. |
| `context_id` | `uuid` | NO | None | None | - | Public | UUIDv4 | Context target row ID. |
| `comment_text` | `text` | NO | None | None | - | Public | Standard string | Comment text. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Commenting user. |
| `created_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Record timestamp. |

---

### 2.21 Table Name: `public.planning_attachments`
Logs file attachments uploaded to support budget or forecast assumptions.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `context_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'budget_line'`, `'forecast_line'` | Target container context. |
| `context_id` | `uuid` | NO | None | None | - | Public | UUIDv4 | Target container ID. |
| `file_name` | `varchar(255)`| NO | None | None | - | Public | Standard string | Document label. |
| `storage_path` | `varchar(512)`| NO | None | None | - | Public | Standard path | Cloud storage URL. |
| `uploaded_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Uploading user. |

---

### 2.22 Table Name: `public.planning_snapshots`
The serialized frozen output tables of budget models used for auditing and comparisons.

*   **Purpose**: Caches frozen planning tables for audit comparisons and historical rollbacks.
*   **Ownership**: Internal Auditing.
*   **Lifecycle**: Created once and permanently locked (immutable).
*   **Retention**: Permanent (7+ years).
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `budget_version_id`|`uuid` | YES | `NULL` | FK -> `budget_versions(id)` | - | Public | UUIDv4 | Source budget revision. |
| `forecast_version_id`|`uuid`| YES | `NULL` | FK -> `forecast_versions(id)`| - | Public | UUIDv4 | Source forecast revision. |
| `snapshot_data` | `jsonb` | NO | None | None | - | Financial | Valid JSON | Serialized planning structure.|
| `created_at` | `timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Record timestamp. |
| `signature_hash` | `varchar(64)` | NO | None | None | - | Public | SHA-256 Hash | Integrity verification. |

---

## SECTION 3: CORPORATE BUDGETING MODELS

The Planning Engine supports diverse budgeting models to accommodate standard enterprise planning methodologies:

```
                            [BUDGETING MODELS]
                            
      [ Strategic Models ]            [ Operational Models ]            [ Financial Models ]
      - Zero-Based Budgeting          - CapEx & OpEx Budgets            - Cash Flow Budgeting
      - Driver-Based Planning         - Department & Project Budgets    - Rolling Budgets
```

1.  **Annual / Quarterly / Monthly Budgets**: Sets fiscal boundaries over standard calendar timelines, dividing corporate limits into discrete planning periods.
2.  **Department / Cost Center Budgets**: Distributes target spending limits by divisional ownership, enabling localized budget tracking.
3.  **Project Budgets**: Establishes performance targets and spending boundaries for specific projects, capping total expenditures across the project lifespan.
4.  **Capital Expenditure (CapEx) Budgets**: Controls long-term investments in physical infrastructure, equipment, or assets, requiring separate multi-year amortization schedules.
5.  **Operational Expense (OpEx) Budgets**: Manages day-to-day operational expenses (such as marketing, rent, and utility costs) linked to department codes.
6.  **Revenue Budgets**: Sets sales performance targets by territory, product line, or account manager.
7.  **Cash Budgets**: Projects cash inflows and outflows to monitor operating liquidity and prevent short-term funding gaps.
8.  **Rolling Budgets**: Automatically extends the budgeting timeline forward by a month or quarter as the current period closes, maintaining a continuous 12-month outlook.
9.  **Zero-Based Budgeting (ZBB)**: Requires budget owners to justify every expense from scratch for each budgeting cycle, rather than adjusting the previous year's actuals.
10. **Driver-Based Budgeting**: Automatically calculates budget lines using operational drivers and assumptions (e.g., calculating the Hosting Expense budget as `Expected Users * Hosting Cost per User`).

---

## SECTION 4: ENTERPRISE FORECASTING MODELS

Forecasting models project future performance by combining historical actuals with dynamic operational assumptions.

### 4.1 Dynamic Forecast Classes

*   **Rolling Forecast (e.g., 3+9 or 6+6)**: Automatically merges historical actuals with future forecast lines based on the selected cutoff date:
    $$\text{Period Value} = \begin{cases} 
      \text{GL Ledger Actual Balance} & \text{if Period Date} \le \text{Cutoff Date} \\
      \text{Forecast Line Projection} & \text{if Period Date} > \text{Cutoff Date}
    \end{cases}$$
*   **Revenue Projections**: Projects future billings by analyzing active subscription terms, user seat counts, and anticipated sales pipelines.
*   **Expense Projections**: Projects future spending by analyzing recurring contracts, planned headcount growth, and historical cost trends.
*   **Headcount Projections**: Dynamically calculates salary, benefit, and equipment expenses by linking them to the corporate hiring roadmap.

---

## SECTION 5: WHAT-IF SCENARIO PLANNING & SENSITIVITY ANALYSIS

To support strategic decision-making, the system models the impact of varied economic conditions on financial performance within isolated workspaces.

```
                           [SCENARIO ANALYSIS LAYER]
                           
       [ Source Actuals ] ──► [ Scenario Engine ] ──► [ Calculated Outcomes ]
                                    ▲
                                    │ Evaluates
                                    ▼
                        [ Scenario Assumptions ]
                        - Inflation Rate Adjustment
                        - Exchange Rate Fluctuations
                        - Churn Rate Trends
```

*   **Assumption Matrices**: Scenarios model risks by adjusting key operational assumptions stored in `public.scenario_assumptions` (such as changing interest rates, inflation rates, or subscription churn rates).
*   **Consolidation and Comparison Runs**: The scenario engine processes calculations on a copy of the base budget. This enables users to compare outcomes across multiple models (e.g., comparing "Worst Case" and "Best Case" revenue trends) without modifying active production budgets.

---

## SECTION 6: PLANNING VERSION ENGINE

The Planning Version Engine manages draft cycles, revisions, and approvals across multiple coexisting budgets and forecasts.

### 6.1 Version Control Workflows

*   **Branching & Cloning**: Users can clone active planning baselines to draft branches, allowing them to test adjustments in isolation.
*   **Approval & Locking**: Once a draft is finalized and passes validation checks, submitting it triggers the review workflow. Upon receiving CFO approval, the version's status is set to `Locked` and its rows are marked read-only.
*   **Auditing Changes**: Amendments to locked budgets must post as structured adjustments to `public.budget_adjustments`, ensuring a complete audit trail of all changes.

---

## SECTION 7: VARIANCE ANALYSIS ENGINE

The Variance Analysis Engine calculates deviations between actual outcomes and planned targets to identify operational overruns and cost trends.

```
 [ General Ledger ] ──► [ Variance Engine ] ◄── [ Planning Engine ]
 (Actual Balances)      - Absolute: Actual - Plan    (Budgets/Forecasts)
                        - Percentage: (Abs / Plan) * 100
```

### 7.1 Variance Calculations

*   **Absolute Variance**:
    $$\text{Variance}_{\text{Absolute}} = \text{Actual Amount} - \text{Planned Amount}$$
*   **Percentage Variance**:
    $$\text{Variance}_{\text{Percent}} = \left( \frac{\text{Actual Amount} - \text{Planned Amount}}{\text{Planned Amount}} \right) \times 100$$
*   **Standard Performance Buckets**: Variance reports categorize deviations by cause (such as price fluctuations, volume changes, or timing delays), helping management identify and address cost overruns.

---

## SECTION 8: CONFIGURABLE FINANCIAL DRIVERS

To simplify maintenance, the Planning Engine calculates target figures dynamically using configurable, operational drivers.

### 8.1 Core Driver Directory

*   **Headcount Drivers**: Calculates salary, tax, benefit, and software licensing costs by linking them directly to active employee records and hiring schedules.
*   **SaaS Growth Drivers**: Computes recurring revenue lines by evaluating subscriber counts, average contract values (ACV), and historical churn rates.
*   **Infrastructure Drivers**: Projects cloud hosting expenses by linking them to anticipated database usage, active customer connections, and AI processing demands.
*   **Treasury Drivers**: Projections evaluate multi-currency actuals against historical exchange rates, regional inflation indexes, and active bank interest rates.

---

## SECTION 9: PLANNING WORKFLOW & GOVERNANCE

Creating and approving budgets follows a strict maker-checker workflow to satisfy corporate governance and audit requirements.

```
                         [PLANNING APPROVAL WORKFLOW]
                         
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 1. Draft Creation                                                      │
   │    - Department managers enter local target lines.                     │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 2. Validation & Submission                                             │
   │    - System verifies that line totals reconcile to template limits.    │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 3. Multi-Level Approvals                                               │
   │    - Routes through Department, Finance, and Executive reviews.        │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 4. Lock & Snapshot                                                     │
   │    - CFO signs off; budget is locked and saved as a read-only snapshot.│
   └────────────────────────────────────────────────────────────────────────┘
```

*   **Delegation and Reassignment**: Approving officers can delegate review authority to designated deputies, with all actions logged to `public.budget_approvals`.
*   **Change Log Auditing**: Re-opening a locked budget or applying manual overrides to forecast lines requires a mandatory justification comment, creating an auditable log of planning changes.

---

## SECTION 10: EXECUTIVE DASHBOARDS & CORE PERFORMANCE METRICS

Executive dashboards aggregate operational and planning data to present a real-time view of corporate health and performance.

### 10.1 Executive Performance Metrics

1.  **Revenue Growth**: Measures year-over-year or month-over-month sales trends.
2.  **EBITDA**: Earnings before interest, taxes, depreciation, and amortization, tracking core operating profitability.
3.  **Operating Margin**: Proportions of revenue remaining after covering variable operating expenses.
4.  **Budget Utilization %**: Compares actual expenditures against approved departmental budget limits:
    $$\text{Utilization \%} = \frac{\text{Actual Operating Expenditure}}{\text{Approved Budget Limit}} \times 100$$
5.  **Forecast Accuracy %**: Measures the precision of historical projection models:
    $$\text{Accuracy \%} = 100 - \left| \frac{\text{Actual Amount} - \text{Projected Amount}}{\text{Actual Amount}} \right| \times 100$$
6.  **SaaS Operating Metrics**: Monitors active subscriber health by tracking Monthly Recurring Revenue (MRR), Customer Acquisition Cost (CAC), and Customer Lifetime Value (LTV).
7.  **Runway & Burn Rate**: Evaluates net cash expenditures to project the number of months the company can operate before requiring additional capital:
    $$\text{Runway (Months)} = \frac{\text{Current Cash Balance}}{\text{Average Monthly Net Outflow (Burn Rate)}}$$

---

## SECTION 11: AI-ASSISTED ENTERPRISE PLANNING SUPPORT

The Planning Engine incorporates machine learning capabilities to automate forecasting, identify anomalies, and optimize resource allocations.

```
   [ Historical Actuals ] ──┐
                            ├─► [ Gemini Forecasting Engine ] ─► [ AI Draft Recommendations ]
   [ Assumptions & Trends ] ──┘                                        (Requires Human Approval)
```

### 11.1 Core AI Planning Capabilities

*   **Automated Forecasting**: Deep learning models analyze historical revenue and expense patterns, generating baseline forecast drafts that incorporate seasonal trends and growth rates.
*   **Trend & Anomaly Detection**: Nightly scans compare current actuals against historical baseline plans, flagging unusual cost overruns or revenue drops for review.
*   **Scenario Recommendations**: AI algorithms evaluate strategic priorities and constraint profiles, recommending budget re-allocations to optimize spending and improve margins.
*   **Strict Governance Policy**: AI recommendations are strictly advisory. The system cannot publish budgets, modify locked versions, or alter operational forecasts automatically. AI suggestions must be reviewed and approved by an authorized financial controller before posting.

---

## SECTION 12: MATERIALIZED VIEWS & CACHING STRATEGY

To maintain high query performance under heavy analytical loads, the Reporting Engine utilizes pre-computed materialized views for dashboard widgets and variance reports.

```sql
-- 1. Materialized view pre-aggregating budget vs actual balances by period and department
CREATE MATERIALIZED VIEW public.mv_budget_vs_actual_summary AS
SELECT 
    b.organization_id,
    b.accounting_period_id,
    b.account_id,
    b.department_id,
    COALESCE(SUM(b.budget_amount), 0.00) AS total_budgeted,
    COALESCE(SUM(l.closing_balance), 0.00) AS total_actual,
    COALESCE(SUM(l.closing_balance - b.budget_amount), 0.00) AS variance_absolute
FROM public.budget_lines b
LEFT JOIN public.ledger_balances l ON 
    l.organization_id = b.organization_id AND 
    l.accounting_period_id = b.accounting_period_id AND 
    l.account_id = b.account_id
GROUP BY b.organization_id, b.accounting_period_id, b.account_id, b.department_id;

-- Create unique index to support concurrent refreshes
CREATE UNIQUE INDEX mv_budget_vs_actual_summary_uid 
  ON public.mv_budget_vs_actual_summary(organization_id, accounting_period_id, account_id, department_id);
```

*   **Refresh Strategy**: Materialized views refresh nightly during low-traffic periods using the `CONCURRENTLY` keyword, ensuring dashboard queries remain fast without locking analytical databases.

---

## SECTION 13: REAL-TIME SYSTEM EVENTS

The Planning Engine is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 13.1 System Events

#### `budget.locked`
Emitted immediately upon successfully reviewing and locking an approved budget version.

```json
{
  "event_id": "evt_plan_01A9512301",
  "event_type": "budget.locked",
  "organization_id": "org_771829",
  "correlation_id": "corr_wf_88291",
  "payload": {
    "budget_id": "bud_112903",
    "budget_version_id": "bver_449102",
    "fiscal_year": 2026,
    "locked_by_user": "usr_33912",
    "total_expenditure_limit": 4500000.00,
    "currency": "USD"
  },
  "timestamp": "2026-06-28T23:30:00Z"
}
```

#### `variance.calculated`
Emitted immediately upon completing a period variance computation run.

```json
{
  "event_id": "evt_plan_01A9512355",
  "event_type": "variance.calculated",
  "organization_id": "org_771829",
  "correlation_id": "corr_run_99182",
  "payload": {
    "variance_report_id": "var_991029",
    "accounting_period_id": "per_11029",
    "comparison_type": "actual_vs_budget",
    "unfavorable_variance_count": 14,
    "total_variance_absolute": -45200.00
  },
  "timestamp": "2026-06-28T23:35:00Z"
}
```

---

## SECTION 14: PRODUCTION PLANNING VALIDATION CHECKLIST

Before deploying the Budgeting, Forecasting, and Financial Planning Engine to production, verify that the following configurations and controls are in place.

- [ ] **Locking Rules Enforced**: Approved budget versions are verified as read-only, blocking direct line updates or deletions.
- [ ] **Forecast Decoupling Tested**: Verification runs confirm that rolling forecast updates do not alter historical baseline budgets.
- [ ] **Calculations Reconciled**: Budget templates verify that department and project totals reconcile perfectly to top-level budget lines.
- [ ] **GL-Variance Alignment Verified**: Variance calculations utilize only posted ledger actuals, blocking unposted transactional drafts.
- [ ] **Multi-Tenant Security Confirmed**: Row-Level Security (RLS) is active across all planning tables, isolating scenario workspaces between tenants.
- [ ] **Audit Trail Capture Active**: Revisions and overrides to locked plans require mandatory justification comments and are logged to history logs.
- [ ] **Materialized Refreshes Scheduled**: Nightly incremental refreshes for materialized planning views are configured and active.
- [ ] **AI Recommendation Sandbox Confirmed**: AI recommendation pipelines are sandbox-isolated, requiring human approval before posting.

---
**End of Specification.**
