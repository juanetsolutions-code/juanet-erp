# JUANET ERP Financial Dimensions & Cost Allocation Specification
## Phase 2.3.2E.1B — Multi-Dimensional Financial Analysis and Cost Allocation Manual
**Document Version:** 1.0  
**Author:** Chief Enterprise ERP Financial Systems Architect, JUANET Platform  
**Classification:** Technical Specification / Financial Management & Cost Accounting  

---

## SECTION 1: FINANCIAL DIMENSION PHILOSOPHY

### 1.1 The Crucial Distinction: Chart of Accounts vs. Financial Dimensions
In traditional, legacy accounting platforms, any requirement to track financial activity across multiple business lines, projects, or locations resulted in "Chart of Accounts explosion." If an organization needed to track 100 accounts across 10 departments and 5 locations, the ledger required 5,000 unique account codes (e.g., `6100-01-002` for Payroll-Marketing-Nairobi). This layout leads to bloated databases, highly complex reporting configurations, and constant maintenance overhead.

JUANET implements a modern, decoupled **Multi-Dimensional Financial Architecture**. 
*   **The Chart of Accounts (COA)** strictly defines the *nature* of the transaction (e.g., Asset, Liability, Revenue, Expense) to satisfy statutory balance sheet and income statement needs.
*   **Financial Dimensions** capture the *context* or *source* of the transaction (e.g., which department, which project, which customer, which AI model) to satisfy management reporting, cost accounting, and business intelligence requirements.

```
                  ┌──────────────────────────────────────────────┐
                  │          CORE LEDGER TRANSACTION             │
                  └──────────────────────┬───────────────────────┘
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 ▼                                               ▼
    [ STATUTORY ACCOUNTING (COA) ]             [ MANAGEMENT ACCOUNTING (Dimensions) ]
     - Nature of Transaction                    - Context / Source of Transaction
     - Determines IFRS / GAAP lines             - Categorizes for segment analytics
     - Account Number: 6100 (Payroll)           - Department: Marketing (DEP-02)
                                                - Branch: Nairobi (BR-04)
                                                - Project: CRM Expansion (PRJ-99)
```

### 1.2 The Business Case for Dimensions
By isolating core ledger accounts from context tracking, JUANET delivers elite enterprise benefits:
*   **Management Reporting**: Segment-based Profit & Loss statements can be rendered instantly by filtering dimensions rather than parsing complex account string prefixes.
*   **Operational Control**: Managers can monitor expenses and revenues within their specific departments or projects in real-time.
*   **Cost Accounting & Allocations**: Administrative overhead (such as global hosting bills or facilities rent) can be collected in a central bucket and redistributed across operational cost centers using dynamic allocation rules.
*   **Profitability Analysis**: Controllers can track the margins of individual customers, products, subscriptions, or campaigns dynamically.
*   **Budget & Variance Tracking**: Financial budgets can be set and monitored at the dimension level (e.g., Marketing Campaign Budget).

---

## SECTION 2: DIMENSION FRAMEWORK

JUANET uses a flexible dimension framework that balances standardized system behavior with tenant-specific customization.

```
                      [FINANCIAL DIMENSION RELATIONSHIP]
                      
         [ Core Ledger Entry (Immutable Line Account Link) ]
                                 │
     ┌───────────────────────────┼───────────────────────────┐
     ▼                           ▼                           ▼
[ Dimension Set ]      [ System Dimensions ]       [ Tenant Dimensions ]
 (e.g., Nairobi Mktg)   (Project, Dept, Customer)   (Custom AI-Model, etc.)
     │                           │                           │
     ▼                           ▼                           ▼
[ Dimension Values ]   [ Dimension Values ]        [ Dimension Values ]
 (DEP-02, BR-04)        (PRJ-88, CLI-101)           (GPT-4o, Claude-3.5)
```

### 2.1 System-Defined vs. Tenant-Defined Dimensions
1.  **System-Defined Dimensions**: Built-in dimensions optimized by the platform's core database engines. These connect directly to major system entities (e.g., `projects`, `client_accounts`, `vendors`, `departments`).
2.  **Tenant-Defined Dimensions**: Custom dimensions defined by individual organizations to capture unique business lines (e.g., tracking "AI Model Usage Costs" for API tools, or "Fund Codes" for non-profit entities).

### 2.2 Extensibility and Scalability
The database schema stores dimensional associations in isolated relation tables. This allows tenants to define an unlimited number of dimensions and values without needing to run DDL migrations or alter core ledger tables.

---

## SECTION 3: CORE DIMENSION TABLES

The following entities establish the physical storage, mapping, and integrity of the dimensions engine.

### 3.1 Table: `public.financial_dimensions`
This table defines the master metadata registry for all active dimensions.

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Universally unique row identifier. |
| `organization_id` | `uuid` | NO | None | RLS multi-tenant isolation key. |
| `dimension_code` | `varchar(50)` | NO | None | System identifier code (e.g., `'DEPARTMENT'`). |
| `dimension_name` | `varchar(100)` | NO | None | Display name of the dimension (e.g., `'Department'`). |
| `dimension_type` | `varchar(30)` | NO | `'tenant'` | Classification: `'system'` or `'tenant'`. |
| `description` | `text` | YES | `NULL` | Detailed scope description. |
| `display_order` | `integer` | NO | `0` | Order of appearance in configuration forms. |
| `is_required` | `boolean` | NO | `false` | If true, all transactions must specify a value for this dimension. |
| `is_active` | `boolean` | NO | `true` | Active status toggle. |
| `created_at` | `timestamptz` | NO | `now()` | Standard system creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | Standard system update timestamp. |
| `version` | `integer` | NO | `1` | Optimistic locking version. |

---

### 3.2 Table: `public.financial_dimension_values`
This table stores the specific allowed values for each dimension.

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key. |
| `organization_id` | `uuid` | NO | None | Multi-tenant isolation key. |
| `dimension_id` | `uuid` | NO | None | Foreign Key referencing parent dimension. |
| `value_code` | `varchar(50)` | NO | None | Unique alphanumeric value code (e.g., `'DEP-01'`). |
| `value_name` | `varchar(150)` | NO | None | Display name of the value (e.g., `'Engineering'`). |
| `parent_value_id` | `uuid` | YES | `NULL` | Self-reference supporting dimension hierarchies. |
| `is_active` | `boolean` | NO | `true` | Toggle to disable the value. |

---

### 3.3 Table: `public.journal_entry_dimensions`
This table maps individual journal entry lines to their specific dimension values.

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key. |
| `organization_id` | `uuid` | NO | None | Multi-tenant isolation key. |
| `ledger_entry_id` | `uuid` | NO | None | Link to the core ledger line. |
| `dimension_id` | `uuid` | NO | None | Reference to the financial dimension metadata table. |
| `dimension_value_id` | `uuid` | NO | None | Reference to the financial dimension value table. |

---

### 3.4 Table: `public.dimension_sets`
This table defines reusable groups of dimension values, allowing users to apply multiple classifications at once.

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key. |
| `organization_id` | `uuid` | NO | None | Multi-tenant isolation key. |
| `set_code` | `varchar(50)` | NO | None | System key identifier (e.g., `'MKTG_NRB'`). |
| `set_name` | `varchar(100)` | NO | None | Display label (e.g., `'Marketing Department - Nairobi'`). |
| `is_active` | `boolean` | NO | `true` | Toggle to disable the set. |

---

### 3.5 Table: `public.dimension_set_items`
This table maps individual dimension values to a reusable dimension set.

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key. |
| `organization_id` | `uuid` | NO | None | Multi-tenant isolation key. |
| `dimension_set_id` | `uuid` | NO | None | Reference to parent dimension set. |
| `dimension_id` | `uuid` | NO | None | Reference to target dimension. |
| `dimension_value_id` | `uuid` | NO | None | Reference to target dimension value. |

---

### 3.6 Relational & Check Constraints

```sql
-- 1. Ensure dimension codes match standard formatting
CONSTRAINT dim_code_format CHECK (dimension_code ~ '^[A-Z0-9\_]{3,50}$')

-- 2. Validate that parent dimension values do not reference themselves
CONSTRAINT dim_value_prevent_self CHECK (parent_value_id <> id)

-- 3. Verify that dimension values are linked to the correct parent dimension
-- Enforced via composite foreign keys to keep dimension_id matching across hierarchy rows
```

---

## SECTION 4: STANDARD SYSTEM DIMENSIONS

JUANET is provisioned with standard, built-in dimensions that integrate with core business flows.

```
                            [STANDARD SYSTEM DIMENSIONS]
                            
   [ DEPARTMENT ]           [ COST CENTER ]           [ PROJECT ]           [ AI MODEL ]
    Tracks expenses          Aggregates direct         Maps revenue/cost     Tracks resource
    by team (e.g.,           costs by business         to client projects    costs of generative
    Sales, R&D).             unit (e.g., EMEA).        (e.g., Web App).      models (e.g., Gemini).
```

### 4.1 Department (DEP)
*   **Business Purpose**: Tracks payroll, software licenses, and operational expenses across internal teams.
*   **Examples**: `Engineering`, `Marketing`, `Human Resources`, `Customer Success`.

### 4.2 Cost Center (CC)
*   **Business Purpose**: Groups business activities for internal profit & loss reporting and overhead analysis.
*   **Examples**: `EMEA Sales`, `APAC Delivery`, `North America Support`.

### 4.3 Project (PRJ)
*   **Business Purpose**: Maps revenues and expenses directly to specific client deliverables. Essential for measuring project profitability.
*   **Examples**: `SaaS Deployment v1`, `API Core Upgrade`.

### 4.4 Client Account (CLI)
*   **Business Purpose**: Tracks all transactions related to a customer, enabling dynamic customer lifetime value and margin reporting.
*   **Examples**: `Acme Corp`, `Globex Ltd`.

### 4.5 AI Model Usage (AI_MOD)
*   **Business Purpose**: Tracks API consumption costs for generative AI features. This allows organizations to measure, manage, and report on the profitability of AI-driven capabilities.
*   **Examples**: `Gemini-1.5-Pro`, `Gemini-1.5-Flash`, `Claude-3.5-Sonnet`.

---

## SECTION 5: DIMENSION SETS

To save time and prevent data entry errors, financial controllers can create **Dimension Sets**. These sets group multiple dimension values into a single, reusable template.

```
                       [DIMENSION SET APPLICATION]
                       
                       [ Dimension Set Applied ]
                    Marketing Department - Nairobi Branch
                                   │
              ┌────────────────────┴────────────────────┐
              ▼                                         ▼
   [ Dimension: Department ]                  [ Dimension: Branch ]
       Value: Marketing                          Value: Nairobi
```

*   **Creation**: Dimension sets are configured by mapping a single set header to multiple dimension values (e.g., Department = Sales, Branch = Nairobi, Region = East Africa).
*   **Application**: When entering transaction lines, the user selects the Dimension Set. The engine automatically expands this selection, applying the correct individual dimension values to the underlying ledger lines.
*   **Validation**: The system verifies that the dimension values contained in the set are active and valid before processing.

---

## SECTION 6: COST ALLOCATION ENGINE

The Cost Allocation Engine is used to redistribute shared or administrative expenses (such as hosting bills, office rent, or support overhead) across the specific business units or departments that consume them.

```
                      [COST ALLOCATION FLOW]
                      
   [ Consolidated Operating Expense ] (e.g., AWS Hosting Bill: $10,000)
                  │
                  ▼
   [ Cost Allocation Rule ] (Type: Weighted Allocation)
                  │
     ┌────────────┼────────────┐
     ▼            ▼            ▼
[ R&D Team ]  [ Sales Team ]  [ Support Team ]
    60%           20%             20%
     │            │               │
     ▼            ▼               ▼
  $6,000       $2,000          $2,000  (Allocated Ledger Lines)
```

### 6.1 Supported Allocation Methods

1.  **Fixed Percentage**: Divides costs using static, pre-defined percentages configured by the financial controller (e.g., 50% to Department A, 30% to Department B, 20% to Department C).
2.  **Equal Allocation**: Splits expenses evenly among all active business units.
3.  **Revenue Proportional**: Dynamically allocates costs based on the revenue generated by each segment in the previous month.
4.  **Headcount Allocation**: Distributes costs (such as office rent or utilities) based on the number of active employees assigned to each department.
5.  **Usage Allocation**: Allocates technical hosting or compute overhead based on actual system consumption metrics (e.g., CPU hours used, or API calls executed by each AI model).

### 6.2 Execution and Controls
Allocation rules are calculated and posted during month-end closing procedures. The engine generates balancing adjustment journals, debiting the target department or cost center accounts and crediting the originating central suspense account, bringing its balance to zero.

---

## SECTION 7: JOURNAL ENTRY INTEGRATION

Financial Dimensions integrate directly with the core ledger, ensuring that every financial transaction can carry detailed business context.

```
                           [JOURNAL ENTRY SCHEMA]
                           
     [ Journal Entry Header ] (Posting Date, Currency, Batch ID)
                │
                └──► [ Ledger Entry Line ] (Debit/Credit, Account)
                           │
                           └──► [ Dimension Values ]
                                   ├── Department: Sales (DEP-02)
                                   ├── Project: CRM (PRJ-99)
                                   └── AI Model: Gemini-Pro (AI-01)
```

Dimensions can be applied to:
*   **Invoices**: Tagging sales lines with specific projects, customers, and product codes.
*   **Vendor Bills**: Tagging expenses with cost centers, departments, and employee IDs.
*   **Wages and Payroll**: Tagging labor costs with departments and branches.
*   **Asset Depreciation**: Allocating depreciation charges to the specific departments using the physical assets.

---

## SECTION 8: POSTING RULE ENGINE INTEGRATION

The Posting Rule Engine automatically applies default financial dimensions during transaction processing, minimizing the need for manual data entry.

```
                   [POSTING ENGINE RULE INTERPOLATION]
                      Incoming Operational Event
                     (e.g., Customer Invoice Sent)
                                   │
                                   ▼
                       Evaluate Posting Rules
                                   │
                                   ▼
                   Lookup Default Dimension Mappings:
                   ├── Project ID ────► Map Project Dimension Value
                   ├── Customer ID ───► Map Client Dimension Value
                   └── Department ID ─► Map Dept Dimension Value
                                   │
                                   ▼
                    Write Balanced Ledger Entries
                   Tagged with resolved dimensions
```

If default dimensions are configured at multiple levels (e.g., a default department is defined on both the Project and the individual Employee record), the engine resolves conflicts using a strict precedence order:
1.  **Level 1**: Specific Transaction line-item overrides (Highest Precedence).
2.  **Level 2**: Specific Project or Contract defaults.
3.  **Level 3**: Employee or Vendor master defaults.
4.  **Level 4**: Global Organization default templates (Fallback).

---

## SECTION 9: VALIDATION RULES

To maintain clean and accurate financial reports, the engine runs strict dimension validations on all transactions before committing them to the ledger.

```
                    [DIMENSION ENTRY VALIDATOR]
                      Incoming Ledger Entry Line
                                   │
                                   ▼
                    Is Dimension Value Active? ────── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                     Is Dimension Value Closed? ───── Yes ─► [ REJECT ]
                                   │ No
                                   ▼
                   Verify Required Dimensions:
                   (Check if is_required = true)
                   Are required dimensions present? ── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                       Enforce Tenant Isolation:
                   (Check if organization_id matches)
                   Does tenant validation pass? ───── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                               [ COMMIT ]
```

### 9.1 Core Validation Invariants
*   **Tenant Separation**: The engine verifies that all dimension IDs and value IDs belong to the same tenant organization (`organization_id`). Cross-tenant dimension mappings are strictly blocked.
*   **Required Fields**: If a dimension is marked as required (`is_required = true`), the engine will reject any transaction attempting to post to its associated account range without specifying a valid dimension value.
*   **Validity Dates**: Dimension values can be configured with active date bounds. Any postings outside these dates are rejected.
*   **Archived Status**: Inactive or archived dimension values are blocked from accepting new transaction postings.

---

## SECTION 10: MULTI-TENANT SEGREGATION

All dimension metadata, values, and transaction mappings are partitioned using the tenant isolation key (`organization_id`).

Row-Level Security (RLS) is applied to all dimension tables, ensuring that tenants can only view and use their own configured dimensions:

```sql
CREATE POLICY tenant_isolation_dimensions ON public.financial_dimensions
  FOR ALL
  USING (organization_id = current_setting('request.jwt.claim.organization_id')::uuid);
```

Downstream databases, reporting engines, and UI dropdowns are automatically filtered by this RLS policy, guaranteeing complete data isolation between organizations.

---

## SECTION 11: BUDGET INTEGRATION

JUANET supports detailed budget tracking at the dimension level, enabling organizations to compare planned spending against actual performance.

```
                        [BUDGET ENFORCEMENT ENGINE]
                         Proposed Operational Bill
                                     │
                                     ▼
                        Evaluate Dimension Values:
                      (Department: R&D, Cost Center: EU)
                                     │
                                     ▼
                        Retrieve Active Budget Limit
                                     │
                                     ▼
                       Compare Proposed with Limit:
                       Is Remaining Budget Sufficient?
                                     │
               ┌─────────────────────┴─────────────────────┐
               ▼ (Yes)                                     ▼ (No)
            [ PASS ]                                   [ WARNING ]
     Transaction committed                      Warning alert sent to
                                                 department manager
```

Budgets can be configured for specific combinations of accounts and dimensions (e.g., Rent Expense for the Kenya Branch, or Marketing Travel Expense). During month-end reporting, the engine runs variance analyses, comparing actual ledger lines against configured budgets to flag over-expenditures.

---

## SECTION 12: FINANCIAL REPORTING ENGINE

By decoupling accounts from business context, the reporting engine can generate dynamic, multi-dimensional reports without complex data processing.

```
                          [SEGMENTED REPORTING ENGINE]
                          
     Select Segment Filter (e.g., Department: R&D, Region: EMEA)
                                  │
                                  ▼
                     Aggregate Core Ledger Lines:
                     WHERE ledger_entry.account_id IN (Expenses)
                       AND journal_entry_dimensions.department_id = 'R&D'
                       AND journal_entry_dimensions.region_id = 'EMEA'
                                  │
                                  ▼
                   Generate Real-Time Reports:
                   ├── Segment P&L Statement
                   ├── Project Profitability Report
                   └── Campaign ROI Dashboard
```

### 12.1 Enabled Reports
*   **P&L by Department**: Displays the profitability of individual organizational teams.
*   **Project Profitability**: Details revenues and direct expenses for specific client projects, calculating gross profit margins.
*   **AI Cost Analysis**: Tracks the computing and API overhead costs of individual generative AI features.
*   **Branch Performance**: Compares revenues, expenses, and cash flows across different office locations.

---

## SECTION 13: ROLE-BASED ACCESS CONTROL (RBAC)

Access to dimension configurations and allocation rules is governed by strict role-based permissions to ensure financial security.

| Role | Create Dimensions | Modify Dimensions | Configure Allocation Rules | Assign Dimensions | View Performance Reports |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Financial Director / CFO** | Yes | Yes | Yes | Yes | Yes |
| **Senior Controller** | Yes | Yes | Yes | Yes | Yes |
| **Accountant** | No | No | No | Yes | Yes |
| **Department Manager** | No | No | No | No | Department Only |
| **Auditor** | No | No | No | No | Yes (Read-Only) |

---

## SECTION 14: AUDIT REQUIREMENTS & TRACEABILITY

To comply with SOC2, GAAP, and IFRS audit standards, changes to dimensions and cost allocations must be fully traceable.

The system logs all dimensional activity, tracking:
1.  **Configuration Changes**: Any creation, modification, or deactivation of dimensions, values, or sets (capturing the user, timestamp, old value, and new value).
2.  **Allocation History**: A complete record of every execution of the Cost Allocation Engine, including the rule used, the source accounts, the destination accounts, the calculated allocation amounts, and the resulting journal entry IDs.
3.  **Manual Overrides**: Any manual adjustments to default dimension mappings must be logged with a required reason note from the accountant.

---

## SECTION 15: REAL-TIME SYSTEM EVENTS

The dimensions engine emits structured events upon state changes, allowing downstream services to respond dynamically.

### 15.1 Event Definitions

#### `financial_dimension.created`
```json
{
  "event_id": "evt_492810482",
  "event_type": "financial_dimension.created",
  "organization_id": "org_771829",
  "payload": {
    "dimension_id": "dim_0091",
    "dimension_code": "REGION",
    "dimension_name": "Sales Region",
    "is_required": true
  },
  "timestamp": "2026-06-27T09:20:00Z"
}
```

#### `allocation.executed`
```json
{
  "event_id": "evt_492810512",
  "event_type": "allocation.executed",
  "organization_id": "org_771829",
  "payload": {
    "allocation_rule_id": "rule_aws_host_09",
    "source_amount": 10000.00,
    "source_account": "6300",
    "generated_journal_entry_id": "je_90284",
    "distributions": [
      { "department": "R&D", "allocated_amount": 6000.00 },
      { "department": "Sales", "allocated_amount": 2000.00 },
      { "department": "Support", "allocated_amount": 2000.00 }
    ]
  },
  "timestamp": "2026-06-30T23:59:59Z"
}
```

#### `dimension.validation_failed`
```json
{
  "event_id": "evt_492810534",
  "event_type": "dimension.validation_failed",
  "organization_id": "org_771829",
  "payload": {
    "transaction_type": "vendor_bill",
    "account_number": "6200",
    "missing_required_dimension": "DEPARTMENT",
    "user_id": "usr_9921"
  },
  "timestamp": "2026-06-27T09:21:12Z"
}
```

---

## SECTION 16: PERFORMANCE & DATABASE INDEXING

Because dimension values are queried frequently during transaction validation and report generation, highly optimized indexing is required.

```sql
-- 1. Speeds up dimension lookup queries within a tenant
CREATE INDEX financial_dimensions_lookup_idx 
  ON public.financial_dimensions(organization_id, dimension_code, is_active);

-- 2. Optimizes retrieval of allowed values for a specific dimension
CREATE INDEX financial_dimension_values_idx 
  ON public.financial_dimension_values(dimension_id, is_active);

-- 3. Composite covering index for fast transaction validation and reporting
CREATE INDEX journal_entry_dimensions_composite_idx 
  ON public.journal_entry_dimensions(organization_id, ledger_entry_id)
  INCLUDE (dimension_id, dimension_value_id);
```

---

## SECTION 17: FUTURE MODULES EXTENSIBILITY

The decoupled dimensions framework is designed to integrate seamlessly with future enterprise modules:
*   **Manufacturing**: Add an `Assembly Line` or `Work Order` dimension to track factory overhead costs without altering the core ledger.
*   **NGO / Fund Accounting**: Add a `Grant ID` or `Restricted Fund` dimension to track and report on donor-funded programs.
*   **Fleet Management**: Add a `Vehicle ID` dimension to measure and analyze operating costs (such as fuel and maintenance) across a corporate fleet.

---

## SECTION 18: ARCHITECTURAL COMPLIANCE CHECKLIST

Before implementing database migrations or services based on this specification, verify that the following design requirements are met:

- [ ] **Decoupled Architecture**: Financial dimensions do not duplicate or replace accounts in the Chart of Accounts.
- [ ] **Complete Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all dimension metadata, values, and transaction mapping tables.
- [ ] **Configurable Allocation Rules**: The Cost Allocation Engine supports flexible allocation methods (such as fixed percentage or usage-based allocation).
- [ ] **Strict Entry Validation**: Required dimension constraints, active dates, and tenant ownership are validated before committing transactions.
- [ ] **Default Dimension Defaults**: The Posting Rule Engine automatically resolves and applies default dimensions based on transaction context.
- [ ] **Dynamic Budget Tracking**: Budgets can be configured and analyzed at the specific dimension level.
- [ ] **Comprehensive Audit Traceability**: Any changes to dimension settings, values, or allocation executions are recorded in immutable audit logs.

---
**End of Specification.**
