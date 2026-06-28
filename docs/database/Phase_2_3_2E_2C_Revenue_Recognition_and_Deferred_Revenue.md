# JUANET ERP Revenue Recognition & Deferred Revenue Engine Specification
## Phase 2.3.2E.2C — Revenue Recognition and Deferred Revenue Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Accrual Accounting, Revenue Recognition, and Compliance Core  

---

## SECTION 1: REVENUE RECOGNITION PHILOSOPHY

Under modern accrual accounting standards—specifically **IFRS 15** (Revenue from Contracts with Customers) and **ASC 606**—revenue recognition is completely decoupled from the transactional mechanics of billing (invoicing) and treasury (cash collection). Recognizing revenue represents a formal assessment of when an enterprise has satisfied its contractual promises to a customer.

```
                  [DECOUPLED TRANSACTIONAL & ACCRUAL FLOWS]

      [ Billing Event ]                [ Treasury Event ]              [ Performance Event ]
       Invoice Issued                   Cash Receipt                    Service Rendered
             │                                │                                │
             ▼                                ▼                                ▼
    Debits: Accounts Rec.            Debits: Cash Asset               Debits: Deferred Revenue
    Credits: Deferred Rev.           Credits: Accounts Rec.           Credits: Recognized Rev.
```

### 1.1 Core Financial Distinctions

To ensure audit-grade general ledger integrity, JUANET enforces a strict separation between five distinct financial concepts:

1.  **Invoice**: A billing artifact and legal demand for payment. It establishes accounts receivable and registers tax liabilities. It does *not* constitute earned income.
2.  **Cash Receipt**: An asset settlement event. It records the liquidation of accounts receivable or the deposit of prepayments. It is cash flow, not revenue.
3.  **Performance Obligation (POB)**: A contractually distinct promise to transfer a control-bound good or service to a customer. It is the core accounting unit of ASC 606 and IFRS 15.
4.  **Revenue Recognition**: The accounting event that records the transfer of promised goods or services to the customer in an amount that reflects the consideration the enterprise expects to be entitled to. This shifts value from a liability account to an equity/income account.
5.  **Deferred Revenue**: A balance sheet liability account representing cash or billing values received for goods or services that have not yet been fully delivered or performed.

---

### 1.2 Independent Revenue Recognition

The timing of revenue recognition is determined solely by the fulfillment of performance obligations.
*   **Billing Ahead of Performance**: When an annual subscription is invoiced upfront, Accounts Receivable is debited and Deferred Revenue (liability) is credited. Revenue is recognized incrementally over the subsequent 12 months as services are delivered, regardless of when the invoice is paid.
*   **Performance Ahead of Billing**: When work is completed under a milestone contract before an invoice is issued, the system registers **Unbilled Receivables (Accrued Revenue)** as an asset, recognizing revenue immediately. An invoice is generated subsequently, credit-shifting Unbilled Receivables into Accounts Receivable.

---

### 1.3 The 5-Step Model Compliance (IFRS 15 / ASC 606)

The Revenue Recognition Engine automates and enforces compliance with the five-step revenue model:

```
┌─────────────────────────────────┐
│ Step 1: Identify the Contract   │ ──► Multi-tenant contract registry (contract headers)
└────────────────┬────────────────┘
                 ▼
┌─────────────────────────────────┐
│ Step 2: Performance Obligations │ ──► Map distinct line items to performance obligations (POBs)
└────────────────┬────────────────┘
                 ▼
┌─────────────────────────────────┐
│ Step 3: Transaction Price       │ ──► Parse base pricing, discounts, and variable consideration
└────────────────┬────────────────┘
                 ▼
┌─────────────────────────────────┐
│ Step 4: Allocate Price to POBs  │ ──► Allocate transaction price based on Standalone Selling Price (SSP)
└────────────────┬────────────────┘
                 ▼
┌─────────────────────────────────┐
│ Step 5: Recognize Revenue       │ ──► Execute point-in-time or over-time recognition schedules
└─────────────────────────────────┘
```

---

## SECTION 2: PERFORMANCE OBLIGATIONS (POBS)

A Performance Obligation is the atomic tracking unit for revenue. Every sales contract or subscription order in JUANET is decomposed into one or more distinct POBs.

### 2.1 Obligation Categories

1.  **Single Obligation Contracts**: Simple arrangements where a single service or subscription is purchased (e.g., a standard monthly SaaS tier). The contract price is allocated entirely to one POB.
2.  **Multiple Obligation Contracts**: Complex transactions where distinct items are bundled together. Under ASC 606, these must be unbundled and evaluated separately.
3.  **Bundled Services**: Combining distinct software access, data migration services, and dedicated support. Each component represents a separate POB with distinct recognition schedules.
4.  **Hardware + Service Bundles**: A physical IoT gateway shipped to a customer paired with a 36-month monitoring subscription. The hardware is recognized as a *Point-in-Time* POB upon shipping, while the monitoring service is recognized *Over-Time* on a straight-line basis.
5.  **Subscription Services**: Standard continuous cloud access. These obligations are recognized over time as the customer consumes access.
6.  **Milestone Contracts**: Services delivered in phases (e.g., custom enterprise integrations). Revenue is recognized upon formal customer sign-off of specific deliverables.
7.  **Usage-Based Contracts**: Metered consumption billing (e.g., api calls, computing credits). Revenue is recognized as consumption events are logged.

---

### 2.2 Standalone Selling Price (SSP) and Allocation

When multiple POBs are bundled, the contract transaction price is allocated to each POB proportionally based on its relative Standalone Selling Price (SSP).

$$\text{Allocated Price}_{\text{POB}_i} = \text{Transaction Price}_{\text{Total}} \times \left( \frac{\text{SSP}_{\text{POB}_i}}{\sum \text{SSP}} \right)$$

#### SSP Allocation Example
An enterprise contract bundles three distinct items for a total package price of **$12,000**:
*   Enterprise Software License (Standard SSP: **$10,000**)
*   Implementation Services (Standard SSP: **$4,000**)
*   Premium Support SLA (Standard SSP: **$2,000**)

The total combined SSP is **$16,000**. The engine calculates and allocates the contract price as follows:

| Performance Obligation (POB) | Standard SSP | Proportion | Allocated Contract Price | Recognition Method |
| :--- | :--- | :--- | :--- | :--- |
| **Enterprise License** | $10,000 | 62.5% | **$7,500** | Straight-line (12 Months) |
| **Implementation Services** | $4,000 | 25.0% | **$3,000** | Percentage-of-Completion |
| **Premium Support SLA** | $2,000 | 12.5% | **$1,500** | Straight-line (12 Months) |
| **Total Bundle** | **$16,000** | **100.0%** | **$12,000** | - |

---

## SECTION 3: REVENUE RECOGNITION METHODS

The JUANET Revenue Recognition Engine implements deterministic execution paths for standard recognition methods.

```
                           [RECOGNITION METHOD EXECUTIONS]
                           
       POINT-IN-TIME              STRAIGHT-LINE (OVER TIME)          CONSUMPTION/USAGE
   [ Performance Event ]            [ Monthly Scheduler ]          [ Metered Consumption ]
             │                                │                                │
             ▼                                ▼                                ▼
    Recognize 100% of                Amortize proportionally        Recognize dynamically as
    allocated price immediately.     daily/monthly over term.       consumption logs occur.
```

### 3.1 Point-in-Time Recognition
*   **Description**: 100% of the allocated POB transaction price is recognized immediately when control transfers to the customer.
*   **Entry Criteria**: Fulfillment event verified (e.g., warehouse shipping confirmation, digital license key generation).
*   **Completion Event**: Electronic handoff success log or customer signature.
*   **Ledger Impact**: Debits Deferred Revenue (or Unbilled Receivables), Credits Recognized Sales Revenue.

### 3.2 Straight-Line Recognition (Over-Time)
*   **Description**: Amortizes revenue evenly over a defined contract duration.
*   **Entry Criteria**: Contract start date.
*   **Amortization Frequency**: Daily or Monthly schedules.
*   **Completion Event**: Contract end date reached.
*   **Ledger Impact**: Monthly execution debits Deferred Revenue, Credits Recognized Sales Revenue.

### 3.3 Milestone Recognition
*   **Description**: Revenue is recognized in discrete blocks upon completion of specified deliverables.
*   **Entry Criteria**: Project contract initiation.
*   **Completion Event**: Customer signs and uploads a Certificate of Acceptance, triggering the system to release the milestone percentage.
*   **Ledger Impact**: Debits Deferred Revenue, Credits Recognized Sales Revenue for the milestone allocation.

### 3.4 Percentage-of-Completion (Cost-to-Cost Method)
*   **Description**: Recognizes revenue dynamically based on progress toward completing a project.
*   **Calculation**:
    $$\text{Progress \%} = \frac{\text{Actual Costs Incurred}}{\text{Estimated Total Project Costs}}$$
    $$\text{Recognized Revenue} = \text{Progress \%} \times \text{Allocated Price}_{\text{Total}}$$
*   **Entry Criteria**: Operational tracking of hours/costs active.
*   **Completion Event**: Project close-out checklist complete.
*   **Ledger Impact**: Monthly adjusts Accrued Revenue (Asset) and Revenue (Income) to match calculated progress.

### 3.5 Usage-Based / Consumption Recognition
*   **Description**: Revenue is recognized dynamically as the customer consumes metered resources (e.g., storage terabytes or processing credits).
*   **Entry Criteria**: Issuance of prepaid consumption credits.
*   **Recognition Event**: Daily import of consumption telemetry logs.
*   **Ledger Impact**: Debits Deferred Revenue (Prepaid credits), Credits Recognized Sales Revenue based on units consumed.

---

## SECTION 4: DEFERRED REVENUE ENGINE

Deferred revenue is a balance sheet liability indicating that the company has received payment or generated invoices, but has not yet fulfilled its performance obligations.

```
                          [DEFERRED REVENUE LIFECYCLE]
                          
                          [ Upfront Invoice Issued ]
                                      │
                                      ▼
                        [ Debit Accounts Receivable ]
                        [ Credit Deferred Revenue   ]
                                      │
                                      ▼
                          [ Monthly Amortization ]
                          ──► Amortization run evaluates schedule.
                          ──► Debits Deferred Revenue.
                          ──► Credits Recognized Revenue.
                                      │
                   ┌──────────────────┼──────────────────┐
                   ▼                  ▼                  ▼
          [ Contract Mod ]     [ Termination ]      [ Cash Refund ]
          Recalculate balance  Accelerate remaining  Reduce deferred liability
          prospectively.       amortization to P&L.  and issue cash refund.
```

### 4.1 Amortization and Adjustments

1.  **Monthly Amortization Run**: On the last day of each accounting period, a background scheduler processes active recognition schedules (`public.recognition_schedule_lines`), executing amortizations for the period and writing balanced journal entries to the General Ledger.
2.  **Contract Modifications**: When a contract is modified mid-term, the engine recalculates the remaining deferred revenue:
    *   **Prospective Treatment**: Applies modifications to remaining performance obligations, adjusting the amortization rate of future schedules without altering historical postings.
    *   **Retrospective Treatment**: Recalculates historical postings and registers a cumulative adjustment in the current open period.
3.  **Early Terminations**: If a customer terminates a non-refundable contract early, any remaining deferred revenue balance is accelerated and recognized immediately as contract termination income:
    *   **Debit**: Deferred Revenue (Liability)
    *   **Credit**: Recognized Contract Termination Revenue (Income)
4.  **Refund Integrations**: If an active subscription is cancelled and refunded, the deferred revenue liability is debited directly to offset the cash payout, avoiding revenue distortion.

---

## SECTION 5: RECOGNITION SCHEDULE GENERATOR

The Recognition Schedule Generator is a core engine that decomposes an approved performance obligation into a chronological table of monthly recognition lines.

### 5.1 Schedule Generation Parameters

The generator requires five input parameters:
*   `pob_id`: Reference to the core Performance Obligation.
*   `allocated_amount`: The transaction price allocated to this specific POB.
*   `start_date`: The exact date recognition begins.
*   `end_date`: The exact date recognition concludes.
*   `frequency`: The amortization interval (`'daily'`, `'weekly'`, `'monthly'`, `'quarterly'`, `'annual'`).

---

### 5.2 Mathematical Amortization Models

#### Monthly Straight-Line (Standard Months)
For standard monthly straight-line models, the engine divides the allocated amount by the total number of periods:

$$\text{Monthly Rate} = \frac{\text{Allocated Amount}}{\text{Total Periods}}$$

If the contract starts mid-month, the first and last periods are prorated based on active days:

$$\text{Daily Rate} = \frac{\text{Allocated Amount}}{\text{Total Days in Contract}}$$
$$\text{Prorated Month Value} = \text{Active Days in Month} \times \text{Daily Rate}$$

#### Standard Leap Year Handling
To ensure consistency across leap years, daily amortization schedules calculate based on the actual number of calendar days in each year (365 days in standard years, 366 days in leap years), preventing rounding discrepancies.

#### Timezone Neutrality
All recognition dates are stored in Coordinated Universal Time (UTC) to prevent timezone offsets from shifting recognition events across monthly accounting periods.

---

## SECTION 6: CONTRACT MODIFICATION DESIGNS

Contract modifications occur when a customer changes the scope or price of an active contract (e.g., adding user seats or extending subscription terms).

```
                        [CONTRACT MODIFICATION LOGIC]
                        
                         [ Modification Event Signed ]
                                      │
                                      ▼
                      Are Remaining Goods/Services Distinct?
                      ├── Yes ───────────────────────────► Prospective Change
                      │                                    (Treat as new contract)
                      └── No ────────────────────────────► Retrospective Adjustment
                                                           (Cumulative adjustment in
                                                            the current open period)
```

### 6.1 Modification Treatments

1.  **Prospective Modification**: Used when the additional goods or services are contractually distinct and priced at their Standalone Selling Price (SSP).
    *   *Implementation*: The existing recognition schedules are locked. A new, separate contract is initialized to track the additional POBs, ensuring no changes are made to historical postings.
2.  **Retrospective Cumulative Catch-Up**: Used when remaining services are not distinct and form part of a single performance obligation.
    *   *Implementation*: The engine recalculates the entire contract timeline retrospectively based on the new transaction price, writing a cumulative adjustment entry to the current open period to reconcile historical postings with the new schedule.

---

## SECTION 7: SUBSCRIPTION REVENUE GOVERNANCE

Subscription-based SaaS contracts require precise workflows to govern recurring recognition events across diverse customer states.

### 7.1 Subscription State Implementations

*   **Standard Monthly/Annual Contracts**: Revenue is recognized over time using straight-line amortization. Annual subscriptions utilize deferred revenue liabilities to distribute recognition evenly across the 12-month period.
*   **Trial Cycles**: Zero-value trials do not generate revenue schedules or deferred balances.
*   **Grace Periods**: If a customer's payment fails and their subscription enters a grace period, straight-line recognition continues as long as there is a reasonable expectation of payment. If collection is deemed unlikely, recognition is suspended.
*   **Prorated Billing Runs**: Prorations resulting from plan changes mid-cycle recalculate active recognition schedules immediately, updating future amortization lines.
*   **Seat Licensing**: Changes in user seat counts dynamically adjust the contract's transaction price and remaining amortization lines.

---

## SECTION 8: PROJECT REVENUE SERVICES

Professional services and custom engineering projects require milestone and percentage-of-completion recognition schedules.

### 8.1 Professional Service Recognition Rules

*   **Fixed-Price Projects**: Revenue is recognized over time based on the percentage of completion, calculated using the cost-to-cost method.
*   **Time & Materials (T&M)**: Revenue is recognized as hours are logged and approved by project managers:
    $$\text{Monthly Recognized Revenue} = \sum(\text{Approved Project Hours} \times \text{Billable Rate})$$
*   **Retainers**: Prepaid advisory or support hours are placed in a deferred revenue liability account, with revenue recognized as hours are consumed. Any remaining unconsumed balance at the end of the retainer period is recognized immediately as expired retainer revenue.

---

## SECTION 9: JOURNAL POSTING MATRIX

To ensure compliance with accrual accounting principles, the Revenue Recognition Engine posts balanced journal entries for all recognition events.

| Posting Event | Debit Account | Credit Account | Dimension Propagation |
| :--- | :--- | :--- | :--- |
| **Upfront Billing (Invoice Issued)** | Accounts Receivable (Asset) | Deferred Revenue (Liability) | Customer, Organization, Product |
| **Monthly Amortization Run** | Deferred Revenue (Liability) | Recognized Sales Revenue (Income) | Customer, Organization, Department |
| **Accrued Unbilled Revenue** | Unbilled Receivables (Asset) | Recognized Sales Revenue (Income) | Customer, Organization, Project |
| **Billed Accrued Revenue** | Accounts Receivable (Asset) | Unbilled Receivables (Asset) | Customer, Organization |
| **Early Contract Termination** | Deferred Revenue (Liability) | Termination Revenue (Income) | Customer, Organization, Cost Center |
| **Retrospective Catch-up Adjustment**| Deferred Revenue (Liability) | Recognized Sales Revenue (Income) | Customer, Organization |
| **Contract Refund Processing** | Deferred Revenue (Liability) | Customer Refund Clearing (Asset) | Customer, Organization |

---

## SECTION 10: DATABASE TABLES & SCHEMAS

The database schema below defines the storage architecture for performance obligations, recognition schedules, and modification tracking.

### 10.1 `public.performance_obligations`
Tracks contract performance obligations and Standalone Selling Price (SSP) allocations.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `contract_id` | `uuid` | NO | None | FK -> `contracts(id)` | - | Public | UUIDv4 | Parent customer contract. |
| `pob_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Descriptive name of obligation. |
| `ssp_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `ssp_amount > 0.00` | Standalone Selling Price. |
| `allocated_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `allocated_amount >= 0.00`| Proportionally allocated price. |
| `recognition_method`|`varchar(50)` | NO | None | Check Constraint | - | Public | `'point_in_time'`, `'straight_line'`, `'milestone'`, `'percentage_of_completion'`, `'usage_based'` | Target recognition strategy. |

---

### 10.2 `public.revenue_recognition_schedules`
Parent record for a POB's amortization schedule.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `pob_id` | `uuid` | NO | None | FK -> `performance_obligations(id)`| - | Public | UUIDv4 | Linked performance obligation. |
| `total_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `total_amount >= 0.00` | Total revenue to recognize. |
| `recognized_amount`|`numeric(18,2)`| NO | `0.00` | None | - | Financial | `recognized_amount <= total_amount`| Cumulative recognized revenue. |
| `deferred_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `deferred_amount >= 0.00` | Remaining deferred balance. |
| `start_date` | `date` | NO | None | None | - | Public | Valid date | Start date of schedule. |
| `end_date` | `date` | NO | None | None | - | Public | `end_date >= start_date`| End date of schedule. |
| `status` | `varchar(30)` | NO | `'active'` | Check Constraint | - | Public | `'active'`, `'completed'`, `'on_hold'`, `'terminated'` | Current schedule status. |
| `version` | `integer` | NO | `1` | None | - | Public | `version >= 1` | Optimistic locking field. |

---

### 10.3 `public.recognition_schedule_lines`
Individual amortization lines representing monthly recognition postings.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `schedule_id` | `uuid` | NO | None | FK -> `revenue_recognition_schedules(id)`| - | Public | UUIDv4 | Parent recognition schedule. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target accounting period. |
| `scheduled_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `scheduled_amount > 0.00`| Scheduled recognition amount. |
| `posting_date` | `date` | NO | None | None | - | Public | Valid date | Target posting date. |
| `is_posted` | `boolean` | NO | `false` | None | - | Public | Valid boolean | Post status flag. |
| `journal_entry_id` | `uuid` | YES | `NULL` | FK -> `journal_entries(id)`| - | Public | UUIDv4 | Reference posting journal. |

---

### 10.4 `public.deferred_revenue_balances`
Tracks monthly deferred revenue liability balances for auditing and reconciliation.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `client_account_id`| `uuid` | NO | None | FK -> `client_accounts(id)` | - | Public | UUIDv4 | Associated customer profile. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target accounting period. |
| `opening_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Deferred revenue balance at period start. |
| `additions` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | New upfront billings recorded. |
| `amortizations` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Revenue recognized in period. |
| `closing_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `closing_balance = opening_balance + additions - amortizations` | Deferred revenue balance at period close. |

---

### 10.5 `public.revenue_recognition_events`
Audit log of performance satisfaction events triggering point-in-time or milestone recognition.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `pob_id` | `uuid` | NO | None | FK -> `performance_obligations(id)`| - | Public | UUIDv4 | Linked performance obligation. |
| `event_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'shipment'`, `'milestone_acceptance'`, `'usage_log'` | Source trigger type. |
| `trigger_reference`| `varchar(100)`| NO | None | None | - | Public | Standard string | External reference key. |
| `recorded_at` | `timestamp with time zone` | NO | `now()` | None | - | Public | Valid timestamp | Event timestamp. |

---

## SECTION 11: RECOGNITION ENGINE SCHEDULER

The Revenue Recognition Engine utilizes a background scheduler to automate amortization processing and ensure general ledger accuracy.

```
                          [SCHEDULER PROCESSING LOOP]
                          
                          [ Trigger Amortization Run ]
                                       │
                                       ▼
                       Fetch Active Amortization Lines:
                       `is_posted == false` AND `posting_date <= current_date`
                                       │
                                       ▼
                        Partition Processing by Tenant
                        (Enforces strict tenant isolation)
                                       │
                                       ▼
                             Group by Cost Centers
                        (Prepares grouped journal batches)
                                       │
                                       ▼
                              Execute Postings
                        (Writes double-entry journal lines)
```

### 11.1 Execution Logic

*   **Daily Scheduling Runs**: A scheduled daily background job identifies outstanding amortization lines (`is_posted == false`) whose `posting_date` matches or is prior to the current date.
*   **Period-Close Controls**: At month-end, the scheduler executes a comprehensive amortization run, processing all remaining scheduled lines for the period. The accounting period cannot transition to `CLOSED` until all associated recognition schedules have been successfully processed and verified.
*   **Tenant Isolation**: Processing queues are partitioned by `organization_id` to ensure strict multi-tenant isolation and prevent data cross-contamination during execution.
*   **Transactional Postings**: Amortizations are grouped by Cost Center and processed in batched database transactions, ensuring that journal entries are written successfully or rolled back completely in the event of an execution failure.
*   **Exception and Error Handling**: Processing failures are captured, logged to `public.recognition_failures`, and routed to an administrative dashboard for verification. Failed lines are blocked from retry until the underlying issue has been resolved.

---

## SECTION 12: MULTI-CURRENCY CONVERSIONS

When contracts are executed in foreign currencies, the engine applies precise multi-currency calculation rules to prevent foreign exchange discrepancies.

### 12.1 Multi-Currency Rules

1.  **Non-Monetary Liability Classification**: Under GAAP and IFRS, Deferred Revenue is classified as a non-monetary balance sheet liability. It is recorded using the historical exchange rate active on the transaction date and is **not revalued** at month-end.
2.  **Historical Rate Amortization**: When monthly amortizations are recognized, the engine applies the historical exchange rate active on the original transaction date (not the current reporting date). This ensures that the total recognized revenue in base currency matches the original deferred liability value.
3.  **Realized and Unrealized FX Differences**: Fluctuations in exchange rates during contract execution do not generate FX gains or losses in deferred revenue liabilities. However, corresponding cash receipts and accounts receivable balances are subject to standard revaluation and re-measurement protocols, isolating FX exposures within treasury modules.

---

## SECTION 13: ROLE-BASED ACCESS CONTROL & SECURITY

To prevent financial fraud and maintain compliance with SOC2 standards, access to revenue recognition schedules and contract configurations is strictly governed by Role-Based Access Control (RBAC).

### 13.1 Security Roles and Operational Matrix

| Security Role | Configure POB Rules | Generate Schedules | Run Monthly Postings | Apply Schedule Adjustments | Approve Contract Mods | Audit Trail Access |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Billing Clerk** | No | Yes | No | No | No | No |
| **Revenue Analyst** | Yes | Yes | Yes | No | No | Yes |
| **Finance Controller** | Yes | Yes | Yes | Yes | Yes | Yes |
| **CFO / Director** | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 13.2 Security Controls and RLS
*   **Dual Authorization (Four-Eyes Principle)**: Creating or adjusting manual revenue recognition schedules requires a separate creator and approving authority, preventing internal authorization loops.
*   **Row-Level Security (RLS)**: RLS is enabled across all tables, ensuring strict tenant isolation by filtering queries dynamically using the organization context.
*   **Immutable Historical Change Logs**: All updates to recognition schedules, manual adjustments, or modification events write to append-only database history logs, creating a clear audit trail for compliance reviews.

---

## SECTION 14: SYSTEM EVENTS

The Revenue Recognition subsystem is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 14.1 Real-Time System Events

#### `revenue.schedule.created`
Emitted immediately upon generating a recognition schedule for a Performance Obligation.

```json
{
  "event_id": "evt_rev_01A8391823",
  "event_type": "revenue.schedule.created",
  "organization_id": "org_771829",
  "correlation_id": "corr_contract_9011",
  "payload": {
    "schedule_id": "sch_8829103",
    "pob_id": "pob_44921",
    "total_amount": 12000.00,
    "currency_code": "USD",
    "start_date": "2026-07-01",
    "end_date": "2027-06-30",
    "amortization_frequency": "monthly"
  },
  "timestamp": "2026-06-28T19:00:00Z"
}
```

#### `revenue.recognized`
Emitted immediately upon executing a scheduled amortization posting.

```json
{
  "event_id": "evt_rev_01A8391950",
  "event_type": "revenue.recognized",
  "organization_id": "org_771829",
  "correlation_id": "corr_recon_5521",
  "payload": {
    "schedule_id": "sch_8829103",
    "pob_id": "pob_44921",
    "accounting_period_id": "per_11029",
    "recognized_amount": 1000.00,
    "deferred_remaining_balance": 11000.00,
    "journal_entry_id": "je_44912"
  },
  "timestamp": "2026-06-28T19:05:00Z"
}
```

---

## SECTION 15: PRODUCTION REVENUE RECOGNITION VALIDATION CHECKLIST

Before deploying the Revenue Recognition and Deferred Revenue Engine to production, verify that the following configurations and controls are in place.

- [ ] **SSP Allocation Verified**: Pricing unbundling and relative Standalone Selling Price allocation algorithms are tested and validated.
- [ ] **Transition Integrity Confirmed**: Transitions between deferred balances and recognized revenue are verified to ensure mathematical balance.
- [ ] **Daily Amortization Accurate**: Daily straight-line calculations and mid-month proration logic are validated.
- [ ] **Closed Period Protection Active**: System prevents manual adjustments or amortization postings to closed accounting periods.
- [ ] **Leap Year Scenarios Tested**: Amortization runs are verified across leap years to ensure rounding accuracy.
- [ ] **Multi-Currency Rate Locking Checked**: Historical rate enforcement on deferred liabilities is verified.
- [ ] **Maker-Checker Security Verified**: Dual authorization workflows for manual adjustments are active and enforced.
- [ ] **Event Delivery Confirmed**: Real-time event generation and consumption flows are validated.

---
**End of Specification.**
