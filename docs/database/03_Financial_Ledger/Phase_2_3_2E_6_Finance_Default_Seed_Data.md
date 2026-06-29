# JUANET ERP Finance Default Seed Data Specification
## Phase 2.3.2E.6 — Finance Default Seed Data Specification & Tenant Provisioning Manual
**Document Version:** 1.0  
**Author:** Chief Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Database Seed Data, Ledger Templates, and Tenant Provisioning Configuration  

---

## SECTION 1: SEED DATA PHILOSOPHY & TENANT PROVISIONING

In a highly scalable multi-tenant enterprise ERP, the **Finance Subsystem** cannot operate in a vacuum. A newly provisioned tenant (organization) must immediately receive a fully functional, mathematically sound, and regulatory-compliant financial environment. 

The JUANET ERP tenant provisioning architecture employs a structured, template-driven approach to initialize core financial modules, governed by seven engineering principles:

```
                  [TENANT PROVISIONING FLOW]
                  
 ┌────────────────────────────────────────────────────────┐
 │ 1. Tenant Creation Event                               │
 │    - Triggered on organization sign-up.                 │
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 2. Country & Localization Determination               │
 │    - Identify base currency, tax engine, & regulations. │
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 3. Immutable System Seed Injection                      │
 │    - Insert COA, Payment Terms, Calendars, Posting Rules│
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 4. Dynamic Tenant Customization & Overrides            │
 │    - Tenant can add accounts or alter payment terms.   │
 └────────────────────────────────────────────────────────┘
```

1.  **Strict Isolation of System Defaults**: System defaults are defined in global, immutable templates. Tenant-specific seeds are physically written directly to the tenant's database tables (e.g. `public.chart_of_accounts`, `public.tax_rates`) during the provisioning sequence.
2.  **Explicit Tenant Overrides**: Tenants have the autonomy to rename, deactivate, or add custom records to their default financial entities (such as adding custom General Ledger accounts). However, the original system template mappings (such as control account pointers) are preserved using immutable system metadata tags.
3.  **Immutable System Reference Keys**: Seeded entities that are critical to system integrations (such as the default Currency Exchange Gain/Loss account or the Trade Receivables Control account) are marked with `is_system = true`. These records cannot be deleted by tenants.
4.  **Automatic Localization Injection**: The seeding engine dynamically selects the localized seed package based on the tenant's primary country code. This adjusts the base currency, selects the appropriate tax engine (VAT, GST, or US Sales Tax), and provisions the default localized Chart of Accounts.
5.  **Strict Seed Version Control**: Seed configurations are version-controlled. If the system updates its default Chart of Accounts template, active organizations are not disrupted. Updates are applied only to new tenants, while existing tenants are updated via opt-in migration scripts.
6.  **Upgrade Paths & Customization Preservation**: When upgrade scripts modify default tax rates or compliance reports, tenant customizations are preserved. Customizations are tracked using an overrides table, ensuring system upgrades do not overwrite tenant configurations.
7.  **Zero-Configuration Read-to-Bill State**: Provisioning ensures that a tenant can immediately generate invoices, record receipts, process payables, and run trial balances without requiring manual financial setup.

---

## SECTION 2: CANONICAL DEFAULT CHART OF ACCOUNTS (COA)

Every newly provisioned JUANET organization is initialized with an enterprise-grade, GAAP and IFRS compliant Chart of Accounts. This COA uses a structured 6-digit numbering hierarchy that facilitates automated financial grouping and consolidation:

*   **100000 - 199999**: Assets (Current, Non-Current, Control, Suspense)
*   **200000 - 299999**: Liabilities (Current, Non-Current, Control, Clearing)
*   **300000 - 399999**: Equity (Capital, Retained Earnings, Current Earnings)
*   **400000 - 499999**: Revenue (Operating, Non-Operating, Contra-Revenue)
*   **500000 - 599999**: Cost of Goods Sold (COGS) / Cost of Sales (COS)
*   **600000 - 799999**: Operating Expenses (OPEX - Admin, Sales, Payroll, R&D)
*   **800000 - 899999**: Other Income & Expense (Interest, Tax, Foreign Exchange)

### 2.1 Default Accounts Ledger Seeding Schema

| Account Number | Account Name | Normal Balance | Account Type | Is System | Control/Suspense Type | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **101100** | Operating Bank Account | DEBIT | Asset | YES | None | Primary corporate cash clearing. |
| **111100** | Trade Accounts Receivable | DEBIT | Asset | YES | Receivables Control | General subledger control account. |
| **121100** | Prepaid Expenses | DEBIT | Asset | NO | None | Prepayments amortization target. |
| **131100** | Inventory Assets | DEBIT | Asset | YES | Inventory Control | Tracks stock values. |
| **199900** | Bank Reconciliation Clearing | DEBIT | Asset | YES | Bank clearing | Clearing for unpresented checks. |
| **199999** | System Suspense Account | DEBIT | Asset | YES | General Suspense | Catches out-of-balance entries. |
| **211100** | Trade Accounts Payable | CREDIT | Liability | YES | Payables Control | Subledger accounts payable control. |
| **215100** | Sales Tax (VAT/GST) Payable | CREDIT | Liability | YES | Tax Control | Aggregates collected sales tax. |
| **216100** | Payroll Liabilities | CREDIT | Liability | YES | Payroll Control | Employee salaries payable clearing. |
| **221100** | Deferred Revenue | CREDIT | Liability | YES | Deferred Rev Control | Unearned contract revenue. |
| **301100** | Common Stock / Paid-In Capital | CREDIT | Equity | NO | None | Records shareholder investments. |
| **311100** | Retained Earnings | CREDIT | Equity | YES | Retained Earnings | Target for fiscal year-end closures. |
| **321100** | Current Period Earnings | CREDIT | Equity | YES | Current Earnings | Dynamically calculates P&L net. |
| **401100** | Product Sales Revenue | CREDIT | Revenue | NO | None | Primary operating sales revenue. |
| **402100** | Professional Services Revenue | CREDIT | Revenue | NO | None | Service-based contract revenue. |
| **403100** | SaaS Subscription Revenue | CREDIT | Revenue | NO | None | Recurring subscription billing. |
| **499100** | Sales Returns and Allowances | DEBIT | Revenue | YES | Contra-Revenue | Debited on credit note issuances. |
| **501100** | Cost of Goods Sold (COGS) | DEBIT | COGS | NO | None | Direct material/production costs. |
| **502100** | Subcontractor Costs | DEBIT | COGS | NO | None | Direct outsourced labor expenses. |
| **601100** | Salaries and Wages OPEX | DEBIT | Expense | NO | None | General employee salary overhead. |
| **602100** | Office and Admin Expenses | DEBIT | Expense | NO | None | General administrative OPEX. |
| **605100** | Depreciation Expense | DEBIT | Expense | YES | Depreciation | Non-cash fixed asset depreciation. |
| **611100** | IT Hardware & Cloud Compute | DEBIT | Expense | NO | None | Infrastructure hosting costs. |
| **612100** | AI Model Training & Inference | DEBIT | Expense | YES | AI Compute | Metered AI operational usage. |
| **801100** | Interest Income | CREDIT | Other Income| NO | None | Earnings on cash investments. |
| **802100** | Realized Exchange Gain / Loss | DEBIT | Other Expense| YES | FX Realized | Currency conversion variances. |
| **803100** | Unrealized Exchange Gain / Loss| DEBIT | Other Expense| YES | FX Unrealized | Valuation adjustments. |
| **899100** | Income Tax Expense | DEBIT | Other Expense| YES | Tax Expense | Corporate tax provision expenses. |

---

## SECTION 3: DEFAULT FINANCIAL DIMENSIONS

Financial dimensions provide multi-dimensional analysis of transactions without cluttering the Chart of Accounts. Every new tenant is seeded with these eight core dimensions:

```
                            [DIMENSIONAL ANALYSIS]
                            
                       ┌───────────────────────────────┐
                       │      Core Ledger Transaction  │
                       │      (Amount: $10,000.00)     │
                       └───────────────┬───────────────┘
                                       │
     ┌───────────────────┬─────────────┼─────────────┬───────────────────┐
     ▼                   ▼             ▼             ▼                   ▼
 [Department]        [Region]     [Cost Center]   [Product]          [AI Usage]
 (Engineering)       (EMEA)       (R&D Hub)       (SaaS License)     (Model Inference)
```

1.  **Departments (`public.dimensions` - Class: `'department'`)**:
    *   *Seeded Values*: `DEP-100` (Administration), `DEP-200` (Sales & Marketing), `DEP-300` (Engineering / R&D), `DEP-400` (Customer Support), `DEP-500` (Professional Services).
2.  **Projects (`public.dimensions` - Class: `'project'`)**:
    *   *Seeded Values*: `PRJ-DEFAULT` (Non-Project Operations). Dynamically updated as new projects are created.
3.  **Cost Centers (`public.dimensions` - Class: `'cost_center'`)**:
    *   *Seeded Values*: `CC-HQ` (Corporate Headquarters), `CC-RD-US` (US R&D Hub), `CC-RD-EU` (EU R&D Hub), `CC-SALES-AMER` (Americas Sales).
4.  **Business Units (`public.dimensions` - Class: `'business_unit'`)**:
    *   *Seeded Values*: `BU-SaaS` (Software-as-a-Service), `BU-Services` (Professional Advisory Services).
5.  **Regions (`public.dimensions` - Class: `'region'`)**:
    *   *Seeded Values*: `REG-AMER` (Americas), `REG-EMEA` (Europe, Middle East & Africa), `REG-APAC` (Asia-Pacific).
6.  **Channels (`public.dimensions` - Class: `'channel'`)**:
    *   *Seeded Values*: `CHN-Direct` (Direct Enterprise Sales), `CHN-SelfService` (Self-Service Online Portal), `CHN-Partner` (Partner Network & Distributors).
7.  **Products (`public.dimensions` - Class: `'product'`)**:
    *   *Seeded Values*: `PROD-SaaS-Enterprise` (Enterprise Subscription), `PROD-Consulting-Hour` (Standard Consulting Hours).
8.  **AI Usage (`public.dimensions` - Class: `'ai_usage'`)**:
    *   *Seeded Values*: `AI-Inference` (General Model Inference), `AI-FineTuning` (Fine-Tuning Runs), `AI-VectorDB` (Semantic Search Vector Storage).

---

## SECTION 4: DEFAULT TAX CONFIGURATIONS

The tax engine is initialized based on the tenant's primary country code, provisioning standard rates and tax accounts to ensure compliant billing.

### 4.1 System Seed Tax Code Parameters

| Tax Code ID | Localization Target | Display Name | Applied Tax Rate | Tax Account Number | Tax Type Classification | Processing Logic Rules |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **TAX-US-EX**| United States | Exempt Sales | 0.0000 % | 215100 | Exempt | No tax calculated. |
| **TAX-US-ST**| United States | General State Tax | 6.2500 % | 215100 | Standard | Applied directly to products. |
| **TAX-EU-ST**| European Union | Standard VAT | 20.0000 % | 215100 | Value Added Tax (VAT) | Tax-inclusive/exclusive support. |
| **TAX-EU-ZR**| European Union | Zero-Rated VAT | 0.0000 % | 215100 | Zero-Rated | Internal cross-border shipping.|
| **TAX-EU-RC**| European Union | Reverse Charge VAT | 0.0000 % | 215100 | Reverse Charge | Postings balanced across accounts. |
| **TAX-GB-ST**| United Kingdom | Standard VAT UK | 20.0000 % | 215100 | Value Added Tax (VAT) | UK HMRC compliant tax. |
| **TAX-CA-ST**| Canada | Standard GST/HST | 13.0000 % | 215100 | GST/HST | Dual-tier tax processing. |
| **TAX-WHT-10**| Global | Withholding Tax 10%| 10.0000 % | 215100 | Withholding | Deduced at payment source. |

---

## SECTION 5: DEFAULT LEDGER POSTING RULES

Posting rules define how operational actions are converted into double-entry ledger postings. This automation ensures consistency and keeps operational teams focused on their workflows instead of accounting mechanics.

```
                    [AUTO-POSTING JOURNAL GENERATOR]
                    
Operational Event:                    Corresponding Double-Entry Posting:
┌────────────────────────┐            ┌────────────────────────────────────┐
│ Invoice Issued         ├───────────►│ DEBIT: Accounts Receivable (111100)│
│                        │            │ CREDIT: Product Revenue    (403100)│
└────────────────────────┘            └────────────────────────────────────┘
```

*   **Invoice Issuance Posting Rules**:
    *   **Debit**: Accounts Receivable Control Account (`111100`) — $100.00 % of gross invoice value.
    *   **Credit**: Configured Revenue Account (e.g. `403100` SaaS Revenue) — $100.00 % of net invoice value.
    *   **Credit**: Sales Tax Payable Control Account (`215100`) — $100.00 % of tax amount.
*   **Customer Payment Processing Rules**:
    *   **Debit**: Operating Bank Account (`101100`) — $100.00 % of received cash value.
    *   **Credit**: Accounts Receivable Control Account (`111100`) — $100.00 % of cleared value.
*   **Vendor Bill Posting Rules**:
    *   **Debit**: Target Expense Account (e.g. `602100` Admin Expenses) — $100.00 % of net expense value.
    *   **Credit**: Accounts Payable Control Account (`211100`) — $100.00 % of gross bill value.
*   **Vendor Payment Processing Rules**:
    *   **Debit**: Accounts Payable Control Account (`211100`) — $100.00 % of cleared payment value.
    *   **Credit**: Operating Bank Account (`101100`) — $100.00 % of cash paid out.
*   **Sales Credit Note Posting Rules**:
    *   **Debit**: Sales Returns and Allowances Contra-Revenue (`499100`) — $100.00 % of net credit value.
    *   **Debit**: Sales Tax Payable Control Account (`215100`) — $100.00 % of credited tax value.
    *   **Credit**: Accounts Receivable Control Account (`111100`) — $100.00 % of total credit value.
*   **Payroll Accrual Posting Rules**:
    *   **Debit**: Salaries and Wages OPEX Expense (`601100`) — Gross salaries total.
    *   **Credit**: Payroll Liabilities Control Account (`216100`) — Net payroll liability.
*   **Fixed Asset Depreciation Posting Rules**:
    *   **Debit**: Depreciation Expense (`605100`) — Period depreciation value.
    *   **Credit**: Accumulated Depreciation Asset Account — Contra-asset balance reduction.
*   **Deferred Revenue Posting Rules**:
    *   **Debit**: Accounts Receivable Control Account (`111100`) — Gross value of multi-period invoice.
    *   **Credit**: Deferred Revenue Liability Account (`221100`) — Net unearned value.
*   **Deferred Revenue Amortization Posting Rules**:
    *   **Debit**: Deferred Revenue Liability Account (`221100`) — Monthly earned portion.
    *   **Credit**: SaaS Subscription Revenue Account (`403100`) — Monthly earned portion.
*   **Bank Admin Fees Posting Rules**:
    *   **Debit**: Office and Admin Expenses OPEX (`602100`) — Bank transaction charges.
    *   **Credit**: Operating Bank Account (`101100`) — Cash deducted by bank.
*   **Realized Foreign Exchange Conversion Posting Rules**:
    *   **Debit/Credit**: Realized Exchange Gain / Loss Account (`802100`) — Variance from rate changes.
    *   **Credit/Debit**: Operating Bank Account (`101100`) — Exchange difference adjustment.
*   **Bad Debt Write-Off Posting Rules**:
    *   **Debit**: Office and Admin Expenses OPEX (`602100`) — Uncollectible invoice amount.
    *   **Credit**: Accounts Receivable Control Account (`111100`) — Clears the outstanding invoice.

---

## SECTION 6: DEFAULT PAYMENT TERMS

Payment terms define invoice due dates and cash discount parameters. Every tenant is seeded with these standard payment profiles:

*   **COD (Immediate Payment)**:
    *   *Properties*: Due Days: `0`, Cash Discount: `0.00%`, Penalty Rate: `0.00%`.
*   **Net-7**:
    *   *Properties*: Due Days: `7`, Cash Discount: `0.00%`, Penalty Rate: `0.00%`.
*   **Net-14**:
    *   *Properties*: Due Days: `14`, Cash Discount: `0.00%`, Penalty Rate: `0.00%`.
*   **Net-30 (Standard Corporate Default)**:
    *   *Properties*: Due Days: `30`, Cash Discount: `0.00%`, Penalty Rate: `0.00%`.
*   **Net-60**:
    *   *Properties*: Due Days: `60`, Cash Discount: `0.00%`, Penalty Rate: `0.00%`.
*   **2/10 Net-30 (Cash Discount Option)**:
    *   *Properties*: Due Days: `30`, Cash Discount: `2.00%` if settled within `10` days.

---

## SECTION 7: DEFAULT FISCAL CALENDARS

The fiscal calendar manages accounting periods and ensures postings are made to active, open periods.

```
                         [STANDARD MONTHLY CALENDAR]
                         
        [ Jan ] [ Feb ] [ Mar ] [ Apr ] [ May ] [ Jun ] ... [ Dec ]
        Period-1 Period-2 Period-3 Period-4 Period-5 Period-6 ... Period-12
```

1.  **Standard Calendar Year (January - December)**:
    *   *Structure*: 12 monthly periods matching the calendar year. This is the default calendar provisioned for new organizations.
2.  **Standard US Fiscal Year (October - September)**:
    *   *Structure*: 12 monthly periods starting October 1st. Designed for US organizations aligned with government fiscal cycles.
3.  **UK Fiscal Year (April - March)**:
    *   *Structure*: 12 monthly periods starting April 1st, aligning with UK tax reporting timelines.
4.  **4-4-5 Retail Calendar**:
    *   *Structure*: Structured into repeating 4-week, 4-week, and 5-week periods to ensure payroll and billing cycles map consistently to days of the week.

---

## SECTION 8: DEFAULT CANONICAL FINANCIAL REPORTS

Every newly created tenant is provisioned with ten standardized financial reports, allowing users to run audits and check corporate balances immediately.

### 8.1 Reporting Catalog & Query Rules

| Report Identifier | Output Type | Primary Query Method / Logic | Core Target Filters | Key Mathematical Outputs |
| :--- | :--- | :--- | :--- | :--- |
| **REP-TB** | Trial Balance | Aggregate balances for active ledger accounts, verifying debits equal credits. | Current Period, Active Tenant | `Sum(Debits)`, `Sum(Credits)` |
| **REP-BS** | Balance Sheet | Evaluates corporate assets, liabilities, and equity to reflect financial health. | Year-to-Date Period | `Total Assets`, `Total Liabilities` |
| **REP-IS** | Income Statement | Calculates profitability by subtracting cost of sales and expenses from revenue. | Date Range Interval | `Gross Margin`, `Net Income` |
| **REP-CF** | Cash Flow Report | Traces cash movements across operating, investing, and financing activities. | Date Range Interval | `Net Cash Balance Change` |
| **REP-BVA** | Budget vs Actual | Compares actual transaction values with budgeted targets to track variance. | Cost Center, Fiscal Period| `Budgeted`, `Actual`, `Variance` |
| **REP-AR** | Aged Receivables | Categorizes open customer invoices into aging buckets (e.g. 30, 60, 90+ days). | Current Date Snapshot | `Aged Customer Balances` |
| **REP-AP** | Aged Payables | Categorizes open vendor bills into aging buckets to manage cash outflows. | Current Date Snapshot | `Aged Vendor Liabilities` |
| **REP-GL** | General Ledger | Renders a detailed audit trail of all transactions posted to accounts. | Date Range, Account Number| `Transaction Lines Audit Trail` |
| **REP-JR** | Journal Report | Lists posted journal entry headers and lines in sequential order. | Fiscal Period | `Journal Entry Postings` |
| **REP-TAX** | Tax Return Report | Aggregates collected and paid sales taxes by tax code for tax filings. | Tax Period Interval | `Gross Sales`, `Net Tax Due` |

---

## SECTION 9: DEFAULT TREASURY & RISK CONFIGURATIONS

The treasury and risk modules are initialized with conservative settings to safeguard liquidity and ensure proper approval controls from day one.

### 9.1 Default Liquidity Thresholds
*   **Operating cash reserve target**: Provisoned to cover a minimum of 45 days of operating expenses, calculated from active ledger averages.
*   **Minimum balance warning threshold**: Set to alert the treasurer if available cash falls below 15 days of operating expenses.

### 9.2 Default Risk Exposure Limits
*   **Single-bank holding limit**: Set to a maximum of 40% of total group cash to mitigate banking partner risk.
*   **Unhedged FX exposure limit**: Triggers warning workflows if unhedged foreign currency exposure exceeds $150,000.00 USD.

### 9.3 Default Financial Approval Limits (Maker-Checker Limits)

```
                            [APPROVAL GATEWAYS]
                            
 Payment Value:
 ┌────────────────────────┐
 │ $0.00 - $9,999.99      ├───────────► Auto-Executed (Single Sign-off)
 └────────────────────────┘
 ┌────────────────────────┐
 │ $10,000.00 - $49,999.99├───────────► Requires Treasury Administrator Approval
 └────────────────────────┘
 ┌────────────────────────┐
 │ $50,000.00+            ├───────────► Requires CFO Double Sign-off
 └────────────────────────┘
```

*   **Level 1 (Junior Accountant / Maker)**: Limit: $10,000.00 USD. Can prepare and initiate transactions but requires approval for execution.
*   **Level 2 (Treasury Administrator / Checker)**: Limit: $50,000.00 USD. Authorized to approve transactions within limit.
*   **Level 3 (Chief Financial Officer / CFO Overlord)**: Limit: Unlimited. Requires dual-checker authorization (four-eyes principle) for all transactions exceeding $100,000.00 USD.

---

## SECTION 10: COUNTRY LOCALIZATION MATRIX

Financial configurations are adapted based on the tenant's primary tax jurisdiction, aligning base currencies and regional formatting.

| Country Code (ISO) | Base Currency | Primary Tax Type | Default Tax Code | System Language | Regional Date / Number Formatting |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **US** | USD | Sales Tax | TAX-US-ST | English | `MM/DD/YYYY` / `1,234,567.89` |
| **GB** | GBP | VAT | TAX-GB-ST | English | `DD/MM/YYYY` / `1,234,567.89` |
| **DE** | EUR | VAT (MwSt) | TAX-EU-ST | German | `DD.MM.YYYY` / `1.234.567,89` |
| **FR** | EUR | VAT (TVA) | TAX-EU-ST | French | `DD/MM/YYYY` / `1 234 567,89` |
| **CA** | CAD | GST/HST | TAX-CA-ST | English | `YYYY-MM-DD` / `1,234,567.89` |
| **AU** | AUD | GST | TAX-CA-ST | English | `DD/MM/YYYY` / `1,234,567.89` |
| **SG** | SGD | GST | TAX-CA-ST | English | `DD/MM/YYYY` / `1,234,567.89` |
| **AE** | AED | VAT | TAX-CA-ST | Arabic/English| `DD/MM/YYYY` / `1,234,567.89` |

---

## SECTION 11: SEED DATA UPGRADE & MIGRATION POLICY

To keep the platform current as tax regulations and reporting requirements change, the seeding engine supports versioned upgrades that preserve tenant-specific customizations.

```
                    [SAFE SEED UPGRADE WORKFLOW]
                    
   ┌────────────────────────────────────────────────────────┐
   │ 1. Read Tenant Customization Schema                    │
   │    - Identify user-altered tax codes or account names. │
   └───────────────────────────┬────────────────────────────┘
                               │
                               ▼
   ┌────────────────────────────────────────────────────────┐
   │ 2. Apply Non-Destructive Injections                    │
   │    - Insert new compliance accounts or reporting tags.  │
   └───────────────────────────┬────────────────────────────┘
                               │
                               ▼
   ┌────────────────────────────────────────────────────────┐
   │ 3. Log Legacy Configuration Snapshot                   │
   │    - Store historical rates in audit logs for trace.   │
   └────────────────────────────────────────────────────────┘
```

1.  **Seed Version Tagging**: Every record injected during tenant provisioning is tagged with a `seed_version` number (e.g. `seed_version = '1.0.0'`). This allows migration scripts to identify the origin version of any default financial configuration.
2.  **No Overwriting Tenant Customizations**: If a tenant has modified a seeded record (e.g. changing the default name of account `602100` from "Office Expenses" to "HQ Office Supplies"), upgrade scripts must **never** overwrite these changes. This is enforced by verifying `is_customized = true` on the target record before applying updates.
3.  **Non-Destructive Compliance Upgrades**: When tax rates or reporting standards change, the update engine applies changes by creating new records rather than modifying active ones in place. Legacy transactions continue using the historical settings, while new transactions use the updated configuration.
4.  **Rollback Safeguards**: Before applying an upgrade script to a tenant's database, the migration engine takes a snapshot of the current configuration. This allows the system to rollback changes instantly if an upgrade fails, preventing downtime or data corruption.

---

## SECTION 12: SYSTEM VALIDATION MATRIX

The validation matrix defines the rules used to verify that every newly provisioned tenant receives a complete, internally consistent, and ready-to-use financial configuration.

| Validation Rule ID | Target Module | Check Condition | Error Mitigation Action |
| :--- | :--- | :--- | :--- |
| `VAL-SEED-001` | Chart of Accounts | Verify that the Trial Balance is perfectly balanced (Total Debits = Total Credits) upon initialization. | Block tenant creation, abort the transaction, and alert the provisioning system. |
| `VAL-SEED-002` | Tax Configurations | Verify that every tax code is mapped to an active, valid General Ledger account in the COA. | Prevent the upgrade run and prompt the administrator to correct the account map. |
| `VAL-SEED-003` | Posting Rules | Ensure that every auto-posting rule balance debit matches credit percentage targets (100% total).| Reject the posting rule configuration, blocking any associated transaction. |
| `VAL-SEED-004` | Fiscal Calendar | Verify that the seeded fiscal calendar includes exactly 12 active, non-overlapping periods. | Correct the calendar dates using UTC standards automatically, logging the fix. |
| `VAL-SEED-005` | Control Accounts | Confirm that accounts tagged as subledger controls (e.g., Accounts Receivable) do not allow manual postings. | Block any manual journal entries targeted directly to control accounts, forcing subledger posting. |
| `VAL-SEED-006` | Multi-Tenant Safety| Ensure that all seeded financial records have a valid, unique `organization_id` value. | Throw a database isolation violation error, rolling back the provisioning transaction. |
| `VAL-SEED-007` | Localization Alignment| Verify that the base currency of the Chart of Accounts matches the primary currency of the country. | Correct the base currency setting based on the localization matrix automatically. |
| `VAL-SEED-008` | Version Lockout | Confirm that seeded templates marked as `is_system = true` cannot be deleted by tenants. | Throw a deletion permission error, blocking the action and logging the attempt. |

---

## SECTION 13: END-TO-END VERIFICATION PLAN

To ensure the reliability of the tenant provisioning system, development teams must execute and pass the following test suites:

### 13.1 Balanced Initialization Test
*   **Objective**: Confirm that new organizations are provisioned with perfectly balanced financial ledger actuals.
*   **Test Action**: Simulate the creation of a new UK-based organization and run an initial Trial Balance report.
*   **Expected Outcome**: The report is generated successfully, and the sum of all debits exactly equals the sum of all credits, indicating a balanced ledger.

### 13.2 Customization Preservation Test
*   **Objective**: Verify that system upgrades do not overwrite user customizations.
*   **Test Action**: Customize the name of the default OPEX Salaries account (`601100`) inside a test tenant, run a system seed update simulation, and verify the account name.
*   **Expected Outcome**: The seed upgrade runs successfully, and the custom account name is preserved, demonstrating that user changes are not overwritten.

### 13.3 Localization Mapping Verification Test
*   **Objective**: Verify that localization configurations are applied correctly based on country codes.
*   **Test Action**: Trigger the provisioning sequence for a new Germany-based organization and verify the base currency, tax settings, and language configuration.
*   **Expected Outcome**: The tenant is initialized with EUR as its base currency, VAT configured as its primary tax type, and MwSt standard rates mapped to tax ledger accounts.
