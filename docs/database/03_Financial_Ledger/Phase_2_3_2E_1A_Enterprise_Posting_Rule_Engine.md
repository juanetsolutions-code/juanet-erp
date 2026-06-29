# JUANET ERP Ledger Posting Rule Engine Specification
## Phase 2.3.2E.1A — Configurable Accounting Posting Engine Manual
**Document Version:** 1.0  
**Author:** Chief Enterprise ERP Financial Systems Architect, JUANET Platform  
**Classification:** Technical Specification / Accounting Engine Configuration  

---

## SECTION 1: POSTING ENGINE PHILOSOPHY

### 1.1 The Prohibition of Hardcoded Accounting Logic
In legacy financial systems, debit and credit assignments are frequently hardcoded inside the application code (e.g., executing a raw SQL query inside `InvoiceService` that hardcodes account numbers like `1200` and `4100`). JUANET strictly prohibits this pattern.

Hardcoding accounting logic introduces structural fragilities:
*   **System Rigidness**: Altering a tenant's accounting treatment (such as switching from a generalized sales revenue account to department-specific revenue channels) requires code modifications, re-testing, and redeployment.
*   **Audit Deficiencies**: It hides financial transaction logic from auditors, preventing visual traceability of the conversion from operational events to ledger lines.
*   **Localization Obstacles**: Multi-country taxation models (e.g., VAT vs. US Sales Tax) and local Charts of Accounts cannot be dynamically adjusted per tenant.
*   **Extensibility Roadblocks**: Introducing new modules (such as an Inventory or Manufacturing module) forces engineers to modify core billing routines to write associated expense accounts.

### 1.2 Configurable Posting Rules Architecture
JUANET implements an **Indirect Configurable Posting Rule Engine**. Core application services execute operational business actions (e.g., sending an invoice, completing a project task, receiving inventory) and emit structured, asynchronous **Business Events** containing operational context.

The **Posting Rule Engine** intercepts these events, matches them against highly optimized, tenant-configurable rules, resolves the correct accounts, validates entry safety, and emits double-entry ledger lines.

```
┌─────────────────────────────────┐
│     Business Event Service      │ (e.g., Invoice Sent, Payment Received)
└────────────────┬────────────────┘
                 │
                 │ Emits Event (Event ID, Tenant ID, Amounts, Currencies)
                 ▼
┌─────────────────────────────────┐
│      Posting Rule Engine        │ Resolves rule configuration from
│                                 │ database (account_posting_rules)
└────────────────┬────────────────┘
                 │
                 │ Maps & Validates Debit/Credit allocations
                 ▼
┌─────────────────────────────────┐
│     Double-Entry Validator      │ Enforces Ledger Balance: ∑ Debits = ∑ Credits
└────────────────┬────────────────┘
                 │
                 │ Creates transaction boundary
                 ▼
┌─────────────────────────────────┐
│         General Ledger          │ Writes Immutable Journal & Ledger Entries
└─────────────────────────────────┘
```

### 1.3 Key Architectural Advantages
*   **Dynamic Customization**: Financial controllers can configure, test, and activate tenant-specific rules on the fly without changing a single line of codebase.
*   **Pristine Auditability**: Database auditors can view the exact posting rule used, the originating business event ID, and the correlation payload on every journal ledger line.
*   **Industry Template Bootstrapping**: Standardized rule patterns (e.g., Tech SaaS, Professional Services, General Retailing) can be applied instantly during tenant provisioning.
*   **Unified Error Isolation**: If a rule resolution fails (e.g., due to an inactive account or a missing configuration), the posting fails gracefully, logging an audit exception while keeping downstream operational systems intact.

---

## SECTION 2: POSTING RULE ARCHITECTURE

At the heart of the engine is the `public.account_posting_rules` configuration table. This table maps operational event signatures to accounts and priorities.

### 2.1 Physical Column Catalog: `public.account_posting_rules`

| Column Name | PostgreSQL Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key of the configuration rule. |
| `organization_id` | `uuid` | NO | None | RLS multi-tenant bounding key. |
| `event_type` | `varchar(100)` | NO | None | Matching business event string (e.g., `'invoice.created'`). |
| `document_type` | `varchar(50)` | NO | None | Document scope classification (e.g., `'invoice'`, `'bill'`, `'payroll'`). |
| `transaction_category` | `varchar(50)` | YES | `NULL` | Secondary filter (e.g., `'subscription'`, `'professional_service'`). |
| `debit_account_id` | `uuid` | NO | None | Target chart of accounts ID for debit lines. |
| `credit_account_id` | `uuid` | NO | None | Target chart of accounts ID for credit lines. |
| `tax_account_id` | `uuid` | YES | `NULL` | Target account for tax line items. |
| `discount_account_id` | `uuid` | YES | `NULL` | Target account for discount or promotion items. |
| `exchange_gain_account_id`| `uuid` | YES | `NULL` | Target account for realized exchange rate gains. |
| `exchange_loss_account_id`| `uuid` | YES | `NULL` | Target account for realized exchange rate losses. |
| `priority` | `integer` | NO | `10` | Precedence score for rule evaluation (higher wins). |
| `effective_from` | `date` | NO | `now()::date` | Logical date start for rule validity. |
| `effective_to` | `date` | YES | `NULL` | Logical date end for rule validity. |
| `currency_id` | `uuid` | YES | `NULL` | Optional currency constraint lock. |
| `country_id` | `uuid` | YES | `NULL` | Optional country constraint lock. |
| `industry_template` | `varchar(50)` | YES | `NULL` | Source template flag (e.g., `'saas_standard'`). |
| `is_system_rule` | `boolean` | NO | `false` | If true, protected system default. Cannot be edited by tenant. |
| `is_active` | `boolean` | NO | `true` | Toggle to disable the rule. |
| `created_at` | `timestamptz` | NO | `now()` | Audit creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | Audit update timestamp. |
| `version` | `integer` | NO | `1` | Optimistic locking counter. |

---

### 2.2 Relational Constraints & Check Validation

The posting rules table implements strict validation constraints:

```sql
-- 1. Ensure date validity limits make chronological sense
CONSTRAINT posting_rules_date_bounds CHECK (effective_to >= effective_from)

-- 2. Prevent Debit and Credit accounts from pointing to the same ledger account
CONSTRAINT posting_rules_distinct_accounts CHECK (debit_account_id <> credit_account_id)

-- 3. Restrict rule priority ranges to reasonable levels (0 to 1000)
CONSTRAINT posting_rules_priority_range CHECK (priority >= 0 AND priority <= 1000)

-- 4. Check that either both exchange gain/loss accounts are set, or both are null
CONSTRAINT posting_rules_exchange_accounts CHECK (
  (exchange_gain_account_id IS NOT NULL AND exchange_loss_account_id IS NOT NULL) OR
  (exchange_gain_account_id IS NULL AND exchange_loss_account_id IS NULL)
)
```

---

## SECTION 3: EVENT CATALOG

Every accounting event must trace back to a validated schema entry. Below is the complete catalog of business event codes supported by the JUANET engine.

```
                                     [EVENT SCHEMA TAXONOMY]
                                                │
         ┌──────────────────────────────────────┼──────────────────────────────────────┐
         ▼                                      ▼                                      ▼
    [ BILLING ]                            [ PAYABLES ]                           [ OPERATIONS ]
     ├── invoice.created                    ├── vendor.bill_created                ├── inventory.received
     ├── invoice.payment_received           ├── vendor.bill_paid                   ├── asset.depreciated
     └── credit_note.created                └── debit_note.created                 └── payroll.processed
```

### 3.1 Billing & Invoicing Events
*   `invoice.created`: Emitted when an invoice is validated and finalized into the draft state.
*   `invoice.sent`: Emitted when an invoice is formally issued to a client, locking its entries.
*   `invoice.cancelled`: Emitted when an invoice is voided, requiring complete offset lines.
*   `invoice.payment_received`: Emitted when a cash receipt settles an invoice's remaining balance.
*   `invoice.refunded`: Emitted when cash is returned to a customer for previously paid invoices.
*   `credit_note.created`: Emitted when an administrative credit is issued to a customer.

### 3.2 Payables & Purchasing Events
*   `vendor.bill_created`: Emitted when a supplier bill is entered and validated.
*   `vendor.bill_paid`: Emitted when payment is dispatched and allocated against a supplier bill.
*   `debit_note.created`: Emitted when a debit note is issued to correct vendor payable balances.

### 3.3 Core Operations & Resource Events
*   `expense.recorded`: Emitted when non-billable corporate card or operational cash expenses are posted.
*   `subscription.created`: Emitted when a customer signs up for a recurring subscription tier.
*   `subscription.renewed`: Emitted when a subscription cycle rolls forward, initiating billing lines.
*   `subscription.cancelled`: Emitted when a subscription is terminated, triggering deferred revenue adjustments.
*   `timesheet.approved`: Emitted when an hourly project timesheet is approved, triggering accrued billable cost calculations.
*   `payroll.processed`: Emitted when staff payroll summaries are finalized, creating wage liabilities.

### 3.4 Asset & Inventory Events
*   `asset.depreciated`: Emitted when monthly asset valuation routines calculate depreciation write-offs.
*   `inventory.received`: Emitted when supplier inventory shipments arrive, updating physical asset holding lines.
*   `inventory.sold`: Emitted when items are sold, triggering cost of goods sold calculations.
*   `inventory.adjusted`: Emitted during physical stock counts when inventory audits reveal quantity drifts.

### 3.5 System Ledger Events
*   `exchange.revaluation`: Emitted during month-end closes to adjust foreign-currency accounts to spot rates.
*   `year_end.closed`: Emitted during fiscal close processes to clear temporary revenue and expense balances to retained earnings.

---

## SECTION 4: POSTING MATRIX

Below is the definitive ERP Posting Matrix. Every business event translates into a double-entry debit and credit allocation using these configuration templates.

| Business Event | Primary Debit Account | Primary Credit Account | Ancillary Accounts | Accounting Logic & Rationale |
| :--- | :--- | :--- | :--- | :--- |
| **`invoice.sent`** | `1200 - Accounts Receivable` | `4100 - Sales Revenue` | `2200 - VAT Tax` | Establishes the customer receivable asset, recognizes the revenue, and records the tax liability to the government. |
| **`invoice.payment_received`** | `1110 - Main Bank Account` | `1200 - Accounts Receivable` | - | Settles the outstanding customer invoice, shifting the asset from Accounts Receivable to liquid Cash at Bank. |
| **`vendor.bill_created`** | `6000 - Expense` | `2100 - Accounts Payable` | `2200 - Accrued VAT` | Records the operating or administrative expense while creating a payable liability to the supplier. |
| **`vendor.bill_paid`** | `2100 - Accounts Payable` | `1110 - Main Bank Account` | - | Settles the supplier bill, reducing the outstanding payable liability with a cash disbursement. |
| **`credit_note.created`** | `4900 - Sales Returns & Allowances` | `1200 - Accounts Receivable` | - | Reduces the customer's outstanding receivable asset due to billing corrections or returned goods. |
| **`payroll.processed`** | `6100 - Payroll Expense` | `2300 - Wages Payable` | `2310 - Taxes Withheld` | Accrues gross wages as an operating expense while tracking net salary liabilities and statutory withholdings. |
| **`asset.depreciated`** | `9100 - Depreciation Expense` | `1900 - Accumulated Depreciation` | - | Records the periodic wear-and-tear expense of physical assets, reducing their net book value via a contra-asset account. |
| **`inventory.received`** | `1300 - Inventory Asset` | `2100 - Accounts Payable` | - | Records the receipt of physical goods, increasing inventory assets and creating an associated vendor payable liability. |
| **`inventory.sold`** | `5100 - Cost of Goods Sold` | `1300 - Inventory Asset` | - | Matches revenue with expense by shifting the physical inventory asset to Cost of Goods Sold at the moment of sale. |
| **`subscription.created`** *(Accrual)* | `1200 - Accounts Receivable` | `2400 - Deferred Subscription Revenue` | - | Records the pre-billed subscription receivable, deferring revenue recognition until services are delivered. |
| **`subscription.recognized`** *(Recognize)* | `2400 - Deferred Subscription Revenue` | `4200 - Subscription Revenue` | - | Monthly amortization routine recognizing deferred subscription balances to operating revenue as services are rendered over time. |

---

## SECTION 5: RULE EVALUATION ENGINE

The engine uses a deterministic, priority-weighted resolution algorithm to find and apply the correct posting rule for any given event.

```
                         [RULE RESOLUTION PROCESS]
                             Incoming Business Event
                                        │
                                        ▼
                            Filter by organization_id
                                        │
                                        ▼
                            Filter by active date bounds
                     (effective_from <= Date <= effective_to)
                                        │
                                        ▼
                            Match Event Type & Category
                                        │
                                        ▼
                         Best Match? (Filter Hierarchies)
                         ├── Specific Currency Match (Wins)
                         ├── Country Specific Match (Wins)
                         └── Global Organization Match (Fallback)
                                        │
                                        ▼
                             Resolve Highest Priority
                                        │
                                        ▼
                          Evaluate Debit & Credit Accounts
                                        │
                                        ▼
                            Generate Journal Entry Lines
```

### 5.1 Resolution Hierarchy Invariance
When multiple posting rules match a business event, the engine resolves conflict by evaluating criteria from most specific to most generic:

1.  **Level 1: Explicit Organization and Currency Override**: Points to a specific currency, country, and organization.
2.  **Level 2: Organization Level Customization**: Custom rules created by the tenant organization (no currency restrictions).
3.  **Level 3: Global Template / Industry Defaults**: Fallback rules initialized during tenant onboarding (applicable if no tenant overrides exist).

If multiple rules match at the same evaluation tier, the rule with the highest `priority` value is selected. If priorities are equal, the rule with the most recent `created_at` timestamp wins.

---

### 5.2 Deterministic Selection Logic Reference

The selection logic can be expressed conceptually as:

```sql
SELECT * 
FROM public.account_posting_rules
WHERE organization_id = :tenant_id
  AND event_type = :incoming_event_type
  AND is_active = true
  AND effective_from <= :posting_date
  AND (effective_to IS NULL OR effective_to >= :posting_date)
  AND (currency_id IS NULL OR currency_id = :transaction_currency_id)
  AND (country_id IS NULL OR country_id = :transaction_country_id)
ORDER BY 
  currency_id IS NOT NULL DESC,
  country_id IS NOT NULL DESC,
  priority DESC,
  created_at DESC
LIMIT 1;
```

---

## SECTION 6: TENANT INHERITANCE & OVERRIDES

JUANET uses a multi-tiered inheritance model for posting configurations. This allows the system to provide sensible global defaults while giving tenant organizations the flexibility to override rules as needed.

### 6.1 Inheritance Levels

```
                     [INHERITANCE PRECEDENCE TREE]
                     
     [ LEVEL 1: System Master Defaults ] (Low Priority / Global)
                     │
                     ▼
     [ LEVEL 2: Industry Template Defaults ] (e.g., Tech SaaS, Consulting)
                     │
                     ▼
     [ LEVEL 3: Tenant Organization Overrides ] (High Priority / Tenant Specific)
                     │
                     ▼
     [ LEVEL 4: Regional / Country Specific Rules ] (Highest Priority / Local Tax)
```

1.  **Level 1: System Master Defaults**: Hardened rules applied across the platform. These use fallback Accounts Receivable, Accounts Payable, and Tax Accounts.
2.  **Level 2: Industry Templates**: Rules provisioned during tenant onboarding. For example, a SaaS company will have subscription invoicing mapped directly to Deferred Revenue (`2400`), while a consulting firm will have professional services mapped to Work-in-Progress (`1250`).
3.  **Level 3: Tenant Overrides**: Rules created by a tenant's financial controller. These take precedence over Level 1 and Level 2 templates.
4.  **Level 4: Country-Specific Overrides**: Rules applied dynamically based on customer shipping or billing locations. These are essential for handling complex local tax requirements like VAT, GST, or regional withholding taxes.

---

## SECTION 7: VALIDATION RULES

Before any ledger entries are committed, the posting engine runs a comprehensive verification checklist. If any check fails, the transaction is rejected, and the error is logged.

### 7.1 Integrity Verification Checklist

*   **Account Existence**: Verify that the resolved `debit_account_id` and `credit_account_id` exist in the database and belong to the correct tenant (`organization_id`).
*   **Account Eligibility**: Confirm that both target accounts are active (`is_active = true`) and have not been soft-deleted.
*   **Period Eligibility**: Verify that the proposed transaction date falls within an open accounting period (`public.accounting_periods.status = 'open'`).
*   **Currency Alignment**: Check that the currency of the transaction matches the allowed currencies of the target accounts. If an account is locked to a specific currency, the transaction currency must align.
*   **Control Account Restraints**: Ensure that transactions targeting control accounts (Accounts Receivable, Accounts Payable, Deferred Revenue) originate from validated system sub-ledgers. Manual journal entries targeting these accounts are strictly blocked.

---

## SECTION 8: TAX ENGINE INTEGRATION

Posting rules must dynamically calculate and allocate tax liabilities (such as VAT, GST, or Sales Tax) to their proper ledger accounts.

```
                     [TAX ENGINE ALLOCATION FLOW]
                        Operational Invoice Line
                                   │
                                   ▼
                       Calculate Net Line Amount
                                   │
                                   ▼
                       Determine Tax Jurisdiction
                     (Destination-Based tax routing)
                                   │
                                   ▼
                      Resolve Active Tax Rule Rate
                                   │
                                   ▼
                 Split Output into Ledger Entries:
                 ├── Debit: Accounts Receivable (Net + Tax)
                 ├── Credit: Revenue (Net Line Amount)
                 └── Credit: Tax Payable (Tax Amount)
```

### 8.1 Tax Rule Scopes

*   **Standard VAT / GST**: Taxes collected on sales are recorded in a Liability account (e.g., `2210 - Output VAT`), while taxes paid on vendor bills are recorded in an Asset account (e.g., `1210 - Input VAT`). At month-end, a settlement journal balances these accounts, resulting in a net payment or refund due to/from the tax authority.
*   **Reverse Charge VAT**: Used for cross-border transactions. The engine simultaneously records a debit to Input VAT and a credit to Output VAT, keeping the net cash impact zero while maintaining a complete audit trail.
*   **Withholding Tax (WHT)**: Deducted directly from payments to contractors or foreign entities. The buyer records the withholding tax as a liability, remitting it directly to the government on the seller's behalf.
*   **Zero-Rated, Exempt, and Tax-Free Scopes**: The engine records the transaction with a `0%` tax rate, mapping the line to specific exempt sales accounts for regulatory tax reporting.

---

## SECTION 9: MULTI-CURRENCY POSTING ACTIONS

Multi-currency transactions must be converted and recorded accurately to prevent balance sheet drift over time.

### 9.1 Multi-Currency Conversion Logic

For every ledger line posted in a foreign currency, the engine:
1.  Retrieves the historical exchange rate for the transaction date from `system.exchange_rates`.
2.  Validates that the rate is positive and within a reasonable variance threshold.
3.  Calculates and records the transaction values:
    ```sql
    debit_amount               numeric(18,2)  -- Foreign transaction currency amount
    exchange_rate_at_posting   numeric(18,6)  -- Exchange rate used (Foreign to Base)
    amount_in_base             numeric(18,2)  -- Base currency equivalent
    ```
4.  Ensures that debits and credits balance in both the transaction currency **and** the base currency equivalent before committing.

---

### 9.2 Realized Gain/Loss Resolution

When settling foreign-currency receivables or payables (e.g., paying a foreign invoice), changes in exchange rates between the billing date and payment date can create balance differences. The engine resolves these differences dynamically:

$$\text{Drift Amount} = \text{Amount In Base}_{\text{Settlement Date}} - \text{Amount In Base}_{\text{Invoicing Date}}$$

*   If **Drift Amount > 0** (for Accounts Receivable):
    *   **Debit**: Bank Account (Settlement value in Base Currency)
    *   **Credit**: Accounts Receivable (Original value in Base Currency)
    *   **Credit**: Realized Forex Gain (`exchange_gain_account_id`)
*   If **Drift Amount < 0**:
    *   **Debit**: Bank Account (Settlement value)
    *   **Debit**: Realized Forex Loss (`exchange_loss_account_id`)
    *   **Credit**: Accounts Receivable (Original value)

This ensures that the sub-ledger balances match historical costs, while exchange rate changes are recorded in the correct income statement accounts.

---

## SECTION 10: SUB-LEDGER INTEGRATION

Sub-ledgers provide granular detail for summary accounts on the General Ledger. The Posting Rule Engine maintains tight, trigger-enforced synchronization between sub-ledgers and the core ledger.

```
                          [SUB-LEDGER COHESION MAP]
                          
  [ BILLING SUB-LEDGER ]                              [ PAYABLES SUB-LEDGER ]
   ├── Invoices                                        ├── Bills
   ├── Cash Receipts                                   ├── Vendor Payments
   └── Credit Notes                                    └── Debit Notes
          │                                                   │
          └─────────────────────────┬─────────────────────────┘
                                    ▼
                         [ Posting Rule Engine ]
                                    │
                                    ▼
                          [ GENERAL LEDGER ]
                     (Accounts Receivable / Payable)
```

To maintain ledger integrity, the engine enforces the following requirements:
*   **No Direct Adjustments**: Accounts Receivable (`1200`) and Accounts Payable (`2100`) are flagged as control accounts, blocking manual journal entries.
*   **Mandatory Sub-ledger Mapping**: Any ledger entry referencing `1200` must include a valid `client_account_id`. Any entry referencing `2100` must include a valid `vendor_id`.
*   **Dynamic Balance Checks**: A database-level check ensures that the summary balance of the Accounts Receivable control account exactly equals the sum of all open, unpaid customer invoices in the billing sub-ledger.

---

## SECTION 11: AUTOMATION & BACKGROUND PROCESSING

The posting engine is built to handle high-volume transaction processing reliably, using asynchronous workers and queue-based management.

### 11.1 Processing Architecture

*   **Asynchronous Queue**: When business events are emitted, they are written to a durable database queue (`audit.outbound_events`). High-performance background workers consume events from this queue, evaluate the posting rules, and write the resulting journal entries to the ledger.
*   **Idempotency and De-duplication**: To prevent duplicate postings from network retries, every event payload must include a unique `idempotency_key`. The engine checks this key before processing, rejecting duplicate requests.
*   **Graceful Recovery**: If a transient database lock occurs, the engine retries the transaction automatically using exponential backoff. If a permanent validation error is found (e.g., a missing account), the event is moved to a dead-letter queue (DLQ), and an alert is sent to financial administrators for review.

---

## SECTION 12: AUDIT & SOC2 TRACEABILITY

To comply with SOC2, GAAP, and IFRS audit standards, every transaction posted to the ledger must maintain complete historical context.

```
                     [SOC2 AUDIT COMPLIANCE RECORD]
                     
     [ Ledger Entry Row ]
       ├── ID: uuid
       ├── Debit / Credit Amount: numeric
       ├── Account: chart_of_accounts_id
       │
       └───► [ Journal Entry Header ]
               ├── Timestamp: timestamptz
               ├── User: user_id
               ├── Posting Rule Used: posting_rule_id
               ├── Originating Event: event_id
               └── Correlation ID: uuid (Traceable to source CRM/Invoice record)
```

This structural link provides complete, end-to-end audit traceability. An auditor can inspect any ledger entry row and trace it directly back to the original posting rule configuration, the resolving event, the authorizing user, and the physical source record (e.g., an invoice PDF).

---

## SECTION 13: ROLE-BASED ACCESS CONTROL (RBAC)

To prevent financial fraud, access to posting rule configurations is strictly controlled based on user roles.

| Role | View Rules | Create Rules | Modify Rules | Publish Rules | Rollback Rules |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Financial Director / CFO** | Yes | Yes | Yes | Yes | Yes |
| **Senior Controller** | Yes | Yes | Yes | Yes | No |
| **Accountant** | Yes | No | No | No | No |
| **Internal Auditor** | Yes | No | No | No | No |
| **IT Admin / Developer** | Yes | No | No | No | No |

*   **The Four-Eyes Principle (Dual Authorization)**: Any creation or modification of a posting rule must be reviewed and approved by a second authorized user before it can be published and applied to active transactions.

---

## SECTION 14: SYSTEM EVENTS

The posting engine emits structured, real-time events to notify downstream services of rule changes and ledger posting status.

### 14.1 Event Definitions

#### `posting_rule.created`
Emitted immediately after a new posting rule is configured.
```json
{
  "event_id": "evt_90284029482",
  "event_type": "posting_rule.created",
  "organization_id": "org_771829",
  "payload": {
    "rule_id": "rule_2384902",
    "event_type": "invoice.sent",
    "debit_account": "1200",
    "credit_account": "4100",
    "created_by": "usr_99218"
  },
  "timestamp": "2026-06-27T09:10:00Z"
}
```

#### `journal.generated`
Emitted when an operational event is successfully processed and translated into a journal entry.
```json
{
  "event_id": "evt_90284029512",
  "event_type": "journal.generated",
  "organization_id": "org_771829",
  "payload": {
    "journal_entry_id": "je_883921",
    "posting_rule_id": "rule_2384902",
    "source_event_id": "evt_112049",
    "total_amount": 1300.00,
    "currency": "USD"
  },
  "timestamp": "2026-06-27T09:10:02Z"
}
```

#### `journal.failed`
Emitted when rule resolution or validation fails, preventing transaction posting.
```json
{
  "event_id": "evt_90284029533",
  "event_type": "journal.failed",
  "organization_id": "org_771829",
  "payload": {
    "source_event_id": "evt_112049",
    "error_code": "ACCOUNT_INACTIVE",
    "error_message": "Target Debit Account 1200 is marked as inactive",
    "event_payload_snapshot": {
      "invoice_id": "inv_0029",
      "amount": 1300.00
    }
  },
  "timestamp": "2026-06-27T09:10:03Z"
}
```

---

## SECTION 15: PERFORMANCE & DATABASE INDEXING

To minimize database overhead during high-volume event processing, the `account_posting_rules` table must include highly optimized index strategies.

```sql
-- 1. Optimizes rule lookup searches by organization and event type
CREATE INDEX posting_rules_lookup_idx 
  ON public.account_posting_rules(organization_id, event_type, is_active)
  WHERE is_active = true;

-- 2. Speeds up range-based searches on rule effective dates
CREATE INDEX posting_rules_timeline_idx 
  ON public.account_posting_rules(effective_from, effective_to);

-- 3. Optimizes conflict-checking queries by checking for duplicate priority assignments
CREATE UNIQUE INDEX posting_rules_priority_conflict_idx 
  ON public.account_posting_rules(organization_id, event_type, priority, effective_from)
  WHERE is_active = true;
```

---

## SECTION 16: FUTURE MODULES EXTENSIBILITY

The indirect event-driven architecture allows for easy expansion into new operational domains. New modules can be added without modifying the core accounting engine.

```
                      [FUTURE MODULE INTEGRATION MAP]
                      
         [ New Module ] ──► Emits Custom Event (e.g., 'pos.sale_completed')
                                    │
                                    ▼
       [ Posting Rule Configurator ] ──► Maps event to target COA Accounts
                                    │
                                    ▼
         [ Posting Engine ] ──► Processes & validates posting rules
                                    │
                                    ▼
               [ General Ledger ] ──► Posts balanced entry
```

To integrate a new module:
1.  Define the business events emitted by the module (e.g., `'manufacturing.work_completed'`).
2.  Add mapping configurations for the events to the database `account_posting_rules` table, specifying the target debit and credit accounts.
3.  The core posting engine will automatically capture, process, and post entries for these new events, requiring zero application code changes.

---

## SECTION 17: ARCHITECTURAL COMPLIANCE CHECKLIST

Verify that migrations and application services comply with the following requirements:

- [ ] **No Hardcoded Accounts**: All debit and credit account mappings must be resolved from the database `account_posting_rules` configurations.
- [ ] **Tenant Isolation Enforced**: All queries executed on the rules table must use the RLS `organization_id` filter.
- [ ] **Balance Reconciliation Checks**: The engine must confirm that total debits match total credits before committing any transaction.
- [ ] **Dual-Authorization Controls**: Any changes to posting rules require a secondary approval before activation.
- [ ] **Foreign Exchange Rates Snapshot**: All foreign currency postings must capture and save historical conversion rates.
- [ ] **Control Account Validation**: Direct manual postings to summary control accounts (Accounts Receivable, Accounts Payable) are blocked.
- [ ] **Durable Audit Trail Traceability**: Every journal ledger line must include references to the originating event, correlation key, and posting rule used.

---
**End of Specification.**
