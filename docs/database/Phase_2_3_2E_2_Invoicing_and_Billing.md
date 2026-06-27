# JUANET ERP Invoicing & Billing Engineering Constitution
## Phase 2.3.2E.2 — Invoicing, Billing Lifecycle, and Accounts Receivable Core Specification
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET SaaS Platform  
**Classification:** Technical Architecture / General Ledger Integration Standard  

---

## SECTION 1: BILLING PHILOSOPHY & FIRST PRINCIPLES

### 1.1 Invoices as Legal Instruments vs. Payment Records
In high-integrity enterprise architectures (e.g., SAP S/4HANA, NetSuite ERP, Microsoft Dynamics 365 Finance), an **Invoice** is structurally and philosophically distinct from a **Payment Record**.

An invoice represents an authoritative, legal, and tax-binding assertion of value exchanged at a specific point in time. It is a contractual document that establishes:
1.  **A Legal Debt**: A formal receivable claim against a distinct counterparty (`client_accounts`).
2.  **A Tax Event**: The point at which value-added tax (VAT), goods and services tax (GST), or sales tax is accrued and legally owed to tax authorities under accrual accounting standards.
3.  **Revenue Recognition**: The formal declaration that performance obligations have been satisfied (partially or fully), converting deferred liabilities or unbilled accruals into earned income.

```
                          [THE BILLING TRILOGY DECOUPLING]
                          
   [ Legal Invoice ] ──────────────► [ Ledger Posting ] ──────────────► [ Payment Allocation ]
   Authoritative document            Asynchronous translation            Zero-impact matching
   of debt & tax accrual.            to general ledger debits/credits.   settles ledger balance.
```

A **Payment Record**, conversely, is a cash-flow event. Decoupling these concepts is mandatory to prevent severe audit failures:
*   An invoice can remain unpaid indefinitely (Overdue), but its tax liability must still be reported.
*   A payment can occur before an invoice is issued (Deposit / Pre-payment) or after (Collection), and can span multiple invoices or only partially settle a single invoice line.
*   Decoupling billing from payments protects the ledger from "payment races" and ensures absolute tax and reporting compliance.

---

### 1.2 Ten Principles of the JUANET Billing Engine

1.  **Revenue Recognition Separation (ASC 606 / IFRS 15)**: Billing a client does not equate to recognizing revenue. The Billing Engine manages invoice generation, while the Revenue Recognition Engine determines the timing and distribution of credit entries into either standard Revenue accounts (e.g., `4100`) or Deferred Revenue liability accounts (e.g., `2100`) based on performance obligations.
2.  **Accrual Accounting Invariance**: The Billing Engine operates on an accrual basis. Taxes and receivables must be recorded at the moment the invoice is formally issued (`status = 'issued'`), regardless of cash collection timelines.
3.  **Strict Immutability**: Once an invoice transitions out of the `Draft` state and receives a sequential, gapless billing number, it is **physically immutable**. No column representing financial values, tax rates, line quantities, or accounts can ever be modified. Corrections require issuing a formal Credit Note or Debit Note.
4.  **Decoupled Ledger Posting**: The Billing Engine does not write directly to ledger tables. Instead, it transitions states and emits structured business events. The **Ledger Posting Rule Engine** captures these events asynchronously to write balanced journal entries to the General Ledger.
5.  **Event-Driven Financial Architecture**: Every step of the billing lifecycle emits a deterministic event. Downstream systems (e.g., inventory write-offs, sales commissions, subscription renewals, or client notifications) react exclusively via event consumers.
6.  **Strict Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all billing, line-item, tax, and discount tables. Cross-tenant queries are blocked at the database engine layer.
7.  **Multi-Currency Support**: Every invoice is billed in a distinct Transaction Currency. The engine preserves the locked exchange rate at the time of issuance, tracking both the original amount and its equivalent value in the Organization's Base Currency and Reporting Currency to prevent exchange rate drift.
8.  **Tax Neutrality**: The billing core must remain neutral to regional tax policies. It delegates tax computation to specialized, pluggable local tax modules (VAT, sales tax, reverse charge) while storing uniform tax lines for general reporting.
9.  **Provider-Agnostic Payment Integration**: The billing engine supports offline payments (checks, bank wires) and multiple payment gateways (Stripe, Adyen, PayPal) through a provider-neutral abstraction layer, ensuring the billing schema remains completely decoupled from third-party vendor code.
10. **Historical Auditability**: Every mutation of status, metadata, or printing template records a history line, maintaining a complete, SOC2-compliant audit trail.

---

## SECTION 2: BILLING DOMAIN ARCHITECTURE

The Billing Domain is divided into highly specialized, decoupled subsystems, each responsible for an isolated step of the billing and cash-allocation process.

```
                           [JUANET BILLING SYSTEM ARCHITECTURE]

       ┌────────────────────────────────────────────────────────────────────────┐
       │                          CRM / Quotations / Sales                      │
       └───────────────────────────────────┬────────────────────────────────────┘
                                           │
                                           ▼
       ┌────────────────────────────────────────────────────────────────────────┐
       │                               Invoice Engine                           │
       └─────┬───────────────────┬───────────────────┬───────────────────┬──────┘
             │                   │                   │                   │
             ▼                   ▼                   ▼                   ▼
       ┌───────────┐       ┌───────────┐       ┌───────────┐       ┌───────────┐
       │  Pricing  │       │    Tax    │       │ Discount  │       │  Billing  │
       │  Engine   │       │  Engine   │       │  Engine   │       │ Scheduler │
       └─────┬─────┘       └─────┬─────┘       └─────┬─────┘       └─────┬─────┘
             │                   │                   │                   │
             └───────────────────┼───────────────────┼───────────────────┘
                                 ▼
       ┌────────────────────────────────────────────────────────────────────────┐
       │                       Posting Validation & Rule Engine                 │
       └─────────────────────────────────┬──────────────────────────────────────┘
                                         │
                                         ▼
                               [ Ledger Postings ]
```

### 2.1 Component Responsibilities & Domain Boundaries

#### A. Invoice Engine (`public.invoices`)
*   **Role**: The central coordinator of the billing domain. It aggregates line items, calculates document-level sums (subtotal, taxes, discounts, totals), and coordinates transitions through the invoice lifecycle.
*   **Boundary**: Owns the master state of the billing document. It does not perform tax math, pricing logic, or gateway payment processing.

#### B. Quote Conversion Service
*   **Role**: Converts approved sales quotes, opportunities, or contract drafts from the CRM domain into valid, draft invoices.
*   **Boundary**: Governs the transition from CRM (non-ledger) into Finance (ledger-ready). It verifies CRM quantities against inventory levels before spawning billing drafts.

#### C. Billing Scheduler
*   **Role**: Evaluates subscription schedules, recurring contract intervals, and installment payment structures to spawn automated invoices at exact intervals.
*   **Boundary**: Operates as a background job runner. It is responsible only for timing and initiation, delegating the invoice calculation entirely to the Invoice Engine.

#### D. Pricing Engine
*   **Role**: Resolves item, catalog, and contract-specific pricing rules. It applies volume breaks, customer-tier price sheets, or dynamic multi-tenant usage fees to determine the raw unit price of a line.
*   **Boundary**: Pure calculation engine. It takes input variables (Customer Profile, Item ID, Quantity) and returns a unit cost.

#### E. Tax Engine
*   **Role**: Resolves and calculates complex multi-jurisdictional tax liabilities (e.g., GST, VAT, state-level sales taxes) based on the customer's billing address and item categories.
*   **Boundary**: Pluggable microservice. It manages tax jurisdictions, compound rates, reverse charges, and zero-rating rules, returning structured tax rows (`public.invoice_tax_lines`) to the Invoice Engine.

#### F. Discount Engine
*   **Role**: Applies promotional codes, contract discounts, volume discounts, or prompt-payment discounts (e.g., 2/10 Net 30).
*   **Boundary**: Computes line-level or document-level reductions and writes references to `public.invoice_discounts`.

#### G. Credit Control & Limits Service
*   **Role**: Monitors customer credit health. It tracks outstanding receivables against defined credit limits and blocks or flags the issuance of new invoices or shipments if thresholds are breached.
*   **Boundary**: Intercepts the transition of invoices from `Draft` to `Issued`.

#### H. Collections Engine
*   **Role**: Monitors aging reports and triggers automated escalation sequences (dunning emails, account suspensions) based on overdue days.
*   **Boundary**: Triggered exclusively by `invoice.overdue` events.

#### I. Payment Allocation Engine
*   **Role**: Matches incoming cash payments with outstanding invoices. It manages partial allocations, overpayments, and invoice write-offs.
*   **Boundary**: Writes to `public.receivable_allocations`. It decreases the `balance_due` column of the target invoice to zero but never modifies the historical billing total (`total_amount`).

#### J. Revenue Recognition Engine
*   **Role**: Manages multi-period revenue distribution schedules for prepaid or deferred services.
*   **Boundary**: Owns the transition of deferred liabilities to active general ledger revenue accounts over time, independent of invoice billing cycles.

#### K. Posting Rule Engine
*   **Role**: Translates billing events (e.g., `invoice.sent`, `invoice.cancelled`) into structural double-entry journal lines.
*   **Boundary**: Map events to pre-defined accounts in the Chart of Accounts, creating immutable database records in `public.ledger_entries`.

#### L. Notification Engine
*   **Role**: Distributes rendered invoices to client billing contacts via email, secure customer portals, or electronic document networks (e.g., Peppol for e-invoicing).
*   **Boundary**: Handles delivery tracking and records the `invoice_delivery_statuses`.

---

## SECTION 3: BILLING LIFECYCLE & STATE TRANSITIONS

Every invoice flows through a strict, deterministic, and audited state machine. Under no circumstances can a step be bypassed, ensuring complete trace integrity for corporate audits.

```
                                [INVOICE STATE MACHINE]
                                
                  ┌───────────────────────┐
                  │         DRAFT         │◄────────────────────────┐
                  └───────────┬───────────┘                         │
                              │                                     │
                              ▼                                     │
                  ┌───────────────────────┐                         │
                  │   PENDING APPROVAL    ├─────────────────────────┤ (Rejected)
                  └───────────┬───────────┘                         │
                              │                                     │
                              ▼                                     │
                  ┌───────────────────────┐                         │
                  │       APPROVED        ├─────────────────────────┘
                  └───────────┬───────────┘
                              │
                              ▼
                  ┌───────────────────────┐
                  │        ISSUED         │ (Receives final immutable sequence number)
                  └───────────┬───────────┘
                              │
         ┌────────────────────┴────────────────────┐
         ▼                                         ▼
   ┌───────────┐                             ┌───────────┐
   │ DELIVERED │                             │ CANCELLED │ (Voided with reversal entries)
   └─────┬─────┘                             └───────────┘
         │
         ▼
   ┌───────────┐
   │  VIEWED   │
   └─────┬─────┘
         │
         ▼
   ┌───────────────────────────────────────────────┐
   ├───────────────────────┬───────────────────────┤
   ▼                       ▼                       ▼
┌──────────────┐    ┌──────────────┐        ┌──────────────┐
│ PARTIAL PAID │    │  FULLY PAID  │        │   OVERDUE    │
└──────┬───────┘    └──────┬───────┘        └──────┬───────┘
       │                   │                       │
       │                   │                       ▼
       │                   │                ┌──────────────┐
       │                   │                │ WRITTEN OFF  │
       │                   │                └──────┬───────┘
       │                   │                       │
       └───────────────────┼───────────────────────┘
                           ▼
                    ┌──────────────┐
                    │   ARCHIVED   │ (Immutable read-only storage)
                    └──────────────┘
```

### 3.1 Detail of Lifecycle Transitions

1.  **Draft**:
    *   *Definition*: The temporary workspace of the billing document. Line items can be added, modified, or removed freely.
    *   *Allowed Actions*: Full CRUD on lines, taxes, and discounts.
    *   *Ledger Impact*: Zero. It does not appear in Accounts Receivable or Revenue.
2.  **Pending Approval**:
    *   *Definition*: Locked for modification pending review by a Financial Controller.
    *   *Allowed Actions*: Transition to Approved or return to Draft with rejection comments.
    *   *Ledger Impact*: Zero.
3.  **Approved**:
    *   *Definition*: Verified as correct but not yet legally committed or delivered to the customer.
    *   *Allowed Actions*: Transition to Issued. If pricing details need adjustment, it must revert to Draft.
    *   *Ledger Impact*: Zero.
4.  **Issued**:
    *   *Definition*: The crucial point of financial crystallization. The invoice is locked and assigned a non-deletable, gapless sequence number.
    *   *Allowed Actions*: Transition to Delivered, Cancelled (under strict audit), or Paid.
    *   *Ledger Impact*: Emits `invoice.issued`. The Ledger Posting Engine immediately debits Accounts Receivable and credits Sales Revenue/Taxes.
5.  **Delivered**:
    *   *Definition*: Confirmed as transmitted to the customer's delivery channel (e.g., SMTP delivery success, Peppol gateway confirmation).
    *   *Allowed Actions*: Transition to Viewed, Paid, or Overdue.
6.  **Viewed**:
    *   *Definition*: Confirmed as opened or downloaded by the customer ( portal tracking or read receipt).
    *   *Allowed Actions*: Transition to Paid or Overdue.
7.  **Partially Paid**:
    *   *Definition*: One or more partial payments have been allocated, leaving `balance_due > 0` but `balance_due < total_amount`.
    *   *Allowed Actions*: Transition to Paid, Overdue, or Written Off (for outstanding fraction).
8.  **Paid**:
    *   *Definition*: Payments have been fully allocated, bringing `balance_due` to exactly `0.00`.
    *   *Allowed Actions*: Transition to Archived.
9.  **Overdue**:
    *   *Definition*: Current date has passed the defined `due_date` of the invoice, and `balance_due > 0`.
    *   *Allowed Actions*: Trigger escalation, charge overdue interest, transition to Written Off or Paid (upon collection).
10. **Written Off**:
    *   *Definition*: Debts identified as uncollectible (e.g., client bankruptcy, small balance write-off).
    *   *Allowed Actions*: Transition to Archived.
    *   *Ledger Impact*: Emits `invoice.written_off`. Posts Debit to Bad Debt Expense and Credit to Accounts Receivable.
11. **Archived**:
    *   *Definition*: Legally closed, paid, or adjusted. Historical records are compressed and stored in write-once-read-many (WORM) configurations for compliance.

---

### 3.2 Prohibited Lifecycle Mutations (Hard Constraints)

To prevent serious financial irregularities, database constraint rules and RLS policies block the following state transitions:

*   **Prohibit `Issued -> Draft`**: An issued invoice cannot be reverted to Draft. To correct an issued invoice, a Credit Note must be generated.
*   **Prohibit `Paid -> Draft / Pending / Approved`**: Once an invoice is paid, it cannot be reverted to any prior state.
*   **Prohibit `Cancelled -> Paid`**: A cancelled invoice represents a voided document; it can never receive cash allocations.
*   **Prohibit `Written Off -> Paid`**: Write-offs are definitive financial events. If payment is collected later, it must be logged as a "Bad Debt Recovery" revenue event, not as a standard invoice allocation.

---

## SECTION 4: BILLING DOCUMENT TYPE SPECTRUM

JUANET supports a comprehensive spectrum of billing documents to cover all corporate sales structures.

```
                          [BILLING DOCUMENT TYPOLOGY]
                          
      Operational Bills                      Correction Bills
      ├── Invoice (Standard)                 ├── Credit Note (Reversal)
      ├── Pro Forma Invoice                  └── Debit Note (Addition)
      ├── Subscription Invoice
      └── Progress Invoice (Milestone)
```

| Document Type | Purpose | Financial Impact | Lifecycle Rules |
| :--- | :--- | :--- | :--- |
| **Standard Invoice** | Billed for shipped goods or completed services. | Debits Accounts Receivable immediately upon issuance. | Draft -> Issued -> Paid. |
| **Pro Forma Invoice** | Draft estimate provided to customers before goods are shipped. | No general ledger impact. Used for customs or pre-payments. | Always non-posting. |
| **Recurring Invoice** | Template generated at scheduled intervals. | Spawns standard invoices dynamically. | Never posts directly. |
| **Subscription Invoice** | Usage-based or subscription billing. | Integrates with deferred revenue schedules. | Accrues to Deferred Revenue. |
| **Credit Note** | Reduces the value of a previously issued invoice. | Credits Accounts Receivable, debits Sales Revenue. | Must link to a source invoice. |
| **Debit Note** | Increases the value of a previously issued invoice. | Debits Accounts Receivable, credits Sales Revenue. | Must link to a source invoice. |
| **Deposit Invoice** | Requests a prepayment or retainer before work starts. | Debits Accounts Receivable, credits Prepayment Liability. | Resolved when final bill is issued. |
| **Progress Invoice** | Milestone billing for long-term projects. | Debits Accounts Receivable, offsets Work-in-Progress (WIP). | Linked to project stage completion. |
| **Final Invoice** | Closes a progress-billed contract, detailing all offsets. | Settles WIP and records the final net receivable. | Closes the billing contract. |
| **Cancellation Invoice** | Automatically voids a standard invoice. | Fully reverses original postings. | Used for simple billing mistakes. |
| **Internal Charge** | Transfer of cost between departments. | Debits Receiving Dept, credits Supplying Dept. | Net-zero consolidated impact. |
| **Intercompany Invoice** | Invoice issued between subsidiary legal entities. | Requires automated matching payable at receiving entity. | Strictly eliminated during consolidation. |

---

## SECTION 5: GAPLESS SEQUENTIAL NUMBERING ARCHITECTURE

To satisfy global tax audits and comply with corporate finance standards, billing documents must be assigned **gapless, sequential, unique, and concurrency-safe** numbers upon transition to the `Issued` state. Missing invoice numbers can indicate unreported sales and lead to severe tax audits.

### 5.1 Numbering Standards & Multi-Segment Masks
The JUANET system uses flexible, token-based templates configured per organization or branch entity:

$$\text{Format Mask} = [\text{Prefix}]-[\text{Year}]-[\text{Sequence}]$$

*   **Standard Invoice Sequence**: `INV-2026-000001`
*   **Credit Note Sequence**: `CN-2026-000021`
*   **Debit Note Sequence**: `DN-2026-000004`
*   **Branch-Specific Sequence**: `INV-LON-2026-000192` (London office)

---

### 5.2 Concurrency-Safe, Gapless Allocation Algorithm
In high-throughput SaaS environments, relying on simple PostgreSQL `serial` or identity columns is dangerous. If a transaction fails or is aborted after pulling a number, standard database sequences will skip that number, leaving a permanent gap in the billing records.

To prevent gaps, JUANET utilizes a dedicated numbering sequence registry combined with selective database row locks (`SELECT ... FOR UPDATE`).

```
                    [GAPLESS NUMBER ALLOCATION FLOW]
                        Invoice Ready to Issue
                                   │
                                   ▼
                    Acquire Lock on Sequence Row
               SELECT FOR UPDATE on Sequence Registry
               (Blocks other invoices on this sequence)
                                   │
                                   ▼
                    Read Current Sequence Value
                                   │
                                   ▼
                   Generate Formatted Invoice Number
                                   │
                                   ▼
                    Update Invoice Record with Number
                                   │
                                   ▼
                   Increment Sequence Registry Value
                                   │
                                   ▼
                   Commit Transaction & Release Lock
```

This lock ensures that numbering is allocated and committed sequentially within a single database transaction. If the transaction fails, the row lock is released without incrementing the sequence registry, maintaining a clean, gapless audit trail.

---

## SECTION 6: BILLING LOOKUP SCHEMAS

The relational states of invoices are managed via discrete lookup tables to ensure clear separation of concerns.

```
                    [INVOICE STATUS LOOKUP STRUCT]
                    
                        [ public.invoices ]
                                 │
     ┌───────────────────┬───────┴───────────┬───────────────────┐
     ▼                   ▼                   ▼                   ▼
[ Statuses ]    [ Delivery Status ]   [ Payment Status ]  [ Approval Status ]
- Draft         - Pending             - Unpaid            - Draft
- Approved      - Sent                - Partially Paid    - Pending Approval
- Issued        - Delivered           - Paid              - Approved
- Cancelled     - Failed              - Written Off       - Rejected
```

### 6.1 Status Tables Definitions

*   **`public.invoice_statuses`**: Represents the primary operational status of the billing document. Valid statuses are `'draft'`, `'pending_approval'`, `'approved'`, `'issued'`, `'cancelled'`, and `'archived'`.
*   **`public.invoice_delivery_statuses`**: Tracks transmission state to ensure client delivery compliance. Valid statuses are `'not_delivered'`, `'sent'`, `'delivered'`, and `'failed'`.
*   **`public.invoice_payment_statuses`**: Managed by the Cash Allocation Engine. Valid statuses are `'unpaid'`, `'partially_paid'`, `'paid'`, and `'written_off'`.
*   **`public.invoice_approval_statuses`**: Tracks the approval routing process. Valid statuses are `'not_submitted'`, `'submitted'`, `'approved'`, and `'rejected'`.
*   **`public.invoice_collection_statuses`**: Used by credit control systems to trigger collections actions. Valid statuses are `'current'`, `'dunning_stage_1'`, `'dunning_stage_2'`, `'legal_escalation'`, and `'uncollectible'`.

---

## SECTION 7: CUSTOMER BILLING PROFILES

Every client organization must define a default **Customer Billing Profile** to ensure accurate pricing, tax calculation, and financial ledger routing.

### 7.1 Billing Profile Specifications

*   **Default Currency**: Specifies the currency in which standard invoices are generated.
*   **Language**: Determines the language used for PDF generation and notification templates.
*   **Tax Jurisdiction & Region**: The primary taxing district, such as local state codes or specific international VAT classifications.
*   **Payment Terms**: Defines the credit window, such as Net 30, Net 15, or Prompt Payment structures (e.g., 2% discount if paid within 10 days).
*   **Credit Limit**: The maximum amount of outstanding debt allowed for the client. If this limit is breached, the invoicing engine flags or blocks further shipments or invoice approvals.
*   **Billing Contact**: Primary and secondary email addresses used for electronic invoice distribution.
*   **Preferred Payment Gateway**: Resolves which payment gateway (e.g., Stripe, Adyen) to display on electronic invoices.
*   **Preferred Delivery Method**: Specifies whether invoices should be sent via email, an API integration, or an e-invoicing network.
*   **Default Dimensions**: Default Cost Center, Project, or Department codes assigned to the client's invoice lines, enabling streamlined profit-and-loss tracking.
*   **Default Taxes**: Default tax rates applied to the customer's line items.
*   **Default Discounts**: Pre-negotiated discount rates applied to the customer's invoices.

---

## SECTION 8: BILLING VALIDATION ENGINE

To prevent invalid or incorrect ledger postings, every invoice must pass through a strict validation pipeline before it can be finalized and issued.

```
                    [INVOICE VALIDATION SEQUENCE]
                    
                        [ Submit for Issuance ]
                                   │
                                   ▼
                        Is Client Profile Active? ─── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                       Verify Pricing & Line Sums ─── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                      Is Accounting Period Open? ──── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                      Verify Posting Rules Exist ──── No ──► [ REJECT ]
                                   │ Yes
                                   ▼
                       Check Credit Limit Rules ───── No ──► [ FLAG/HOLD ]
                                   │ Yes
                                   ▼
                       [ ALLOCATE INVOICE NUMBER ]
```

### 8.1 Validation Invariant Matrix

| Validation Step | Rule Checked | Failure Action | Architectural Rationale |
| :--- | :--- | :--- | :--- |
| **Client Status** | Verify customer status is `'active'`. | **REJECT** | Prevents billing suspended or deactivated accounts. |
| **Line Item Totals** | Verify: $\text{Subtotal} + \text{Tax} - \text{Discount} = \text{Total}$ | **REJECT** | Prevents arithmetic drift and rounding discrepancies. |
| **Active Currency** | Ensure `currency_id` is supported and active in system configurations. | **REJECT** | Blocks postings in unsupported currencies. |
| **Open Period** | Confirm the invoice `issue_date` falls within an `OPEN` accounting period. | **REJECT** | Prevents retroactive postings to closed financial periods. |
| **Posting Rules** | Ensure valid posting rules exist for the target transaction event. | **REJECT** | Ensures the invoice can be translated into general ledger entries without failure. |
| **Credit Limits** | Check that `total_amount` + current receivables is less than the client's credit limit. | **HOLD / WARN** | Prevents over-extending credit to customers. |
| **Positive Line Sums**| Verify quantity and unit price are greater than zero for standard items. | **REJECT** | Prevents negative invoices (credits should use Credit Notes). |

---

## SECTION 9: MULTI-CURRENCY BILLING & EXCHANGE RATES

Enterprise billing requires support for foreign currencies, requiring clear guidelines to manage exchange rates and prevent balance discrepancies.

```
                       [EXCHANGE RATE DRIFT MECHANISM]
                       
   Invoiced: USD 1,000 (Rate: 1.30) ───────────────► Accounts Receivable: GBP 769.23
   
   Paid:     USD 1,000 (Rate: 1.35) ───────────────► Cash at Bank:        GBP 740.74
                                                                               │
                                                                               ▼
                                                            Difference:   GBP 28.49
                                                            (Posted to Realized Forex Loss)
```

### 9.1 Multi-Currency Rules

*   **Locked Invoice Currency**: The exchange rate is snapped and locked at the exact moment the invoice is transitioned to `'issued'`. This rate (`exchange_rate_at_posting`) is recorded on the invoice and used to calculate the transaction value in the organization's base currency, preventing subsequent rate changes from retroactively altering the invoice.
*   **Base Currency vs. Reporting Currency**:
    *   **Base Currency**: The primary currency used to record transactions in the organization's general ledger.
    *   **Reporting Currency**: The currency used to prepare consolidated financial reports for external stakeholders.
*   **Realized vs. Unrealized Forex Gains/Losses**:
    *   **Realized Gains/Losses**: Calculated and posted when a foreign-currency invoice is paid. If the exchange rate changes between the billing date and the payment date, the difference is posted to a Realized Forex Gain/Loss account.
    *   **Unrealized Gains/Losses**: Calculated during period close by revaluing open foreign-currency receivables against current spot rates. The adjustment is posted to an Unrealized Forex Gain/Loss account and reversed on the first day of the next period.
*   **Rounding and Exchange Adjustments**: Minor rounding discrepancies resulting from currency conversion are allocated to a dedicated Currency Rounding account, ensuring the general ledger remains balanced.

---

## SECTION 10: TAX COMPLIANCE FOUNDATION

JUANET's billing engine implements a modular tax framework to support diverse regional tax compliance requirements.

```
                         [MODULAR TAX RESOLUTION]
                             Invoice Engine
                                    │
                                    ▼
                          Determine Tax Region
                                    │
                                    ▼
                        Pluggable Tax Calculator
                     ┌──────────────┼──────────────┐
                     ▼              ▼              ▼
                   [ VAT ]       [ GST ]     [ Sales Tax ]
                     │              │              │
                     └──────────────┼──────────────┘
                                    ▼
                         Write Invoice Tax Lines
```

### 10.1 Tax Architecture Classifications

*   **Value-Added Tax (VAT)**: Indirect tax added to products or services at each stage of production. Standard VAT rates are applied, with physical support for reverse-charge mechanisms for B2B cross-border transactions.
*   **Goods and Services Tax (GST)**: Uniform multi-stage tax applied to goods and services, requiring detailed reporting of input tax credits.
*   **Sales Tax**: Destination-based tax applied to final retail sales, resolved based on the customer's shipping address.
*   **Withholding Tax (WHT)**: Taxes deducted at the source by the payer, requiring the billing engine to track both the gross invoice total and the net cash collection expectation.
*   **Reverse Charge**: Shifts the liability to report and pay VAT from the seller to the buyer, resulting in zero-rated tax invoices.
*   **Zero-Rated and Exempt**: Documents marked as zero-rated or exempt are validated against required regulatory codes to ensure audit compliance.
*   **Destination-Based vs. Origin-Based Taxation**:
    *   **Destination-Based**: Tax rate resolved based on the customer's delivery address.
    *   **Origin-Based**: Tax rate resolved based on the seller's physical shipping point.
*   **Compound Taxes**: Supports layered tax calculations where secondary taxes are computed on top of subtotals and primary taxes.

---

## SECTION 11: BILLING EVENTS SPECIFICATION

The Billing Engine is fully event-driven, emitting structured events to trigger downstream general ledger postings and system actions.

```
                           [EVENT POSTING ENGINE]
                           
      [ Invoice Engine ] ──► Emit Event ──► [ Event Registry ] ──► [ GL Posting Engine ]
```

### 11.1 Full Emitted Events Registry

JUANET emits the following standard, high-integrity events throughout the billing document lifecycle.

| Event Type | Producer Component | Core Consumers | Purpose & Downstream Actions |
| :--- | :--- | :--- | :--- |
| **`invoice.created`** | Invoice Engine | CRM Integration, Audit Log | Generated when a draft invoice or credit note is initialized. |
| **`invoice.approved`** | Invoice Engine | Notification Engine, History | Generated when a manager/controller signs off on a draft invoice. |
| **`invoice.issued`** | Invoice Engine | Ledger Posting Engine, Credit Control | Emitted when an invoice receives its gapless sequence number, triggering AR & Revenue ledger postings. |
| **`invoice.sent`** | Notification Engine | Email Service, Peppol Gateway | Emitted when the invoice has been successfully delivered to the customer. |
| **`invoice.viewed`** | Customer Portal | CRM, Collections Service | Triggered when the customer opens the invoice on the digital portal. |
| **`invoice.overdue`** | Billing Scheduler | Collections Engine, Dunning Service | Emitted when standard terms expire and balance remains outstanding. |
| **`invoice.cancelled`** | Invoice Engine | Ledger Posting Engine, CRM | Emitted when a document is voided, initiating reversing posting entries. |
| **`invoice.paid`** | Payment Allocation Engine | Ledger Posting Engine, Subscription Engine | Emitted when allocations bring the outstanding balance to exactly `0.00`. |
| **`invoice.voided`** | Invoice Engine | Ledger Posting Engine, Audit Log | Emitted when a draft or approved document is cancelled before issuance. |
| **`invoice.written_off`** | Credit Control / CFO | Ledger Posting Engine, Tax Engine | Emitted when bad debt is registered, offsetting AR and accruing bad debt expenses. |
| **`invoice.reopened`** | Auditor Override | Audit Engine, Ledger Posting Engine | Emitted under dual authorization during historic period adjustments. |

---

### 11.2 Real-Time Event Structures

#### `invoice.issued`
```json
{
  "event_id": "evt_bill_019283711",
  "event_type": "invoice.issued",
  "organization_id": "org_771829",
  "payload": {
    "invoice_id": "inv_8829103",
    "invoice_number": "INV-2026-000492",
    "client_account_id": "cli_44921",
    "subtotal": 12000.00,
    "tax_amount": 2400.00,
    "discount_amount": 0.00,
    "total_amount": 14400.00,
    "currency_code": "EUR",
    "exchange_rate": 1.085,
    "amount_in_base": 15624.00,
    "issue_date": "2026-06-27",
    "due_date": "2026-07-27"
  },
  "timestamp": "2026-06-27T09:30:00Z"
}
```

#### `invoice.paid`
```json
{
  "event_id": "evt_bill_019283750",
  "event_type": "invoice.paid",
  "organization_id": "org_771829",
  "payload": {
    "invoice_id": "inv_8829103",
    "invoice_number": "INV-2026-000492",
    "client_account_id": "cli_44921",
    "payment_allocation_id": "alloc_002931",
    "amount_allocated": 14400.00,
    "balance_due": 0.00,
    "settlement_currency": "EUR"
  },
  "timestamp": "2026-06-27T14:15:22Z"
}
```

#### `invoice.cancelled`
```json
{
  "event_id": "evt_bill_019283799",
  "event_type": "invoice.cancelled",
  "organization_id": "org_771829",
  "payload": {
    "invoice_id": "inv_8829103",
    "invoice_number": "INV-2026-000492",
    "cancellation_reason": "Billing correction - incorrect tax rate applied",
    "credit_note_generated_id": "cn_330912"
  },
  "timestamp": "2026-06-27T16:00:10Z"
}
```

---

### 11.2 Reliability, Retries & Idempotency
*   **Idempotency Keys**: All billing event payloads include a deterministic `event_id` or an `idempotency_key` (derived from `invoice_id` and `target_status`). Downstream consumers and ledger post-workers must verify this key before writing records to prevent duplicate postings.
*   **Retry Policies**: If a downstream ledger posting fails (e.g., due to a temporary database lock), the event worker retries the action using an exponential backoff strategy with jitter, up to a maximum of 10 attempts over 24 hours. If failures persist, the event is routed to a Dead Letter Queue (DLQ) for administrative intervention.

---

## SECTION 12: ROLE-BASED ACCESS CONTROL & AUDITABILITY

To ensure compliance with SOC2 standards and prevent internal financial fraud, permissions within the Billing Engine are tightly controlled.

### 12.1 Security Permissions Grid

| Operational Permission | Billing Clerk | Sales Manager | Financial Controller | CFO / Director |
| :--- | :---: | :---: | :---: | :---: |
| **Create Draft Invoice** | Yes | Yes | Yes | Yes |
| **Modify Draft Invoice** | Yes | Yes | Yes | Yes |
| **Approve Invoice** | No | Yes | Yes | Yes |
| **Issue Invoice (Commit)** | No | No | Yes | Yes |
| **Void / Cancel Invoice** | No | No | Yes | Yes |
| **Write Off Invoice** | No | No | No | Yes |
| **Reopen Archived Invoice**| No | No | No | Yes |

---

### 12.2 Security and SOC2 Controls
*   **Dual Authorization (Four-Eyes Principle)**: Invoices with total values exceeding $50,000 cannot be issued by a single user. They must be drafted by one user and approved by a second authorized controller.
*   **Immutable Invoice History**: All modifications, status transitions, and delivery attempts are recorded in an append-only audit table (`public.invoice_history`). This table cannot be altered or deleted.

---

## SECTION 13: PERFORMANCE ENGINE & OPTIMIZATION

High-volume invoicing systems require targeted database configurations to ensure rapid report generation and document loading.

### 13.1 Database Performance Configurations

```sql
-- 1. Speeds up billing search queries in active workflows
CREATE INDEX invoices_workflow_lookup_idx 
  ON public.invoices(organization_id, status)
  WHERE status IN ('draft', 'pending_approval', 'issued', 'overdue');

-- 2. Optimizes Accounts Receivable calculations for customer portals
CREATE INDEX invoices_balance_tracking_idx 
  ON public.invoices(client_account_id, balance_due)
  WHERE balance_due > 0.00;

-- 3. Composite index to speed up month-end revenue and tax reports
CREATE INDEX invoices_tax_reporting_idx 
  ON public.invoices(organization_id, issue_date)
  INCLUDE (subtotal, tax_amount, discount_amount, total_amount);
```

---

### 13.2 Asynchronous Document Rendering Queue
To prevent database connection pool bottlenecks, PDF generation is decoupled from the transactional flow. When an invoice transitions to `'issued'`, the system adds a rendering job to a background queue, rather than generating the PDF synchronously within the HTTP request thread. This ensures rapid API response times and isolates the invoice generation process.

---

## SECTION 14: CONCURRENCY & RACE SAFARDS

Because invoices are high-volume, multi-user entities, the system implements robust concurrency controls to ensure data safety.

### 14.1 Concurrency Safeguards

*   **Optimistic Locking (`version`)**: The `invoices` table includes an integer `version` column. All update operations verify that the version matches the record at the time of reading, preventing concurrent update conflicts.
*   **Double-Allocation Prevention**: When two users attempt to allocate a payment to the same invoice concurrently, the database uses a row lock (`SELECT FOR UPDATE`) on the invoice. This forces allocations to execute sequentially, preventing duplicate payments or incorrect balances.
*   **Idempotent Payment Webhooks**: To prevent duplicate payment allocations from payment gateways (e.g., Stripe sending multiple webhook attempts), all incoming payments are validated against an idempotency key before being processed.

---

## SECTION 15: FUTURE ARCHITECTURAL EXPANSION

The billing engine is designed to accommodate complex commercial billing models out of the box.

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

*   **Usage and Metered Billing**: Future support for consumption-based models (e.g., billing per GB or API call). The engine tracks consumption logs and automatically calculates and appends usage-based charges to the active billing cycle.
*   **Milestone and Progress Billing**: Streamlined structures for long-term project billing, tracking work-in-progress and releasing partial progress invoices upon milestone approval.
*   **Installment Billing**: Support for structured payment terms, allowing a single large invoice to be divided and collected across multiple pre-defined payment dates.

---

## SECTION 16: PRODUCTION BILLING READINESS CHECKLIST

Before deploying the Billing Engine to production, verify that the following configurations and controls are in place.

- [ ] **Gapless Sequences Configured**: Gapless numbering sequences are defined and tested under high-concurrency scenarios.
- [ ] **Tax Plugs Verified**: Regional tax modules (VAT/Sales Tax) are configured and tested against current tax rates.
- [ ] **Posting Rules Validated**: Downstream general ledger posting rules are verified for all core billing events.
- [ ] **Credit Limits Enabled**: Credit check rules are active and tested against outstanding receivables.
- [ ] **Idempotency Keys Enforced**: Event consumer queues are verified as fully idempotent.
- [ ] **Immutable History Active**: Database triggers are confirmed as preventing updates to issued invoice history records.
- [ ] **PDF Rendering Offloaded**: Background worker queues are active and successfully offloaded from the transactional thread.

---
**End of Specification.**
