# JUANET ERP Invoice Lifecycle Engine Specification
## Phase 2.3.2E.2A — Invoice Lifecycle Engine Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET SaaS Platform  
**Classification:** Technical Architecture / Invoice Lifecycle and Accounts Receivable Core  

---

## SECTION 1: INVOICE LIFECYCLE PHILOSOPHY

In high-integrity enterprise resource planning (ERP) systems such as SAP S/4HANA, Oracle Fusion Financials, and NetSuite ERP, an invoice is not simply a record of an outstanding payment. It is a formal, legally binding, and audit-immutable accounting instrument. 

The JUANET Invoice Lifecycle Engine governs the lifecycle, verification, approval routing, delivery, and payment matching of every customer receivable on the platform.

```
                         [DECOUPLED AR TRANSACTION PIPELINE]
                         
   ┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
   │  Sales/Contract  │ ───► │  Invoicing Core  │ ───► │  Payment Engine  │
   │  (Opportunity)   │      │ (Legal Invoice)  │      │ (Cash Receipts)  │
   └──────────────────┘      └────────┬─────────┘      └────────┬─────────┘
                                      │                         │
                                      ▼                         ▼
                             [ Ledger Postings ]       [ Cash Allocations ]
                             AR Control Account        Settles Outstanding
                             & Revenue Accruals        Receivable Balance
```

### 1.1 The Multiple Identities of an Invoice

An invoice simultaneously holds several distinct identities across different domains of the enterprise:

1.  **Legal Identity**: It represents a contractually enforceable claim of debt against a customer (`client_account`). It binds the customer to specific payment terms (e.g., Net 30) and provides the legal basis for collections, dispute resolution, or bad-debt write-offs in a court of law.
2.  **Accounting Identity**: Under accrual accounting standards (IFRS/US GAAP), an invoice is the primary trigger for recognizing receivables. Its issuance represents the formal transfer of risk and control of goods or services to the buyer.
3.  **Tax Identity**: It is a tax-binding document. In jurisdictions enforcing Value-Added Tax (VAT), Goods and Services Tax (GST), or Sales Tax, the act of issuing an invoice (the *time of supply*) dictates the tax period in which the tax liability must be reported and remitted to authorities, regardless of when cash is collected.
4.  **Customer Obligation**: It translates back-office operations into a human-readable statement of what was delivered, the unit price, tax breakdowns, and payment instructions.
5.  **Revenue Recognition (ASC 606 / IFRS 15)**: The invoice records the billing event, but the Revenue Recognition Engine determines whether that value is immediately recognized as earned income (e.g., standard sales) or deferred as a liability (e.g., prepaid subscriptions).

---

### 1.2 Separation from Payments and Ledger Entries

To maintain a clean audit trail, JUANET separates the operational billing engine from ledger postings and cash payments:

*   **Separation from Payments**: An invoice's total amount (`total_amount`) remains fixed from the moment of issuance. Payment events do not modify this total. Instead, separate payment allocation records (`public.receivable_allocations`) are mapped to the invoice, dynamically reducing its remaining `balance_due`. This prevents payment failures or adjustments from altering the historical tax-reporting basis of the invoice.
*   **Separation from Ledger Entries**: The invoicing database tables (`public.invoices`, `public.invoice_line_items`) contain rich business metadata (such as line descriptions, billing contacts, and delivery logs). These tables do not write directly to the general ledger. Instead, the transition of an invoice's state triggers transactional events that the **Ledger Posting Rule Engine** translates into balanced double-entry journal lines (`public.ledger_entries`).

---

### 1.3 Core Lifecycle Invariants

The Billing Engine enforces the following strict business invariants at the database level:

1.  **Immutability Invariant**: Once an invoice transitions out of the `Draft` state and receives a sequential, gapless billing number, it is **physically immutable**. No column representing financial values, tax rates, line quantities, or accounts can ever be modified. Corrections require issuing a formal Credit Note or Debit Note.
2.  **The Balance Equation**: For every invoice, the mathematical relation between subtotal, taxes, discounts, and total must hold true at all times:
    $$\text{Total Amount} = \text{Subtotal} + \sum(\text{Taxes}) - \sum(\text{Discounts})$$
3.  **Outstanding Balance Cap**: The outstanding balance due on an invoice can never be negative, and can never exceed the total invoice amount:
    $$0.00 \le \text{Balance Due} \le \text{Total Amount}$$
4.  **Accounting Period Alignment**: The `issue_date` of an invoice must fall within an active, `OPEN` accounting period. Postings to soft-closed, hard-closed, or archived periods are blocked.

---

## SECTION 2: COMPLETE INVOICE STATE MACHINE

The JUANET Invoice Lifecycle Engine operates as a deterministic, finite state machine (FSM). 

```
                                 [INVOICE LIFECYCLE FSM]
                                 
                         ┌────────────────────────────────────┐
                         │               DRAFT                │
                         └─────────────────┬──────────────────┘
                                           │
                                           ▼
                         ┌────────────────────────────────────┐
                         │          PENDING APPROVAL          │
                         └─────────────────┬──────────────────┘
                                           │
                                           ▼
                         ┌────────────────────────────────────┐
                         │              APPROVED              │
                         └─────────────────┬──────────────────┘
                                           │
                                           ▼
                         ┌────────────────────────────────────┐
                         │               ISSUED               │◄────────────────────────┐
                         └───────┬─────────┬─────────┬────────┘                         │
                                 │         │         │                                  │
                                 ▼         │         ▼                                  │
                         ┌───────────┐     │   ┌───────────┐                            │
                         │ DELIVERED │     │   │ CANCELLED │                            │
                         └─────┬─────┘     │   └───────────┘                            │
                               │           │                                            │
                               ▼           ▼                                            │
                         ┌───────────┐ ┌───────┐                                        │
                         │  VIEWED   │ │VOIDED │                                        │
                         └─────┬─────┘ └───────┘                                        │
                               │                                                        │
         ┌─────────────────────┴──────────────────────┐                                 │
         ▼                                            ▼                                 │
   ┌───────────┐                                ┌───────────┐                           │
   │ DISPUTED  ├───────────────────────────────►│  OVERDUE  │                           │
   └─────┬─────┘                                └─────┬─────┘                           │
         │                                            │                                 │
         │                                            ▼                                 │
         │                                      ┌───────────┐                           │
         │                                      │WRITTEN OFF│                           │
         │                                      └─────┬─────┘                           │
         │                                            │                                 │
         └─────────────────────┬──────────────────────┘                                 │
                               ▼                                                        │
                         ┌───────────┐                                                  │
                         │ PARTIAL   ├──────────────────────────────────────────────────┘ (Receipt Allocated)
                         │ PAID      │
                         └─────┬─────┘
                               │
                               ▼
                         ┌───────────┐
                         │   PAID    │
                         └─────┬─────┘
                               │
                               ▼
                         ┌───────────┐
                         │  CLOSED   │
                         └─────┬─────┘
                               │
                               ▼
                         ┌───────────┐
                         │ ARCHIVED  │
                         └───────────┘
```

---

### 2.1 Complete Lifecycle State Catalog

#### 2.1.1 `DRAFT`
*   **Description**: The initial workspace of the invoice. The document is fully editable.
*   **Entry Conditions**: Spawning via manual entry, subscription run, quote conversion, or CRM opportunity win.
*   **Exit Conditions**: Submission for approval or manual deletion.
*   **Allowed Transitions**: `PENDING_APPROVAL`, `VOIDED`.
*   **Forbidden Transitions**: `ISSUED`, `DELIVERED`, `PAID`, `OVERDUE`.
*   **Required Permissions**: `invoice:create`, `invoice:write`.
*   **Produced Events**: `invoice.created`.

#### 2.1.2 `PENDING_APPROVAL`
*   **Description**: Locked for review. Awaiting authorization from designated financial controllers or managers.
*   **Entry Conditions**: Submission from `DRAFT` state.
*   **Exit Conditions**: Approval signature or rejection back to `DRAFT`.
*   **Allowed Transitions**: `APPROVED`, `DRAFT` (Rejected), `VOIDED`.
*   **Forbidden Transitions**: `ISSUED`, `DELIVERED`, `PAID`, `OVERDUE`.
*   **Required Permissions**: `invoice:submit`.
*   **Produced Events**: `invoice.submitted_for_approval`.

#### 2.1.3 `APPROVED`
*   **Description**: Validated and cleared for issuance. Financial parameters are locked, but no ledger entries have been written.
*   **Entry Conditions**: Receipt of required approval signatures.
*   **Exit Conditions**: Formal release or manual reversion to Draft for adjustments.
*   **Allowed Transitions**: `ISSUED`, `DRAFT` (Reverted), `VOIDED`.
*   **Forbidden Transitions**: `DELIVERED`, `PAID`, `OVERDUE`.
*   **Required Permissions**: `invoice:approve`.
*   **Produced Events**: `invoice.approved`.

#### 2.1.4 `ISSUED`
*   **Description**: The invoice is legally finalized, locked, and assigned a sequential number. It represents an active receivable.
*   **Entry Conditions**: Transition from `APPROVED` by a Billing Clerk or automated scheduler.
*   **Exit Conditions**: Payment allocations, delivery confirmations, or cancellations.
*   **Allowed Transitions**: `DELIVERED`, `PARTIALLY_PAID`, `PAID`, `OVERDUE`, `CANCELLED`, `DISPUTED`.
*   **Forbidden Transitions**: `DRAFT`, `PENDING_APPROVAL`, `APPROVED`.
*   **Required Permissions**: `invoice:issue`.
*   **Produced Events**: `invoice.issued`.

#### 2.1.5 `DELIVERED`
*   **Description**: Confirmed as sent to the customer's delivery channel (e.g., SMTP delivery success or electronic exchange receipt).
*   **Entry Conditions**: Integration confirmation from the delivery system.
*   **Exit Conditions**: Portal activity detection or payment allocation.
*   **Allowed Transitions**: `VIEWED`, `PARTIALLY_PAID`, `PAID`, `OVERDUE`, `CANCELLED`, `DISPUTED`.
*   **Forbidden Transitions**: `DRAFT`, `PENDING_APPROVAL`, `APPROVED`.
*   **Required Permissions**: `system:background_worker` or `invoice:send`.
*   **Produced Events**: `invoice.delivered`.

#### 2.1.6 `VIEWED`
*   **Description**: Confirmed as opened or downloaded by the customer ( portal tracking or read receipt).
*   **Entry Conditions**: Customer opens the invoice on the secure digital portal.
*   **Exit Conditions**: Payment allocation or terms expiration.
*   **Allowed Transitions**: `PARTIALLY_PAID`, `PAID`, `OVERDUE`, `CANCELLED`, `DISPUTED`.
*   **Forbidden Transitions**: `DRAFT`, `PENDING_APPROVAL`, `APPROVED`.
*   **Required Permissions**: `public_access` (customer tracking token).
*   **Produced Events**: `invoice.viewed`.

#### 2.1.7 `PARTIALLY_PAID`
*   **Description**: One or more payments have been allocated, leaving `balance_due > 0` but `balance_due < total_amount`.
*   **Entry Conditions**: Allocation of a cash receipt that does not fully cover the total outstanding amount.
*   **Exit Conditions**: Subsequent payments, terms expiration, or bad-debt write-offs.
*   **Allowed Transitions**: `PAID`, `OVERDUE`, `CANCELLED` (under strict audit), `WRITTEN_OFF`, `DISPUTED`.
*   **Forbidden Transitions**: `DRAFT`, `PENDING_APPROVAL`, `APPROVED`.
*   **Required Permissions**: `payment:allocate` (Ledger matching).
*   **Produced Events**: `invoice.partially_paid`.

#### 2.1.8 `PAID`
*   **Description**: Payments have been fully allocated, bringing `balance_due` to exactly `0.00`.
*   **Entry Conditions**: Cash receipts match or exceed the total outstanding invoice balance.
*   **Exit Conditions**: Period closure processing.
*   **Allowed Transitions**: `CLOSED`, `DRAFT` (only if payment is completely reversed or chargeback occurs).
*   **Forbidden Transitions**: `ISSUED`, `DELIVERED`, `OVERDUE`.
*   **Required Permissions**: `payment:allocate` or `system:matching_engine`.
*   **Produced Events**: `invoice.paid`.

#### 2.1.9 `OVERDUE`
*   **Description**: Current date has passed the defined `due_date` of the invoice, and `balance_due > 0`.
*   **Entry Conditions**: Midnight background terms evaluation.
*   **Exit Conditions**: Debt collection success, settlement, or write-off.
*   **Allowed Transitions**: `PARTIALLY_PAID`, `PAID`, `WRITTEN_OFF`, `CANCELLED` (only if voided by legal correction), `DISPUTED`.
*   **Forbidden Transitions**: `DRAFT`, `APPROVED`.
*   **Required Permissions**: `system:cron_worker`.
*   **Produced Events**: `invoice.overdue`.

#### 2.1.10 `DISPUTED`
*   **Description**: The client formally challenges a charge or line item. Collections activities are paused while under review.
*   **Entry Conditions**: Manual flag by Billing Admin or customer-initiated dispute.
*   **Exit Conditions**: Resolution agreement or dispute rejection.
*   **Allowed Transitions**: `ISSUED`, `DELIVERED`, `PARTIALLY_PAID`, `PAID`, `CANCELLED` (if credited), `OVERDUE`.
*   **Forbidden Transitions**: `CLOSED`, `ARCHIVED`.
*   **Required Permissions**: `invoice:dispute_write`.
*   **Produced Events**: `invoice.disputed`.

#### 2.1.11 `CANCELLED`
*   **Description**: The invoice was issued in error or corrected. Reversing transactions are generated to zero out the ledger.
*   **Entry Conditions**: CFO or Senior Controller triggers cancellation, linking it to a reversing Credit Note.
*   **Exit Conditions**: Transition to Archived.
*   **Allowed Transitions**: `ARCHIVED`.
*   **Forbidden Transitions**: All operational states.
*   **Required Permissions**: `invoice:cancel`.
*   **Produced Events**: `invoice.cancelled`.

#### 2.1.12 `VOIDED`
*   **Description**: A draft or approved invoice is cancelled before issuance.
*   **Entry Conditions**: Deletion or void action on a non-issued invoice.
*   **Exit Conditions**: Transition to Archived.
*   **Allowed Transitions**: `ARCHIVED`.
*   **Forbidden Transitions**: All operational states.
*   **Required Permissions**: `invoice:write`.
*   **Produced Events**: `invoice.voided`.

#### 2.1.13 `WRITTEN_OFF`
*   **Description**: Outstanding balance is classified as uncollectible, shifting the receivable to bad debt.
*   **Entry Conditions**: Authorized CFO write-off approval.
*   **Exit Conditions**: Transition to Archived.
*   **Allowed Transitions**: `ARCHIVED`.
*   **Forbidden Transitions**: All operational states.
*   **Required Permissions**: `invoice:write_off` (CFO Role).
*   **Produced Events**: `invoice.written_off`.

#### 2.1.14 `CLOSED`
*   **Description**: Fully paid and reconciled. Accounts Receivable ledger balances match bank cash accounts.
*   **Entry Conditions**: Reconciliation approval by Senior Controller.
*   **Exit Conditions**: Transition to Archived.
*   **Allowed Transitions**: `ARCHIVED`.
*   **Forbidden Transitions**: `DRAFT`, `ISSUED`, `PAID`.
*   **Required Permissions**: `period:close`.
*   **Produced Events**: `invoice.closed`.

#### 2.1.15 `ARCHIVED`
*   **Description**: Legally closed, paid, or adjusted. Historical records are compressed and stored in write-once-read-many (WORM) configurations for compliance.
*   **Entry Conditions**: Automated background archival job runs on closed periods.
*   **Exit Conditions**: Read-only state; no transitions out of Archived.
*   **Allowed Transitions**: None.
*   **Forbidden Transitions**: All transitions.
*   **Required Permissions**: None (WORM state).
*   **Produced Events**: `invoice.archived`.

---

## SECTION 3: STATE TRANSITION RULES & MATRICES

### 3.1 State Transition Matrix

The table below defines all permissible state transitions. Under no circumstances can a transition not marked in this matrix occur.

| Current State | Target State | Triggering Mechanism | Core Validation Requirements | Accounting Implications | Required Permissions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`DRAFT`** | `PENDING_APPROVAL` | User clicks "Submit" | All required fields populated; total > 0.00. | None. | `invoice:submit` |
| **`DRAFT`** | `VOIDED` | User clicks "Delete" | Only permitted if invoice is not issued. | None. | `invoice:write` |
| **`PENDING_APPROVAL`**| `APPROVED` | Approver signs off | Approval rules satisfied. | None. | `invoice:approve` |
| **`PENDING_APPROVAL`**| `DRAFT` | Approver rejects | Rejection comment populated. | None. | `invoice:approve` |
| **`APPROVED`** | `ISSUED` | Billing clerk releases | Target accounting period is `OPEN`. | Debit AR, Credit Revenue/Taxes. | `invoice:issue` |
| **`ISSUED`** | `DELIVERED` | Integration worker | Delivery receipt received. | None. | `system:worker` |
| **`ISSUED`** | `CANCELLED` | CFO correction | Linked Credit Note created. | Reverse AR & Revenue/Taxes. | `invoice:cancel` |
| **`ISSUED`** | `DISPUTED` | Customer challenges | Dispute reason and evidence attached. | None (Collections paused).| `invoice:dispute` |
| **`DELIVERED`** | `VIEWED` | Tracking token loaded | Tracking token matches invoice. | None. | `public_access` |
| **`VIEWED`** | `PARTIALLY_PAID` | Payment allocation | Allocated amount < balance due. | Debit Cash, Credit AR. | `payment:allocate` |
| **`VIEWED`** | `PAID` | Payment allocation | Allocated amount == balance due. | Debit Cash, Credit AR. | `payment:allocate` |
| **`VIEWED`** | `OVERDUE` | Midnight cron job | Current date > invoice due date. | None. | `system:worker` |
| **`OVERDUE`** | `WRITTEN_OFF` | CFO authorization | Collections process exhausted. | Debit Bad Debt, Credit AR. | `invoice:write_off` |
| **`PAID`** | `CLOSED` | Month-end close | Reconciliation approved. | Locked in AR sub-ledger. | `period:close` |
| **`CLOSED`** | `ARCHIVED` | Archival job | Period hard-closed. | Marked read-only. | `system:worker` |

---

## SECTION 4: INVOICE CREATION PIPELINE

To prevent invalid or incorrect ledger postings, every invoice must pass through a strict validation pipeline before it can be finalized and issued.

```
                           [INVOICE CREATION PIPELINE]
                           
      [ Start creation ]
             │
             ▼
      [ Customer Validation ] ──► Status active? Credit check okay?
             │
             ▼
      [ Currency Validation ] ──► Base/transaction currency configuration active?
             │
             ▼
      [ Pricing Resolution ] ───► Fetch standard/custom catalog rates.
             │
             ▼
      [ Discount Resolution ] ──► Apply promotion, contract, or prompt-payment rules.
             │
             ▼
      [ Tax Resolution ] ───────► Resolve jurisdiction-specific rates.
             │
             ▼
      [ Dimension Resolution ] ─► Populate cost centers, departments, or projects.
             │
             ▼
      [ Posting Rule Check ] ───► Verify accounts exist in Chart of Accounts.
             │
             ▼
      [ Period Check ] ─────────► Confirm target accounting period is OPEN.
             │
             ▼
      [ Number Allocation ] ────► Assign unique gapless sequence number.
             │
             ▼
      [ Persist Invoice ] ──────► Save to database with secure hash.
             │
             ▼
      [ Generate Events ] ──────► Emit real-time Kafka/RabbitMQ billing events.
```

### 4.1 Detailed Pipeline Stages

1.  **Customer Validation**: Resolves the customer's billing profile. The pipeline verifies that the `client_account` is active and checks current receivables against the customer's credit limit.
2.  **Currency Validation**: Verifies that the invoice's transaction currency is active in the system and retrieves current conversion rates to the organization's base currency.
3.  **Pricing Resolution**: Resolves unit prices for all line items based on active pricing schedules, volume brackets, or negotiated contract sheets.
4.  **Discount Resolution**: Calculates and applies line-level or document-level discounts, verifying that the reductions do not violate minimum margin rules.
5.  **Tax Resolution**: Sends the invoice payload to the local tax engine, which calculates and appends tax lines (`public.invoice_tax_lines`) based on item classifications and regional tax rules (e.g., origin-based, destination-based, or reverse-charge).
6.  **Dimension Resolution**: Resolves and appends financial tracking dimensions (such as Cost Center, Project, or Department) to each line item, enabling detailed divisional reporting.
7.  **Posting Rule Validation**: Checks that valid posting rules exist for the transaction event (e.g., verifying that the Accounts Receivable control account and Revenue offset accounts are active in the Chart of Accounts).
8.  **Accounting Period Validation**: Confirms that the target invoice date falls within an active, `OPEN` accounting period.
9.  **Invoice Number Allocation**: Acquires a sequence lock and reserves a gapless, sequential invoice number.
10. **Persist Invoice**: Writes the complete document, line items, taxes, and discounts to the database, calculating and storing a secure SHA-256 integrity hash of the document contents.
11. **Generate Events**: Emits real-time billing events to trigger ledger postings and downstream notifications.

---

## SECTION 5: APPROVAL ENGINE

To prevent unauthorized sales or incorrect pricing, JUANET implements a highly configurable multi-level Approval Engine.

```
                          [APPROVAL ROUTING DESIGN]
                          
                          [ Submit Invoice for Approval ]
                                         │
                   ┌─────────────────────┴─────────────────────┐
                   ▼                                           ▼
         [ Total < $10,000 ]                         [ Total >= $10,000 ]
         Single-Level Route                          Multi-Level Route
                 │                                             │
                 ▼                                             ▼
        Department Manager                            Department Manager
                 │                                             │
                 ▼                                             ▼
             [ APPROVE ]                               Finance Controller
                                                               │
                                                               ▼
                                                       Executive VP / CFO
                                                               │
                                                               ▼
                                                           [ APPROVE ]
```

### 5.1 Configurable Routing Rules

Approval workflows are resolved dynamically based on document characteristics:

*   **Value-Based Routing**: Thresholds define who must sign off on a document:
    *   $\text{Invoice Total} < \$10,000$: Single-level approval by the local Department Manager.
    *   $\$10,000 \le \text{Invoice Total} < \$50,000$: Dual-level approval by the Department Manager and the Finance Controller.
    *   $\text{Invoice Total} \ge \$50,000$: Three-level approval by the Department Manager, Finance Controller, and CFO.
*   **Discount-Based Routing**: If an invoice's discount rate exceeds 15%, the workflow automatically routes the document to the VP of Sales for approval, bypassing standard value thresholds.
*   **Emergency Approvals**: In critical operational scenarios, the CEO or CFO can bypass standard routing queues by applying an emergency override. This action requires a logged business justification and triggers real-time administrative alerts.
*   **Approval Delegation**: Users can delegate their approval authority to a peer or manager for a specified period (e.g., during scheduled leave), with all delegated approvals clearly logged for audit traceability.

---

## SECTION 6: VERSIONING & HISTORICAL SNAPSHOTS

To comply with international tax audits (e.g., SOC2, regional tax reviews), invoices must maintain a complete, transparent revision history.

```
                      [SNAPSHOT STORAGE SCHEME]
                      
   [ Event Trigger ] ──────► Render Invoice State JSON ──────► Write Snapshot
   (e.g., Status Change)                                        (Immutable Row)
```

### 6.1 Versioning Rules

*   **Draft Revisions**: While in the `Draft` state, modifications increments minor version numbers (e.g., 1.0 -> 1.1 -> 1.2). These draft revisions are tracked internally for work-in-progress visibility but do not write ledger entries.
*   **Issued Immutability**: Once an invoice transitions to `Issued`, it is **permanently immutable**. Directly editing an issued invoice is strictly forbidden. Corrections must be handled by issuing a formal Credit Note or Debit Note, or through the cancellation and replacement workflow.
*   **Immutable Snapshot Storage**: Every status transition or modification triggers an automated database routine that serializes the complete state of the invoice, its lines, taxes, and discounts into a JSONB snapshot stored in the append-only table `public.invoice_history`. This ensures auditors can reconstruct the exact state of the document at any point in time.

---

## SECTION 7: CANCELLATION & CORRECTION ENGINE

Correcting errors on issued invoices requires clear protocols to maintain general ledger accuracy and tax compliance.

```
                      [CANCELLATION & REVERSAL RULES]
                      
         [ Original Invoice: INV-001 ] ──► Debits AR $1,000, Credits Revenue $1,000
                       │
                       ▼
             [ Trigger Cancel ]
                       │
                       ▼
          [ Credit Note Generated ] ─────► Credits AR $1,000, Debits Revenue $1,000
                       │
                       ▼
       [ Replacement Invoice: INV-001-R ] ─► Debits AR $950, Credits Revenue $950
```

### 7.1 Cancellation Definitions

1.  **Voiding**: Applied exclusively to non-issued invoices (e.g., Draft or Pending Approval). It marks the document as void, preventing future status changes or ledger postings.
2.  **Cancellation (Issued Invoices)**: Applied to issued invoices. The engine generates a reversing transaction to offset original postings:
    *   **Same Period**: Reversals are posted to the same accounting period as the original invoice.
    *   **Closed Period**: If the original period is closed, reversals are posted to the current open period.
3.  **Credit Notes & Debit Notes**:
    *   **Credit Notes**: Reduces the customer's outstanding balance, reversing corresponding revenue and tax liabilities.
    *   **Debit Notes**: Adds charges to an existing invoice, debiting Accounts Receivable and crediting Sales Revenue.
    *   **Linkage**: All Credit and Debit Notes must link to their source invoice via the `parent_invoice_id` column, maintaining clean audit trails.

---

## SECTION 8: DISPUTE MANAGEMENT

When a customer challenges a billed charge, the invoice transitions to the `Disputed` state to prevent aggressive collection activities from damaging customer relations.

```
                         [DISPUTE RESOLUTION PIPELINE]
                         
                            [ Active Dispute Raised ]
                                       │
                                       ▼
                             Status = 'DISPUTED'
                             (Pauses Collections)
                                       │
                                       ▼
                             Under Review Stage
                        (Evidence files attached)
                                       │
                     ┌─────────────────┴─────────────────┐
                     ▼                                   ▼
             [ Dispute Upheld ]                [ Dispute Rejected ]
                     │                                   │
                     ▼                                   ▼
           Generate Credit Note                  Status = 'OVERDUE'
            to adjust balance.                   Resume collections.
```

### 8.1 Dispute Rules & Invariants

*   **Collections Pause**: Transitioning an invoice to `Disputed` pauses automated collections reminders and dunning escalations, preventing relationship friction during review.
*   **Partial Disputes**: If only a portion of an invoice is disputed, the customer is expected to settle the undisputed balance. The payment matching engine permits partial payment allocations against disputed invoices without resolving the dispute state.
*   **Evidence Attachment**: Every dispute record must link to an attachment log containing emails, contracts, or delivery photos, preserving a complete record of the dispute for audit.
*   **Resolution Approvals**: Settling a dispute by issuing a Credit Note requires standard approval routing based on the value of the credit.

---

## SECTION 9: DELIVERY TRACKING & AUDITING

The Delivery Tracking Engine monitors the complete path of rendered invoices to ensure delivery compliance and provide real-time visibility into customer engagement.

```
                          [DELIVERY TRACKING PIPELINE]
                          
  [ Generated ] ──► [ Queued ] ──► [ Sent ] ──► [ Delivered ] ──► [ Opened/Downloaded ]
                                                                        │
                                                                        ▼
                                                                 [ Failed/Bounced ]
                                                                 (Triggers Resend Queue)
```

### 9.1 Tracking Specifications

*   **Delivery Channels Supported**:
    *   **SMTP Email**: Distributed to registered client billing contacts, tracking delivery success, bounces, and email opens via secure tracking tokens.
    *   **SMS & Webhooks**: Supports modern notifications and real-time delivery tracking.
    *   **Secure Customer Portal**: Provides direct invoice downloads, with each access event logging secure timestamps, IP addresses, and user agents.
    *   **Electronic Document Networks**: Integrates with global B2B networks (such as Peppol) for structured e-invoicing delivery.
*   **Bounced Deliveries**: If an email delivery fails (e.g., hard bounce), the delivery tracker updates `invoice_delivery_statuses` to `'failed'` and routes the document to an administrative queue for verification and resending.

---

## SECTION 10: PAYMENT STATUS SYNCHRONIZATION

The Cash Allocation Engine matches incoming payments with outstanding receivables without modifying the historical data of issued invoices.

```
                      [RECEIVABLE ALLOCATION DESIGN]
                      
         [ Cash Receipt (Payment) ]          [ Outstanding Invoice ]
         Total: $5,000                       Total: $10,000
               │                                     │
               └──────────────────┬──────────────────┘
                                  ▼
                     [ public.receivable_allocations ]
                     Allocates $5,000 to Invoice
                                  │
                                  ▼
                   Update Invoice: balance_due = $5,000
                   Status = 'PARTIALLY_PAID'
```

### 10.1 Payment Entities Mapping

*   **Payment Intent**: Tracks a customer's payment request (e.g., checkout session initialization) without changing invoice balances.
*   **Payment Attempt**: Logs transaction attempts, capturing gateway status codes and transaction responses.
*   **Payment Receipt**: Confirms the receipt of cash (e.g., bank transfer or gateway settlement).
*   **Payment Allocation**: Maps cash receipts to specific invoices (`public.receivable_allocations`). This allocation reduces the invoice's `balance_due` dynamically via a database transaction.
*   **Overpayments**: If a payment exceeds the outstanding invoice balance, the excess is recorded as an unallocated customer credit, which can be applied to future invoices.
*   **Refunds & Chargebacks**: Reversing allocations increases the invoice's `balance_due` and returns the document to its corresponding unpaid state.

---

## SECTION 11: CREDIT CONTROL & RISK MANAGEMENT

The Credit Control Engine monitors outstanding receivables and customer risk profiles to protect the organization from bad-debt exposure.

```
                      [CREDIT CHECK PIPELINE]
                      
                        [ Draft Invoice Created ]
                                   │
                                   ▼
                         Calculate Credit Usage:
                         Outstanding Invoices + Current Draft
                                   │
                                   ▼
                     Does Usage Exceed Credit Limit?
                     ├── Yes ───────────────────────────► [ BLOCK / HOLD ]
                     └── No ────────────────────────────► [ ALLOW APPROVAL ]
```

### 11.1 Credit Control Policies

*   **Real-Time Credit Tracking**: The engine calculates credit utilization dynamically:
    $$\text{Credit Utilized} = \sum(\text{Outstanding Receivables}) + \sum(\text{Unposted Approved Invoices})$$
*   **Automatic Blocks**: If credit utilization exceeds the customer's credit limit, the system blocks new drafts from transitioning to `Approved` or `Issued`.
*   **Credit Overrides**: Senior Controllers can apply temporary credit overrides, allowing critical invoices to clear with a logged justification.
*   **Risk Scores**: Tracks customer payment behaviors (e.g., average days past due) to adjust credit limits dynamically and flag high-risk accounts.

---

## SECTION 12: ROLE-BASED ACCESS CONTROL & SECURITY

To prevent financial fraud and maintain compliance with SOC2 standards, access to invoice states and billing controls is strictly governed by Role-Based Access Control (RBAC).

### 12.1 Security Permissions Matrix

| Operations Role | Create/Edit Draft | Submit for Approval | Approve Invoice | Issue Invoice | Cancel / Void | CFO Write-Off | Reopen Archive |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Billing Clerk** | Yes | Yes | No | No | No | No | No |
| **Sales Representative** | Yes | Yes | No | No | No | No | No |
| **Sales Manager** | Yes | Yes | Yes | No | No | No | No |
| **Financial Controller** | Yes | Yes | Yes | Yes | Yes | No | No |
| **CFO / Director** | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 12.2 Security Audits and Compliance
*   **Four-Eyes Principle**: Invoices with total values exceeding $50,000 require a separate draft creator and approving controller, preventing internal authorization loops.
*   **Immutable System Change Logs**: All status transitions, approval signatures, and delivery events write to the append-only table `public.invoice_history`, guaranteeing a complete audit trail for corporate compliance reviews.

---

## SECTION 13: REAL-TIME BILLING EVENTS

The JUANET Invoice Lifecycle Engine is fully event-driven, emitting structured events on all lifecycle changes to trigger downstream postings and notifications.

### 13.1 Event Schemas

#### `invoice.created`
```json
{
  "event_id": "evt_bill_01A928311",
  "event_type": "invoice.created",
  "organization_id": "org_771829",
  "correlation_id": "corr_sales_990182",
  "payload": {
    "invoice_id": "inv_8829103",
    "client_account_id": "cli_44921",
    "currency_code": "USD",
    "subtotal": 5000.00,
    "tax_amount": 400.00,
    "discount_amount": 100.00,
    "total_amount": 5300.00
  },
  "timestamp": "2026-06-27T10:00:00Z"
}
```

#### `invoice.issued`
```json
{
  "event_id": "evt_bill_01A928350",
  "event_type": "invoice.issued",
  "organization_id": "org_771829",
  "correlation_id": "corr_sales_990182",
  "payload": {
    "invoice_id": "inv_8829103",
    "invoice_number": "INV-2026-000492",
    "client_account_id": "cli_44921",
    "total_amount": 5300.00,
    "currency_code": "USD",
    "exchange_rate": 1.000,
    "issue_date": "2026-06-27",
    "due_date": "2026-07-27"
  },
  "timestamp": "2026-06-27T10:15:00Z"
}
```

#### `invoice.disputed`
```json
{
  "event_id": "evt_bill_01A928399",
  "event_type": "invoice.disputed",
  "organization_id": "org_771829",
  "correlation_id": "corr_sales_990182",
  "payload": {
    "invoice_id": "inv_8829103",
    "invoice_number": "INV-2026-000492",
    "dispute_reason": "Pricing mismatch on consulting hours line",
    "disputed_amount": 1200.00,
    "initiated_by": "usr_7712"
  },
  "timestamp": "2026-06-27T11:30:22Z"
}
```

---

### 13.2 Reliability, Retries & Idempotency
*   **Correlation IDs**: Every event payload includes a unique `correlation_id` to trace the transaction flow across multiple downstream microservices (e.g., tracking a CRM sale through invoicing and cash collection).
*   **Idempotency Keys**: Consumers utilize the combination of `invoice_id` and the target status transition as an idempotency key to prevent duplicate ledger postings or notifications.
*   **Retry Mechanisms**: Failed delivery or integration attempts use an exponential backoff retry strategy with random jitter, routing persistent failures to a Dead Letter Queue (DLQ) for review.

---

## SECTION 14: CONCURRENCY & TRANSACTION ISOLATION

High-volume invoicing requires robust database concurrency configurations to ensure data integrity and prevent performance bottlenecks.

### 14.1 Concurrency Safeguards

*   **Optimistic Locking**: The `invoices` table includes an integer `version` column. All update operations verify that the version matches the record at the time of reading, preventing concurrent update conflicts:
    ```sql
    -- Conceptual optimistic lock validation during state update
    UPDATE public.invoices
    SET status = 'issued', version = version + 1
    WHERE id = :invoice_id AND version = :read_version;
    ```
*   **Lock Ordering Invariance**: To prevent database deadlocks, all batch closing operations or bulk payment runs must sort their target records by primary key before acquiring row locks (`SELECT FOR UPDATE`).
*   **Idempotency Key Indexes**: Unique index constraints on idempotency keys prevent duplicate transaction allocations during high-concurrency payment events.

---

## SECTION 15: DATABASE PERFORMANCE & INDEXING

To ensure fast financial calculations and real-time report generation, the database must include targeted indexes.

### 15.1 Indexing & Partitioning Specifications

```sql
-- 1. Speeds up billing searches in active workflows
CREATE INDEX invoices_lifecycle_active_idx 
  ON public.invoices(organization_id, status)
  WHERE status IN ('draft', 'pending_approval', 'issued', 'disputed', 'overdue');

-- 2. Optimizes Accounts Receivable calculations for customer portals
CREATE INDEX invoices_receivable_outstanding_idx 
  ON public.invoices(client_account_id, balance_due)
  WHERE balance_due > 0.00;

-- 3. Composite covering index to speed up month-end revenue and tax reports
CREATE INDEX invoices_financial_reporting_idx 
  ON public.invoices(organization_id, issue_date)
  INCLUDE (subtotal, tax_amount, discount_amount, total_amount);
```

---

### 15.2 Archival and Partitioning Strategies
*   **Table Partitioning**: High-volume invoice transactional tables are partitioned by `organization_id` (for multi-tenant isolation) or by `issue_date` ranges (quarterly partitions) to keep active tables small and ensure rapid query times.
*   **Materialized Views**: Aging reports (e.g., AR aging schedules for 30/60/90+ days) use materialized views updated during off-peak hours, offloading heavy analytical queries from transactional databases.
*   **Historical Archiving**: Invoices older than 7 years are moved to a separate read-only archive database schema, ensuring historical lookup capability without slowing down standard operations.

---

## SECTION 16: FUTURE ARCHITECTURAL EXPANSION

The JUANET Invoice Lifecycle Engine is designed to scale and adapt to diverse commercial structures and global tax compliance standards.

```
                      [EXTENSIBLE CONTRACT BILLING]
                      
                        [ Enterprise Contract ]
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
   [ Usage-Based ]          [ Milestones ]             [ Installments ]
    Invoiced on               Billed upon               Billed on pre-defined
    metered consumption.      phase completion.         payment schedules.
```

*   **Usage-Based & AI Consumption Invoices**: Tracks real-time metered usage (such as API call volumes or computing hours) and dynamically compiles consumption logs into recurring billing drafts.
*   **Milestone & Progress Invoices (Construction and Professional Services)**: Supports progress billing models, tracking Work-in-Progress (WIP) and releasing milestone billing against percentage-of-completion reports.
*   **Installment Billing**: Support for structured payment terms, allowing a single large invoice to be divided and collected across multiple pre-defined payment dates.
*   **Global Government E-Invoicing**: Pre-configured pipelines to export issued invoices to structured XML formats (e.g., Peppol, Factur-X) to comply with European and Latin American e-invoicing laws.

---

## SECTION 17: PRODUCTION BILLING LIFECYCLE CHECKLIST

Before deploying the Invoice Lifecycle Engine to production, verify that the following configurations and controls are in place.

- [ ] **State Machine Verified**: State machine transitions are tested, and forbidden transitions are blocked by database constraints.
- [ ] **Gapless Numbers Active**: Number sequence generation is confirmed as gapless under high-concurrency conditions.
- [ ] **Approval Routing Tested**: Multilevel approval thresholds and delegation rules are verified.
- [ ] **Tax Resolvers Validated**: Modular tax engine calculations match current local tax rates.
- [ ] **Immutable Logging Confirmed**: Invoice history writes are verified as active and append-only.
- [ ] **Event Idempotency Enforced**: Event consumers are confirmed as fully idempotent.
- [ ] **Performance Indexes Applied**: All primary performance and reporting database indexes are active.
- [ ] **Dunning Triggers Ready**: Overdue terms evaluations are configured and tested.

---
**End of Specification.**
