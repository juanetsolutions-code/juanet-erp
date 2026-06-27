# JUANET ERP Accounting Calendar & Period Close Engine Specification
## Phase 2.3.2E.1C — Accounting Periods, Fiscal Calendar, and Period Close Engine Manual
**Document Version:** 1.0  
**Author:** Chief Enterprise ERP Financial Systems Architect, JUANET Platform  
**Classification:** Technical Specification / Ledger Period Control & Close Automation  

---

## SECTION 1: ACCOUNTING CALENDAR PHILOSOPHY

### 1.1 Core Architecture: Decoupling Ledger Time from Calendar Time
In high-integrity enterprise ERP systems (e.g., SAP S/4HANA, Oracle Fusion Financials), the concepts of "physical date" and "accounting time" are strictly decoupled. 

JUANET enforces this decoupling to prevent operational activity from disrupting statutory financial schedules. While physical business operations happen on physical dates, financial postings always belong to a specific, structurally bounded **Accounting Period** within a **Fiscal Year**.

```
                           [LEDGER TIME DECOUPLING]
                           
      Physical Date (e.g., Jan 3, 2026) ────► Physical Event (e.g., Invoice Sent)
                                                      │
                                                      ▼
  Accounting Period (e.g., DEC-2025) ◄─── Assign Ledger Posting Period (Close Adjustments)
```

This separation is critical for several key reasons:
*   **Late Adjustments**: Allows accountants to post adjustment entries (such as accruals, depreciation, and tax allocations) to December long after January has begun, without distorting active January operations.
*   **Comparability**: Standardizes month-to-month and year-to-year comparative reports by ensuring each period contains exactly the revenues and expenses attributable to it (the matching principle), regardless of when cash changed hands or when physical entries were logged.
*   **Compliance and Close Rules**: Prevents retroactively posting transactions to periods that have been audited and signed off by financial directors.

### 1.2 The Structure of the Fiscal Calendar

```
                                [FISCAL YEAR STRUCTURE]
                                
   [ Period 00: Opening ] ──► [ Periods 01 to 12: Monthly ] ──► [ Period 13: Adjustments ]
            │                                                              │
            ▼                                                              ▼
   Stores inherited opening                                        Auditing and corporate
   balances from past years                                        tax closing adjustments
```

The JUANET fiscal calendar divides physical time into the following structural periods:
1.  **Opening Balance Period (Period 00)**: A zero-duration period at the beginning of each fiscal year. It stores the opening balances carried forward from the prior year's closing balances. It does not accept operational transaction lines.
2.  **Accounting Periods (Periods 01 to 12 / 13)**: Operational periods (typically monthly) where standard day-to-day transactions (Invoices, Bills, Payments) are recorded.
3.  **Adjustment Period (Period 13 / Closing Period)**: A dedicated year-end period used exclusively for post-closing adjustments, auditor entries, and corporate tax provisions before finalizing reports.
4.  **Reporting Periods**: Logical groupings of accounting periods (e.g., Q1, Q2, H1) used to compile aggregated statements for stakeholders.

### 1.3 Period-Bounded Journal Posting Invariant
Every transaction line written to the ledger must be assigned to an active, valid `accounting_period_id`. If a transaction's posting date does not fall within the boundary of its assigned period, the posting engine will reject the transaction, preventing date-shifting drifts.

---

## SECTION 2: FISCAL YEAR CONFIGURATION

### 2.1 Configuration Record Fields: `public.fiscal_years`

The master configuration for a tenant's accounting calendar is defined on the `fiscal_years` entity.

| Column Name | Physical Type | Nullable | Default Value | Validation & Architectural Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Universally unique row identifier. |
| `organization_id` | `uuid` | NO | None | RLS multi-tenant separation key. |
| `year_name` | `varchar(10)` | NO | None | Alphanumeric label (e.g., `'FY2026'`). |
| `start_date` | `date` | NO | None | Start date of the fiscal year. |
| `end_date` | `date` | NO | None | End date of the fiscal year. |
| `current_status` | `varchar(30)`| NO | `'open'` | Status: `'open'`, `'closed'`, `'locked'`. |
| `closed_by` | `uuid` | YES | `NULL` | Reference to the CFO/Director who closed the year. |
| `closed_at` | `timestamptz` | YES | `NULL` | Timestamp when the year was finalized. |
| `reopened_by` | `uuid` | YES | `NULL` | Reference to the auditor who reopened the year. |
| `reopened_at` | `timestamptz` | YES | `NULL` | Timestamp of the reopening action. |
| `country` | `varchar(2)` | NO | `'US'` | ISO country code defining local tax rules. |
| `reporting_currency`| `uuid` | NO | None | Base currency used for year-end reporting. |

---

### 2.2 Supported Fiscal Calendars

The calendar generation engine supports multiple operational structures:

*   **Calendar Year**: A standard calendar starting on January 1st and ending on December 31st.
*   **Custom Fiscal Year**: Any 12-month period configured by the tenant (e.g., April 1st to March 31st for UK entities, or July 1st to June 30th for Australian entities).
*   **13-Period Accounting**: A system dividing the year into 13 equal 4-week periods (exactly 28 days each). This system is used widely in retail and hospitality to ensure each period has the exact same number of weekend days, improving comparative analysis.
*   **4-4-5 Calendar**: Divides each fiscal quarter into three periods of 4 weeks, 4 weeks, and 5 weeks respectively. This ensures quarters always end on the same day of the week (e.g., the last Saturday of March), making week-over-week tracking cleaner.

---

### 2.3 Calendar Validation Rules

*   **No Overlapping Dates**: No two fiscal years for the same organization can share overlapping date ranges:
    $$\text{End Date}_{\text{FY}_n} < \text{Start Date}_{\text{FY}_{n+1}}$$
*   **Sequential Continuation**: The start date of a new fiscal year must be exactly one day after the end date of the prior year.
*   **Currency Invariance**: The `reporting_currency` of the fiscal year must match the organization's base currency, preventing reporting translation drifts.

---

## SECTION 3: ACCOUNTING PERIOD STATES

Accounting periods transition through several states over their lifecycle, defining exactly what types of postings they can accept.

```
                            [PERIOD STATE TRANSITIONS]
                            
       [ OPEN State ] ───────► [ SOFT CLOSED State ] ───────► [ HARD CLOSED State ]
             │                         │                              │
             ▼                         ▼                              ▼
     All postings allowed      Tax / Adjustments only       Immutable (Locked)
```

### 3.1 Lifecycle States

1.  **OPEN**: The default operational state. All validated transactions (Invoices, Bills, Cash Receipts, General Journals) can be posted freely.
2.  **SOFT CLOSED**: Day-to-day operations (Billing, Purchasing, Payments) are locked. No new bills or invoices can be posted to this period. However, adjusting journal entries (e.g., depreciation, deferred revenue recognition, and tax accruals) can still be posted by authorized controllers.
3.  **HARD CLOSED**: The period is fully locked. No transactions or adjustments of any kind can be posted. Triggers reject any database modifications targeting this period.
4.  **ARCHIVED**: The period has been audited and finalized. Transaction logs are marked read-only at the database engine level. This state is irreversible except by a specialized system override.

---

## SECTION 4: PERIOD LOCKING ENGINE

The Period Locking Engine provides granular control over who can post to specific ledger modules when closing a period.

```
                        [MODULE-LEVEL LOCK SCHEMES]
                        
                        [ Accounting Period: DEC ]
                                     │
         ┌───────────────────────────┼───────────────────────────┐
         ▼                           ▼                           ▼
  [ AR Sub-ledger ]           [ AP Sub-ledger ]           [ GL Adjustments ]
    Status: LOCKED              Status: LOCKED              Status: OPEN
     (No Invoices)               (No Bills)                  (Accruals Allowed)
```

### 4.1 Locking Schemes

*   **Soft Lock**: Temporary lock applied to operational sub-ledgers (AR, AP) while the finance team reviews reconciliations. It can be toggled on and off easily by the Senior Controller.
*   **Hard Lock**: Full period lock. All sub-ledgers and general journals are locked, preventing any database insertions or updates.
*   **Module Lock**: Allows controllers to lock individual sub-ledgers separately. For example, Accounts Payable can be locked on day 1 of close, while Accounts Receivable remains open until day 3.
*   **User Override**: Allows designated senior administrators to post entries to a soft-locked period without reopening the period for other users.
*   **Emergency Auditor Override**: A secure, audit-logged process that allows certified auditors to post corrections to a hard-locked period, requiring dual authorization from the CFO.

---

### 4.2 Required Role Permissions

| Role | Apply Soft Lock | Apply Hard Lock | Assign Module Lock | Post Adjustments | Emergency Reopen |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Financial Director / CFO** | Yes | Yes | Yes | Yes | Yes |
| **Senior Controller** | Yes | No | Yes | Yes | No |
| **Lead Auditor** | No | No | No | Yes | Yes |
| **Accountant** | No | No | No | No | No |

---

## SECTION 5: POSTING VALIDATION ENGINE

Every transaction sent to the ledger passes through a strict validation pipeline to verify the target accounting period is open and eligible to receive entries.

```
                        [POSTING VALIDATION FLOW]
                         Incoming Ledger Posting
                                    │
                                    ▼
                         Resolve Posting Date
                                    │
                                    ▼
                        Does Active Period Exist? ─── No ──► [ REJECT ]
                                    │ Yes
                                    ▼
                        Check Period Status:
                        ├── Status is 'HARD CLOSED' ────────► [ REJECT ]
                        ├── Status is 'ARCHIVED' ───────────► [ REJECT ]
                        └── Status is 'SOFT CLOSED'
                                    │
                                    ▼
                        Is User Authorized? ───────── No ──► [ REJECT ]
                                    │ Yes
                                    ▼
                          Does Module Lock Apply? ─── Yes ─► [ REJECT ]
                                    │ No
                                    ▼
                                [ COMMIT ]
```

### 5.1 Validation Matrix

| Target Period State | Transaction Type | User Role | Posting Engine Action |
| :--- | :--- | :--- | :--- |
| **`OPEN`** | Any Posting | Any Role | **COMMIT** |
| **`SOFT CLOSED`** | Customer Invoice | Staff Accountant | **REJECT** (Invoicing is locked) |
| **`SOFT CLOSED`** | Accrual Journal | Senior Controller | **COMMIT** (Adjustments allowed) |
| **`SOFT CLOSED`** | Accrual Journal | Staff Accountant | **REJECT** (Requires senior role) |
| **`HARD CLOSED`** | Any Transaction | Senior Controller | **REJECT** (Hard lock is absolute) |
| **`HARD CLOSED`** | Auditor Adjustment | CFO + Auditor | **COMMIT** (With Emergency Override) |
| **`ARCHIVED`** | Any Transaction | CFO | **REJECT** (Archived periods are immutable) |

---

## SECTION 6: REOPENING CLOSED PERIODS

Reopening a previously closed accounting period is a high-risk action that can change historical financial reports. JUANET enforces strict security and audit controls around this process.

```
                      [REOPENING AUTHORIZATION FLOW]
                         Reopen Period Request
                                   │
                                   ▼
                       CFO / Director Initiates
                                   │
                                   ▼
                        Auditor Approval Required
                     (Enforces Four-Eyes Principle)
                                   │
                                   ▼
                       Validate Reopen Window
                     (Must be within max allowed days)
                                   │
                                   ▼
                      Execute Period Status Change:
                      - Emit Warning Event
                      - Log User, Timestamp, and Reason
                      - Generate Immutable Audit Log
```

### 6.1 Reopening Rules & Controls

*   **Four-Eyes Principle (Dual Authorization)**: A hard-locked period cannot be reopened by a single user. The request must be initiated by the CFO and approved by a second authorized controller or external auditor.
*   **Time Limitations**: By default, the system restricts the reopening of periods to a maximum window of **90 days** from the initial close date. Reopening periods older than 90 days requires system administrator override.
*   **Audit Requirements**: Every reopening action must log the requesting user, approving user, timestamp, and a required business justification. This log is written to an immutable audit table (`audit.period_reopen_logs`) to satisfy SOC2 compliance standards.

---

## SECTION 7: YEAR-END CLOSE ENGINE

The Year-End Close Engine is a highly automated process that zeroes out temporary accounts, calculates net income, and rolls balances forward to the next fiscal year.

```
                      [YEAR-END ENGINE EXECUTION]
                      
   [ Temporary Accounts ] (P&L)          [ Permanent Accounts ] (Balance Sheet)
    ├── Revenue Accounts                  ├── Cash & Assets
    └── Expense Accounts                  ├── Liabilities
             │                            └── Shareholder Equity
             ▼                                     │
   Close P&L Balances to                           │ Carry Forward
   Retained Earnings (3200)                        ▼
             │                        [ Period 00: Opening Balances ]
             ▼                                     │
   Set P&L Balances to Zero                        ▼
                                      Balances carried forward intact
```

### 7.1 Permanent vs. Temporary Accounts
*   **Temporary Accounts**: Accounts that track financial activity within a single fiscal year (Revenue, COGS, Expenses). During the year-end close, their balances are summarized and reset to zero.
*   **Permanent Accounts**: Accounts that track long-term value (Assets, Liabilities, Equity). Their balances are carried forward intact into the new fiscal year's opening period (Period 00).

---

### 7.2 Year-End Closing Sequence

```
[SYSTEM WORKFLOW: YEAR-END CLOSE]
 1. LOCK the final operational period (Period 12) and the Adjustment Period (Period 13).
 2. CALCULATE P&L Balance: Total P&L Revenue minus Total P&L Expenses.
 3. GENERATE Closing Journal Entry:
     a. Debit all credit-balance revenue accounts to bring their balances to zero.
     b. Credit all debit-balance expense accounts to bring their balances to zero.
     c. Record the net difference (Net Income) to Retained Earnings (Account 3200).
 4. SUM and verify Balance Sheet accounts (Assets, Liabilities, Equity).
 5. GENERATE Opening Balances (Period 00) for the next fiscal year.
 6. UPDATE Fiscal Year Status to 'CLOSED' and apply hard locks.
```

---

### 7.3 Balance Sheet Rollovers

*   **Retained Earnings Calculation**: Net income calculated from closed P&L accounts is added to the starting Retained Earnings balance:
    $$\text{Retained Earnings}_{\text{Closing}} = \text{Retained Earnings}_{\text{Opening}} + \text{Net Income}_{\text{Fiscal Year}}$$
*   **Balance Rollforward**: Assets, Liabilities, and Equity balances are written to the next fiscal year's opening period (Period 00):
    $$\text{Opening Balance}_{\text{FY}_{n+1}, \text{Period } 00} = \text{Closing Balance}_{\text{FY}_n, \text{Period } 13}$$

---

### 7.4 Failure Recovery & Rollback Invariance
If a year-end close process fails midway (e.g., due to a database timeout or a validation failure), the entire routine is rolled back instantly. The database transaction boundary guarantees that either the entire fiscal year close completes successfully, or the system reverts to its pre-close state, leaving no partially closed entries or unbalanced lines.

---

## SECTION 8: ADJUSTMENT PERIOD POSTINGS

Adjustment periods (typically designated as Period 13) are used to record year-end bookkeeping adjustments without cluttering normal monthly reports.

```
                      [ADJUSTMENT PERIOD POSITION]
                      
  [ Operational Period 12 ] ────► Normal December Transactions (e.g., Invoices, Payroll)
  
  [ Adjustment Period 13 ] ─────► Year-End Reclassifications, Tax adjustments, Auditor adjustments
```

Typical adjustments recorded in Period 13 include:
*   **Auditor Reclassifications**: Adjustments recommended by external auditors during the year-end financial review.
*   **Corporate Tax Provisions**: Recording accrued tax liabilities for the fiscal year.
*   **Prior Period Corrections**: Fixing minor errors found in earlier months of the same fiscal year.
*   **Amortization of Intangible Assets**: Posting annual write-offs of goodwill, patent values, or software assets.

---

## SECTION 9: INTER-MODULE CLOSING DEPENDENCIES

In an enterprise ERP, all operational modules must complete their postings and freeze their sub-ledgers before the accounting period can be closed.

```
                    [MODULE CLOSE DEPENDENCIES]
                    
     [ CRM Invoicing ] ──► [ Subscription Engine ] ──► [ Project Costs ]
             │                       │                      │
             └───────────────────────┼──────────────────────┘
                                     ▼
                        [ Billing Sub-ledger (AR) ]
                                     │
                                     ▼
                          [ Accounts Payable (AP) ]
                                     │
                                     ▼
                        [ Cash & Bank Reconciliations ]
                                     │
                                     ▼
                       [ General Ledger Period Close ]
```

### 9.1 Module Interlocking Rules

*   **CRM & Billing**: All invoices must transition out of the `'draft'` state. No invoices can be edited or deleted once the Accounts Receivable sub-ledger close begins.
*   **Subscriptions**: All deferred revenue recognition schedules for the month must be executed and recognized to revenue.
*   **Project Costs**: All approved employee timesheets and expense reports must be billed and posted to their respective projects.
*   **Inventory**: Physical stock valuations and Cost of Goods Sold (COGS) adjustments must be completed and posted.
*   **Fixed Assets**: Monthly depreciation runs must execute, calculating and posting depreciation charges to the general ledger.
*   **Reconciliations**: All bank statement feeds for the period must be imported and reconciled, bringing the Bank Account ledger balance in line with actual bank statements.

---

## SECTION 10: PRODUCTION PERIOD CLOSE CHECKLIST

Before a Senior Controller or CFO can transition an accounting period to `'HARD CLOSED'`, the system runs automated audits to verify that the following tasks are complete.

### 10.1 System Compliance Check List

- [ ] **No Unposted Transactions**: Check that all draft invoices, bills, and journal entries are either posted or moved to the following period.
- [ ] **Completed Revenue Recognition**: Verify that all deferred revenue schedules for the period have run and recognized active subscription balances.
- [ ] **Executed Depreciation Runs**: Ensure that monthly depreciation routines have run and posted charges for all active fixed assets.
- [ ] **Completed Bank Reconciliations**: Confirm that all bank feeds are reconciled and match actual bank statements.
- [ ] **Executed Forex Revaluations**: Verify that month-end currency revaluation has run, updating foreign assets and liabilities to current spot rates.
- [ ] **Closed Sub-ledgers**: Confirm that Accounts Receivable, Accounts Payable, and Inventory sub-ledgers are locked.
- [ ] **No Allocation Backlogs**: Verify that all cost center and department allocation rules for the period have been executed and posted.
- [ ] **Balanced Trial Balance**: Confirm that the sum of all debits exactly equals the sum of all credits across the entire Chart of Accounts.

---

## SECTION 11: CONCURRENCY & BACKGROUND CLOSING PATTERNS

Because the period close process touches millions of transaction records, the engine uses robust background architectures to ensure data safety and prevent performance slowdowns.

```
                      [CONCURRENCY CLOSE ENGINE]
                        Trigger Close Request
                                  │
                                  ▼
                     Acquire Tenant-Level Mutex
               (Blocks other postings during close run)
                                  │
                                  ▼
                   Initialize Closing Workers (Batching)
               (Processes transactions in blocks of 5,000)
                                  │
                                  ▼
                     Write Balanced Journal Entries
                                  │
                                  ▼
                         Release Mutex Lock
```

### 11.1 Concurrency Safeguards

*   **Tenant Mutex Lock**: When a period close runs, the engine acquires a tenant-level lock (`pg_try_advisory_xact_lock` at the database level). This blocks any other concurrent posting attempts by that tenant's users until the close finishes.
*   **Batch Processing**: Transaction processing is divided into chunks of 5,000 rows. This keeps memory usage low and prevents long-lasting database lockups.
*   **Idempotent Execution**: If a background close worker is interrupted (e.g., due to a pod restart), the job can be restarted safely. The engine checks existing transaction IDs before processing, preventing duplicate postings.

---

## SECTION 12: ROLE-BASED ACCESS CONTROL & SECURITY

To prevent financial fraud and comply with SOC2 audit standards, access to period locks and calendar configurations is tightly controlled.

### 12.1 Security & Compliance Measures
*   **Immutable Change Logs**: All changes to period states, calendar dates, or module locks are written to an append-only audit table (`audit.period_change_logs`). This table cannot be updated or deleted.
*   **Approval Chains**: Reopening a period or changing the status of a fiscal year requires dual-authorization (the four-eyes principle). The system automatically routes these requests to the designated approvers, tracking the complete history of approvals and rejections.

---

## SECTION 13: REAL-TIME SYSTEM EVENTS

The calendar engine emits structured events on period transitions, allowing downstream services to respond dynamically.

### 13.1 Event Definitions

#### `period.soft_closed`
```json
{
  "event_id": "evt_501829482",
  "event_type": "period.soft_closed",
  "organization_id": "org_771829",
  "payload": {
    "accounting_period_id": "per_dec_2025",
    "period_name": "DEC-2025",
    "locked_modules": ["ar_billing", "ap_purchasing"],
    "closed_by": "usr_99218"
  },
  "timestamp": "2026-01-02T18:00:00Z"
}
```

#### `period.closed`
```json
{
  "event_id": "evt_501829512",
  "event_type": "period.closed",
  "organization_id": "org_771829",
  "payload": {
    "accounting_period_id": "per_dec_2025",
    "period_name": "DEC-2025",
    "fiscal_year_id": "fy_2025",
    "audited_by": "usr_00192"
  },
  "timestamp": "2026-01-05T21:30:15Z"
}
```

#### `year.closed`
```json
{
  "event_id": "evt_501829530",
  "event_type": "year.closed",
  "organization_id": "org_771829",
  "payload": {
    "fiscal_year_id": "fy_2025",
    "year_name": "FY2025",
    "retained_earnings_transferred": 420500.00,
    "closing_journal_entry_id": "je_90281"
  },
  "timestamp": "2026-01-05T21:35:00Z"
}
```

---

## SECTION 14: DATABASE PERFORMANCE & INDEXING

To ensure fast financial calculations and real-time report generation, the database must include targeted indexes.

```sql
-- 1. Speeds up period status verification queries during transaction validation
CREATE INDEX acc_period_status_lookup_idx 
  ON public.accounting_periods(organization_id, status)
  WHERE status IN ('open', 'soft_closed');

-- 2. Optimizes range searches for dates within active accounting periods
CREATE INDEX acc_period_timeline_idx 
  ON public.accounting_periods(organization_id, start_date, end_date);

-- 3. Composite covering index for fast retrieval of historical fiscal years
CREATE INDEX fiscal_years_composite_idx 
  ON public.fiscal_years(organization_id, start_date)
  INCLUDE (current_status, reporting_currency);
```

---

## SECTION 15: FUTURE ARCHITECTURAL EXPANSION

The calendar and closing engine is designed to scale alongside growing international enterprises.

### 15.1 Multi-Entity Consolidations
*   **Staggered Closing Schedules**: Future support for consolidated multi-company environments. Subsidiaries can be closed on different schedules, with intercompany transactions locked and consolidated at the parent level at year-end.
*   **Parallel Ledgers**: Future support for parallel ledgers, allowing organizations to maintain different books for IFRS and local GAAP. Transactions can be posted and closed on different schedules within each ledger, ensuring compliance with both international and local requirements.

---

## SECTION 16: ARCHITECTURAL COMPLIANCE CHECKLIST

Verify that migrations and application services comply with the following requirements:

- [ ] **Complete Date Separation**: Ledger postings are assigned to distinct, structurally bounded accounting periods rather than calendar dates.
- [ ] **Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all calendar and period state tables.
- [ ] **Strict Status Validation**: All transaction postings are validated against active period states before being committed.
- [ ] **Sub-ledger Interlock Controls**: Accounts Receivable, Accounts Payable, and Inventory sub-ledgers must be locked before closing an accounting period.
- [ ] **Automated Year-End Closing**: P&L account balances are closed out to Retained Earnings automatically, resetting temporary accounts to zero for the next fiscal year.
- [ ] **Dual-Authorization for Reopening**: Reopening a previously closed period requires dual-authorization and a logged business justification.
- [ ] **Audit Trail Traceability**: All changes to period states, calendar dates, or module locks are recorded in immutable audit logs.

---
**End of Specification.**
