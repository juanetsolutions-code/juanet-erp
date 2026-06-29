# JUANET ERP Finance Engineering Standards & Developer Playbook
## Phase 2.3.2E.14 — Code Invariants, Service Layer Contracts, Transaction boundaries, and Post-Release Safety Gates
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, Lead Financial Systems Engineer, & Director of Engineering Quality  
**Classification:** Technical Operations Manual, Developer Standard, and Engineering Constitution  

---

## SECTION 1: PURPOSE & ROLE OF THIS HANDBOOK

In a high-scale enterprise ERP environment, financial code must be held to a significantly higher standard of rigor, predictability, and defensibility than standard application modules. While a bug in a content management or notification system results in temporary user frustration, a bug in a financial ledger can trigger immediate compliance failures, tax audits, currency translation drift, or severe legal penalties.

Financial code on the JUANET Enterprise SaaS Platform values **correctness and defensibility over developer convenience**. This playbook functions as the official implementation handbook that every engineer must follow when reading, writing, or modifying any system containing financial business logic. The objectives of this handbook are:
*   **Financial Correctness Over Convenience**: Every calculation must be exact, using arbitrary-precision arithmetic to prevent rounding errors or decimal drift.
*   **Absolute Determinism**: Execution paths must be 100% deterministic, producing identical database records and journal balances regardless of network latency, thread scheduling, or application instance restarts.
*   **Mathematical Reproducibility**: Financial states (e.g., Trial Balances, Customer Aging, General Ledger states) must be reproducible at any given historical point-in-time using append-only transaction histories.
*   **Comprehensive Auditability**: Every mutation must be accompanied by an immutable audit record, linking the business event, the user identity, the approval history, and the system correlation tokens.
*   **Aviation-Grade Safety**: Implements multi-layered defensive validation pipelines, ensuring that invalid, unbalanced, or cross-tenant postings are physically rejected by both application services and database triggers.

---

## SECTION 2: THE TEN FINANCIAL CODE INVARIANTS

Every financial service, model, and database interaction must adhere to these ten unbreakable engineering rules. Violating any of these principles represents a critical architectural failure and will block pull requests during code reviews.

```
                     [LEDGER MUTATION PROTECTION WALL]
                     
     ┌────────────────────────────────────────────────────────┐
     │ ❌ DIRECT WRITE BLOCKED                                │
     │    INSERT / UPDATE / DELETE statements from API bypass  │
     └───────────────────────────┬────────────────────────────┘
                                 │
                                 ▼ (Must route through)
     ┌────────────────────────────────────────────────────────┐
     │ ✅ POSTING RULE ENGINE                                 │
     │    Compiles operational events into balanced journals  │
     └───────────────────────────┬────────────────────────────┘
                                 │
                                 ▼ (Evaluated in database)
     ┌────────────────────────────────────────────────────────┐
     │ ✅ BALANCING TRIGGER                                   │
     │    Asserts: SUM(Debits) - SUM(Credits) = 0             │
     └────────────────────────────────────────────────────────┘
```

1.  **Never Update Posted Journals**: Once a journal entry transitions to the `'posted'` state, its values, account associations, amounts, and dates are physically immutable. If corrections are needed, they must be executed via distinct, corrective journal entries.
2.  **Never Hard Delete Financial Records**: Tables containing financial data (e.g., invoices, payments, matching maps, journal lines) must be strictly append-only. Hard `DELETE` operations are completely blocked at the database engine level via security policies and database triggers.
3.  **Always Post reversals**: To correct errors, engineers must write reversal lines that offset the original balances, preserving the clear historical record of all transactions.
4.  **Never Bypass the Posting Rule Engine**: Subledger systems (e.g., billing, invoicing, payments) must never write directly to the general ledger tables. Instead, they must publish business events that route through the centralized, metadata-driven **Posting Rule Engine**.
5.  **Never Bypass the Approval Engine**: Financial transactions exceeding cost thresholds or period closures must route through the automated **Maker-Checker Approval Engine**, preventing single-user overrides of corporate thresholds.
6.  **Never Write Directly Into Ledger Tables**: Direct `INSERT` statements into `public.ledger_entries` or `public.journal_headers` from general API services are strictly forbidden. All ledger postings must execute within transactions managed by the dedicated `LedgerService`.
7.  **Never Bypass Row-Level Security (RLS)**: Application services must pass the active tenant context (`TenantSession`) into database transactions, ensuring the database-level isolation policies remain active.
8.  **Never Bypass Optimistic Concurrency**: Any update to mutable balances (e.g., customer account balances, cash positions, budget thresholds) must use optimistic concurrency controls (such as checking matching `version` columns) to prevent race conditions during concurrent modifications.
9.  **Always Emit Domain Events**: Financial state changes must publish domain events (e.g., `invoice.posted_v1`, `payment.cleared_v1`) to the transactional outbox table, supporting downstream synchronization.
10. **Always Validate Accounting Periods First**: No transaction can post to a closed, locked, or uninitialized accounting period. Services must verify period lock states before starting calculations.

---

## SECTION 3: SERVICE LAYER BOUNDARIES & CONTRACTS

Financial services must maintain strict separation of concerns, ensuring each service owns a specific, well-defined domain and implements clear boundaries.

```
   [ Subledgers / Operational Services ] ────────► [ Core Financial Services ]
   ├── InvoiceService (Billing / Taxes)            ├── PostingRuleService (Mapping)
   ├── PaymentService (Gateway / Matching)         ├── LedgerService (Double-Entry GL)
   └── ReceivableService (Aging / Balance)         └── ReportingService (Aggregations)
```

### 3.1 Subledger & Operational Services

#### `InvoiceService`
*   **Domain Ownership**: Owns billing cycles, invoice lines, regional tax computations, and gapless sequence numbering locks.
*   **Mandatory Responsibilities**: Locks invoice document states during transition, computes regional tax liabilities using verified tax templates, and emits `invoice.posted_v1` events.
*   **Strict Prohibitions**: Must never write directly to general ledger tables. It must not modify invoice lines after posting has completed.

#### `PaymentService`
*   **Domain Ownership**: Owns bank statement ingestion, payment gateway payloads, and cleared-cash transitions.
*   **Mandatory Responsibilities**: Tracks payment authorizations, marks funds as cleared, and handles stripe/gateway reconciliations.
*   **Strict Prohibitions**: Must never bypass `ReceivableService` when allocating cash receipts to open invoices.

#### `ReceivableService`
*   **Domain Ownership**: Owns customer subledger balances, invoice matching maps, and historical aging computations.
*   **Mandatory Responsibilities**: Manages payment-to-invoice allocations and computes customer aging tiers (30/60/90 days).
*   **Strict Prohibitions**: Must never adjust invoice balances without recording corresponding payment matches or credit adjustments.

---

### 3.2 Core Financial Services

#### `LedgerService`
*   **Domain Ownership**: The custodian of double-entry ledger entries and journal postings.
*   **Mandatory Responsibilities**: Validates that debits match credits before commit, verifies target periods are open, and locks balances.
*   **Strict Prohibitions**: Must never expose methods that allow updating or deleting posted ledger lines.

#### `PostingRuleService`
*   **Domain Ownership**: Translates business events into balanced journal entry drafts based on active rules.
*   **Mandatory Responsibilities**: Evaluates dynamic event contexts and resolves target accounts.
*   **Strict Prohibitions**: Must never execute ledger-write transactions directly; its responsibility is strictly generating balanced drafts for review or auto-posting.

#### `RevenueRecognitionService`
*   **Domain Ownership**: Manages ASC 606 deferred amortization tables and revenue schedules.
*   **Mandatory Responsibilities**: Builds deferred revenue schedules and posts monthly amortization journals.
*   **Strict Prohibitions**: Must never post unrecognized revenue directly to earnings accounts.

#### `TreasuryService`
*   **Domain Ownership**: Manages cash sweeps, corporate investments, and covenant threshold alerts.
*   **Mandatory Responsibilities**: Analyzes cash liquidity and records sweep transactions.
*   **Strict Prohibitions**: Must never sweep funds without verifying regional capital limits.

#### `BudgetService`
*   **Domain Ownership**: Manages departmental cost-center budget allocations and variance logs.
*   **Mandatory Responsibilities**: Tracks budget limits and rejects expenditures that exceed cost-center budgets.
*   **Strict Prohibitions**: Must never modify budget allocations without formal approvals logged in the database.

#### `ReportingService`
*   **Domain Ownership**: Pre-aggregates financial statements, including Balance Sheets, P&Ls, and Cash Flow statements.
*   **Mandatory Responsibilities**: Triggers materialized view refreshes concurrently and generates historical report caches.
*   **Strict Prohibitions**: Must never run expensive, unindexed queries on active transactional tables during peak operational hours.

---

## SECTION 4: DATABASE TRANSACTION & CONCURRENCY CONTROLS

Financial transactions demand the highest levels of isolation and predictability to prevent race conditions, dirty reads, or unbalanced postings under heavy write load.

```
                           [LOCKING HIERARCHY RULES]
                           
     1. Lock Parent (Tenant) ──► 2. Lock Cost Center ──► 3. Lock Ledger Row
     (Prevent concurrent         (Prevent race           (Ensure sequential
      period closes)              conditions)             double-entry writes)
```

### 4.1 Transaction Boundary Standards
*   **Atomic Subledger Writes**: Subledger state transitions, event emissions, and outbox writes must execute within a single database transaction. If any part of the execution fails, the entire transaction rolls back, preventing partial data writes.
*   **Short Execution Lifecycles**: Keep transactions short. Never perform HTTP requests, file system writes, or expensive computational loops within a database transaction, as this holds database locks open and can cause resource exhaustion.
*   **Exploiting Isolation Levels**: Standard transactions use the `READ COMMITTED` isolation level. Core ledger postings and balance updates must use `SERIALIZABLE` or `REPEATABLE READ` levels to prevent phantom reads or write anomalies during concurrent postings.
*   **Defensive Locking Hierarchy**: When updating records across multiple tables, always acquire locks in the same hierarchical order to prevent deadlocks:
    1.  Lock tenant configuration profiles.
    2.  Lock target chart-of-accounts and cost-center records.
    3.  Insert new journal header entries.
    4.  Insert corresponding ledger entry lines.

### 4.2 Deadlock Prevention & Retry Logic
```typescript
// Standard Resilient Transaction Wrapper for Ledger Posting
export async function runResilientLedgerTransaction<T>(
  prisma: PrismaClient,
  tenantId: string,
  fn: (tx: Prisma.TransactionClient) => Promise<T>,
  retries = 3,
  delayMs = 100
): Promise<T> {
  let attempt = 0;
  while (attempt < retries) {
    try {
      return await prisma.$transaction(async (tx) => {
        // 1. Establish tenant lock context immediately to prevent phantom updates
        await tx.$executeRaw`SELECT pg_advisory_xact_lock(hashtext(${tenantId}));`;
        
        // 2. Set strict local lock timeout
        await tx.$executeRaw`SET LOCAL lock_timeout = '5s';`;
        
        return await fn(tx);
      }, {
        isolationLevel: Prisma.TransactionIsolationLevel.RepeatableRead
      });
    } catch (error: any) {
      attempt++;
      // Postgres error code 40001 corresponds to serialization_failure (deadlock/concurrent lock)
      if (error.code === 'P2034' || error.message?.includes('40001')) {
        if (attempt >= retries) throw new Error(`Ledger Transaction failed after maximum retries: ${error.message}`);
        await new Promise(resolve => setTimeout(resolve, delayMs * Math.pow(2, attempt))); // Exponential Backoff
      } else {
        throw error; // Immediately propagate non-retryable exceptions
      }
    }
  }
  throw new Error('Transaction execution failed due to exhaustion of retries.');
}
```

---

## SECTION 5: STRICT EXCEPTION HANDLING STANDARDS

Financial exceptions must provide explicit, structured error codes, logging contexts, and traceback identifiers to speed up resolution. Generic `"Internal Server Error"` messages are strictly forbidden for business-critical financial operations.

```
                             [ERROR CLASSIFICATION]
                             
  [ Business Exceptions ]  ──► Rule violation. (e.g. Account suspended) -> Reversible.
  [ Validation Errors   ]  ──► Formatting / Type error. -> No mutation, reject.
  [ Database Failures   ]  ──► Connection / Lock timeout. -> Retry or fail safe.
```

### 5.1 Financial Error Schema Standard
All financial errors returned by APIs must adhere to the standardized response contract:
```json
{
  "success": false,
  "error": {
    "code": "PERIOD_LOCKED",
    "message": "The accounting period for 2026-06 is closed and locked against postings.",
    "correlationId": "tx-9a2f-4881-b51c-8e42f1aa0c92",
    "timestamp": "2026-06-29T03:20:15.012Z",
    "details": {
      "targetPeriod": "2026-06",
      "tenantId": "org_7f10b2a9",
      "lockDetails": "Closed on 2026-06-28 by user_9e3d"
    }
  }
}
```

### 5.2 Error Classification Matrix

| Error Code Class | Recoverable? | Recommended Mitigation | Audit Log Action |
| :--- | :--- | :--- | :--- |
| **`UNBALANCED_JOURNAL`** | No | Reject the transaction. Block code compilation and trigger immediate developer notification. | Log transaction metadata and inputs to audit table. |
| **`PERIOD_LOCKED`** | No | Reject the transaction, prompting the user to select an active accounting period. | Log attempted posting to audit table. |
| **`LOCK_TIMEOUT`** | Yes | Retry the transaction using exponential backoff up to 3 times. | Log warnings with correlation IDs. |
| **`INSUFFICIENT_FUNDS`** | No | Reject the transaction, logging the cost limit and cost-center usage. | Log warning to budget dashboard. |
| **`DUPLICATE_POST`** | Yes | Skip the transaction. Return the existing record, avoiding duplicate postings. | Log deduplication action. |

---

## SECTION 6: THE TRANSACTIONAL OUTBOX PATTERN

To prevent synchronization drift between the database and downstream services, the platform uses the **Transactional Outbox Pattern** to manage event publishing.

```
                    [TRANSACTIONAL OUTBOX MECHANISM]
                    
  1. Write Core Record ┐
  2. Write Outbox Row  ├─► Committed atomically in ONE database transaction.
                       │
                       ▼
  3. Outbox Publisher Reads Outbox table ──► 4. Publish Event ──► 5. Mark Published
```

1.  **Atomic Event Persistence**: When a service modifies a financial state (e.g., posting an invoice), it must write the corresponding event payload to the `public.transactional_outbox` table in the same database transaction.
2.  **Continuous Outbox Processing**: A background worker continuously queries the outbox table for unprocessed events, publishes them to the message broker, and marks them as published upon confirmation:
    ```sql
    -- Safe Outbox Selection Pattern (Prevents duplicate processing)
    SELECT id, event_type, payload 
    FROM public.transactional_outbox
    WHERE status = 'pending' 
    ORDER BY created_at ASC 
    LIMIT 100 
    FOR UPDATE SKIP LOCKED;
    ```
3.  **Deduplication and Idempotency**: Event consumers must track processed message IDs in a dedicated table (`public.processed_events`), skipping already-processed events to prevent duplicate ledger updates.

---

## SECTION 7: API DESIGN STANDARDS FOR FINANCIAL WORKFLOWS

Financial APIs must enforce idempotency, trace operations with unique correlation IDs, and maintain clear contracts:

*   **POST Requests (Creation)**: Requires a unique `Idempotency-Key` header. If the key exists in the database, return the existing record and skip processing:
    ```typescript
    // API Idempotency Key Validation Filter
    const idempotencyKey = request.headers['idempotency-key'];
    const existingTransaction = await database.checkIdempotency(idempotencyKey);
    if (existingTransaction) {
      return response.status(200).json(existingTransaction);
    }
    ```
*   **PATCH Requests (State Changes)**: Must use optimistic concurrency checks by requiring an `If-Match` header containing the record's current version token, preventing concurrent modifications:
    ```sql
    -- Optimistic Concurrency Check Pattern
    UPDATE public.invoices 
    SET status = 'posted', version = version + 1 
    WHERE id = $1 AND version = $2;
    ```
*   **DELETE Requests (Removals)**: Deletions are strictly forbidden on financial tables. Calling `DELETE /api/v1/finance/*` must return a `405 Method Not Allowed` exception.
*   **Standard Pagination**: All search and list endpoints must implement limit-offset or cursor pagination, maintaining sub-second query performance.

---

## SECTION 8: THE MULTI-STAGE VALIDATION PIPELINE

Every financial mutation must pass through our multi-stage validation pipeline before executing, ensuring no invalid or unauthorized queries reach the database:

```
  1. Auth Check ──► 2. Tenant isolation ──► 3. Period Validation ──► 4. Balancing Checks
```

1.  **Identity Verification**: Confirms the caller is authenticated and checks permissions against access control matrices.
2.  **Tenant Isolation**: Extracts and validates the tenant session context, ensuring the caller is authorized to access the requested resource.
3.  **Period Validation**: Confirms the target accounting period is active and unlocked.
4.  **Business Logic Checks**: Runs domain-specific validation checks (e.g., verifying invoice lines and payment status).
5.  **Financial Integrity Checks**: Verifies double-entry balances and checks account ranges.
6.  **Outbox Construction**: Appends the transactional event payload to the database transaction, preparing for commit.

---

## SECTION 9: PERFORMANCE & OPTIMIZATION GUIDELINES

Financial ledgers process massive volumes of transaction records daily. Writing unoptimized queries or failing to partition tables can degrade database performance:

*   **Avoid N+1 Query Scenarios**: Use explicit table joins and pre-fetch related records, keeping database trips to a minimum.
*   **Declarative Table Partitioning**: Implement range partitioning on high-volume tables (such as ledger entries and audit logs) using date ranges:
    ```sql
    -- Ledger Entries Partitioning Standard
    CREATE TABLE public.ledger_entries (
      id UUID NOT NULL,
      posting_date DATE NOT NULL,
      tenant_id UUID NOT NULL,
      ...
    ) PARTITION BY RANGE (posting_date);
    ```
*   **Use Concurrent Materialized Views**: Materialized views used for financial statements must be refreshed concurrently, preventing query blocks during refreshes:
    ```sql
    REFRESH MATERIALIZED VIEW CONCURRENTLY public.mv_balance_sheet;
    ```
*   **No Business Logic inside Triggers**: Database triggers must focus strictly on technical safety checks (e.g., double-entry balancing and block deletion rules). Complex business logic and routing decisions must remain in application services.

---

## SECTION 10: ENTERPRISE SECURITY & COMPLIANCE CONTROLS

To protect sensitive financial data and meet regulatory requirements, developers must implement security controls across all financial services:

```
                    [MAKER-CHECKER SECURITY PIPELINE]
                    
  Authorized User A (Proposer) ──► Request Action ──► Draft Ledger Entry State
                                                           │
                                                           ▼ (System blocks auto-post)
  Authorized User B (Reviewer) ──► Approve Action  ──► Commit to General Ledger
```

*   **PostgreSQL Row-Level Security (RLS)**: Ensure RLS is active on all multi-tenant tables. Database-level security policies filter query results dynamically based on tenant contexts, preventing data leaks.
*   **Maker-Checker Security Workflows**: High-risk financial operations (such as ledger adjustments or period-close actions) require dual-authorization. The user proposing the change cannot be the user approving it.
*   **Administrative Audit Logs**: Track administrative actions and configuration changes in separate, write-once audit tables.
*   **Database-Level Encryption**: Protect sensitive financial details (e.g., bank account numbers, tax IDs) using columns encrypted with AES-256 keys managed by external key management services (KMS).

---

## SECTION 11: MODULAR REPOSITORY ORGANIZATION

To support scalability, maintenance, and clear separation of concerns, codebase structures must organize financial domains into modular directories:

```
src/finance/
├── controllers/      # Exposes REST endpoints, handles inputs, maps API routes.
├── services/         # Manages business workflows, transactions, and logic.
├── repositories/     # Executes optimized database queries and schema filters.
├── validators/       # Runs multi-stage integrity and data formatting checks.
├── domain/           # Defines core entity schemas, types, and constraints.
├── events/           # Compiles and validates outbound transactional outbox events.
├── dto/              # Formats and serializes payload transfers.
├── mappers/          # Maps database schemas to localized business models.
├── workers/          # Background workers processing outbox events and refreshes.
└── tests/            # High-coverage unit, integration, and security test suites.
```

---

## SECTION 12: TESTING EXPECTATIONS & CODE COVERAGE GATES

Financial code changes must pass high-coverage automated tests before merging to staging or production environments:

*   **Ledger Balancing Assertions**: Tests must verify that any unbalanced postings are rejected by the validation triggers, keeping ledger balances aligned:
    $$\sum \text{Ledger Debits} - \sum \text{Ledger Credits} = 0$$
*   **Strict Multi-Tenant Leak Checks**: Run security tests that attempt to fetch financial records using mismatched tenant sessions, confirming RLS successfully isolates cross-tenant requests.
*   **High-Volume Concurrency Stress Tests**: Simulate concurrent posting operations under peak loads, verifying database transactions resolve without deadlocks or double-postings.
*   **Gapless Sequence Checks**: Run automated test runs to confirm document sequences remain continuous and gapless under heavy write volume.

---

## SECTION 13: STRICTLY PROHIBITED CODE ANTI-PATTERNS

The practices below represent severe architectural violations and are strictly prohibited in the codebase:

*   ❌ **SQL in API Controllers**: Writing raw SQL queries or invoking ORM methods directly within API controllers. Database queries must reside inside repositories or services.
*   ❌ **Bypassing the Posting Engine**: Writing custom database queries inside subledger services to alter ledger tables directly. All postings must route through the Posting Rule Engine.
*   ❌ **Modifying Posted Entries**: Attempting to run `UPDATE` statements on posted journals. Adjustments must use reversal entries.
*   ❌ **Hardcoded Account Identifiers**: Using hardcoded values (such as account IDs or currency codes) in application services. Configuration values must resolve dynamically using metadata configurations.
*   ❌ **Skipping Concurrency Controls**: Executing balance modifications without comparing version columns or using optimistic locking, risking race conditions.

---

## SECTION 14: CODE REVIEW CHECKLIST FOR REVIEWERS

Reviewers must verify that pull requests involving financial systems meet all safety guidelines before approving merge requests:

- [ ] **Data Immutability**: All financial tables are append-only. There are no direct `UPDATE` or `DELETE` statements targeting transactional tables.
- [ ] **Double-Entry Balance Checks**: Posted entries are verified to ensure debits equal credits before commit.
- [ ] **Active Tenant Isolation**: Queries and database transactions validate active tenant session context, keeping RLS active.
- [ ] **Outbox Pattern Implemented**: Financial state changes write corresponding event payloads to the outbox table in the same transaction.
- [ ] **Optimistic Locking Active**: Balance updates check version parameters, preventing concurrent modification issues.
- [ ] **Locking Order Followed**: Multi-table transactions acquire locks in hierarchical order, minimizing deadlock risks.
- [ ] **Arbitrary-Precision Types**: Decimal values use accurate arbitrary-precision classes (e.g., Decimal.js or Prisma Decimal), preventing floating-point rounding errors.

---

## SECTION 15: FUTURE EVOLUTION & SCHEMAS STABILITY

As the platform expands and supports new business requirements, financial database schemas must evolve according to these stability guidelines:

1.  **Always Maintain Backward Compatibility**: Schema modifications must be backward-compatible with active application nodes, supporting rolling deployments without downtime.
2.  **Add Columns as Nullable**: When extending tables, add columns as nullable or with a default value, preventing exclusive table locks on production systems.
3.  **Run Migrations behind Feature Flags**: Guard new database columns and features behind application-layer feature flags, allowing quick rollbacks without database modifications.
4.  **Conduct Rigorous Staging Dry-Runs**: Test migrations against staging databases under production-mirrored loads before deploying to production systems, confirming execution paths and verifying performance levels.

---

## SECTION 16: REVISION INDEX

### 16.1 Revision History

| Date | Document Version | Author / Reviewer | Summary of Changes | Approved By |
| :--- | :--- | :--- | :--- | :--- |
| **2026-06-29** | `1.0` | Chief Solutions Architect | Initial compilation and publication of the Finance Engineering Standards. | Director of Quality |
