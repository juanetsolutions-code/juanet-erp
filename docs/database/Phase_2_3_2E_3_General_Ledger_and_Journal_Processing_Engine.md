# JUANET ERP General Ledger & Journal Processing Engine Specification
## Phase 2.3.2E.3 — General Ledger and Journal Processing Engine Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Core Financial Ledger, General Ledger, and Journal Processing Subsystem  

---

## SECTION 1: GENERAL LEDGER PHILOSOPHY

At the core of the JUANET Enterprise SaaS Platform lies the **General Ledger (GL)** and the transactional **Journal Processing Engine**. This subsystem serves as the single source of financial truth for the entire enterprise. Whether an transaction originates from client CRM invoicing, vendor accounts payable, automated payroll, multi-region sales, inventory depletion, or usage-based AI billing, every operational value eventually consolidates here.

```
 [Operational Modules] ──► [Structured Events] ──► [Posting Rule Engine] ──► [Journal Engine] ──► [General Ledger]
 (CRM, HR, Inventory)     (invoice.issued, etc)   (Resolves accounts/dims)  (Validates balances)  (Immutable entries)
```

### 1.1 Separation of Operations and Accounting

To ensure auditability, operational modules (e.g., Salesforce sync, warehouse shipping, payroll systems) are **prohibited** from writing directly to GL ledger tables. Instead, they interact via an **Event-Driven Accounting Architecture**:
1.  **Operational Event Creation**: Operational events (such as `invoice.issued` or `bill.received`) are emitted to a high-throughput event broker.
2.  **Posting Rule Translation**: The **Ledger Posting Rule Engine** consumes these events, looks up active accounting configurations, resolves the correct Debit/Credit target accounts from the Chart of Accounts, maps financial dimensions, and prepares a balanced `Draft` Journal Entry.
3.  **Journal Processing Verification**: The Journal Engine performs real-time financial validation before committing entries.

This decoupled architecture ensures that changes in operational services do not affect accounting workflows, preserving audit trails and compliance integrity.

---

### 1.2 Journal-First Accounting and Core Invariants

The GL operates under a strict **Journal-First** principle. Direct account balance modification is impossible. To alter an account balance, a structured transaction—a **Journal Entry** comprising a header and at least two **Ledger Entries**—must be submitted, validated, and posted.

The Journal Processing Engine enforces the following strict invariants at the database level:
*   **The Double-Entry Balance Equation**: For every journal entry, the sum of debits must equal the sum of credits in the transaction currency:
    $$\sum \text{Debit Amounts} = \sum \text{Credit Amounts}$$
*   **Append-Only Ledger**: Postings are strictly append-only. Modifying, deleting, or updating a posted journal entry or ledger line is physically impossible. Corrections require generating a separate, balanced reversal or adjustment entry.
*   **Accounting Period Verification**: Entries can only be posted to accounting periods that are explicitly in an `OPEN` status.
*   **Dimension Constraint**: Ledger entries must pass dimension validation rules (e.g., matching Cost Centers, Projects, or Departments) as defined in the global financial configuration.

---

## SECTION 2: JOURNAL LIFECYCLE & STATE MACHINE

Every Journal Entry within JUANET passes through a deterministic state machine, ensuring thorough verification and approval before balances are finalized.

```
                                [JOURNAL ENTRY LIFECYCLE FSM]
                                
                             ┌─────────────────────────────────┐
                             │              DRAFT              │
                             └────────────────┬────────────────┘
                                              │
                                              ▼
                             ┌─────────────────────────────────┐
                             │       PENDING VALIDATION        │
                             └────────────────┬────────────────┘
                                              │
                                              ▼
                             ┌─────────────────────────────────┐
                             │        PENDING APPROVAL         │
                             └────────────────┬────────────────┘
                                              │
                                              ▼
                             ┌─────────────────────────────────┐
                             │            APPROVED             │
                             └────────────────┬────────────────┘
                                              │
                                              ▼
                             ┌─────────────────────────────────┐
                             │             POSTED              │◄────────────────────────┐
                             └────────┬─────────────────┬──────┘                         │
                                      │                 │                                │
                                      ▼                 ▼                                │
                             ┌─────────────────┐ ┌───────────────┐                       │
                             │    REVERSED     │ │   CANCELLED   │                       │ (Reversing Journal)
                             └─────────────────┘ └───────────────┘                       │
                                      │                                                  │
                                      ▼                                                  │
                             ┌─────────────────┐                                         │
                             │    ARCHIVED     │─────────────────────────────────────────┘
                             └─────────────────┘
```

### 2.1 Complete Journal States

#### 2.1.1 `DRAFT`
*   **Description**: The initial state of a journal entry. Line items and dimensions are fully editable.
*   **Entry Criteria**: Spawned by manual entry, batch import pipelines, or automatic posting rule resolvers.
*   **Exit Criteria**: Submitting the journal for validation.
*   **Allowed Transitions**: `PENDING_VALIDATION`, `CANCELLED`.
*   **Forbidden Transitions**: `PENDING_APPROVAL`, `APPROVED`, `POSTED`, `REVERSED`.
*   **Audit Implications**: Entries in `DRAFT` do not impact ledger balances and are excluded from public trial balances.

#### 2.1.2 `PENDING_VALIDATION`
*   **Description**: Locked state during which the Validation Engine runs automated integrity checks.
*   **Entry Criteria**: Explicit submission from the creator or batch worker.
*   **Exit Criteria**: Successful completion of all validation tests.
*   **Allowed Transitions**: `PENDING_APPROVAL` (on success), `DRAFT` (on failure).
*   **Forbidden Transitions**: `APPROVED`, `POSTED`, `REVERSED`.
*   **Concurrency Considerations**: Validations are executed within isolated database transactions to prevent race conditions during dimension checks.

#### 2.1.3 `PENDING_APPROVAL`
*   **Description**: Awaiting review and approval by authorized controllers or managers.
*   **Entry Criteria**: Success of all validation rules on journals exceeding configured automated approval thresholds.
*   **Exit Criteria**: Receiving all required approval signatures.
*   **Allowed Transitions**: `APPROVED`, `DRAFT` (on rejection).
*   **Forbidden Transitions**: `POSTED`, `REVERSED`.
*   **Audit Implications**: Captures the individual user ID, timestamp, and delegation keys of reviewers.

#### 2.1.4 `APPROVED`
*   **Description**: Cleared for posting. Values and dimensions are locked, but account balances are not yet updated.
*   **Entry Criteria**: Successful completion of approval routes.
*   **Exit Criteria**: Triggering the posting engine to finalize the journal.
*   **Allowed Transitions**: `POSTED`, `DRAFT` (if recalled before posting).
*   **Forbidden Transitions**: `REVERSED`, `ARCHIVED`.
*   **Audit Implications**: Locked to prevent edits; any changes require reverting the document to Draft, which clears previous approvals.

#### 2.1.5 `POSTED`
*   **Description**: The journal is permanently committed to the ledger, and account balances are updated.
*   **Entry Criteria**: Execution of the Posting Engine on an `APPROVED` journal.
*   **Exit Criteria**: Transition to Archived (upon fiscal year-end close).
*   **Allowed Transitions**: `REVERSED`, `ARCHIVED`.
*   **Forbidden Transitions**: `DRAFT`, `PENDING_APPROVAL`, `APPROVED`.
*   **Audit Implications**: Read-only state. Modifying, deleting, or updating a posted journal is physically impossible.

#### 2.1.6 `REVERSED`
*   **Description**: A posted journal has been cancelled by creating a corresponding reversing journal entry.
*   **Entry Criteria**: Reversal process is executed on a `POSTED` journal, generating a linking reversal ID.
*   **Exit Criteria**: Month-end reconciliation.
*   **Allowed Transitions**: `ARCHIVED`.
*   **Forbidden Transitions**: All active operational states.
*   **Audit Implications**: Links the original journal ID and the reversal journal ID (`reversal_entry_id`), preserving a complete audit trail.

#### 2.1.7 `CANCELLED`
*   **Description**: A draft or pending journal is rejected and marked as cancelled.
*   **Entry Criteria**: Creator or approver voids the document before posting.
*   **Exit Criteria**: Terminal state.
*   **Allowed Transitions**: None.
*   **Forbidden Transitions**: All operational states.
*   **Audit Implications**: Preserves the original records for audit purposes to track draft numbers and prevent deletion gaps.

#### 2.1.8 `ARCHIVED`
*   **Description**: Read-only historical entries stored in write-once-read-many (WORM) configurations for compliance.
*   **Entry Criteria**: Fiscal year-end close completed and audited.
*   **Exit Criteria**: Read-only state; no transitions out of Archived.
*   **Allowed Transitions**: None.
*   **Forbidden Transitions**: All active states.

---

### 2.2 Lifecycle State-Transition Matrix

| Current State | Target State | Triggering Mechanism | Core Validation Requirements | Accounting Implications | Required Permissions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`DRAFT`** | `PENDING_VALIDATION`| User clicks "Validate" | Header and line rows exist; amounts populated. | None. | `journal:write` |
| **`DRAFT`** | `CANCELLED` | User clicks "Void" | Only permitted for non-posted journals. | None. | `journal:write` |
| **`PENDING_VALIDATION`**| `PENDING_APPROVAL` | System execution | All validation rules passed. | None. | `system:engine` |
| **`PENDING_VALIDATION`**| `DRAFT` | Validation failure | Automated check fails (e.g., unbalanced). | None. | `system:engine` |
| **`PENDING_APPROVAL`** | `APPROVED` | Controller approval | All approval signatures recorded. | None. | `journal:approve` |
| **`PENDING_APPROVAL`** | `DRAFT` | Controller rejection | Rejection justification provided. | None. | `journal:approve` |
| **`APPROVED`** | `POSTED` | User clicks "Post" | Target period status is `OPEN`. | Debits and Credits posted to ledger. | `journal:post` |
| **`POSTED`** | `REVERSED` | Reversal action | Linking reversal journal is created. | Balanced reversing entries posted. | `journal:reverse` |
| **`POSTED`** | `ARCHIVED` | Year-end close run | Fiscal period is hard-closed. | Marked read-only. | `system:worker` |

---

## SECTION 3: JOURNAL BATCH PROCESSING

For high-volume transaction processing (e.g., payroll processing or import runs), journals are grouped and processed within transactional batches (`public.journal_batches`).

```
                         [BATCH TRANSACTION PIPELINE]
                         
                             [ Initialize Batch ]
                                      │
                                      ▼
                        Batch Balance Verification:
                        Check if sum of all journal values == 0.00
                                      │
                     ┌────────────────┴────────────────┐
                     ▼                                 ▼
         [ Batch Balance Balance ]            [ Unbalanced Batch ]
                     │                                 │
                     ▼                                 ▼
             Lock All Journals                    Reject Batch
         (SELECT FOR UPDATE sequence)         (Log errors and rollback)
                     │
                     ▼
             Execute Postings
         (Writes double-entry journal lines)
```

### 3.1 Batch Processing Rules

1.  **Gapless Batch Numbering**: Batches are assigned gapless, sequential batch numbers. Sequence generation utilizes lock synchronization to ensure gapless numbering under high concurrency.
2.  **Batch Balance Verification**: A batch cannot transition to `Posted` unless all contained journals balance. The batch total value must balance to zero:
    $$\sum \text{Debit Amounts (Batch)} - \sum \text{Credit Amounts (Batch)} = 0.00$$
3.  **Locks and Concurrency**: Posting a batch locks all contained journal entries (`SELECT FOR UPDATE`), preventing concurrent modification during execution.
4.  **Transaction Boundaries and Atomic Rollback**: Batches are processed within a single database transaction boundary. If any journal validation or posting fails within a batch, the **entire batch rolls back**, ensuring no partial postings occur.
5.  **Failure Resolution**: Execution failures are captured, logged to `public.posting_failures`, and the batch status is set to `Draft` for verification and adjustment.

---

## SECTION 4: JOURNAL ENTRY VALIDATION ENGINE

The Journal Entry Validation Engine is a strict pipeline that evaluates every transaction to ensure the accuracy and integrity of general ledger postings.

```
                           [JOURNAL ENTRY VALIDATION ENGINE]
                           
      [ Submit Journal ]
             │
             ▼
      [ Balanced Check ] ──────► Debits == Credits?
             │
             ▼
      [ Active Account Check ] ──► Target accounts active in Chart of Accounts?
             │
             ▼
      [ Control Account Check ] ─► Direct manual entries to control accounts blocked?
             │
             ▼
      [ Dimension Validation ] ──► Cost Centers, Projects, Departments match rules?
             │
             ▼
      [ Period Status Check ] ───► Target accounting period is OPEN?
             │
             ▼
      [ Currency Verification ] ─► ISO currency active with valid exchange rates?
             │
             ▼
      [ Duplicate Detection ] ──► Matches unique hashing check (Prevent duplicate postings)?
             │
             ▼
      [ Cleared for Posting ]
```

### 4.1 Detailed Pipeline Stages

*   **Balanced Check**: Enforces double-entry constraints. The sum of debits must equal the sum of credits within a transaction:
    $$\sum \text{Debit Amounts} = \sum \text{Credit Amounts}$$
*   **Active Account Check**: Confirms that all target accounts exist and are currently active in the Chart of Accounts (`public.chart_of_accounts`).
*   **Control Account Check**: Restricts direct manual postings to system-controlled accounts (such as Accounts Receivable Control or Accounts Payable Control). These accounts can only be written to via automated, system-authorized posting runs.
*   **Dimension Validation**: Validates that all active financial tracking dimensions (such as Cost Center, Project, or Department) are populated on the journal lines in compliance with global financial configuration rules.
*   **Period Status Check**: Confirms that the target posting date falls within an active, `OPEN` accounting period. Postings to closed or locked periods are blocked.
*   **Currency Verification**: Verifies that the transaction currency is active in the system and that valid exchange rates are available to translate the values into the organization's base currency.
*   **Duplicate Detection**: Generates a SHA-256 hash of the transaction metadata (comprising the organization ID, posting date, total amount, and line item details) and compares it against active database records to prevent duplicate postings.

---

## SECTION 5: POSTING ENGINE

The Posting Engine is the core transactional component of the General Ledger, responsible for committing validated journal entries to the database and updating account balances.

### 5.1 Posting Execution & Transaction Isolation

*   **Transactional Boundaries**: All ledger postings are processed within strict database transactions. If an execution fails, all changes roll back completely, ensuring no partial postings or orphaned lines occur.
*   **Row Locking Sequence**: To prevent database deadlocks during concurrent multi-row updates, the engine acquires row-level locks on the target accounts (`SELECT FOR UPDATE`) sorted in ascending order by primary key before executing any balance updates.
*   **Idempotency Checks**: Every posting request must provide a unique idempotency key. The engine verifies this key against the database before execution to prevent double-posting from network retries.
*   **Real-Time Balance Updates**: In addition to writing ledger entries, the engine updates the cached balances in the `public.ledger_balances` table, enabling high-performance financial reporting and trial balance generation.

---

## SECTION 6: JOURNAL REVERSALS

Correcting errors on posted journals requires generating a separate, reversing journal entry to offset the original balances.

```
                         [REVERSAL JOURNAL PAIR]
                         
        [ Original Journal Entry ]          [ Reversing Journal Entry ]
        - Debit Account A $1,000            - Credit Account A $1,000
        - Credit Account B $1,000           - Debit Account B $1,000
        - Status: Posted                    - Status: Posted
        - reversal_entry_id = NULL ────────►- reversal_entry_id = Original ID
```

### 6.1 Reversal Governance

*   **Reversal Linking**: A reversing journal entry must link directly to its source journal via the `reversal_entry_id` column, maintaining a clean audit trail.
*   **Accounting Period Rules**:
    *   **Open Periods**: If the original accounting period is open, the reversing journal is posted to the same period.
    *   **Closed Periods**: If the original period is closed, the reversal is posted to the current open period.
*   **Accrual Reversals**: Supports automated reversals for temporary accruals (e.g., month-end expense accruals), with reversing journals scheduled to post automatically on the first day of the subsequent accounting period.

---

## SECTION 7: RECURRING JOURNALS

Recurring journals automate the posting of repeating transactions, such as rent expenses, subscription accruals, or depreciation schedules.

### 7.1 Recurring Execution Rules

*   **Templates**: Recurring journals are generated from predefined templates (`public.journal_templates`) that define the target accounts, dimensions, and relative distribution percentages.
*   **Execution Schedulers**: A background scheduler processes active schedules, executing generation runs at the configured interval (daily, weekly, monthly, quarterly, or annually).
*   **Proration and Adjustments**: Changes to the parent recurring contract dynamically recalculate the associated templates and adjust future posting lines.
*   **Governance and Suspensions**: If a recurring run fails (e.g., due to a closed target accounting period), the schedule is automatically suspended, and alerts are routed to the administrative dashboard for verification.

---

## SECTION 8: INTERCOMPANY ACCOUNTING

Intercompany transactions record the transfer of value between distinct legal entities (organizations) within the corporate group.

```
                      [INTERCOMPANY BALANCING ARCHITECTURE]
                      
         [ Entity A Ledger ]                         [ Entity B Ledger ]
         - Debit: Accounts Receivable $1,000         - Credit: Accounts Payable $1,000
         - Credit: Intercompany Due-To $1,000        - Debit: Intercompany Due-From $1,000
```

### 8.1 Intercompany Rules & Balances

*   **Dual-Sided Postings**: Every intercompany journal must generate matching, balanced entries in the ledgers of both participating organizations.
*   **Due-To / Due-From Accounts**: The engine automatically inserts balancing lines utilizing designated Intercompany Due-To (Liability) and Due-From (Asset) clearing accounts to balance the individual entity ledgers.
*   **Elimination Journals**: Consolidation runs automatically generate elimination entries to offset intercompany balances, preventing group-level balance distortions during financial reporting.

---

## SECTION 9: MULTI-CURRENCY LEDGERS

The multi-currency ledger allows organizations to conduct business globally while maintaining unified financial reporting in their functional currency.

### 9.1 Currency Layering and Translation

1.  **Transaction Currency**: The currency in which the transaction is executed (e.g., Euros on a local utility bill).
2.  **Functional Currency**: The primary economic currency of the reporting legal entity (e.g., USD for a US-based subsidiary).
3.  **Reporting Currency**: The currency used to consolidate and report financial results at the corporate group level (e.g., USD for group reporting).
4.  **Exchange Rate Determinations**:
    *   *Historical Rates*: Applied to non-monetary balance sheet accounts (such as Equipment, Deferred Revenue, or Retained Earnings).
    *   *Average Rates*: Applied to income statement accounts (Revenue, Expenses) to translate monthly activity.
    *   *Closing Rates*: Applied to monetary asset and liability accounts (Cash, Accounts Receivable, Accounts Payable) at the end of each reporting period.
5.  **Revaluation Runs**: Periodic background processes evaluate outstanding monetary balances, updating the ledger with realized and unrealized FX gains or losses resulting from exchange rate fluctuations.

---

## SECTION 10: FINANCIAL DIMENSIONS & INHERITANCE

Financial Dimensions are structural metadata tags appended to ledger entries to enable detailed divisional, project, or product-line reporting.

### 10.1 Dimension Inheritance & Overrides

```
[ Customer Profile ] ──► [ Subscription Run ] ──► [ Invoice Line Item ] ──► [ Ledger Entries ]
(Inherits Dept/Project)  (Overwrites Location)    (Adds Product Tag)        (Appends all tags)
```

*   **Hierarchical Validation**: Dimensions are validated against strict structural rules:
    *   *Required Combinations*: For example, posting to a Sales Revenue account may require populating both the Department and Product dimensions.
    *   *Forbidden Combinations*: For example, posting to a Research & Development expense account may restrict the use of the Production Cost Center.
*   **Dimension Inheritance Rules**:
    *   Ledger entries inherit tracking dimensions from their parent operational entities (such as inheriting Department from the Employee Profile or Project ID from the Contract Header).
    *   Line-level dimensions take precedence and overwrite inherited header dimensions to support precise cost allocation.

---

## SECTION 11: LEDGER INTEGRITY & COMPLIANCE

To guarantee that general ledger data is audit-secure and compliant with regulatory standards (e.g., SOC2, regional audits), JUANET implements strict physical and cryptographic security controls.

### 11.1 Cryptographic Chain of Ledger Integrity

```
[ Posted Journal: JE-100 ] ──► Compute SHA-256 Hash ──┐
                                                        ▼
[ Posted Journal: JE-101 ] ──► Compute SHA-256 Hash (Current Data + JE-100 Hash) ──► Write to database
```

*   **Cryptographic Ledger Hashing**: Posted journal entries are secured using a cryptographic hashing chain. Each new journal posting generates a SHA-256 hash comprising its own transactional data combined with the hash of the preceding journal entry. Any unauthorized modification to historic ledger entries breaks the chain, immediately triggering security alerts.
*   **Immutable Sequences**: Sequential journal numbers are generated using database-enforced sequences, preventing numbering gaps and ensuring audit compliance.
*   **Verification Audits**: Automated nightly jobs verify the integrity of the cryptographic ledger chain and reconcile sub-ledger balances against general ledger accounts, identifying and logging any discrepancies.

---

## SECTION 12: DATABASE TABLES & ENTERPRISE SCHEMAS

The physical schema below defines the storage architecture for journal batches, journal headers, ledger lines, and balances.

### 12.1 `public.journal_batches`
Groups and controls the execution of high-volume journal imports and postings.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `batch_number` | `varchar(100)`| NO | None | Unique constraint | - | Public | Standard sequence | Gapless sequential batch code. |
| `batch_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | Descriptive name. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'pending_validation'`, `'posted'`, `'cancelled'` | Current batch execution state. |
| `created_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Audit track. |

---

### 12.2 `public.journal_entries`
Header record for balanced financial transactions.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `batch_id` | `uuid` | YES | `NULL` | FK -> `journal_batches(id)` | - | Public | UUIDv4 | Parent batch, if grouped. |
| `entry_number` | `varchar(100)`| NO | None | Unique constraint | - | Public | Standard sequence | Gapless transaction number. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Target financial period. |
| `posting_date` | `date` | NO | None | None | - | Public | Valid date | Date of transaction effect. |
| `reversal_entry_id`| `uuid` | YES | `NULL` | FK -> `journal_entries(id)` | - | Public | UUIDv4 | Reference to reversal journal. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'approved'`, `'posted'`, `'reversed'` | Lifecycle state. |
| `integrity_hash` | `varchar(64)` | YES | `NULL` | None | - | Public | SHA-256 string | Cryptographic verification hash. |
| `version` | `integer` | NO | `1` | None | - | Public | `version >= 1` | Optimistic locking field. |

---

### 12.3 `public.ledger_entries`
Individual ledger posting lines representing debits or credits.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `journal_entry_id` | `uuid` | NO | None | FK -> `journal_entries(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent journal header. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Target account. |
| `debit_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `debit_amount >= 0.00` | Debit value. |
| `credit_amount` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `credit_amount >= 0.00` | Credit value. |
| `cost_center_id` | `uuid` | YES | `NULL` | FK -> `cost_centers(id)` | - | Public | UUIDv4 | Tracking cost center dimension. |
| `department_id` | `uuid` | YES | `NULL` | FK -> `departments(id)` | - | Public | UUIDv4 | Tracking department dimension. |
| `project_id` | `uuid` | YES | `NULL` | FK -> `projects(id)` | - | Public | UUIDv4 | Tracking project dimension. |

---

### 12.4 `public.ledger_balances`
Real-time cached account balances for accelerated reporting.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Target account. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| - | Public | UUIDv4 | Accounting period. |
| `opening_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | Balance at period start. |
| `debits_total` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Cumulative debits in period. |
| `credits_total` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Cumulative credits in period. |
| `closing_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | Balance at period close. |

---

## SECTION 13: PERFORMANCE STRATEGY

To handle high-volume transaction loads and ensure rapid reporting times, the General Ledger and Journal Processing Engine utilize targeted indexes and performance optimizations.

### 13.1 Indexing and Query Optimization

```sql
-- 1. Composite covering index to accelerate period-end reporting queries
CREATE INDEX ledger_entries_reporting_idx 
  ON public.ledger_entries(organization_id, account_id)
  INCLUDE (journal_entry_id, debit_amount, credit_amount, cost_center_id, department_id);

-- 2. Index to optimize journal lookup by batch and status
CREATE INDEX journal_entries_batch_idx 
  ON public.journal_entries(organization_id, batch_id, status)
  WHERE status = 'posted';

-- 3. Covering index to accelerate real-time balance sheet and income statement generation
CREATE INDEX ledger_balances_lookup_idx 
  ON public.ledger_balances(organization_id, account_id, accounting_period_id)
  INCLUDE (opening_balance, debits_total, credits_total, closing_balance);
```

---

### 13.2 High-Throughput Performance Design

1.  **Horizontal Partitioning**: Transactional tables (`ledger_entries`, `journal_entries`) are partitioned by `organization_id` using hash partitioning to isolate tenant workspaces and maintain rapid, responsive query performance.
2.  **Parallel Batch Posting**: Import and posting runs are distributed across parallel background workers. The engine groups journals by Cost Center or Department, utilizing isolated database threads to maximize throughput while avoiding row-locking deadlocks.
3.  **Real-Time Materialized Balances**: Rather than executing resource-intensive aggregation queries across millions of ledger rows, the financial reporting engine queries the pre-computed `public.ledger_balances` table, enabling near-instantaneous trial balance and financial statement generation.

---

## SECTION 14: ROLE-BASED ACCESS CONTROL & SECURITY

To prevent financial fraud and maintain compliance with SOC2 standards, access to general ledger configurations and journal postings is strictly governed by Role-Based Access Control (RBAC).

### 14.1 Security Roles and Operational Matrix

| Operations Role | Create Draft Journal | Submit for Approval | Approve Manual Journal | Post Journal | Reverse Posted Journal | Close Accounting Period | Audit Log Read |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Billing Clerk** | Yes | Yes | No | No | No | No | No |
| **Accounts Analyst** | Yes | Yes | No | No | No | No | Yes |
| **Financial Controller** | Yes | Yes | Yes | Yes | Yes | No | Yes |
| **CFO / Director** | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 14.2 Security Controls and Compliance

*   **Maker-Checker approvals (Dual Authorization)**: Postings or manual adjustments exceeding $10,000 require dual authorization: a clerk or analyst must propose the entry (the Maker), and an authorized controller or manager must approve it (the Checker).
*   **Tenant Isolation**: Row-Level Security (RLS) is enabled on all ledger tables, filtering queries dynamically using the tenant context:
    ```sql
    ALTER TABLE public.ledger_entries ENABLE ROW LEVEL SECURITY;
    
    CREATE POLICY tenant_isolation_policy ON public.ledger_entries
      FOR ALL USING (organization_id = current_setting('app.current_organization_id', true)::uuid);
    ```

---

## SECTION 15: REAL-TIME SYSTEM EVENTS

The Journal Processing Engine is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 15.1 Real-Time System Events

#### `journal.posted`
Emitted immediately upon successfully posting a journal entry to the ledger.

```json
{
  "event_id": "evt_gl_11A8391822",
  "event_type": "journal.posted",
  "organization_id": "org_771829",
  "correlation_id": "corr_sales_99018",
  "payload": {
    "journal_entry_id": "je_8829103",
    "entry_number": "JE-2026-000491",
    "posting_date": "2026-06-28",
    "accounting_period_id": "per_11029",
    "total_debit_value": 5300.00,
    "line_item_count": 2
  },
  "timestamp": "2026-06-28T21:00:00Z"
}
```

#### `posting.failed`
Emitted upon execution failures within the posting engine, routing details to administrators.

```json
{
  "event_id": "evt_gl_11A8391855",
  "event_type": "posting.failed",
  "organization_id": "org_771829",
  "correlation_id": "corr_sales_99018",
  "payload": {
    "journal_entry_id": "je_8829103",
    "failure_reason": "Target accounting period is locked or CLOSED",
    "rejection_code": "GL_ERR_PERIOD_LOCKED",
    "initiated_by_user": "usr_99182"
  },
  "timestamp": "2026-06-28T21:05:00Z"
}
```

---

## SECTION 16: PRODUCTION GENERAL LEDGER VALIDATION CHECKLIST

Before deploying the General Ledger and Journal Processing Engine to production, verify that the following configurations and controls are in place.

- [ ] **Double-Entry Balance Enforced**: Hashing and trigger constraints block any journal submissions where debits do not equal credits.
- [ ] **Locking Rules Configured**: Account row-locking order is implemented and verified to prevent database deadlocks.
- [ ] **Closed Period Protection Active**: Postings or reversals to locked or closed accounting periods are strictly blocked.
- [ ] **Control Account Protections Verified**: Manual journal direct entries to system-controlled accounts are blocked.
- [ ] **Integrity Hashing Active**: Cryptographic chain hashing on journal postings is active and verified.
- [ ] **Dimension Rules Configured**: Cost center, project, and department dimension validation rules are active.
- [ ] **Reversal Workflows Verified**: Accrual and manual reversal linking rules are validated.
- [ ] **Event Delivery Confirmed**: Real-time event generation and consumption flows are validated.

---
**End of Specification.**
