# JUANET ERP Ledger Configuration & Chart of Accounts Specification
## Phase 2.3.2E.1 — Chart of Accounts Architecture Manual
**Document Version:** 1.0  
**Author:** Chief Enterprise ERP Financial Systems Architect, JUANET Platform  
**Classification:** Technical Specification / Accounting Engine Configuration  

---

## SECTION 1: ACCOUNTING PHILOSOPHY & GENERAL PRINICIPLES

The Chart of Accounts (COA) within the JUANET SaaS Platform serves as the core semantic structure for all business and financial operations. This document specifies the architectural rules, category taxonomies, control invariants, multi-currency engines, and lifecycle state machines governing ledger accounts.

### 1.1 Double-Entry Bookkeeping
JUANET enforces strict, non-negotiable double-entry accounting at the database engine level. Every accounting event is represented as a single `journal_entry` comprising two or more `ledger_entry` records. The system enforces the fundamental accounting equation:
$$\text{Assets} = \text{Liabilities} + \text{Equity}$$
For any given transaction boundary:
$$\sum \text{Debits} - \sum \text{Credits} = 0$$
This balance is checked on every database transaction. Any attempt to commit an out-of-balance transaction results in a database-level rollback.

### 1.2 GAAP and IFRS Compliance
The accounting engine is architected to be fully compliant with Generally Accepted Accounting Principles (GAAP) and International Financial Reporting Standards (IFRS):
*   **Asset Valuation and Depreciation**: Supports straight-line, declining balance, and units-of-production depreciation structures mapped directly to accumulated depreciation control sub-accounts.
*   **Revenue Recognition (ASC 606 / IFRS 15)**: Supports multi-step revenue recognition criteria via deferred revenue control schedules. Subscriptions are posted to Deferred Revenue (Liability) upon invoicing and recognized incrementally over time to Subscription Revenue (Income).
*   **Expense Recognition (Matching Principle)**: Expenses are recognized in the period they occur, regardless of cash flow timing, utilizing accrual liabilities and amortization assets.

### 1.3 Accrual vs. Cash Accounting
JUANET supports dual-representation reporting. Although the default state is modern accrual-based accounting, the database schema allows for cash-basis statements by evaluating payment allocation logs (`public.receivable_allocations` and `public.payable_allocations` in Section 7 of `Phase_2_3_2E_Finance_Physical_Tables.md`) instead of raw invoices and bills.

### 1.4 Ledger Immutability & SOC-2 Audit Invariance
Once a transaction is finalized and its parent `journal_entries` record status transitions to `'posted'`, it is physically and logically immutable.
*   **No Modifications**: Database triggers prevent `UPDATE` operations on posted rows.
*   **No Deletions**: Database triggers block `DELETE` operations on posted rows.
*   **Reversing Entries**: All corrections must be achieved using reversing entries (Credit Notes, Debit Notes, or adjustment Journal Entries).
*   **Audit Trail Logs**: All modifications to draft accounts, system configuration switches, and status modifications are tracked in a secure, append-only history log.

### 1.5 Multi-Tenant & Multi-Currency Foundations
*   **Multi-Tenancy**: Every ledger entity is partitioned using the mandatory `organization_id` column. Row-Level Security (RLS) is applied to isolate tenant accounts completely.
*   **Multi-Currency (ASC 830 / IAS 21)**: Every tenant is assigned a "Base Reporting Currency" (e.g., USD). Individual ledger accounts can be designated as "Foreign Currency Accounts" (e.g., a EUR bank account). All postings maintain both the transaction currency value and the calculated base reporting currency value captured at the moment of posting.

---

## SECTION 2: ACCOUNT CATEGORY HIERARCHY

All accounts within the Chart of Accounts belong to a designated financial category, which determines its classification on the financial statements, its normal balance side, and its mechanical behavior during operations.

```
                                  [Chart of Accounts Hierarchy]
                                                |
        +-----------------------+---------------+---------------+-----------------------+
        |                       |                               |                       |
     Assets                Liabilities                       Equity                  Income                  Expenses
        |                       |                               |                       |                       |
  +-----+-----+           +-----+-----+                   +-----+-----+           +-----+-----+           +-----+-----+
  |           |           |           |                   |           |           |           |           |           |
Current     Fixed      Current    Long-Term             Owner    Retained       Sales   Deferred       COGS     Operating
  |           |           |           |                Equity    Earnings       |           |           |           |
  |-Cash      |-Property  |-Payable   |-Bonds                     (Reserve)     |-Service   |-Subs      |-Stock  |-Payroll
  |-Bank      |-Equip     |-Tax       |-Loans                                   |-Product   |-Prepaid   |-Freight|-Rent
  |-Receiv.   |-AccumDepr |-Payroll                                                                              |-Util.
  |-Inventory             |-Deferred                                                                             |-Amort.
```

### 2.1 Assets

#### 2.1.1 Current Assets
*   **Purpose**: Highly liquid assets expected to be converted to cash, sold, or consumed within one operating cycle (typically 12 months).
*   **Normal Balance**: Debit.
*   **Posting Rules**: Increases recorded as Debits; decreases recorded as Credits.
*   **Manual Posting Allowed**: Yes, for custom assets. No, for restricted control accounts (e.g., Accounts Receivable).
*   **System Generated**: Standard accounts (e.g., Undeposited Funds, Accounts Receivable) are generated during tenant initialization.
*   **Reporting Behavior**: Included in the Asset section of the Balance Sheet.

##### 2.1.1.1 Cash
*   **Sub-Category Purpose**: Represents physical cash registers, cash drawers, and on-premises physical cash equivalents.
*   **Reconciliation Required**: Highly encouraged; subject to physical count audits.

##### 2.1.1.2 Bank
*   **Sub-Category Purpose**: Represents commercial checking, savings, or merchant holding accounts.
*   **Reconciliation Required**: Yes, subject to monthly bank statement import and matching.

##### 2.1.1.3 Petty Cash
*   **Sub-Category Purpose**: Minor physical cash reserves maintained for day-to-day office incidentals.
*   **Reconciliation Required**: Yes, utilizing the imprest system.

##### 2.1.1.4 Accounts Receivable
*   **Sub-Category Purpose**: Tracks outstanding customer invoice balances.
*   **Reconciliation Required**: Reconciled continuously with CRM and customer outstanding aging logs.
*   **Manual Posting Allowed**: **NO**. Must only be written by system-controlled invoicing and customer cash receipt workflows.

##### 2.1.1.5 Inventory
*   **Sub-Category Purpose**: Represents raw materials, work-in-progress, and finished goods held for resale.
*   **Reconciliation Required**: Reconciled with physical stock valuations.

#### 2.1.2 Fixed Assets
*   **Purpose**: Long-term tangible physical assets used in operations (e.g., buildings, machinery, computing hardware, vehicles).
*   **Normal Balance**: Debit.
*   **Posting Rules**: Increases recorded as Debits; decreases recorded as Credits.
*   **Manual Posting Allowed**: Yes.
*   **System Generated**: No.
*   **Reporting Behavior**: Shown in the Fixed Asset section of the Balance Sheet.

##### 2.1.2.1 Accumulated Depreciation
*   **Sub-Category Purpose**: Contra-asset account tracking the aggregate depreciation taken on fixed assets over time.
*   **Normal Balance**: Credit (Contra-Asset).
*   **Posting Rules**: Decreases in net book value recorded as Credits; adjustments/write-offs recorded as Debits.
*   **Manual Posting Allowed**: No; managed by system depreciation routines.

---

### 2.2 Liabilities

#### 2.2.1 Current Liabilities
*   **Purpose**: Financial obligations expected to be settled within 12 months using current assets.
*   **Normal Balance**: Credit.
*   **Posting Rules**: Increases recorded as Credits; decreases recorded as Debits.
*   **Manual Posting Allowed**: Yes, except for restricted control accounts (Accounts Payable, Taxes Payable).
*   **System Generated**: Generated on tenant onboarding.
*   **Reporting Behavior**: Liability section of the Balance Sheet.

##### 2.2.1.1 Accounts Payable
*   **Sub-Category Purpose**: Tracks outstanding supplier bills and operational debts.
*   **Manual Posting Allowed**: **NO**. Written exclusively via Vendor Bills and Vendor Payments workflows.

##### 2.2.1.2 Taxes Payable
*   **Sub-Category Purpose**: Tracks sales taxes, VAT, corporate taxes, and payroll taxes collected but not yet remitted to regulatory bodies.
*   **Manual Posting Allowed**: **NO**. Posted via transactional tax calculators and tax filing settlement steps.

##### 2.2.1.3 Payroll Liabilities
*   **Sub-Category Purpose**: Accrued employee wages, bonuses, and statutory deductions.
*   **Manual Posting Allowed**: Yes, via payroll journal entries.

##### 2.2.1.4 Deferred Revenue
*   **Sub-Category Purpose**: Represents prepayments received for services or subscriptions to be delivered in future periods.
*   **Manual Posting Allowed**: **NO**. Written by the invoicing engine and reversed by the automated Revenue Recognition schedule processor.

#### 2.2.2 Long-Term Liabilities
*   **Purpose**: Obligations due beyond 12 months (e.g., bank loans, issued corporate bonds).
*   **Normal Balance**: Credit.
*   **Manual Posting Allowed**: Yes.

---

### 2.3 Equity

#### 2.3.1 Owner Equity
*   **Purpose**: Direct investments made by business owners or shareholders (e.g., Common Stock, Paid-in Capital).
*   **Normal Balance**: Credit.
*   **Posting Rules**: Increases recorded as Credits; decreases recorded as Debits.
*   **Manual Posting Allowed**: Yes.
*   **Reporting Behavior**: Shown in the Equity section of the Balance Sheet.

#### 2.3.2 Retained Earnings
*   **Purpose**: Cumulative net income retained in the business rather than distributed to shareholders.
*   **Normal Balance**: Credit.
*   **Posting Rules**: Closed balances from income statements are moved here at year-end.
*   **Manual Posting Allowed**: **NO**. Written exclusively during Year-End Close routines.

---

### 2.4 Income (Revenue)

#### 2.4.1 Sales (Product Revenue)
*   **Purpose**: Revenue from selling physical goods or product licenses.
*   **Normal Balance**: Credit.
*   **Posting Rules**: Sales recorded as Credits; returns/allowances recorded as Debits.
*   **Reporting Behavior**: Displayed in the Revenue section of the Profit & Loss statement.

#### 2.4.2 Professional Services
*   **Purpose**: Hourly, fixed-bid, or milestone-based professional service revenue.
*   **Normal Balance**: Credit.

#### 2.4.3 Subscriptions (Recurring Revenue)
*   **Purpose**: SaaS subscription revenue recognized per month or year.
*   **Normal Balance**: Credit.

#### 2.4.4 Other Revenue
*   **Purpose**: Ancillary revenue streams (e.g., interest earned, asset sales, foreign exchange gains).
*   **Normal Balance**: Credit.

---

### 2.5 Expenses

#### 2.5.1 Cost of Goods Sold (COGS)
*   **Purpose**: Direct costs attributable to the production or acquisition of goods sold or services delivered (e.g., direct materials, hosting costs for SaaS platform operations).
*   **Normal Balance**: Debit.
*   **Posting Rules**: Increases recorded as Debits; decreases (returns) recorded as Credits.
*   **Reporting Behavior**: Appears immediately below Revenue to compute Gross Profit on the P&L.

#### 2.5.2 Operating Expenses
*   **Purpose**: General, administrative, selling, and R&D costs incurred to run the business (e.g., payroll, rent, utilities, travel).
*   **Normal Balance**: Debit.
*   **Reporting Behavior**: Appears in the Operating Expenses section of the Profit & Loss statement.

##### 2.5.2.1 Payroll Expense
*   **Sub-Category Purpose**: Gross wages, employer-paid payroll taxes, and benefits.

##### 2.5.2.2 Utilities Expense
*   **Sub-Category Purpose**: Electricity, internet, software licenses, communications.

##### 2.5.2.3 Rent Expense
*   **Sub-Category Purpose**: Commercial office leases and workspace costs.

##### 2.5.2.4 Marketing Expense
*   **Sub-Category Purpose**: Customer acquisition, advertising, PR campaigns.

##### 2.5.2.5 Travel & Entertainment Expense
*   **Sub-Category Purpose**: Corporate travel, meals, accommodations.

##### 2.5.2.6 Depreciation Expense
*   **Sub-Category Purpose**: Monthly or annual physical asset depreciation allocation.

##### 2.5.2.7 Interest Expense
*   **Sub-Category Purpose**: Cost of servicing debt (loans, bonds).

---

## SECTION 3: CHART OF ACCOUNTS STRUCTURE

Every ledger account record must include columns tracking classification, restriction rules, currency parameters, system lock flags, and operational metadata. Below is the definitive columns catalog for the `chart_of_accounts` entity.

### 3.1 Column Specifications

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Architectural & Business Reason |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Universally unique row identifier. |
| `organization_id` | `uuid` | NO | None | RLS multi-tenant bounding key. |
| `category_id` | `uuid` | NO | None | Maps account to classification hierarchy rules. |
| `parent_account_id` | `uuid` | YES | `NULL` | Links account to parent for nested tree rollup structure. |
| `account_number` | `varchar(30)` | NO | None | Numeric/Alphanumeric accounting sorting code (e.g., '1200'). |
| `name` | `varchar(150)` | NO | None | Human-readable account descriptor (e.g., 'Accounts Receivable'). |
| `description` | `text` | YES | `NULL` | Comprehensive scope notes regarding account posting rules. |
| `currency_id` | `uuid` | NO | None | Standard currency binding. Posts are translated using this key. |
| `allow_manual_entries`| `boolean` | NO | `true` | When `false`, only system sub-ledger modules can write entries. |
| `allow_reconciliation` | `boolean` | NO | `false` | Enables matching of bank statement files to local entries. |
| `allow_budgeting` | `boolean` | NO | `true` | Allows account inclusion in corporate financial budgets. |
| `is_control_account` | `boolean` | NO | `false` | System flag locking manual journals. Forces sub-ledger routing. |
| `is_system_account` | `boolean` | NO | `false` | System protected. Cannot be renamed, deleted, or archived. |
| `is_bank_account` | `boolean` | NO | `false` | Signals requirement for balance tracking and feed sync. |
| `is_tax_account` | `boolean` | NO | `false` | Binds account to physical tax jurisdiction code routing. |
| `is_active` | `boolean` | NO | `true` | Master toggle. Suspends postings when `false`. |
| `created_at` | `timestamptz` | NO | `now()` | Standard system timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | Standard system timestamp. |
| `deleted_at` | `timestamptz` | YES | `NULL` | Log-marking deletion point for audit history tracking. |
| `created_by` | `uuid` | YES | `NULL` | Traceability reference to the initializing user. |
| `updated_by` | `uuid` | YES | `NULL` | Traceability reference to the mutating user. |
| `version` | `integer` | NO | `1` | Optimistic concurrency engine version. |

---

### 3.2 Relational Integrity & Check Constraints

To guarantee structural safety, the following check constraints must be declared on the `chart_of_accounts` physical entity:

```sql
-- 1. Prevent accounts from referencing themselves as a parent
CONSTRAINT coa_prevent_self_hierarchy CHECK (parent_account_id <> id)

-- 2. Validate standard alphanumeric format for account numbers
CONSTRAINT coa_account_number_format CHECK (account_number ~ '^[A-Z0-9\-]{3,30}$')

-- 3. Require account names to be of meaningful length
CONSTRAINT coa_name_length CHECK (length(trim(name)) >= 3)

-- 4. Ensure control accounts cannot accept manual entries
CONSTRAINT coa_control_no_manual CHECK (
  NOT (is_control_account = true AND allow_manual_entries = true)
)

-- 5. Force system accounts to remain active
CONSTRAINT coa_system_always_active CHECK (
  NOT (is_system_account = true AND is_active = false)
)
```

---

## SECTION 4: ACCOUNT NUMBERING STANDARD

A strict, logical numbering layout guarantees that users can navigate financial statements efficiently. While tenants are allowed to customize local account numbers within bounds, the system initializes using a standardized layout.

```
                                [ACCOUNT NUMBERING RANGE ALLOCATIONS]
                                
   1000 - 1999              2000 - 2999              3000 - 3999              4000 - 4999              5000 - 9999
  [ ASSETS ]              [ LIABILITIES ]            [ EQUITY ]              [ REVENUE ]              [ EXPENSES ]
       |                        |                        |                        |                        |
       +- 1100 Cash             +- 2100 Payables         +- 3100 Capital          +- 4100 Product          +- 5000 COGS
       +- 1110 Main Bank        +- 2200 Taxes            +- 3200 Earnings         +- 4200 Services         +- 6000 Payroll
       +- 1200 Rec.             +- 2300 Payroll                                   +- 4300 Subscriptions    +- 7000 Admin
```

### 4.1 Enterprise Range Allocations

| Account Range | Category | Sub-Category Mapping | Examples |
| :--- | :--- | :--- | :--- |
| **`1000 - 1999`** | **Assets** | - | - |
| `1000 - 1099` | Assets | Cash & Equivalents | Cash Drawers, Petty Cash |
| `1100 - 1199` | Assets | Bank Checking & Savings | Main Commercial Checking, Payroll Savings |
| `1200 - 1299` | Assets | Receivables Ledger | Accounts Receivable Control |
| `1300 - 1399` | Assets | Inventory Ledger | Raw Materials, Finished Goods |
| `1400 - 1499` | Assets | Prepaid Expenses | Prepaid Hosting, Prepaid Rent |
| `1500 - 1899` | Assets | Fixed Assets | Machinery, Office Furniture |
| `1900 - 1999` | Assets | Contra-Assets | Accumulated Depreciation, Allowance for Bad Debt |
| **`2000 - 2999`** | **Liabilities** | - | - |
| `2000 - 2099` | Liabilities | Short-Term Borrowings | Corporate Credit Cards |
| `2100 - 2199` | Liabilities | Payables Ledger | Accounts Payable Control |
| `2200 - 2299` | Liabilities | Sales & Corporate Taxes | VAT Collected, Income Tax Payable |
| `2300 - 2399` | Liabilities | Payroll & Accruals | Accrued Wages, Health Insurance Accrual |
| `2400 - 2499` | Liabilities | Unearned Revenue | Deferred Subscription Revenue |
| `2500 - 2999` | Liabilities | Long-Term Obligations | Commercial Property Mortgages |
| **`3000 - 3999`** | **Equity** | - | - |
| `3000 - 3099` | Equity | Paid-In Capital | Common Stock, Retained Capital |
| `3100 - 3199` | Equity | Corporate Reserves | Treasury Stock |
| `3200 - 3299` | Equity | System Retained Earnings | Retained Earnings, Opening Balance Equity |
| **`4000 - 4999`** | **Revenue** | - | - |
| `4000 - 4099` | Revenue | Product Sales | Hardware Sales, License Sales |
| `4100 - 4199` | Revenue | Services Income | Implementation Consulting, Auditing |
| `4200 - 4299` | Revenue | SaaS Subscription Revenue | Professional Tier monthly billing |
| `4300 - 4999` | Revenue | Ancillary Revenue | Interest Income, Realized Gain on Forex |
| **`5000 - 5999`** | **COGS** | Direct Delivery Expenses | Hosting Bandwidth, Subcontractor Costs |
| **`6000 - 9999`** | **Expenses** | - | - |
| `6000 - 6199` | Expenses | Staffing Expenses | Sales Commissions, Executive Payroll |
| `6200 - 6299` | Expenses | Operational Overhead | Office Rent, Office Supplies |
| `6300 - 6399` | Expenses | Utilities & Tools | AWS Bill, CRM License Cost |
| `6400 - 6499` | Expenses | Marketing & Sales | Google Ads Expense, Conference Travel |
| `9000 - 9999` | Expenses | Non-Operating / Tax | Depreciation Charge, Realized Loss on Forex |

---

### 4.2 System Number Constraints

1.  **Duplicate Detection Invariant**: Account numbers must be strictly unique within an organization:
    $$\text{Unique}(\text{organization\_id}, \text{account\_number})$$
2.  **Parent Range Match Rule**: Sub-accounts (where `parent_account_id` is defined) must have an account number that starts with the parent account range prefix. For example:
    *   Parent: `1100` (Main Checking)
    *   Sub-Account Allowed: `1100-01` or `1110` (if matching the parent range of `1100` to `1199`).
3.  **Range Enforcement Trigger**: A database trigger verifies that the `account_number` value aligns with the account category mapped via `category_id`:
    *   Category `Asset` $\rightarrow$ Account number must begin with `1`.
    *   Category `Liability` $\rightarrow$ Account number must begin with `2`.
    *   Category `Equity` $\rightarrow$ Account number must begin with `3`.
    *   Category `Income` $\rightarrow$ Account number must begin with `4`.
    *   Category `COGS` $\rightarrow$ Account number must begin with `5`.
    *   Category `Expense` $\rightarrow$ Account number must begin with `6`, `7`, `8`, or `9`.

---

## SECTION 5: CONTROL ACCOUNTS

Control accounts serve as system-controlled summary accounts for detailed sub-ledgers. They ensure that operational transactions (such as sending invoices, logging bills, processing payroll, or collecting sales taxes) are recorded through dedicated, validation-backed system modules rather than manual adjustments.

### 5.1 Invariance Mechanisms

1.  **Manual Posting Lock (`allow_manual_entries = false`)**: No user, regardless of role permissions, can post a manual journal entry containing a debit or credit line targeting a control account.
2.  **Required Sub-ledger Correlation**: Every transaction posting to a control account must specify a sub-ledger entity:
    *   **Accounts Receivable** $\rightarrow$ Must supply a valid `client_account_id` correlation.
    *   **Accounts Payable** $\rightarrow$ Must supply a valid `vendor_id` correlation.
    *   **Sales Tax / VAT** $\rightarrow$ Must supply a valid tax line and tax jurisdiction tracking code.
    *   **Deferred Revenue** $\rightarrow$ Must be linked to a structured revenue recognition schedule.
3.  **Trigger-Enforced Sub-ledger Match**: A database-level constraint rejects any ledger insertion targeting a control account where the secondary sub-ledger reference columns are empty (`NULL`).

---

### 5.2 Control Account Listing

| Control Account Number | Account Name | Target Sub-Ledger Entity | Invariant Rule |
| :--- | :--- | :--- | :--- |
| **`1200`** | Accounts Receivable | `public.client_accounts` | Balance must equal the sum of outstanding unpaid customer invoices. |
| **`1300`** | Inventory | `public.inventory_items` | Balance must match inventory valuation ledger. |
| **`2100`** | Accounts Payable | `public.vendors` | Balance must equal the sum of outstanding unpaid bills. |
| **`2200`** | VAT / Sales Tax Payable | `public.tax_rates` | Tracks liability by jurisdiction code. |
| **`2400`** | Deferred Subscription Revenue | `public.revenue_schedules` | Balance matches unrecognized values on active subscriptions. |
| **`3200`** | Retained Earnings | `public.fiscal_periods` | Immutable during the fiscal year. Calculated only at closing. |

---

## SECTION 6: DEFAULT SYSTEM ACCOUNTS

To ensure a functional workspace from day one, every new organization is automatically provisioned with a standardized Chart of Accounts.

### 6.1 Provisioning Manifest

The following template accounts are automatically initialized in the database:

```
[Default System Chart Schema Initialization]
 ├── 1010 Petty Cash [Cash]
 ├── 1110 Operating Checking [Bank]
 ├── 1200 Accounts Receivable [AR Control]
 ├── 1300 Inventory Control [Asset Control]
 ├── 2100 Accounts Payable [AP Control]
 ├── 2200 Sales Tax / VAT Payable [Tax Control]
 ├── 2400 Deferred Revenue [Revenue Control]
 ├── 3200 Retained Earnings [Equity]
 ├── 3210 Opening Balance Equity [Equity]
 ├── 4100 General Sales Revenue [Income]
 ├── 5100 General Cost of Goods Sold [COGS]
 ├── 9010 FX Gain/Loss [Income/Expense]
 ├── 9999 General Suspense Account [Control]
```

### 6.2 Initialization Workflow

Upon successful completion of the `organization.created` workflow, the database triggers the following initialization sequence:

```
[SYSTEM WORKFLOW: COA INITIALIZATION]
 1. READ Base Currency parameter from system.organizations.
 2. INSERT Default Account Categories (Assets, Liabilities, Equity, Income, Expenses).
 3. INSERT System Accounts using the Base Currency Key:
     a. Cash & Bank (1010, 1110)
     b. AR Control (1200)
     c. AP Control (2100)
     d. Tax & Deferred Revenue (2200, 2400)
     e. Retained Earnings & Opening Balance Equity (3200, 3210)
     f. Revenue & COGS (4100, 5100)
     g. FX Gain/Loss (9010)
     h. General Suspense Account (9999)
 4. VERIFY all Foreign Keys are mapped correctly.
 5. EMIT Event: "organization.chart_initialized".
```

### 6.3 Suspense & Opening Balance Accounts

*   **Opening Balance Equity (`3210`)**: Used to maintain double-entry balancing during historical data migration. When migrating open historical accounts, matching debits or credits are posted to this account. Once migration is complete, a balancing journal entry moves any remaining balance to Retained Earnings, bringing `3210` to zero.
*   **General Suspense Account (`9999`)**: Acts as a temporary hold for unclassified transaction lines (e.g., bank feed imports where the target account is unknown). Daily alerts notify financial controllers to reallocate any balance in `9999` to its proper account.

---

## SECTION 7: ACCOUNT VALIDATION RULES

The JUANET ledger engine runs continuous validation checks to maintain historical accuracy and prevent transactional drift.

```
                   [POSTING VALIDATION FLOW]
                 New Transaction Line Entry
                             │
                             ▼
                    Is Account Active? ────── No ──► [ REJECT ]
                             │ Yes
                             ▼
                Is Control Account Flagged? ─── Yes ─► [ REQUIRE SUB-LEDGER ]
                             │ No
                             ▼
              Does Line Currency Match? ───── No ──► [ APPLY FX TRANSLATION ]
                             │ Yes
                             ▼
                 Are Parent Accounts Closed? ─ Yes ──► [ REJECT ]
                             │ No
                             ▼
                         [ COMMIT ]
```

### 7.1 Duplicate Detection Invariant
To prevent accounting naming confusion, a composite unique constraint is enforced on the database table:
$$\text{Unique}(\text{organization\_id}, \text{account\_number}, \text{deleted\_at})$$
This prevents duplicate active accounts while allowing deleted accounts to remain in the database for auditing purposes.

### 7.2 Inactive & Suspended Accounts Validation
Accounts where `is_active = false` are blocked from accepting new transaction postings. Database triggers reject any draft journal entries attempting to debit or credit an inactive account.

### 7.3 Currency Alignment Rules
*   If an account has a specific currency defined (`currency_id != base_currency_id`), any postings to this account must be made in that designated currency.
*   The transaction engine will apply exchange rates to calculate the base currency equivalent (`amount_in_base`) before committing.

### 7.4 Parent-Child Structural Validation
*   **Balance Rollup**: Child account balances roll up to their parent accounts on financial reports.
*   **Posting Level Restriction**: To keep reporting clean, if an account has child accounts, direct postings to the parent account are blocked. Postings must be made to the specific child leaf nodes.

```
                    [Chart Rollup Representation]
                    
                    Parent Account: 1100 Bank (No Direct Postings)
                        ├── Child A: Checking 1110 ($45,000)
                        └── Child B: Savings 1120 ($10,000)
                        ─────────────────────────────────
                        Report Balance Rollup: $55,000
```

---

## SECTION 8: MULTI-CURRENCY ACCOUNTING

JUANET is built for international SaaS organizations, providing full support for transactions across multiple currencies.

### 8.1 Base vs. Account vs. Transaction Currency

```
                     [CURRENCY TRANSACTION RELATIONSHIPS]
                     
  [ Transaction Currency (e.g., GBP) ]
         │
         ├──► Local Account Currency (e.g., EUR Bank)
         │       Translate to Account Currency (IAS 21)
         │
         └──► Organization Base Currency (e.g., USD Reporting)
                 Record permanent baseline historical cost (ASC 830)
```

1.  **Organization Base Currency**: The core reporting currency of the legal entity (defined in `system.organizations`).
2.  **Account Currency**: The currency defined on the individual `chart_of_accounts` record. By default, this matches the organization base currency unless specifically configured otherwise (e.g., a EUR bank account).
3.  **Transaction Currency**: The currency in which a specific transaction occurs (e.g., a GBP sales invoice).

### 8.2 Translation & Exchange Rate Invariance

For every journal line posting in a foreign currency, the system writes three distinct currency values to ensure full auditability:

```sql
debit_amount               numeric(18,2)  -- Value in foreign transaction currency
exchange_rate_at_posting   numeric(18,6)  -- Exchange rate to Base Currency (1 Unit Foreign = X Base)
amount_in_base             numeric(18,2)  -- Calculated value in Base Currency
```

At the transaction commit boundary, the engine enforces the following balance validation:
$$\text{amount\_in\_base} = \text{Round}(\text{debit\_amount} \times \text{exchange\_rate\_at\_posting}, 2)$$

### 8.3 Realized vs. Unrealized Foreign Exchange Gains/Losses

*   **Realized Forex Gains/Losses**: Calculated and posted when a foreign currency transaction is settled.
    *   *Example*: An invoice is issued for GBP 1,000 when GBP 1 = USD 1.30 (USD 1,300 value). The invoice is paid when GBP 1 = USD 1.35 (USD 1,350 value). The cash receipt posts USD 1,350 to the Bank, offsets Accounts Receivable by USD 1,300, and records a USD 50 Realized Forex Gain to account `9010`.
*   **Unrealized Forex Gains/Losses**: Calculated at the end of each accounting period during revaluation of open foreign currency assets and liabilities (e.g., outstanding foreign invoices or foreign bank balances). The revaluation utility posts adjustment entries to write the balance sheet accounts to current market rates, with a matching entry to the Unrealized Forex Gain/Loss account. These adjustment entries are automatically reversed on the first day of the following period.

---

## SECTION 9: MULTI-TENANT ACCOUNT CONFIGURATION

JUANET uses a multi-tenant architecture designed to keep customer data completely secure and isolated, while also allowing for flexible, tenant-specific customization of account structures.

```
                        [TENANT ARCHITECTURE PATTERN]
                        
             [ JUANET Platform Global Accounting Template Registry ]
                                       │
     ┌─────────────────────────────────┼─────────────────────────────────┐
     ▼                                 ▼                                 ▼
[ Tenant A Org ]               [ Tenant B Org ]               [ Tenant C Org ]
 (US GAAP Tech)                 (EU IFRS SaaS)                 (Services Custom)
     │                                 │                                 │
     ├── RLS Isolated                  ├── RLS Isolated                  ├── RLS Isolated
     └── Custom Accounts               └── Custom Accounts               └── Custom Accounts
```

### 9.1 Tenant Isolation & Row-Level Security (RLS)

All tables in the Finance domain enforce strict Row-Level Security (RLS). Every query executed by the application must include the tenant context, which is checked by the database engine:

```sql
CREATE POLICY tenant_isolation_policy ON public.chart_of_accounts
  FOR ALL
  USING (organization_id = current_setting('request.jwt.claim.organization_id')::uuid);
```

### 9.2 Global Industry Templates

When a new organization is created, the system provisions its Chart of Accounts based on the selected industry template:

1.  **SaaS/Subscription Template**: Configured with pre-defined Deferred Subscription Revenue (`2400`), Subscription Revenue (`4200`), and hosting cost COGS (`5100`) accounts.
2.  **Professional Services Template**: Includes WIP Services Asset accounts, Service Revenue (`4100`) accounts, and sub-accounts for tracking contractor costs.
3.  **General Holding Company Template**: Set up with intercompany clearing balances and equity tracking accounts.

---

## SECTION 10: ACCOUNT LIFECYCLE

The lifecycle of an account in the Chart of Accounts is controlled by a state machine that ensures financial history is preserved and prevents postings to retired accounts.

```
                           [ACCOUNT LIFECYCLE STATE MACHINE]
                           
               [ CREATE Draft ] ──► Validate Number Range
                      │
                      ▼
               [ ACTIVE State ] ◄── Default Operational Postings Allowed
                      │
          Suspend     │     Reactivate
          Postings    ▼     Postings
              [ SUSPENDED State ]
                      │
                      ▼
               [ ARCHIVED State ] ──► RLS View Allowed for Audits / Posting Blocked
```

### 10.1 Account Lifecycle States

1.  **Draft**: The account is being configured. No transactions can be posted.
2.  **Active**: The default state. Valid transactions can be posted freely.
3.  **Suspended**: Postings are temporarily paused (e.g., during audit review). Existing transactions remain, but new entries are rejected.
4.  **Archived**: The account is retired and closed. It cannot accept any postings, but remains visible on historical financial reports.

### 10.2 Immutability & Soft Deletions

Financial accounts with posting history **cannot be physically deleted** from the database. Any attempt to run a hard delete (`DELETE FROM chart_of_accounts`) is intercepted and rejected by database triggers.

To retire an account:
1.  Verify the current balance is exactly zero.
2.  Change the status to `is_active = false`.
3.  Set the `deleted_at` timestamp. This soft-delete flag hides the account from active dropdown menus while preserving it for audit reports.

---

## SECTION 11: YEAR-END CLOSE BEHAVIOR

The Year-End Close routine is a critical process that resets temporary accounts and carries forward balances to the next fiscal year.

```
                       [YEAR-END CLOSE PROCESS]
                       
     1. LOCK current accounting periods to prevent new transactions.
     2. SUM all temporary revenue and expense accounts (Revenue - Expense = Net Income).
     3. CALCULATE Net Income for the closing fiscal period.
     4. GENERATE closing Journal Entries:
          a. Debit revenue accounts to bring balances to zero.
          b. Credit expense accounts to bring balances to zero.
          c. Post the net difference (Net Income) to Retained Earnings (3200).
     5. CARRY FORWARD Balance Sheet asset, liability, and equity balances.
     6. MARK the Fiscal Period status as 'closed'.
```

Once a fiscal period is closed:
*   The `status` of the period is set to `'closed'`.
*   A database trigger blocks any new transaction postings with dates falling within that closed period, ensuring past financial reports remain locked and unchangeable.

---

## SECTION 12: FINANCIAL REPORTING RELATIONSHIPS

Every account in the Chart of Accounts is mapped to specific financial statements, ensuring that reports can be generated dynamically.

```
                              [LEDGER REPORT MAPS]
                              
   [ Chart of Accounts ]
             │
             ├──► Balance Sheet
             │       ├── Current / Non-Current Assets
             │       ├── Liabilities
             │       └── Shareholder Equity
             │
             ├──► Profit & Loss (Income Statement)
             │       ├── Revenue Streams
             │       ├── COGS
             │       └── Operating Expenses
             │
             └──► Statement of Cash Flows
                     ├── Operating Activities (Direct/Indirect)
                     ├── Investing Activities
                     └── Financing Activities
```

### 12.1 Financial Statement Mapping Details

*   **Balance Sheet**:
    *   *Assets*: Sub-divided into Current Assets (Cash, Bank, Accounts Receivable, Inventory) and Fixed Assets.
    *   *Liabilities*: Sub-divided into Current Liabilities (Accounts Payable, Taxes Payable, Accruals) and Long-Term Liabilities.
    *   *Equity*: Common Stock, Paid-in Capital, Retained Earnings.
*   **Profit & Loss (P&L)**:
    *   *Revenue*: Operating Revenue, Professional Services, Subscriptions.
    *   *Cost of Goods Sold (COGS)*: Direct costs of delivery.
    *   *Operating Expenses (OPEX)*: Admin overhead, Payroll, Marketing, R&D.
*   **Statement of Cash Flows**:
    *   *Operating Activities*: Calculated from net operating income, adjusted for non-cash expenses (Depreciation) and changes in working capital (Accounts Receivable, Accounts Payable, Inventory).
    *   *Investing Activities*: Purchase or sale of long-term physical assets.
    *   *Financing Activities*: Proceeds from loans, repayment of debt, or equity transactions.

---

## SECTION 13: ROLE-BASED ACCESS CONTROL (RBAC)

Financial security is enforced through role-based access controls, defining exactly what actions users can take based on their assigned role.

| Role | Create / Edit Accounts | Archive Accounts | Manual Postings | Period Close Routines | View Financial Reports |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Financial Director / CFO** | Yes | Yes | Yes | Yes | Yes |
| **Senior Controller** | Yes | Yes | Yes | No | Yes |
| **Accountant** | No | No | Yes | No | Yes |
| **Accounts Receivable Specialist** | No | No | AR Sub-ledger Only | No | Read-only AR Reports |
| **Accounts Payable Specialist** | No | No | AP Sub-ledger Only | No | Read-only AP Reports |
| **Executive / Auditor** | No | No | No | No | Yes (Read-Only) |

---

## SECTION 14: SYSTEM EVENTS

The ledger engine emits structured, real-time events upon state changes in the Chart of Accounts, allowing downstream services to respond dynamically.

### 14.1 Event Definitions

#### `account.created`
Emitted immediately after a new account is added to the Chart of Accounts.
```json
{
  "event_id": "evt_32948239084",
  "event_type": "account.created",
  "organization_id": "org_771829",
  "payload": {
    "account_id": "acc_1010294",
    "account_number": "1110",
    "name": "Main Operating Bank Checking",
    "currency_id": "cur_usd",
    "is_control_account": false
  },
  "timestamp": "2026-06-27T08:45:00Z"
}
```

#### `account.updated`
Emitted when an account's name, description, or configuration switches are modified.
```json
{
  "event_id": "evt_32948239095",
  "event_type": "account.updated",
  "organization_id": "org_771829",
  "payload": {
    "account_id": "acc_1010294",
    "changes": {
      "name": { "old": "Old Bank Name", "new": "Main Operating Bank Checking" }
    }
  },
  "timestamp": "2026-06-27T08:46:12Z"
}
```

#### `account.archived`
Emitted when an account is retired and deactivated.
```json
{
  "event_id": "evt_32948239102",
  "event_type": "account.archived",
  "organization_id": "org_771829",
  "payload": {
    "account_id": "acc_1010294",
    "account_number": "1110"
  },
  "timestamp": "2026-06-27T09:00:00Z"
}
```

#### `organization.chart_initialized`
Emitted when an organization's default template Chart of Accounts is successfully provisioned.
```json
{
  "event_id": "evt_32948239115",
  "event_type": "organization.chart_initialized",
  "organization_id": "org_771829",
  "payload": {
    "template_used": "saas_standard",
    "total_accounts_created": 18
  },
  "timestamp": "2026-06-27T08:45:02Z"
}
```

---

## SECTION 15: PERFORMANCE & DATABASE INDEXING

To ensure fast financial calculations and real-time report generation, the database must include targeted indexes.

### 15.1 Recommended Database Indexes

#### Primary and Unique Sorting Indexes
```sql
-- Enforces fast account number lookups and uniqueness within each tenant
CREATE UNIQUE INDEX coa_org_number_idx 
  ON public.chart_of_accounts(organization_id, account_number);
```

#### Partial Operational Indexes
```sql
-- Speeds up active account searches by filtering out archived/inactive accounts
CREATE INDEX coa_active_lookup_idx 
  ON public.chart_of_accounts(organization_id, id) 
  WHERE is_active = true;
```

#### Parent Rollup Traversal Indexes
```sql
-- Optimizes recursive queries used to build the nested parent-child account tree
CREATE INDEX coa_parent_child_idx 
  ON public.chart_of_accounts(parent_account_id) 
  WHERE parent_account_id IS NOT NULL;
```

---

## SECTION 16: FUTURE ARCHITECTURAL EXPANSION

The JUANET ledger is designed to scale alongside growing enterprises, supporting complex structural expansions out of the box.

```
                      [Future Enterprise Expansion Map]
                      
         [ Core Ledger (Global Consolidations Engine) ]
                        │
       ┌────────────────┴────────────────┐
       ▼                                 ▼
 [ Legal Entity A ]                [ Legal Entity B ]
   ├── Branch North                  ├── Branch Europe
   └── Branch South                  └── Branch Asia
```

### 16.1 Multi-Entity Consolidations
*   **Intercompany Balancing**: Future support for intercompany balancing accounts (`1490` and `2190`) to automate the elimination of intercompany balances during group consolidation.
*   **Segment Reporting**: Includes placeholder columns for segment codes, allowing organizations to run individual balance sheets and profit & loss statements by department or division.

### 16.2 Segmented Cost Accounting
*   **Cost Centers & Departments**: Transactions can be tagged with specific `cost_center_id` or `department_id` codes. This enables granular cost-allocation reporting for deep departmental overhead analysis without needing to create thousands of highly specific accounts.

---

## SECTION 17: ARCHITECTURAL COMPLIANCE CHECKLIST

Before implementing database migrations or services based on this specification, verify that the following design requirements are met:

- [ ] **Double-Entry Balance Check**: All journal entries are verified to ensure that the sum of debits exactly equals the sum of credits before being committed.
- [ ] **GAAP/IFRS Revenue Recognition**: Deferred revenue is tracked in a liability control account and recognized over time via automated recognition schedules.
- [ ] **Strict Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all tables to guarantee complete data isolation between organizations.
- [ ] **Immutable History Guard**: Posted transactions cannot be updated or physically deleted under any circumstances.
- [ ] **Control Account Protection**: Manual postings to system-controlled accounts (such as Accounts Receivable or Accounts Payable) are strictly blocked.
- [ ] **Automated Forex Calculations**: All foreign currency postings are calculated and recorded with exchange rate snapshots and their base currency equivalents.
- [ ] **Self-Balancing Year-End Close**: Temporary accounts are closed out to Retained Earnings at the end of each fiscal period, leaving balance sheet accounts active for the next year.
- [ ] **Preserved Soft Deletes**: Retiring an account deactivates it (`is_active = false`) and sets a `deleted_at` timestamp, keeping all historical financial data intact.

---
**End of Specification.**
