# JUANET ERP Accounts Receivable & Payment Allocation Engine Specification
## Phase 2.3.2E.2B — Accounts Receivable & Payment Allocation Engine Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Accounts Receivable, Treasury, and Cash Application Core  

---

## SECTION 1: ARCHITECTURAL PHILOSOPHY

In standard, high-integrity enterprise financial systems (e.g., SAP S/4HANA, NetSuite ERP, Oracle Fusion Financials), **Accounts Receivable (AR)** is structured as an independent financial subsystem that remains decoupled from the operational billing engine. This decoupling is a core architectural requirement to preserve audit trails, maintain tax compliance, and protect data integrity.

```
                         [DECOUPLED AR TRANSACTION PIPELINE]
                         
   ┌────────────────────────────────┐       ┌────────────────────────────────┐
   │     OPERATIONAL BILLING        │       │      ACCOUNTS RECEIVABLE       │
   │  - Line items & discounts      │       │  - Outstanding debt ledgers     │
   │  - Regional tax calculations   │ ────► │  - Cash application engine     │
   │  - Invoice delivery channels   │       │  - Customer credit management   │
   │  - Locked tax basis (Immutable)│       │  - Dynamic collections state   │
   └────────────────────────────────┘       └───────────────┬────────────────┘
                                                            │
                                                            ▼
                                           ┌────────────────────────────────┐
                                           │       TREASURY & GENERAL       │
                                           │  - Bank wire & card receipts   │
                                           │  - Balanced double postings    │
                                           │  - Realized currency FX gains  │
                                           └────────────────────────────────┘
```

### 1.1 Independence of the AR Subsystem

Operational invoice documents represent the **time-of-supply tax basis** and legal billing event. They are physically immutable once issued. The AR subsystem, by contrast, is a dynamic ledger of financial claims that must handle cash receipts, partial allocations, overpayments, dispute holds, and write-offs over time.

By isolating AR from the billing engine, JUANET ensures that:
1.  **Invoices Never Hold Payment Data**: Invoices store what the customer *should* pay, including subtotals, tax rates, and contract rates. Direct payment information, bank transaction references, or payment attempt status keys are isolated in independent transactional tables.
2.  **Preservation of Tax reporting**: If a payment fails, is disputed, or is partially settled, the tax reporting basis established by the issued invoice remains unmodified. Adjustments are written to separate AR allocation records, preventing historical tax reporting modifications.
3.  **Strict Transaction Separation**:
    *   **Legal Debt**: Represented by the outstanding AR ledger item, tracking the customer's legal liability.
    *   **Cash Receipt**: Represented by the inbound bank wire or credit card settlement logged by the Treasury module.
    *   **Ledger Posting**: The double-entry journal entries generated to increase cash and reduce the AR control asset account.
    *   **Reconciliation**: The matching process that binds cash receipts to outstanding AR items.

---

### 1.2 Event-Driven Cash Application

The cash application process is entirely event-driven. Inbound payments from banks, direct debits, or credit card gateways emit `payment.received` events. The Cash Allocation Engine consumes these events, executes matching rule algorithms, maps cash to one or more outstanding AR rows, updates invoice balances, and triggers real-time double-entry journal postings to the General Ledger.

---

## SECTION 2: RECEIVABLE LIFECYCLE

The lifecycle of an accounts receivable line item is governed by a deterministic state machine, running parallel to the parent invoice's state.

```
                             [RECEIVABLE LIFECYCLE FSM]
                             
                               ┌──────────────────────┐
                               │         OPEN         │
                               └──────────┬───────────┘
                                          │
                  ┌───────────────────────┼───────────────────────┐
                  ▼                       ▼                       ▼
        ┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐
        │  PARTIALLY PAID   │   │    IN DISPUTE     │   │     CANCELLED     │
        └─────────┬─────────┘   └─────────┬─────────┘   └───────────────────┘
                  │                       │
                  └───────────┬───────────┘
                              ▼
                        ┌───────────┐
                        │   PAID    │
                        └─────┬─────┘
                              │
                  ┌───────────┴───────────┐
                  ▼                       ▼
        ┌───────────────────┐   ┌───────────────────┐
        │     REFUNDED      │   │    WRITTEN OFF    │
        └───────────────────┘   └───────────────────┘
```

### 2.1 Accounts Receivable States

#### 2.1.1 `OPEN`
*   **Entry Conditions**: Triggered automatically when an invoice is transitioned to `'issued'` state, generating a row in the `public.accounts_receivable` table with `remaining_balance == original_amount`.
*   **Exit Conditions**: Cash receipt allocation, cancellation, or manual dispute flagging.
*   **Prohibited Transitions**: Cannot transition directly to `Refunded` or `Written Off` without intermediate transactions.
*   **Ledger Implications**: Debits the Accounts Receivable Asset Control account, Credits Sales Revenue and Tax Liability accounts.

#### 2.1.2 `PARTIALLY_PAID`
*   **Entry Conditions**: A payment allocation is recorded where `0.00 < allocated_amount < remaining_balance`.
*   **Exit Conditions**: Additional payments bring the balance to `0.00`, or a write-off or credit note settles the remaining balance.
*   **Prohibited Transitions**: Cannot transition directly to `Open` without a complete payment reversal.
*   **Ledger Implications**: Debits Cash/Bank Account, Credits Accounts Receivable Asset Control account for the allocated portion.

#### 2.1.3 `PAID`
*   **Entry Conditions**: Sum of all validated allocations matches the original outstanding amount, bringing `remaining_balance` to exactly `0.00`.
*   **Exit Conditions**: Reversal of an allocated payment or cash refund.
*   **Prohibited Transitions**: Cannot transition directly to `In Dispute` or `Written Off` while balance is zero.
*   **Ledger Implications**: Accounts Receivable Asset balance is zeroed out for this document; funds are fully applied to cash assets.

#### 2.1.4 `OVERPAID`
*   **Entry Conditions**: Payment allocations exceed the original receivable amount.
*   **Exit Conditions**: Re-allocating the excess cash to other invoices, or issuing a refund.
*   **Prohibited Transitions**: Cannot transition to `Written Off`.
*   **Ledger Implications**: Invoice balance is zeroed. The excess cash is debited, and an offsetting Credit Liability is registered in the Unapplied Cash liability account.

#### 2.1.5 `IN_DISPUTE`
*   **Entry Conditions**: Client challenges invoice calculations, triggering an administrative hold.
*   **Exit Conditions**: Resolution of dispute (rejection of claim or Credit Note adjustment).
*   **Prohibited Transitions**: Cannot transition directly to `Written Off` without exhausting review workflows.
*   **Ledger Implications**: No direct journal entries. It flags the sub-ledger to halt automatic collections alerts and past-due interest accruals.

#### 2.1.6 `WRITTEN_OFF`
*   **Entry Conditions**: Authorization by the CFO to classify a balance as uncollectible.
*   **Exit Conditions**: Terminal state (except in rare cases of post-write-off cash recovery).
*   **Prohibited Transitions**: Cannot transition to any active operational state.
*   **Ledger Implications**: Debits the Allowance for Doubtful Accounts (Contra-Asset) or Bad Debt Expense account, and Credits the Accounts Receivable Asset Control account.

#### 2.1.7 `CANCELLED`
*   **Entry Conditions**: Voiding of the underlying invoice, accompanied by a linking Credit Note.
*   **Exit Conditions**: Terminal archival state.
*   **Prohibited Transitions**: All active payment states.
*   **Ledger Implications**: Complete reversal posting (Credits Accounts Receivable, Debits Revenue / Tax liability accounts).

#### 2.1.8 `REFUNDED`
*   **Entry Conditions**: Executing a cash refund against a previously settled receivable.
*   **Exit Conditions**: Reopens the receivable balance, or closes it permanently if accompanied by a corresponding Credit Note.
*   **Prohibited Transitions**: Cannot transition to `Partially Paid` without transactional justification.
*   **Ledger Implications**: Debits Accounts Receivable Asset (re-instating the debt) or Revenue, Credits Cash/Bank Account.

---

## SECTION 3: PAYMENT ALLOCATION ENGINE

The Payment Allocation Engine automates the matching of inbound cash receipts to outstanding accounts receivable balances. It supports both automated batch matching and fine-grained manual applications.

```
                           [PAYMENT ALLOCATION FLOW]
                           
                           [ Inbound Payment Event ]
                                      │
                                      ▼
                        Check for Manual Allocation
                        ├── Yes ──► Direct Application to Invoice(s)
                        └── No  ──► Execute Matching Rule Waterfall
                                           │
         ┌─────────────────────────────────┼─────────────────────────────────┐
         ▼                                 ▼                                 ▼
   [ Invoice Match ]               [ Balance Match ]                  [ FIFO Match ]
   Match payment reference         Match payment amount             Apply payment to oldest
   directly to invoice number.     to net customer balance.         outstanding invoice.
```

### 3.1 Allocation Rule Catalog

1.  **Direct Invoice Matching**: The engine parses the inbound payment's reference metadata (e.g., ACH/Wire message payload). If it matches an active invoice number (e.g., `'INV-2026-0418'`), cash is directly allocated to that invoice.
2.  **Customer Net Balance Matching**: If the incoming payment matches the customer's total outstanding AR balance, the engine allocates the payment across all open invoices, bringing the customer's net balance to zero.
3.  **First-In, First-Out (FIFO) Match**: If no reference matches and the payment is generic, cash is applied to the oldest outstanding invoices first based on their `due_date` or `issue_date`.
4.  **Proportional Allocation**: Applied to parent-subsidiary billing, distributing cash proportionally across all active invoices based on each document's weight in the total outstanding debt.
5.  **Deposit Drawdown**: Dynamic allocation of existing customer deposits or retainers to settle newly issued invoices.
6.  **Unapplied Prepayments**: Payments received in advance are placed in a liabilities holding account (`customer_credit_balances`) and automatically applied to the next billing cycle run.

---

### 3.2 Deterministic Allocation Waterfall

When a payment is received without manual application rules, the matching engine runs a strict, sequential waterfall to resolve the allocation:

```
Step 1: Exact Match on Reference ID (Invoice Number / Order Reference ID)
   ↳ IF Found: Allocate to target invoice. Stop.
   ↳ ELSE: Proceed to Step 2.

Step 2: Exact Match on Customer Outstanding Net Balance
   ↳ IF Payment Amount matches Total Customer Balance: Allocate and close all open invoices. Stop.
   ↳ ELSE: Proceed to Step 3.

Step 3: Single Invoice Amount Match
   ↳ IF Payment Amount matches exactly one outstanding invoice balance for that customer: Allocate. Stop.
   ↳ ELSE: Proceed to Step 4.

Step 4: FIFO (First-In, First-Out) Application
   ↳ Allocate cash sequentially to the oldest open invoice first.
   ↳ Loop until the payment is fully exhausted.
   ↳ IF a remaining payment balance exists: Write excess to Unapplied Cash holding accounts.
```

---

## SECTION 4: PARTIAL PAYMENTS & ADJUSTMENTS

When a customer pays less than the total invoice amount, the system handles the outstanding portion through precise, rule-based calculations.

### 4.1 Partial Payment Treatment

*   **Balance Realignment**: Inbound cash allocation updates the receivable line dynamically:
    $$\text{Remaining Balance}_{\text{new}} = \text{Remaining Balance}_{\text{old}} - \text{Allocated Cash}$$
*   **Late Interest Accruals**: Outstanding balances that cross the due date accrue interest based on configured corporate policies (e.g., 1.5% monthly compound interest). Interest is posted as a separate ledger line item and does not modify the original invoice subtotal.
*   **Recalculation of Prompt Payment Discounts**:
    *   If terms specify a discount (e.g., `'2/10 Net 30'` — 2% discount if paid in 10 days), and a partial payment arrives within the 10-day window, the discount is applied proportionally only to the settled portion of the debt.
    *   **Example**: On a $1,000 invoice with 2/10 terms, a partial cash payment of $490 arrives on Day 5. The settled receivable is calculated as:
        $$\text{Settled Debt} = \frac{\$490}{1 - 0.02} = \$500$$
        The outstanding receivable balance is reduced by $500, leaving a remaining balance of $500, with a $10 discount recognized in the ledger.

---

## SECTION 5: OVERPAYMENTS & UNAPPLIED CASH

When a payment exceeds the outstanding balance of the target invoice, the excess funds must be secured and tracked within a controlled credit lifecycle.

```
                         [OVERPAYMENT & CREDIT LIFE]
                         
                          [ Inbound Payment $1,200 ]
                                      │
                                      ▼
                        [ Target Invoice AR $1,000 ]
                                      │
                     ┌────────────────┴────────────────┐
                     ▼                                 ▼
           [ Allocate $1,000 ]                 [ Excess Cash $200 ]
            Invoice marked PAID                        │
                                                       ▼
                                         [ public.customer_credit_balances ]
                                         - Added as Liability Credit
                                         - Emits credit.issued event
                                                       │
                     ┌─────────────────────────────────┴─────────────────────────────────┐
                     ▼                                                                   ▼
       [ Auto-Apply to Next Invoice ]                                            [ Refund Request ]
       Applies $200 credit to invoice                                            Initiates cash payout
       when issued.                                                              reversal.
```

### 5.1 Excess Cash Governance

1.  **Isolation from Invoice Total**: Overpaid amounts are never appended to the invoice's financial totals. The invoice's `balance_due` is capped at $0.00.
2.  **Liability Ledger Posting**: The excess cash amount is written to the customer's credit account (`public.customer_credit_balances`) and posted to the **Unapplied Cash Holding** liability account in the General Ledger.
3.  **Statement Propagation**: Customer statements show the credit balance as a deduction from their total outstanding accounts receivable balance.
4.  **Dynamic Clearing**: When a new invoice is issued for the customer, the system checks their active credit balance. If a credit balance exists, the system automatically applies the credit to the invoice, creating an allocation record and reducing both the invoice balance and the credit balance.

---

## SECTION 6: CUSTOMER DEPOSITS & RETAINERS

Deposits represent advance payments received from customers for services or products to be delivered in the future (e.g., implementation retainers or custom hardware builds).

### 6.1 Deposit Workflow and Application

*   **Deposit Receipt**: When a customer pays a deposit, the system creates a row in the `public.customer_deposits` table.
*   **Accounting Entry**:
    *   **Debit**: Cash / Bank Account (Asset)
    *   **Credit**: Customer Deposits / Deferred Revenue (Liability)
*   **Drawdown Application**: Upon delivery of services or products, the billing run generates a standard invoice. The matching engine applies the deposit balance to the invoice, writing corresponding journal entries:
    *   **Debit**: Customer Deposits / Deferred Revenue (Liability)
    *   **Credit**: Accounts Receivable (Asset)
*   **Refunds and Expiration**: Unused deposits are eligible for refunds based on contract terms. Expired deposits are recognized as revenue (breakage) under ASC 606 standards:
    *   **Debit**: Customer Deposits / Deferred Revenue (Liability)
    *   **Credit**: Misc Revenue / Breakage Income (Revenue)

---

## SECTION 7: CREDIT ALLOCATION & ADJUSTMENT ENGINE

The Credit Allocation Engine governs non-cash adjustments that reduce outstanding receivables, such as Credit Notes, promotional allowances, rebates, and adjustments for billing errors.

```
                      [CREDIT ENGINE ALLOCATION SEGREGATION]
                      
   ┌────────────────────────────────┐       ┌────────────────────────────────┐
   │          CREDIT NOTE           │       │       PROMOTIONAL CREDIT       │
   │  - Issued for billing errors   │       │  - Issued for customer loyalty │
   │  - Reduces original sales tax  │       │  - Does not affect sales tax   │
   │  - Linked to original invoice  │       │  - Posted to marketing expense │
   └───────────────┬────────────────┘       └───────────────┬────────────────┘
                   │                                        │
                   └───────────────────┬────────────────────┘
                                       ▼
                       [ Credit Allocation Subsystem ]
                                       │
                                       ▼
                     - Reduces accounts_receivable balance.
                     - Updates remaining_balance columns.
```

### 7.1 Credit Type Governance

*   **Credit Notes (Reversing Invoices)**: Issued to correct billing errors or handle product returns. These adjustments reduce both the Accounts Receivable balance and the corresponding Sales Tax/VAT liabilities.
*   **Manual Adjustments (Goodwill)**: Credits granted directly to customers for relationship management or service issues. These adjustments do not alter tax liabilities and are posted directly to a dedicated marketing or goodwill expense account.
*   **Rebates & Volume Allowances**: Automated credits calculated and applied when a customer's total purchase volume crosses configured contract thresholds.
*   **Loyalty & Promotional Credits**: Non-tax-deductible credits issued to incentivize purchases. These are tracked in distinct ledger accounts (e.g., Marketing Promotion Expense).

---

## SECTION 8: WRITE-OFF ENGINE

When collection activities are exhausted and an outstanding receivable is classified as uncollectible, the Write-Off Engine removes the balance from active receivables.

### 8.1 Write-Off Classifications and Materiality

1.  **Small Balance Write-Offs**: Automatically clears minor discrepancies (e.g., outstanding balances $< \$2.00$ resulting from payment rounding issues).
    *   *Tolerance limit*: Configured globally (default is \$5.00).
    *   *Approval*: Auto-cleared by background jobs daily, writing directly to a Rounding Adjustment Expense account.
2.  **Bad Debt Write-Offs**: Clears significant, uncollectible customer debts.
    *   *Approval*: Requires manual sign-off based on materiality thresholds:
        *   Balances $< \$1,000$: Approved by Credit Manager.
        *   Balances $\ge \$1,000$: Approved by CFO.
    *   *Ledger Postings*:
        *   **Debit**: Allowance for Doubtful Accounts (Contra-Asset)
        *   **Credit**: Accounts Receivable Asset Control account
3.  **Recoveries**: If a customer settles a debt *after* a write-off has occurred, the recovery is logged to prevent balance distortions:
    *   **Debit**: Accounts Receivable Asset Control account
    *   **Credit**: Allowance for Doubtful Accounts (or Bad Debt Recovery Revenue)
    *   The cash payment is then processed normally, debiting Cash and crediting Accounts Receivable.

---

## SECTION 9: REFUND ENGINE

The Refund Engine processes and tracks outgoing payments to customers, reversing cash receipts and adjusting accounts receivable ledger accounts.

```
                           [REFUND REVERSAL PROCESS]
                           
                         [ Execute Refund Resolution ]
                                      │
                   ┌──────────────────┴──────────────────┐
                   ▼                                     ▼
         [ Refund Cash Receipt ]                 [ Reverse Overpayment ]
         Outstanding Receivable                   Credit Balance is
         is re-opened to original debt            reduced to zero.
                   │                                     │
                   └──────────────────┬──────────────────┘
                                      ▼
                        - Debit: Accounts Receivable / Credit Balance
                        - Credit: Cash / Bank Account (Gateway reversal)
```

### 9.1 Refund Workflows

*   **Gateway Integrations**: Integrates directly with credit card and payment processors (e.g., Stripe, Adyen). Upon approval of a refund, the system initiates the gateway transaction and monitors the result.
*   **Refund Methods**: Refunds are processed back to the original payment instrument to prevent fraud and money laundering. In scenarios where this is not possible (e.g., closed bank accounts), the refund is processed via manual wire, requiring secondary controller validation.
*   **Ledger Adjustments**:
    *   Refunding an overpayment: Debits the Unapplied Cash liability account, Credits the Cash/Bank asset account.
    *   Refunding a paid invoice: Debits Sales Revenue/Tax Liability accounts, Credits the Cash/Bank asset account.

---

## SECTION 10: RECEIVABLE AGING ENGINE

The Receivable Aging Engine runs scheduled, automated calculations to categorize outstanding receivables based on time elapsed since their due date.

### 10.1 Aging Buckets

Outstanding balances are categorized into six standard time-based buckets:

```
[ Current (0 Days Past Due) ] ──► [ 1-30 Days ] ──► [ 31-60 Days ] ──► [ 61-90 Days ] ──► [ 91-120 Days ] ──► [ 120+ Days ]
```

*   **Daily Calculation Runs**: A background job evaluates balances nightly, updating the `public.receivable_aging_snapshots` table to reflect accurate aging categories.
*   **Customer Risk Profiling**: Tracks customer payment patterns over time to calculate risk scores (e.g., Days Sales Outstanding, credit velocity) and adjust collection priority.
*   **Expected Credit Loss (ECL)**: Automatically calculates doubtful debt provision percentages based on aging buckets, complying with IFRS 9 and ASC 326 (CECL) requirements:
    *   *Current*: 0.5% provision
    *   *1-30 Days*: 1.5% provision
    *   *31-60 Days*: 5.0% provision
    *   *61-90 Days*: 15.0% provision
    *   *91-120 Days*: 40.0% provision
    *   *120+ Days*: 85.0% provision

---

## SECTION 11: COLLECTIONS WORKFLOW & DUNNING ENGINE

The Collections and Dunning Engine automates reminders and guides collection activities for past-due receivables.

```
                         [DUNNING ESCALATION WORKFLOW]
                         
        [ Invoice Overdue ] ──► Status = 'OVERDUE'
                                       │
                                       ▼
                        [ 5 Days Past Due: Reminder 1 ]
                        Auto-email sent with digital invoice link.
                                       │
                                       ▼
                        [ 15 Days Past Due: Reminder 2 ]
                        Email + SMS notification; customer portal alert.
                                       │
                                       ▼
                        [ 30 Days Past Due: Collection Hold ]
                        System access is paused; automated PTP collection call.
                                       │
                                       ▼
                        [ 45 Days Past Due: Legal Referral ]
                        Account marked for handoff to collections agency.
```

### 11.1 Dunning Escalation Policies

1.  **Promise-to-Pay (PTP)**: Collectors can log customer payment promises with specific commitment dates. If a payment is not allocated by the promised date, the system marks the commitment as broken, triggering immediate escalation.
2.  **Payment Plans**: Supports installment-based settlement of past-due balances. The engine tracks payment plan compliance, automatically returning the customer to standard collections if an installment is missed.
3.  **Customer Credit Holds**: If an invoice crosses 30 days past due, the credit control engine places the customer account on hold, preventing new orders or subscriptions from provisioning.

---

## SECTION 12: MULTI-CURRENCY RECEIVABLES & FX REVALUATION

When invoices are issued in foreign currencies, the AR system must track exchange rate differences and calculate realized and unrealized FX gains or losses.

### 12.1 Multi-Currency Calculations

*   **Historical Invoice Rate**: The exchange rate active on the invoice's `issue_date` defines the base currency value:
    $$\text{Base Value}_{\text{invoice}} = \text{Invoice Amount (FC)} \times \text{Exchange Rate}_{\text{issue\_date}}$$
*   **Settlement Rate**: The exchange rate active on the payment's `allocation_date` defines the settled value in the base currency:
    $$\text{Base Value}_{\text{settlement}} = \text{Payment Amount (FC)} \times \text{Exchange Rate}_{\text{allocation\_date}}$$
*   **Realized FX Gain/Loss**: Calculated upon payment allocation:
    $$\text{Realized FX Gain/Loss} = \text{Base Value}_{\text{settlement}} - \text{Base Value}_{\text{invoice}}$$
    *   *Positive variance*: Realized FX Gain (Revenue account).
    *   *Negative variance*: Realized FX Loss (Expense account).
*   **Month-End Revaluation**: Nightly or monthly period-close runs calculate unrealized gains or losses on all outstanding foreign currency balances, updating the general ledger with revaluation postings:
    $$\text{Unrealized FX Gain/Loss} = \text{Outstanding FC Balance} \times (\text{Exchange Rate}_{\text{reporting\_date}} - \text{Exchange Rate}_{\text{issue\_date}})$$

---

## SECTION 13: LEDGER INTEGRATION & JOURNAL POSTING RULES

The AR subsystem integrates with the General Ledger by posting balanced double-entry journals for all financial events.

| Posting Event | Debit Account | Credit Account | Dimension Propagation |
| :--- | :--- | :--- | :--- |
| **Payment Received (Allocated)** | Cash / Bank Account (Asset) | Accounts Receivable Control (Asset) | Customer ID, Organization, Cost Center |
| **Payment Received (Unallocated)**| Cash / Bank Account (Asset) | Unapplied Customer Cash (Liability) | Customer ID, Organization |
| **Prepayment Deposit Received** | Cash / Bank Account (Asset) | Customer Deposits (Liability) | Customer ID, Organization, Project ID |
| **Deposit Applied to Invoice** | Customer Deposits (Liability) | Accounts Receivable Control (Asset) | Customer ID, Organization, Project ID |
| **Receivable Cash Refund** | Accounts Receivable Control / Unapplied Cash | Cash / Bank Account (Asset) | Customer ID, Organization |
| **Small Balance Write-Off** | Rounding Write-Off Expense | Accounts Receivable Control (Asset) | Customer ID, Organization, Department |
| **Bad Debt Write-Off** | Allowance for Doubtful Accounts (Contra-Asset)| Accounts Receivable Control (Asset) | Customer ID, Organization |
| **Bad Debt Recovery (Cash)** | Cash / Bank Account (Asset) | Bad Debt Recovery (Revenue) | Customer ID, Organization |
| **Realized FX Exchange Gain** | Accounts Receivable Control (Asset) | Realized FX Exchange Gain (Revenue) | Customer ID, Organization |
| **Realized FX Exchange Loss** | Realized FX Exchange Loss (Expense) | Accounts Receivable Control (Asset) | Customer ID, Organization |

---

## SECTION 14: DATABASE TABLES & ENTERPRISE SCHEMAS

The physical schema below defines the storage architecture for Accounts Receivable, cash allocations, and collections tracking.

### 14.1 `public.accounts_receivable`
Tracks open debtor claims and remaining outstanding balances.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Debtor account. |
| `invoice_id` | `uuid` | NO | None | FK -> `invoices(id)` | - | Public | UUIDv4 | Source invoice document. |
| `original_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `original_amount > 0.00`| Total invoice debt. |
| `remaining_balance`|`numeric(18,2)`| NO | None | None | - | Financial | `remaining_balance >= 0.00`| Remaining outstanding balance. |
| `currency_code` | `varchar(3)` | NO | `'USD'` | None | - | Public | Valid ISO code | Transaction currency. |
| `status` | `varchar(30)` | NO | `'open'` | Check Constraint | - | Public | `'open'`, `'partially_paid'`, `'paid'`, `'disputed'`, `'written_off'` | Operational state tracking. |
| `version` | `integer` | NO | `1` | None | - | Public | `version >= 1` | Optimistic locking field. |

---

### 14.2 `public.receivable_allocations`
Maps cash receipts and credit adjustments to accounts receivable items.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `receivable_id` | `uuid` | NO | None | FK -> `accounts_receivable(id)` | - | Public | UUIDv4 | Target receivable item. |
| `allocated_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `allocated_amount > 0.00`| Applied cash portion. |
| `allocation_date` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Timestamp of cash application. |
| `payment_reference`| `varchar(100)`| YES | `NULL` | None | - | Public | Standard string | External payment transaction ID. |
| `credit_note_id` | `uuid` | YES | `NULL` | FK -> `credit_notes(id)` | - | Public | UUIDv4 | Reference Credit Note if adjustment. |

---

### 14.3 `public.customer_deposits`
Tracks advance payments received from customers for future delivery.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Customer owning deposit. |
| `deposit_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `deposit_amount > 0.00` | Original deposit receipt value. |
| `remaining_balance`|`numeric(18,2)`| NO | None | None | - | Financial | `remaining_balance >= 0.00` | Unapplied deposit balance. |
| `deposit_date` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Deposit booking timestamp. |
| `status` | `varchar(30)` | NO | `'active'` | Check Constraint | - | Public | `'active'`, `'applied'`, `'refunded'`, `'expired'` | Current deposit state. |

---

### 14.4 `public.customer_credit_balances`
Tracks unapplied prepayments and excess cash from customer overpayments.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Customer profile. |
| `credit_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `credit_amount > 0.00` | Credit balance value. |
| `remaining_balance`|`numeric(18,2)`| NO | None | None | - | Financial | `remaining_balance >= 0.00` | Unapplied credit balance. |
| `source_type` | `varchar(30)` | NO | None | Check Constraint | - | Public | `'overpayment'`, `'goodwill'`, `'rebate'`, `'promo'` | Source classification. |

---

### 14.5 `public.payment_reconciliations`
Records the outcome of matching runs that reconcile bank statements with AR ledgers.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `reconciliation_run_id`|`uuid` | NO | None | None | - | Public | UUIDv4 | Group execution tracker. |
| `payment_reference`| `varchar(100)`| NO | None | None | - | Public | Standard string | External bank transaction reference. |
| `reconciled_amount`| `numeric(18,2)`| NO | None | None | - | Financial | `reconciled_amount > 0.00`| Total reconciled value. |
| `reconciliation_date`|`timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Timestamp of matching run. |

---

### 14.6 `public.receivable_writeoffs`
Logs accounts receivable items written off as bad debt.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `receivable_id` | `uuid` | NO | None | FK -> `accounts_receivable(id)` | - | Public | UUIDv4 | Target receivable item. |
| `writeoff_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `writeoff_amount > 0.00` | Written-off amount. |
| `writeoff_type` | `varchar(30)` | NO | None | Check Constraint | - | Public | `'small_balance'`, `'bad_debt'` | Write-off categorization. |
| `approved_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Approving authority profile. |
| `justification` | `text` | NO | None | None | - | Public | Standard string | Audit justification notes. |

---

### 14.7 `public.receivable_refunds`
Tracks payment refunds processed and paid out to customers.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Customer profile. |
| `refund_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `refund_amount > 0.00` | Refunded value. |
| `refund_date` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Payout timestamp. |
| `gateway_transaction_id`|`varchar(100)`| YES | `NULL` | None | - | Public | Standard string | External gateway processor key. |

---

### 14.8 `public.receivable_aging_snapshots`
Daily snapshots of outstanding customer balances categorized into aging buckets.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `snapshot_date` | `date` | NO | `current_date` | None | - | Public | Valid date | Snapshot timestamp. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Customer profile. |
| `current_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Current balance (0 days past due). |
| `past_due_1_30` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Outstanding debt (1-30 days). |
| `past_due_31_60` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Outstanding debt (31-60 days). |
| `past_due_61_90` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Outstanding debt (61-90 days). |
| `past_due_91_120` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Outstanding debt (91-120 days). |
| `past_due_over_120`| `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Outstanding debt (120+ days). |

---

### 14.9 `public.collection_cases`
Manages cases created for past-due receivables assigned to the Collections Engine.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Target customer. |
| `case_opened_date` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Case opening timestamp. |
| `current_stage` | `varchar(30)` | NO | `'dunning'` | Check Constraint | - | Public | `'dunning'`, `'payment_plan'`, `'hold'`, `'legal_referral'` | Current case stage. |
| `assigned_collector_id`|`uuid`| YES | `NULL` | FK -> `users(id)` | - | Public | UUIDv4 | Assigned collections agent. |

---

### 14.10 `public.collection_actions`
Tracks individual dunning and collections activities executed within a collection case.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `case_id` | `uuid` | NO | None | FK -> `collection_cases(id)` | - | Public | UUIDv4 | Parent collection case. |
| `action_date` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Action timestamp. |
| `action_type` | `varchar(30)` | NO | None | Check Constraint | - | Public | `'email'`, `'sms'`, `'call'`, `'legal_letter'`, `'hold'` | Type of activity executed. |
| `action_notes` | `text` | YES | `NULL` | None | - | Public | Standard string | Detailed activity logs. |

---

### 14.11 `public.payment_matching_rules`
Defines operational rule chains for matching inbound bank cash receipts.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `priority` | `integer` | NO | `1` | None | - | Public | `priority >= 1` | Execution priority in waterfall. |
| `rule_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'ref_id'`, `'net_balance'`, `'single_match'`, `'fifo'` | Matching logic code. |
| `is_enabled` | `boolean` | NO | `true` | None | - | Public | Valid boolean | Status flag. |

---

## SECTION 15: PERFORMANCE STRATEGY

To support high transaction volumes under heavy database load, the Accounts Receivable and Cash Application Engines utilize specialized indexing and performance patterns.

### 15.1 Indexing and Query Optimization

```sql
-- 1. Covering Index for active outstanding receivable balance queries
CREATE INDEX ar_active_balances_idx 
  ON public.accounts_receivable(organization_id, status)
  INCLUDE (client_account_id, original_amount, remaining_balance)
  WHERE status IN ('open', 'partially_paid', 'disputed');

-- 2. Index to accelerate aging calculations by organization and due date
CREATE INDEX ar_due_dates_idx 
  ON public.accounts_receivable(organization_id, status, client_account_id)
  WHERE status IN ('open', 'partially_paid', 'disputed');

-- 3. Composite covering index to accelerate Cash Application matching queries
CREATE INDEX ar_matching_idx 
  ON public.accounts_receivable(organization_id, client_account_id)
  INCLUDE (id, remaining_balance)
  WHERE status IN ('open', 'partially_paid');
```

---

### 15.2 Advanced Data Management Strategies

1.  **Horizontal Table Partitioning**: High-volume tables (`accounts_receivable`, `receivable_allocations`) are partitioned by `organization_id` (using hash partitioning) to isolate tenant workspaces and maintain rapid, responsive query performance.
2.  **Incremental Aging Aggregations**: Rather than recalculating customer aging schedules from scratch on every report request, a scheduled nightly background job runs incremental calculations and writes the results to `public.receivable_aging_snapshots`, isolating analytical workloads from core transactional tables.
3.  **Transactional Locks**: To prevent concurrent update conflicts (such as two processes allocating different cash receipts to the same invoice simultaneously), the engine acquires row-level locks in a deterministic order:
    ```sql
    -- Acquire deterministic row-level locks to prevent deadlocks
    SELECT id, remaining_balance, version 
    FROM public.accounts_receivable 
    WHERE id = :target_id 
    FOR UPDATE;
    ```

---

## SECTION 16: SECURITY, ROLES & TENANT ISOLATION

To prevent financial fraud and ensure regulatory compliance (e.g., SOC2, GDPR), the Accounts Receivable subsystem implements strict isolation controls and governance workflows.

### 16.1 Security Roles and Operational Matrix

| Security Role | View AR Balances | Allocate Cash | Dispute Adjustments | Write-Off Small Balances | Bad Debt Write-Off | Process Cash Payouts | Audit Log Read |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Billing Clerk** | Yes | Yes | No | No | No | No | No |
| **Collector / Agent** | Yes | No | Yes | No | No | No | No |
| **Collections Manager**| Yes | Yes | Yes | Yes | No | No | Yes |
| **Senior Controller** | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| **CFO / Finance Director**| Yes | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 16.2 Security and Governance Protocols

*   **Maker-Checker approvals (Dual Authorization)**: Write-off authorizations above $500 or refund executions above $1,000 require dual authorization: a billing clerk or collections agent must propose the transaction (the Maker), and a controller or CFO must approve it (the Checker).
*   **Tenant Isolation**: Row-Level Security (RLS) is enabled on all tables, filtering queries dynamically using the tenant context:
    ```sql
    -- Row-Level Security policy schema
    ALTER TABLE public.accounts_receivable ENABLE ROW LEVEL SECURITY;
    
    CREATE POLICY tenant_isolation_policy ON public.accounts_receivable
      FOR ALL USING (organization_id = current_setting('app.current_organization_id', true)::uuid);
    ```

---

## SECTION 17: SYSTEM EVENTS

The Accounts Receivable subsystem is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 17.1 Real-Time System Events

#### `payment.allocated`
Emitted immediately upon cash allocation to a receivable line.

```json
{
  "event_id": "evt_ar_99A0192831",
  "event_type": "payment.allocated",
  "organization_id": "org_771829",
  "correlation_id": "corr_recon_5521",
  "payload": {
    "allocation_id": "all_449122",
    "receivable_id": "rec_883201",
    "invoice_id": "inv_129841",
    "allocated_amount": 450.00,
    "remaining_balance": 50.00,
    "payment_reference": "WIRE-BANK-00921"
  },
  "timestamp": "2026-06-28T18:22:10Z"
}
```

#### `deposit.created`
Emitted upon booking an advance customer deposit.

```json
{
  "event_id": "evt_ar_99A0192855",
  "event_type": "deposit.created",
  "organization_id": "org_771829",
  "correlation_id": "corr_depos_1120",
  "payload": {
    "deposit_id": "dep_229103",
    "client_account_id": "cli_44921",
    "deposit_amount": 1500.00,
    "currency_code": "USD",
    "status": "active"
  },
  "timestamp": "2026-06-28T18:25:00Z"
}
```

#### `writeoff.completed`
Emitted when an outstanding balance is written off as bad debt.

```json
{
  "event_id": "evt_ar_99A0192890",
  "event_type": "writeoff.completed",
  "organization_id": "org_771829",
  "correlation_id": "corr_write_0012",
  "payload": {
    "writeoff_id": "wro_77312",
    "receivable_id": "rec_883201",
    "writeoff_amount": 50.00,
    "writeoff_type": "bad_debt",
    "approved_by": "usr_991203"
  },
  "timestamp": "2026-06-28T18:30:15Z"
}
```

---

## SECTION 18: PRODUCTION READINESS VALIDATION CHECKLIST

Before deploying the Accounts Receivable and Cash Application Engines to production, verify that the following configurations and controls are in place.

- [ ] **Double-Entry Ledger Integrity Verified**: Cash application events correctly trigger balanced debits and credits in both the sub-ledgers and General Ledger.
- [ ] **Balance Equation Confirmed**: The sum of all allocated amounts and the remaining balance exactly matches the original receivable amount for all records.
- [ ] **Duplicate Payment Protection Active**: Unique constraints on transaction IDs and payment references prevent duplicate cash allocation postings.
- [ ] **Multi-Currency FX Calculations Tested**: Realized exchange gain and loss entries are calculated and posted accurately during cash application.
- [ ] **Closed Period Safeguards Verified**: System blocks allocations, write-offs, or adjustment postings to closed accounting periods, routing transactions to the next open period.
- [ ] **Locking Rules Configured**: Concurrency lock priorities are validated under high simulation loads to ensure deadlock prevention.
- [ ] **Maker-Checker Thresholds Set**: Materiality authorization checks are active on write-offs and refunds.
- [ ] **Tenant Isolation Audited**: RLS rules are verified on all AR tables to ensure complete multi-tenant isolation.

---
**End of Specification.**
