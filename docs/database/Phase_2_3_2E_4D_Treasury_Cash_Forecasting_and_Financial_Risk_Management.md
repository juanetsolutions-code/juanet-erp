# JUANET ERP Treasury, Cash Forecasting & Financial Risk Management Engine Specification
## Phase 2.3.2E.4D — Treasury, Cash Forecasting & Financial Risk Management Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Treasury Core, Cash Management, Liquidity Pools, Risk Analytics, and Debt/Investment Subsystem  

---

## SECTION 1: TREASURY PHILOSOPHY & PRINCIPLES

In a global enterprise scale ERP (equivalent in depth to SAP Treasury and Risk Management, Kyriba, and Oracle Treasury Cloud), the **Treasury, Cash Forecasting & Financial Risk Management Engine** (the "Treasury Engine") is responsible for ensuring the group has optimal liquidity, protecting financial holdings from currency and interest rate shocks, overseeing investments, and managing debt and capital covenants.

The Treasury Engine separates accounting entry preparation from transactional cash movement and strategic liquidity planning. This operational segregation is governed by nine fundamental principles:

```
                  [TREASURY COEXISTENCE ARCHITECTURE]
                  
 ┌─────────────────────────────────────────────────────────────────────────┐
 │                   Centralized General Ledger (GL)                       │
 │           (The Legal Source of Truth - Immutable Actual Balances)       │
 └────────────────────────────────────┬────────────────────────────────────┘
                                      │ Read-Only Balances
                                      ▼
 ┌─────────────────────────────────────────────────────────────────────────┐
 │                   Treasury Engine (Multi-Entity)                        │
 │  - Liquidity Pools & Sweeps             - Risk Exposures (FX / IR)      │
 │  - Debt & Investment Portfolios         - Rolling 13-Week Cash Forecast │
 └────────────────────────────────────┬────────────────────────────────────┘
                                      │
                                      ▼
 ┌─────────────────────────────────────────────────────────────────────────┐
 │                       Management Dashboards                             │
 │      - Cash Position Monitor            - Treasury KPI Scorecards       │
 │      - Covenant Compliance Alerts       - AI-Assisted Liquidity Insights│
 └─────────────────────────────────────────────────────────────────────────┘
```

1.  **Strict Ledger Non-Interference**: Treasury represents the active management of cash and financial risk. While it consumes real-time general ledger actual balances (`public.ledger_entries`), it does not directly mutate ledger data. All cash movements are sent as sub-ledger entries or operational transactions which flow through standard journal entry pipelines before posting.
2.  **GL remains the Legal Source of Truth**: The ledger tracks accounting substance and legal records. Cash positioning monitors bank reporting (e.g., BAI2, CAMT.053 statement files), pending payments, and receipts. While reconciliation aligns both datasets, the General Ledger remains the absolute legal authority.
3.  **Treasury Forecasts are Projections**: Cash forecasts (such as 13-week rolling cash forecasts) are analytical projections, not accounting records. They combine historical trends, payment runs, billing backlogs, purchasing pipelines, and macroeconomic assumptions, never writing directly to accounting actuals.
4.  **Forecast Versioning and Auditing**: Every forecast run is version-controlled and immutable once finalized. This ensures that actual performance can be audited against historical forecasts, generating historical forecast-accuracy metrics.
5.  **Multi-Entity Legal Autonomy**: Financial risk operations function across multiple subsidiaries. The Treasury Engine acts as a multi-entity consolidator, identifying natural hedges across trading entities before executing external currency swaps or forwards.
6.  **Centralized and Decentralized Cash Management**: The engine supports centralized cash management (e.g., in-house banking, cash sweeps, and notional pooling managed by corporate headquarters) as well as decentralized local cash operating budgets for regional offices.
7.  **Segregation of Duties and Governance**: Cash operations enforce the "Four-Eyes Principle" (dual authorization). A user cannot prepare an external bank transaction and approve it. Segregation of duties isolates the roles of Treasury Administrator, Investment Manager, Treasurer, and Chief Financial Officer.
8.  **Risk Isolation & Limit Controls**: Exposure thresholds (such as currency concentration caps or counterparty banking limits) are enforced at the database layer. Transactions that exceed risk limits trigger automated workflow escalations.
9.  **Historical Reproducibility**: All historical risk models, market valuation snapshots, and covenant measurements must remain reproducible. This requires freezing exchange rates, discount curves, and contract parameters applied during the original calculations.

---

## SECTION 2: ENTERPRISE TREASURY MODEL

The Treasury Engine structures liquidity into operational categories to maximize capital efficiency:

*   **Operational Cash**: Daily operating balances held in transactional accounts, used to cover payables and payroll.
*   **Strategic Cash**: Mid-term surplus cash placed in yield-bearing, low-risk accounts (such as money market funds or short-term treasury bills) that remains liquid.
*   **Restricted Cash**: Cash legally restricted for specific purposes (such as debt service reserves, regulatory deposits, or customer escrow accounts).
*   **Escrow Balances**: Cash held by third-party banks in trust to secure corporate acquisitions, contract milestones, or legal dispute settlements.
*   **Petty Cash**: Local operational cash floats maintained within physical retail locations or local branches, capped by strict ledger control policies.
*   **Investment Portfolios**: Fixed-income securities, commercial paper, treasury bills, and certificates of deposit managed to generate yield while protecting principal capital.
*   **Borrowing Facilities & Credit Lines**: Configured borrowing caps, revolving credit lines (RCF), mortgage agreements, and trade finance limits available to fund short-term working capital needs.
*   **Letters of Credit (LC)**: Guarantees issued by partner banks securing international vendor shipments, reducing trade counterparty risks.
*   **Corporate Guarantees**: Corporate guarantees issued by the parent holding company to back subsidiary debt or operational obligations.
*   **Liquidity Pools & Sweeping**: Cash concentration systems that aggregate cash across separate subsidiary accounts into a master pool. This utilizes **Physical Sweeping** (automatic daily wire transfers of balances to a central account) or **Notional Pooling** (virtual balance aggregation where the bank calculates interest across accounts without executing physical transfers).

---

## SECTION 3: PHYSICAL DATABASE SCHEMAS

The following specifications define the schemas for cash forecasting, liquidity pooling, investment tracking, debt management, and risk exposure monitoring.

### 3.1 Table Name: `public.cash_forecasts`
Stores top-level metadata for corporate cash forecasting models.

*   **Purpose**: Top-level definitions of cash forecasting horizons (e.g., 13-Week Operational Forecast Q3 2026).
*   **Ownership**: Treasury Manager.
*   **Lifecycle**: Draft -> Active -> Archived.
*   **Retention**: 7 years.
*   **Optimistic Locking**: Yes, via `version` column.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_name` | `varchar(150)`| NO | None | None | Public | Standard string | Descriptive forecast label. |
| `horizon_weeks` | `integer` | NO | `13` | `CHECK (horizon_weeks >= 1)` | Public | Standard integer | Forecast timeframe. |
| `base_currency` | `varchar(3)` | NO | `'USD'` | Check Constraint | Public | ISO Code | Currency of consolidated outputs. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | Public | `'draft'`, `'active'`, `'archived'` | State-machine flag. |
| `version` | `integer` | NO | `1` | None | Public | `>= 1` | Optimistic locking field. |

---

### 3.2 Table Name: `public.cash_forecast_versions`
Branches of forecasts representing draft iterations, scenario variations, or historical snapshots.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `cash_forecast_id` | `uuid` | NO | None | FK -> `cash_forecasts(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent forecast container. |
| `version_code` | `varchar(30)` | NO | None | None | Public | Unique per forecast | Code (e.g., 'FC_V1_Base', 'FC_V1_Worst'). |
| `is_approved` | `boolean` | NO | `false` | None | Public | Valid boolean | Denotes approved baseline plan. |
| `created_by` | `uuid` | NO | None | FK -> `users(id)` | Public | UUIDv4 | Creator profile. |

---

### 3.3 Table Name: `public.cash_forecast_lines`
Atomic row-level planning lines distributed by cash flow categories, entities, and forecasting weeks.

*   **Purpose**: Holds weekly projected cash inflows and outflows by category and entity.
*   **Ownership**: Treasury Analyst.
*   **Lifecycle**: Active (editable when parent version is 'draft').
*   **Retention**: 7 years.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_version_id`|`uuid`| NO | None | FK -> `cash_forecast_versions(id)` ON DELETE CASCADE| Public | UUIDv4 | Linked forecast revision. |
| `target_entity_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Local entity projection target.|
| `week_number` | `integer` | NO | None | `CHECK (week_number >= 1 AND week_number <= 53)` | Public | Standard integer | Target forecasting week. |
| `projected_date` | `date` | NO | None | None | Public | Valid date | Start date of forecast week. |
| `category_id` | `uuid` | NO | None | FK -> `cash_flow_categories(id)`| Public | UUIDv4 | Cash category linked. |
| `projected_inflow` | `numeric(18,2)`| NO | `0.00` | `CHECK (projected_inflow >= 0.00)` | Financial | Positive amount | Expected cash receipts. |
| `projected_outflow`|`numeric(18,2)`| NO | `0.00` | `CHECK (projected_outflow >= 0.00)` | Financial | Positive amount | Expected cash disbursements. |

*   **Indexes**:
    *   `CREATE INDEX forecast_lines_search_idx ON public.cash_forecast_lines(organization_id, forecast_version_id, category_id) INCLUDE (projected_inflow, projected_outflow);`

---

### 3.4 Table Name: `public.cash_flow_categories`
Hierarchy of liquidity flow elements (e.g., Customer Receipts, Vendor Disbursements, Debt Service, Payroll).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `category_name` | `varchar(100)`| NO | None | None | Public | Standard string | Category descriptive name. |
| `flow_direction` | `varchar(10)` | NO | None | `CHECK (flow_direction IN ('inflow', 'outflow', 'net'))` | Public | Match direction | Category direction category. |
| `parent_category_id`|`uuid` | YES | `NULL` | FK -> `cash_flow_categories(id)`| Public | UUIDv4 | Recursive parent node. |

---

### 3.5 Table Name: `public.treasury_positions`
Tracks calculated, real-time availability of cash balances aggregated across accounts.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)`| Public | UUIDv4 | Associated bank account. |
| `ledger_actual` | `numeric(18,2)`| NO | `0.00` | None | Financial | Standard numeric | GL cash balance actual. |
| `bank_cleared` | `numeric(18,2)`| NO | `0.00` | None | Financial | Standard numeric | Bank reported cleared balance. |
| `uncleared_receipts`|`numeric(18,2)`|NO | `0.00` | None | Financial | Standard numeric | Uncleared deposits pending. |
| `uncleared_payments`|`numeric(18,2)`|NO | `0.00` | None | Financial | Standard numeric | Unpresented payment checks. |
| `available_cash` | `numeric(18,2)`| NO | None | None | Financial | `bank_cleared + uncleared_receipts - uncleared_payments` | Net daily operating cash. |
| `updated_at` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Record timestamp. |

---

### 3.6 Table Name: `public.liquidity_pools`
Defines multi-account structures for sweeping or virtual interest calculations.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `pool_name` | `varchar(100)`| NO | None | None | Public | Standard string | Liquidity pool name. |
| `pool_type` | `varchar(30)` | NO | None | `CHECK (pool_type IN ('notional', 'physical'))` | Public | Matching type | Pool methodology. |
| `master_account_id`|`uuid` | NO | None | FK -> `bank_accounts(id)`| Public | UUIDv4 | Concentrated target account. |
| `is_active` | `boolean` | NO | `true` | None | Public | Valid boolean | Status confirmation flag. |

---

### 3.7 Table Name: `public.cash_sweeps`
Records physical balance transfers executed to consolidate capital.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `pool_id` | `uuid` | NO | None | FK -> `liquidity_pools(id)` ON DELETE CASCADE | Public | UUIDv4 | Associated pool configuration.|
| `source_account_id`|`uuid` | NO | None | FK -> `bank_accounts(id)`| Public | UUIDv4 | Swept subsidiary account. |
| `transferred_amount`|`numeric(18,2)`|NO | None | `CHECK (transferred_amount <> 0.00)` | Financial | Positive or negative value | Swept balance value. |
| `swept_at` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Sweep execution timestamp. |

---

### 3.8 Table Name: `public.investment_accounts`
Registry of custodial accounts used to purchase securities or hold commercial paper.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `institution_name` | `varchar(150)`| NO | None | None | Public | Standard string | Custodian bank broker. |
| `account_number` | `varchar(50)` | NO | None | None | Private | Account indicator | Account reference identifier. |
| `portfolio_limit` | `numeric(18,2)`| NO | None | None | Public | Standard numeric | Maximum approved investment cap.|

---

### 3.9 Table Name: `public.investment_holdings`
Maintains records of active corporate investments.

*   **Purpose**: Registry of active investments (T-Bills, government bonds, fixed deposits).
*   **Ownership**: Treasury Investment Manager.
*   **Lifecycle**: Active -> Matured -> Sold.
*   **Retention**: 7 years.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `custodial_account_id`|`uuid`| NO | None | FK -> `investment_accounts(id)`| Public | UUIDv4 | Associated custody account. |
| `asset_name` | `varchar(150)`| NO | None | None | Public | Standard string | Asset identifier (e.g., US T-Bill). |
| `asset_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'money_market'`, `'treasury_bill'`, `'govt_bond'`, `'corp_bond'`, `'fixed_deposit'`, `'commercial_paper'` | Asset categorization. |
| `isin_code` | `varchar(12)` | YES | `NULL` | None | Public | ISIN string format | Unique identifier code. |
| `principal_amount` | `numeric(18,2)`| NO | None | `CHECK (principal_amount > 0.00)` | Financial | Positive amount | Initial purchase cost. |
| `annual_yield_rate`|`numeric(6,4)` | NO | None | `CHECK (annual_yield_rate >= 0.0000)` | Public | Interest yield | Annualized interest rate (e.g., 0.0450). |
| `purchase_date` | `date` | NO | None | None | Public | Valid date | Acquisition date. |
| `maturity_date` | `date` | NO | None | `CHECK (maturity_date > purchase_date)` | Public | Valid date | Maturity redemption date. |
| `current_valuation`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Fair market value (mark-to-market). |
| `status` | `varchar(30)` | NO | `'active'` | Check Constraint | Public | `'active'`, `'matured'`, `'liquidated'` | Holding lifecycle state. |
| `version` | `integer` | NO | `1` | None | Public | `>= 1` | Optimistic locking field. |

---

### 3.10 Table Name: `public.investment_transactions`
Logs trade executions, maturity redemptions, and coupon payments.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `holding_id` | `uuid` | NO | None | FK -> `investment_holdings(id)` ON DELETE CASCADE | Public | UUIDv4 | Target investment holding. |
| `transaction_type` | `varchar(30)` | NO | None | Check Constraint | Public | `'purchase'`, `'sell'`, `'coupon_received'`, `'maturity_redemption'` | Transaction category type. |
| `trade_date` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Transaction execution date. |
| `settlement_amount`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Settled cash transfer value. |

---

### 3.11 Table Name: `public.debt_facilities`
Structures borrowing frameworks, credit facilities, and mortgages.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `facility_name` | `varchar(150)`| NO | None | None | Public | Standard string | Creditor borrowing program. |
| `facility_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'term_loan'`, `'revolving_credit'`, `'commercial_mortgage'`, `'working_capital'` | Facility class definition. |
| `lender_name` | `varchar(150)`| NO | None | None | Public | Standard string | Lending bank institution. |
| `approved_limit` | `numeric(18,2)`| NO | None | `CHECK (approved_limit > 0.00)` | Financial | Positive amount | Maximum borrowing capacity. |
| `outstanding_balance`|`numeric(18,2)`|NO | `0.00` | `CHECK (outstanding_balance <= approved_limit)` | Financial | Standard numeric | Currently drawn capital balance. |

---

### 3.12 Table Name: `public.loan_drawdowns`
Tracks loans drawn against approved credit lines.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `facility_id` | `uuid` | NO | None | FK -> `debt_facilities(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent credit facility. |
| `drawdown_amount` | `numeric(18,2)`| NO | None | `CHECK (drawdown_amount > 0.00)` | Financial | Positive amount | Drawn capital value. |
| `drawdown_date` | `date` | NO | `now()` | None | Public | Valid date | Funding settlement date. |
| `interest_rate` | `numeric(6,4)` | NO | None | `CHECK (interest_rate >= 0.0000)` | Public | Interest factor | Interest rate (e.g., 0.0650). |

---

### 3.13 Table Name: `public.loan_repayments`
Records interest payments and principal reductions on loans.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `facility_id` | `uuid` | NO | None | FK -> `debt_facilities(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent credit facility. |
| `payment_date` | `date` | NO | None | None | Public | Valid date | Repayment settlement date. |
| `principal_paid` | `numeric(18,2)`| NO | `0.00` | `CHECK (principal_paid >= 0.00)`| Financial | Standard numeric | Principal reduction portion. |
| `interest_paid` | `numeric(18,2)`| NO | `0.00` | `CHECK (interest_paid >= 0.00)` | Financial | Standard numeric | Interest charge portion. |

---

### 3.14 Table Name: `public.loan_interest_schedules`
Maintains amortization schedules and interest payment deadlines.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `facility_id` | `uuid` | NO | None | FK -> `debt_facilities(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent credit facility. |
| `due_date` | `date` | NO | None | None | Public | Valid date | Scheduled payment date. |
| `estimated_principal`|`numeric(18,2)`|NO | None | None | Financial | Standard numeric | Amortized principal payment portion. |
| `estimated_interest`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Scheduled interest payment portion. |
| `is_paid` | `boolean` | NO | `false` | None | Public | Valid boolean | Status flag. |

---

### 3.15 Table Name: `public.covenant_definitions`
Registers financial covenants and compliance ratios mandated by lenders.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `facility_id` | `uuid` | NO | None | FK -> `debt_facilities(id)` ON DELETE CASCADE | Public | UUIDv4 | Target debt facility. |
| `covenant_name` | `varchar(100)`| NO | None | None | Public | Standard string | Covenant identifier. |
| `covenant_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'debt_service_coverage'`, `'interest_coverage'`, `'debt_to_equity'`, `'minimum_cash'` | Covenant ratio metric classification. |
| `operator` | `varchar(5)` | NO | None | `CHECK (operator IN ('>', '>=', '<', '<='))` | Public | Match operator | Comparison operator. |
| `target_threshold` | `numeric(12,4)`| NO | None | None | Public | Standard numeric | Contractual compliance limit. |

---

### 3.16 Table Name: `public.covenant_measurements`
Logs covenant compliance measurements evaluated over reporting periods.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `covenant_id` | `uuid` | NO | None | FK -> `covenant_definitions(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent covenant configuration. |
| `measured_date` | `date` | NO | `now()` | None | Public | Valid date | Evaluation date. |
| `measured_value` | `numeric(12,4)`| NO | None | None | Public | Standard numeric | Measured value. |
| `is_compliant` | `boolean` | NO | None | None | Public | Valid boolean | Compliance status flag. |

---

### 3.17 Table Name: `public.risk_limits`
Configures corporate risk tolerance thresholds.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `risk_category` | `varchar(50)` | NO | None | Check Constraint | Public | `'counterparty'`, `'currency_concentration'`, `'interest_rate_sensitivity'` | Risk category. |
| `limit_target` | `varchar(100)`| NO | None | None | Public | Standard target | Target parameter (e.g., bank ID). |
| `limit_amount` | `numeric(18,2)`| NO | None | None | Public | Standard numeric | Maximum approved risk exposure limit. |

---

### 3.18 Table Name: `public.risk_exposures`
Tracks calculated risk exposures against corporate limits.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `risk_limit_id` | `uuid` | YES | `NULL` | FK -> `risk_limits(id)` ON DELETE SET NULL | Public | UUIDv4 | Associated risk limit. |
| `exposure_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'fx_exposure'`, `'interest_rate'`, `'counterparty_holding'` | Exposure category. |
| `current_exposure` | `numeric(18,2)`| NO | None | None | Financial | Standard numeric | Measured exposure value. |
| `last_calculated_at`|`timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Evaluation timestamp. |

---

### 3.19 Table Name: `public.fx_exposures`
Tracks currency exposure and balance details by subsidiary and currency.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `currency_code` | `varchar(3)` | NO | None | None | Public | ISO Code | Currency code (e.g., 'EUR'). |
| `asset_amount` | `numeric(18,2)`| NO | `0.00` | None | Financial | Standard numeric | Total currency assets. |
| `liability_amount` | `numeric(18,2)`| NO | `0.00` | None | Financial | Standard numeric | Total currency liabilities. |
| `net_exposure` | `numeric(18,2)`| NO | None | None | Financial | `asset_amount - liability_amount` | Net exposure balance. |

---

### 3.20 Table Name: `public.interest_rate_exposures`
Tracks interest rate sensitivities across fixed and floating debt portfolios.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `fixed_rate_debt` | `numeric(18,2)`| NO | `0.00` | None | Financial | Standard numeric | Total fixed rate balance. |
| `floating_rate_debt`|`numeric(18,2)`|NO | `0.00` | None | Financial | Standard numeric | Total variable rate balance. |
| `sensitivity_factor`|`numeric(12,4)`| NO | None | None | Public | Rate impact | Estimated cost of a 1% rate increase. |

---

### 3.21 Table Name: `public.market_value_snapshots`
Logs frozen fair-market valuations (mark-to-market) for risk management.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `holding_id` | `uuid` | NO | None | FK -> `investment_holdings(id)` ON DELETE CASCADE | Public | UUIDv4 | Target investment holding. |
| `valuation_date` | `date` | NO | None | None | Public | Valid date | Valuation snapshot date. |
| `fair_market_value`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Mark-to-market valuation. |

---

### 3.22 Table Name: `public.treasury_approvals`
Tracks maker-checker approvals and signatures for treasury operations.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `operation_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'large_payment'`, `'investment_trade'`, `'loan_drawdown'`, `'fx_hedging_deal'` | Operational category. |
| `operation_ref_id` | `uuid` | NO | None | None | Public | UUIDv4 | Target operational row ID. |
| `maker_id` | `uuid` | NO | None | FK -> `users(id)` | Public | UUIDv4 | Preparing user profile ID. |
| `checker_id` | `uuid` | YES | `NULL` | FK -> `users(id)` | Public | UUIDv4 | Approving user profile ID. |
| `approval_status` | `varchar(30)` | NO | `'pending'` | Check Constraint | Public | `'pending'`, `'approved'`, `'rejected'` | Approval status state. |

---

### 3.23 Table Name: `public.treasury_events`
Detailed diagnostic and security events emitted by the treasury subsystem.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `event_class` | `varchar(50)` | NO | None | None | Public | Standard class | Class (e.g., 'covenant_breach'). |
| `message` | `text` | NO | None | None | Public | Standard string | Descriptive event log text. |
| `recorded_at` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Record timestamp. |

---

### 3.24 Table Name: `public.cash_position_snapshots`
Immutable daily snapshots caching consolidated group positions.

*   **Purpose**: Caches frozen corporate cash positions across all bank accounts for auditing and trend reporting.
*   **Ownership**: Internal Auditing / Treasurer.
*   **Lifecycle**: Created once daily, permanently locked.
*   **Retention**: 7 years.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `snapshot_date` | `date` | NO | None | None | Public | Valid date | Snapshot date (WORM locked). |
| `consolidated_cash`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Sum of all available bank cash. |
| `investment_market_value`|`numeric(18,2)`|NO | None | None | Financial | Standard numeric | Sum of investment market values.|
| `debt_outstanding` | `numeric(18,2)`| NO | None | None | Financial | Standard numeric | Sum of outstanding loan balances.|
| `net_position_value`|`numeric(18,2)`|NO | None | None | Financial | `consolidated_cash + investment_market_value - debt_outstanding` | Ultimate group liquidity value. |
| `signature_hash` | `varchar(64)` | NO | None | None | Public | SHA-256 Hash | Cryptographic verification signature. |

---

## SECTION 4: CASH FORECASTING ENGINE

To support strategic decision-making, the forecasting engine projects liquidity requirements by combining historical cash flows, operating budgets, and pending transactions.

```
                         [CASH FORECASTING PIPELINE]
                         
  ┌─────────────────────────────────────────────────────────────────────────┐
  │ 1. Core Receipts (Inflows)                                              │
  │    - Cleared customer receipts (Ledger actuals)                         │
  │    - Open Accounts Receivable (Invoices discounted by DSO weights)      │
  │    - Recurring subscription billing runs (MRR/ARR projections)           │
  └────────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │ 2. Core Disbursements (Outflows)                                        │
  │    - Open Accounts Payable (Bills mapped by due dates)                  │
  │    - Projected operating payroll (Hiring plan calculations)             │
  │    - Debt service requirements (Loan interest and principal payments)   │
  └────────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
  ┌─────────────────────────────────────────────────────────────────────────┐
  │ 3. Projection Consolidation                                             │
  │    - Direct cash flow forecasting (Consolidating near-term cash flows)   │
  │    - Indirect cash flow forecasting (Reconciling Net Income to cash)    │
  └─────────────────────────────────────────────────────────────────────────┘
```

### 4.1 Forecasting Horizons
*   **Direct 13-Week Cash Forecast**: The standard operational forecasting tool. Near-term cash flows are mapped to specific weeks based on invoice payment histories, pending purchase orders, and scheduled payroll runs.
*   **Rolling Forecasts (Monthly / Quarterly / Annual)**: Aggregates actuals with dynamic operational assumptions to project cash flows over extended horizons.
*   **Scenario Projections (Best Case, Expected, Worst Case)**: Evaluates corporate cash runways across multiple, isolated scenarios, modeling the impact of market shifts on liquidity.
*   **AI-Assisted Forecasting & Confidence Intervals**: Combines statistical models with deep learning algorithms to predict future cash flows, generating confidence intervals based on historical volatility.

---

## SECTION 5: LIQUIDITY ENGINE

The Liquidity Engine monitors corporate cash availability and operational burn rates to protect the organization from cash shortages.

*   **Operating Liquidity Buffer**: Maintains a minimum cash reserve to cover daily operational fluctuations, calculated using historical cost volatility:
    $$\text{Operating Buffer} = \text{Average Daily Expenses} \times \text{Buffer Margin Target (Days)}$$
*   **Emergency Liquidity Buffers**: Holds highly liquid capital reserves (such as money market funds) to secure business continuity during market downturns.
*   **Burn Rate & Cash Runway**: Evaluates monthly cash expenditures to project how long the business can operate before requiring new financing:
    $$\text{Cash Runway (Months)} = \frac{\text{Current Available Liquidity}}{\text{Average Net Cash Burn Rate}}$$
*   **Liquidity Coverage Ratio (LCR)**: Monitors the organization's capacity to cover short-term liabilities with highly liquid assets:
    $$\text{LCR} = \frac{\text{High-Quality Liquid Assets (HQLA)}}{\text{Net Cash Outflows (30 Days)}} \ge 1.0$$

---

## SECTION 6: INVESTMENT PORTFOLIO MANAGEMENT

The Investment Subsystem manages short-term corporate investments to generate yield on surplus cash while protecting capital principal.

### 6.1 Supported Investment Instruments
1.  **Money Market Funds (MMF)**: Low-volatility mutual funds focused on high-quality short-term debt, used to generate yield on immediate operating cash reserves.
2.  **Treasury Bills (T-Bills)**: Short-term government debt instruments purchased at a discount and redeemed at par upon maturity.
3.  **Government & Corporate Bonds**: Fixed-income securities offering coupon payments across defined maturity timelines.
4.  **Fixed-Term Deposits (CDs)**: Bank deposit contracts holding cash for defined horizons at fixed interest rates, incurring early withdrawal fees.
5.  **Commercial Paper**: Short-term, unsecured debt issued by major corporations to secure immediate working capital.

---

### 6.2 Valuation and Performance Tracking
*   **Mark-to-Market Valuation**: Evaluates and updates active investment valuations based on current market rates, posting unrealized gains or losses directly to treasury logs.
*   **Amortized Interest Accrual**: Calculates and posts interest income earned over consecutive reporting periods:
    $$\text{Daily Accrued Interest} = \text{Principal Amount} \times \left( \frac{\text{Annual Yield Rate}}{365} \right)$$
*   **Maturity Alerts & Refinancing**: Generates automated notifications as investment maturity dates approach, enabling teams to schedule cash redemptions or plan re-investment strategies.

---

## SECTION 7: DEBT & CREDIT FACILITY ENGINE

The Debt Subsystem tracks and manages credit facilities, revolving credit lines, term loans, and associated loan covenants.

```
                          [LOAN LIFECYCLE MANAGEMENT]
                          
  [ Approved Credit Limit ] ──► [ Drawdown Executed ] ──► [ Outstanding Debt ]
                                                                 │
                                                                 ▼
                                                        Evaluate Covenants:
                                                        - Debt-to-Equity Ratio
                                                        - Interest Coverage Ratio
```

### 7.1 Debt Management Capabilities
*   **Credit Facility Drawdowns**: Processes and logs drawdowns against approved revolving credit lines (RCF), updating outstanding debt balances and verifying remaining credit availability.
*   **Principal Amortization & Interest Schedules**: Automates principal and interest schedule calculations, generating payment alerts and managing refinancing workflows as loan maturity deadlines approach.
*   **Contractual Covenant Tracking**: Monitors loan compliance metrics (such as Debt-to-Equity or Interest Coverage ratios) using active ledger data, triggering automated alerts if thresholds are breached.

---

## SECTION 8: TREASURY RISK ASSESSMENT & EXPOSURES

To protect the organization's financial health, the risk management module monitors cash concentrations, currency exposure, and interest rate sensitivities.

```
                         [RISK CONCENTRATION MONITOR]
                         
                      ┌─────────────────────────────────┐
                      │    Total Portfolio Liquidity    │
                      └────────────────┬────────────────┘
                                       │
             ┌─────────────────────────┼─────────────────────────┐
             ▼ (Concentration Limit)   ▼ (Concentration Limit)   ▼ (Concentration Limit)
     [ Custodian Bank A ]       [ Custodian Bank B ]       [ Custodian Bank C ]
     (Exposure: 40% - Normal)   (Exposure: 55% - Warn)     (Exposure: 5% - Normal)
```

*   **Counterparty Banking Limits**: Enforces investment concentration caps across financial counterparties, preventing excessive exposure to a single banking partner.
*   **Currency Exposure Analytics**: Aggregates foreign currency assets and liabilities across global subsidiaries, evaluating net risk exposure to identify currency volatility risks:
    $$\text{Net Currency Exposure} = \text{Foreign Assets} - \text{Foreign Liabilities}$$
*   **Interest Rate Sensitivity**: Measures the impact of interest rate changes on variable-rate debt portfolios, providing the risk analytics required to plan interest rate swap hedges.

---

## SECTION 9: FOREIGN EXCHANGE (FX) & HEDGING STRATEGY

The FX Subsystem protects group margins from currency market fluctuations by managing hedging contracts and tracking currency conversions.

*   **FX Spot Conversions**: Records immediate currency conversions between group bank accounts, capturing applied exchange rates and logging realized conversion gains or losses.
*   **Forward & Swap Hedging Contracts**: Manages foreign exchange hedging contracts (such as forwards or swaps) to secure future exchange rates for planned cross-border transactions.
*   **Natural Hedging Adjustments**: Automatically identifies offset opportunities across global subsidiaries (e.g., matching EUR inflows in Germany with EUR outflows in France), reducing the cost of external hedging transactions:
    $$\text{Net Hedging Exposure} = \sum(\text{Entity Cash Inflows}) - \sum(\text{Entity Cash Outflows})$$

---

## SECTION 10: TREASURY WORKFLOW GOVERNANCE

The Treasury Engine enforces strict dual-authorization controls to satisfy corporate compliance and audit requirements.

```
                        [TREASURY APPROVAL WORKFLOW]
                         
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 1. Transaction Prepared (Maker)                                        │
   │    - Investment manager prepares an investment purchase transaction.   │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 2. Dual Authorization Check                                            │
   │    - Transaction is locked; system routes approval to authorized checker.│
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 3. Sign-off and Execution                                              │
   │    - CFO reviews and signs off; transaction is posted to audit logs.   │
   └────────────────────────────────────────────────────────────────────────┘
```

*   **Authorized Transaction Thresholds**: Operational actions (such as large payments, investment trades, or credit drawdowns) must be reviewed and signed off by authorized controllers based on approved limit matrices.
*   **Transaction Lock Controls**: Preparing an external bank payment locks the transaction, blocking subsequent modification attempts while it is routed through the maker-checker approval pipeline.

---

## SECTION 11: KEY TREASURY PERFORMANCE INDICATORS (KPIs)

The subsystem monitors real-time corporate health using fifteen standardized cash performance metrics:

1.  **Cash Conversion Cycle (CCC)**: Evaluates working capital efficiency by calculating the speed at which capital is recovered from operational investments:
    $$\text{CCC} = \text{Days Inventory Outstanding (DIO)} + \text{Days Sales Outstanding (DSO)} - \text{Days Payable Outstanding (DPO)}$$
2.  **Operating Cash Flow (OCF)**: Total cash generated by core business operations, excluding financing or investment income.
3.  **Free Cash Flow (FCF)**: Net operating cash remaining after deducting capital expenditures (CapEx), representing the cash available for debt service or dividends.
4.  **Quick Ratio**: Measures the company's ability to cover short-term liabilities using highly liquid current assets.
5.  **Current Ratio**: Compares total current assets against current liabilities to evaluate short-term solvency.
6.  **Cash Ratio**: The most conservative liquidity metric, comparing immediate cash holdings directly to current liabilities:
    $$\text{Cash Ratio} = \frac{\text{Cash \& Cash Equivalents}}{\text{Current Liabilities}}$$
7.  **Debt Service Coverage Ratio (DSCR)**: Measures corporate capacity to meet scheduled debt interest and principal obligations:
    $$\text{DSCR} = \frac{\text{Net Operating Income}}{\text{Total Debt Service Payments}} \ge 1.25$$
8.  **Interest Coverage Ratio**: Measures the company's ability to cover interest charges using operating earnings (EBIT).
9.  **Working Capital Balance**: The net capital available for daily operations:
    $$\text{Working Capital} = \text{Current Assets} - \text{Current Liabilities}$$
10. **Net Debt**: Total outstanding debt obligations minus available cash reserves:
    $$\text{Net Debt} = \text{Total Debt Outstanding} - \text{Cash \& Cash Equivalents}$$
11. **Debt-to-Equity Ratio**: Measures capital leverage by comparing total liabilities directly against total shareholder equity.
12. **Monthly Net Burn Rate**: The average net cash deficit incurred over a given month.
13. **Cash Runway**: The projected number of months corporate cash reserves can sustain operations at current burn rates.
14. **Liquidity Coverage Ratio (LCR)**: High-quality liquid assets divided by net cash outflows over a 30-day period.
15. **Treasury Efficiency Score**: An operational score evaluating corporate interest income yields against cash holding costs and transaction fees.

---

## SECTION 12: AI-ASSISTED TREASURY CAPABILITIES

The Treasury Subsystem incorporates machine learning capabilities to automate forecasting, identify anomalies, and optimize working capital.

```
   [ Historical Cash Flows ] ──┐
                               ├─► [ Gemini Liquidity Predictor ] ─► [ AI Allocation Proposals ]
   [ Market Interest Rates ] ──┘                                         (Requires Treasurer Review)
```

*   **Automated Liquidity Prediction**: Machine learning algorithms evaluate historical cash receipts and disbursement patterns, generating cash forecast drafts that incorporate seasonal payment cycles.
*   **Anomaly & Fraud Detection**: Nightly scans evaluate pending bank payments against historical transaction patterns, flagging unusual wire amounts or unexpected counterparty routing codes for review.
*   **Working Capital Optimization**: AI models analyze vendor payment deadlines and early-payment discount offerings, proposing cash payment runs that optimize yields on working capital.
*   **Strict Governance Policy**: AI recommendations are strictly advisory. AI services cannot authorize bank transfers, adjust credit facilities, or execute hedging trades automatically. AI suggestions must be reviewed and approved by an authorized Treasurer before posting.

---

## SECTION 13: PERFORMANCE ENGINEERING & MULTI-PARTITION SCALING

To scale reporting capabilities across high transaction volumes and complex enterprise models, the treasury subsystem utilizes targeted performance designs.

```sql
-- 1. Materialized view caching cash balances by bank account and period
CREATE MATERIALIZED VIEW public.mv_account_liquidity_summary AS
SELECT 
    organization_id,
    bank_account_id,
    COALESCE(SUM(bank_cleared), 0.00) AS total_cleared,
    COALESCE(SUM(uncleared_receipts), 0.00) AS total_pending_in,
    COALESCE(SUM(uncleared_payments), 0.00) AS total_pending_out,
    COALESCE(SUM(available_cash), 0.00) AS total_available
FROM public.treasury_positions
GROUP BY organization_id, bank_account_id;

-- Create unique index to support concurrent refreshes
CREATE UNIQUE INDEX mv_account_liquidity_summary_uid 
  ON public.mv_account_liquidity_summary(organization_id, bank_account_id);
```

*   **Database Partitioning**: Heavy transaction logs (such as `public.investment_transactions` and `public.cash_sweeps`) are partitioned by `organization_id` or `trade_date`, allowing the query planner to bypass unrelated data and accelerate query times.
*   **Cash Position Caching**: Rather than calculating cash positions across raw database rows for every request, dashboard widgets query pre-computed daily snapshots (`public.cash_position_snapshots`).

---

## SECTION 14: REAL-TIME SYSTEM EVENTS

The Treasury Subsystem is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 14.1 System Events

#### `liquidity.threshold.exceeded`
Emitted immediately if an account balance falls below its approved minimum liquidity threshold.

```json
{
  "event_id": "evt_trs_01A9700101",
  "event_type": "liquidity.threshold.exceeded",
  "organization_id": "org_771829",
  "correlation_id": "corr_pos_2291",
  "payload": {
    "bank_account_id": "acc_339102",
    "account_name": "Operating Checking",
    "minimum_threshold": 50000.00,
    "current_balance": 34200.00,
    "deficit_amount": 15800.00,
    "currency": "USD"
  },
  "timestamp": "2026-06-28T23:55:00Z"
}
```

#### `loan.drawdown.created`
Emitted immediately upon successfully processing a credit facility drawdown.

```json
{
  "event_id": "evt_trs_01A9700155",
  "event_type": "loan.drawdown.created",
  "organization_id": "org_771829",
  "correlation_id": "corr_draw_44910",
  "payload": {
    "facility_id": "fac_110293",
    "facility_name": "Corporate Revolver Credit Line",
    "drawdown_amount": 250000.00,
    "interest_rate": 0.0625,
    "outstanding_balance": 750000.00,
    "currency": "USD"
  },
  "timestamp": "2026-06-28T23:50:00Z"
}
```

---

## SECTION 15: PRODUCTION TREASURY VALIDATION MATRIX

Before deploying the Treasury, Cash Forecasting & Financial Risk Management Engine to production, verify that the following configurations and controls are in place.

- [ ] **Dual Authorization Enforced**: Maker-checker controls are active across all payment and investment modules, blocking single-user transactions.
- [ ] **Locking Rules Active**: Cleared payment runs and finalized forecasts are verified as read-only, blocking direct line updates or deletions.
- [ ] **Covenant Auditing Verified**: Covenant calculation engines utilize only posted ledger balances, blocking unposted transactional drafts.
- [ ] **Investment maturity limits Confirmed**: Investment verification checks block purchase actions that exceed custodial risk limits.
- [ ] **Physical Sweeps Balanced**: Sweeping validation rules verify that debits equal credits for all processed physical sweeping transfers.
- [ ] **Currency Rates Confirmed**: Valuation engines utilize spot rates for current holdings and forward rates for hedging contracts.
- [ ] **Liquidity Alerts Scheduled**: Daily liquidity evaluations are scheduled, routing alerts if balances fall below minimum buffers.
- [ ] **Audit Trail Capture Active**: System logs record the configuration, exchange rates, and user approvals for all treasury operations.

---
**End of Specification.**
