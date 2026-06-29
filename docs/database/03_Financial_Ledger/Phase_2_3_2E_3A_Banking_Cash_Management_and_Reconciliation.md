# JUANET ERP Banking, Cash Management & Reconciliation Engine Specification
## Phase 2.3.2E.3A — Banking, Cash Management & Reconciliation Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Banking, Treasury Operations, Cash Management, and Bank Statement Reconciliation Core  

---

## SECTION 1: ARCHITECTURAL PHILOSOPHY

In high-integrity enterprise financial resource planning (ERP) platforms (such as SAP S/4HANA Treasury and Cash Management, Oracle Fusion Cash Management, and NetSuite Premium Bank Reconciliation), **Cash Management** is defined as an independent control subsystem. This subsystem acts as a bridge between the general ledger (the accounting source of truth) and external financial institutions (commercial banks and payment clearinghouses).

```
                      [CASH CONTROL SEPARATION LAYERS]
                      
   ┌──────────────────────────────────────────────────────────────────┐
   │                     THE GENERAL LEDGER                           │
   │  - System-of-record for net worth and financial performance      │
   │  - Balanced double-entry transactions (Posted state is locked)   │
   └────────────────────────────────┬─────────────────────────────────┘
                                    ▲
                                    │ Reconciles & Audit-proves
                                    ▼
   ┌──────────────────────────────────────────────────────────────────┐
   │                    CASH MANAGEMENT SUBSYSTEM                     │
   │  - Bank statements (Imports from MT940, CAMT.053, ISO 20022)     │
   │  - Bank transaction matching & Cash allocations                  │
   │  - Real-time treasury control & Liquidity forecasting            │
   └────────────────────────────────┬─────────────────────────────────┘
                                    ▲
                                    │ Settlement Matching
                                    ▼
   ┌──────────────────────────────────────────────────────────────────┐
   │                    EXTERNAL BANK / GATEWAY LAYER                 │
   │  - Actual cash balances at commercial banks                      │
   │  - Clearinghouse settlements & gateway deposits (Stripe, Adyen)  │
   └──────────────────────────────────────────────────────────────────┘
```

### 1.1 Strict Domain Accounting Separation

To prevent financial fraud and maintain absolute auditing precision, Cash Management enforces strict operational boundaries between cash accounts, gateways, accounts receivable, accounts payable, and the general ledger:

1.  **Invoices vs. Cash**: Cash balances never change merely because an invoice or bill has been generated or approved. An invoice represents a contractual right to receive money (AR asset), and a bill represents a liability to pay (AP liability). Cash changes *only* after an actual, settled treasury movement.
2.  **External Bank Accounts are NOT the Accounting Truth**: Bank account statements are external, unverified lists of movements. They are never ingested directly to modify GL ledger accounts without passing through validation and matching engines. The **General Ledger** remains the sole authoritative source of financial truth.
3.  **Bank Statements as Verification Instruments**: Bank statements are treated strictly as secondary verification files. Their purpose is to prove that the cash balances reported in the General Ledger accurately correspond to actual, cleared balances held at physical financial institutions.
4.  **Decoupling Gateway Processing**: Payment gateways (such as Stripe, Adyen, and PayPal) are treated as operational clearing pipelines (`payment_gateways`), not direct cash accounts. A customer transaction creates a pending gateway balance (`payment_receipts`). Actual Cash is debited only when the clearinghouse issues a settlement payout, transferring funds into the organization's physical bank account.

---

## SECTION 2: BANKING DOMAIN PHYSICAL DATABASE SCHEMA

The tables below define the physical schema for bank account management, statement imports, transaction matching, treasury limits, and settlement calendars.

### 2.1 Table Name: `public.bank_accounts`
Defines physical bank accounts held by the enterprise across commercial institutions.

*   **Purpose**: Records physical bank account credentials, currencies, and GL control account mappings.
*   **Ownership**: Treasury / CFO Office.
*   **Lifecycle**: Static configuration; can be suspended but cannot be deleted if referenced.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Enforced via `version` column.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_name` | `varchar(150)`| NO | None | None | - | Public | Standard string | External bank name. |
| `account_number_hash`| `varchar(64)`| NO | None | Unique constraint | - | PII | SHA-256 Hash | Secure indexing check. |
| `account_number_encrypted`|`text` | NO | None | None | AES-256-GCM | PII | Cryptographic blob | Protect sensitive numbers. |
| `routing_number` | `varchar(50)` | NO | None | None | - | PII | Numeric string | Transit routing identifier. |
| `swift_bic` | `varchar(11)` | NO | None | None | - | Public | Valid BIC format | International wire tracking.|
| `currency_code` | `varchar(3)` | NO | `'USD'` | Check Constraint | - | Public | ISO Code | Account transaction currency. |
| `gl_account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| - | Public | UUIDv4 | Target GL Asset Control. |
| `status` | `varchar(30)` | NO | `'active'` | Check Constraint | - | Public | `'active'`, `'inactive'`, `'frozen'` | Active treasury status. |
| `version` | `integer` | NO | `1` | None | - | Public | `>= 1` | Optimistic locking field. |

*   **Indexes**:
    *   `CREATE UNIQUE INDEX bank_accounts_num_hash_idx ON public.bank_accounts(organization_id, account_number_hash);`
    *   `CREATE INDEX bank_accounts_gl_idx ON public.bank_accounts(organization_id, gl_account_id);`

---

### 2.2 Table Name: `public.bank_account_signatories`
Defines authorized users permitted to initiate payments or sign checks on bank accounts.

*   **Purpose**: Controls approval authorization for physical cash dispersion.
*   **Ownership**: Treasury.
*   **Lifecycle**: Active while employment contract stands.
*   **Retention**: 7 years for audit.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Parent bank account. |
| `user_id` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | System user identifier. |
| `approval_limit` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `approval_limit >= 0.00`| Maximum transaction limit. |
| `status` | `varchar(30)` | NO | `'active'` | Check Constraint | - | Public | `'active'`, `'revoked'`| Active signing authority. |

*   **Indexes**:
    *   `CREATE INDEX bank_signatory_lookup_idx ON public.bank_account_signatories(bank_account_id, user_id) WHERE status = 'active';`

---

### 2.3 Table Name: `public.bank_statement_imports`
Logs statement uploads from diverse formats (MT940, CAMT.053, CSV).

*   **Purpose**: Tracks external bank file ingestion history and structural validation status.
*   **Ownership**: Cashier / Accounts Receivable.
*   **Lifecycle**: Imported -> Validated -> Reconciled.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Target bank account. |
| `file_name` | `varchar(255)`| NO | None | None | - | Public | Standard string | External file name. |
| `file_checksum` | `varchar(64)` | NO | None | Unique constraint | - | Public | SHA-256 | Prevent duplicate imports. |
| `import_format` | `varchar(50)` | NO | None | Check Constraint | - | Public | `'MT940'`, `'CAMT_053'`, `'CSV'`, `'ISO_20022'` | Standard input format. |
| `import_timestamp`|`timestamp with time zone`| NO | `now()` | None | - | Public | Valid timestamp | Record file upload. |
| `imported_by_user_id`|`uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Audit trace of uploader. |
| `status` | `varchar(30)` | NO | `'pending'` | Check Constraint | - | Public | `'pending'`, `'validated'`, `'failed'` | Statement state. |

*   **Indexes**:
    *   `CREATE UNIQUE INDEX statement_checksum_idx ON public.bank_statement_imports(organization_id, file_checksum);`

---

### 2.4 Table Name: `public.bank_statement_lines`
Raw line items extracted from imported bank statement documents.

*   **Purpose**: Raw listing of physical cash movements received from the bank.
*   **Ownership**: Cashier / Accounts Receivable.
*   **Lifecycle**: Imported -> Matched -> Exception.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `import_id` | `uuid` | NO | None | FK -> `bank_statement_imports(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Source import batch. |
| `booking_date` | `date` | NO | None | None | - | Public | Valid date | Date of bank processing. |
| `value_date` | `date` | NO | None | None | - | Public | Valid date | Date of interest balance effect.|
| `transaction_amount`|`numeric(18,2)`| NO | None | None | - | Financial | `transaction_amount <> 0.00`| Cash amount (+ or -). |
| `bank_transaction_code`|`varchar(30)`| NO | None | None | - | Public | Standard code | ISO domain transaction code.|
| `customer_reference`| `varchar(200)`| YES | `NULL` | None | - | Public | Standard string | Reference parsed from wire. |
| `bank_instruction_id`| `varchar(100)`| YES | `NULL` | None | - | Public | Standard string | Bank-side transaction ID. |
| `matching_status` | `varchar(30)` | NO | `'unmatched'`| Check Constraint | - | Public | `'unmatched'`, `'matched'`, `'partially_matched'`, `'exception'` | Reconciliation status. |

*   **Indexes**:
    *   `CREATE INDEX stmt_lines_matching_idx ON public.bank_statement_lines(organization_id, matching_status, transaction_amount);`
    *   `CREATE INDEX stmt_lines_booking_idx ON public.bank_statement_lines(organization_id, booking_date);`

---

### 2.5 Table Name: `public.bank_transactions`
Cleared transactions recognized by the treasury module.

*   **Purpose**: Records cleared bank entries that are approved for ledger synchronization.
*   **Ownership**: Treasury.
*   **Lifecycle**: Posted -> Reconciled.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Parent bank account. |
| `transaction_amount`|`numeric(18,2)`| NO | None | None | - | Financial | `transaction_amount <> 0.00`| Financial value. |
| `value_date` | `date` | NO | None | None | - | Public | Valid date | Actual balance effect date. |
| `bank_statement_line_id`|`uuid` | YES | `NULL` | FK -> `bank_statement_lines(id)`| - | Public | UUIDv4 | Source statement line reference.|
| `reconciliation_status`|`varchar(30)`| NO | `'unreconciled'`| Check Constraint | - | Public | `'unreconciled'`, `'reconciled'`| Match verification. |

---

### 2.6 Table Name: `public.cash_movements`
Internal tracking records detailing individual cash sources and destinations.

*   **Purpose**: Logs specific transaction categories for all outgoing and incoming cash.
*   **Ownership**: Treasurer.
*   **Lifecycle**: Initiated -> Processed -> Reconciled.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Source/Target account. |
| `movement_type` | `varchar(50)` | NO | None | Check Constraint | - | Public | See List Section 3 | Movement classification. |
| `amount` | `numeric(18,2)`| NO | None | None | - | Financial | `amount <> 0.00` | Financial amount. |
| `movement_date` | `date` | NO | None | None | - | Public | Valid date | Intended movement date. |
| `reconciliation_item_id`|`uuid`| YES | `NULL` | None | - | Public | UUIDv4 | Matching tracker ID. |

---

### 2.7 Table Name: `public.cash_transfers`
Tracks liquidity distribution between internal enterprise bank accounts.

*   **Purpose**: Controls intercompany and inter-account liquidity pooling.
*   **Ownership**: Treasurer / Finance Manager.
*   **Lifecycle**: Draft -> Pending Approval -> Cleared -> Rejected.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `source_bank_account_id`|`uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Account sending cash. |
| `target_bank_account_id`|`uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Account receiving cash. |
| `transfer_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `transfer_amount > 0.00`| Cash amount transferred. |
| `exchange_rate` | `numeric(12,6)`| NO | `1.000000` | None | - | Financial | `exchange_rate > 0.00` | Exchange rate applied. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'pending_approval'`, `'cleared'`, `'rejected'` | Approval state. |
| `created_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | Maker identifier. |
| `approved_by` | `uuid` | YES | `NULL` | FK -> `users(id)` | - | Public | UUIDv4 | Checker identifier. |

---

### 2.8 Table Name: `public.bank_fees`
Tracks service and transaction fees charged directly by financial institutions.

*   **Purpose**: Logs bank maintenance, wire execution, and gateway processing expenses.
*   **Ownership**: Accounts Payable / Reconciliation Clerk.
*   **Lifecycle**: Unposted -> Posted.
*   **Retention**: 7 years.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Associated bank account. |
| `fee_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `fee_amount > 0.00` | Fee expense value. |
| `fee_date` | `date` | NO | None | None | - | Public | Valid date | Charge timestamp. |
| `description` | `varchar(255)`| NO | None | None | - | Public | Standard string | Explanatory description. |
| `journal_entry_id` | `uuid` | YES | `NULL` | FK -> `journal_entries(id)`| - | Public | UUIDv4 | Expense posting journal. |

---

### 2.9 Table Name: `public.bank_interest`
Tracks interest payments received or loans charges assessed directly on bank balances.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Associated bank account. |
| `amount` | `numeric(18,2)`| NO | None | None | - | Financial | `amount <> 0.00` | Earnings (+) or cost (-). |
| `posting_date` | `date` | NO | None | None | - | Public | Valid date | Accounting posting timestamp.|
| `interest_type` | `varchar(20)` | NO | None | Check Constraint | - | Public | `'earned'`, `'charged'` | Income or Expense trigger. |

---

### 2.10 Table Name: `public.bank_reconciliations`
Audit-ready header tracking the balance verification execution runs.

*   **Purpose**: Formal record proving that GL cash equals bank statement balances.
*   **Ownership**: Auditor / Financial Controller.
*   **Lifecycle**: In_Progress -> Approved -> Locked.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Account reconciled. |
| `statement_end_date`|`date` | NO | None | None | - | Public | Valid date | Period target limit. |
| `bank_statement_balance`|`numeric(18,2)`|NO|None | None | - | Financial | Standard numeric | Balance reported by bank. |
| `gl_ledger_balance`| `numeric(18,2)`| NO | None | None | - | Financial | Standard numeric | Cash balance reported in GL. |
| `unreconciled_difference`|`numeric(18,2)`|NO|None| None | - | Financial | Must balance to 0.00 | Difference to clear. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'approved'`, `'closed'` | Execution state. |
| `completed_by_user_id`|`uuid` | YES | `NULL` | FK -> `users(id)` | - | Public | UUIDv4 | Completing cashier. |
| `approved_by_user_id` | `uuid` | YES | `NULL` | FK -> `users(id)` | - | Public | UUIDv4 | Controller signature. |

---

### 2.11 Table Name: `public.reconciliation_items`
Mapping tables linking bank statement lines with physical database transactions.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `reconciliation_id`|`uuid` | NO | None | FK -> `bank_reconciliations(id)` ON DELETE CASCADE| - | Public | UUIDv4 | Parent reconciliation header.|
| `bank_statement_line_id`|`uuid`| NO | None | FK -> `bank_statement_lines(id)`| - | Public | UUIDv4 | Bank wire record matched. |
| `matched_transaction_type`|`varchar(50)`|NO|None| None | - | Public | Standard type | Matching transaction class. |
| `matched_transaction_id`|`uuid` | NO | None | None | - | Public | UUIDv4 | Linked system payment or AR. |
| `matching_method` | `varchar(50)` | NO | `'exact'` | Check Constraint | - | Public | `'exact'`, `'tolerance'`, `'manual'`, `'ai'` | Matching methodology. |

---

### 2.12 Table Name: `public.cash_forecasts`
Consolidated planning data for active corporate liquidity calculations.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `forecast_date` | `date` | NO | None | None | - | Public | Valid date | Projection target. |
| `projected_inflows` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Expected incoming cash. |
| `projected_outflows`|`numeric(18,2)`| NO | `0.00` | None | - | Financial | `>= 0.00` | Expected outgoing cash. |
| `opening_cash` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | Estimated starting balance. |
| `closing_cash` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | Standard numeric | Estimated final balance. |

---

### 2.13 Table Name: `public.treasury_limits`
Safety and risk compliance boundaries set to protect corporate liquidity.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `bank_account_id` | `uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Bank account constraint. |
| `minimum_balance` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `minimum_balance >= 0.00`| Floor liquidity warning. |
| `max_single_payment`|`numeric(18,2)`| NO | None | None | - | Financial | `max_single_payment > 0.00`| Maximum transaction limit. |
| `approved_by` | `uuid` | NO | None | FK -> `users(id)` | - | Public | UUIDv4 | CFO User profile ID. |

---

### 2.14 Table Name: `public.payment_batches`
Groups individual outgoing vendor payments or payroll runs.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `source_bank_account_id`|`uuid` | NO | None | FK -> `bank_accounts(id)` | - | Public | UUIDv4 | Bank account funding batch. |
| `batch_total` | `numeric(18,2)`| NO | `0.00` | None | - | Financial | `batch_total >= 0.00` | Total value of payments. |
| `payment_method` | `varchar(30)` | NO | `'ACH'` | Check Constraint | - | Public | `'ACH'`, `'SEPA'`, `'WIRE'`, `'CHECK'` | Outbound rails format. |
| `status` | `varchar(30)` | NO | `'draft'` | Check Constraint | - | Public | `'draft'`, `'approved'`, `'transmitted'`, `'cleared'` | Batch status lifecycle. |

---

### 2.15 Table Name: `public.payment_batch_items`
Individual line payments nested within an active disbursement batch.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | - | Public | UUIDv4 | Multi-tenant separator. |
| `batch_id` | `uuid` | NO | None | FK -> `payment_batches(id)` ON DELETE CASCADE | - | Public | UUIDv4 | Parent execution batch. |
| `payee_name` | `varchar(150)`| NO | None | None | - | PII | Standard string | Target vendor/employee name. |
| `payment_amount` | `numeric(18,2)`| NO | None | None | - | Financial | `payment_amount > 0.00` | Value to disperse. |
| `destination_account_encrypted`|`text`| NO | None | None | AES-256-GCM | PII | Cryptographic blob | Secure payee account. |
| `status` | `varchar(30)` | NO | `'pending'` | Check Constraint | - | Public | `'pending'`, `'transmitted'`, `'cleared'`, `'failed'` | Status of disbursement line.|

---

### 2.16 Table Name: `public.bank_holidays`
Calendar system governing non-settlement business days globally.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `holiday_date` | `date` | NO | None | Unique constraint | - | Public | Valid date | Gregorian date of holiday. |
| `clearing_region` | `varchar(30)` | NO | `'US'` | None | - | Public | Standard ISO code | Country/Region code. |
| `description` | `varchar(150)`| NO | None | None | - | Public | Standard string | Holiday description name. |

---

### 2.17 Table Name: `public.settlement_calendars`
Calculates next available settlement dates based on regional banking schedules.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Encryption | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | - | Public | UUIDv4 | Unique primary identifier. |
| `input_date` | `date` | NO | None | None | - | Public | Valid date | Base date of transaction. |
| `payment_method` | `varchar(30)` | NO | None | None | - | Public | Standard code | ACH, SEPA, SWIFT, etc. |
| `settlement_days` | `integer` | NO | `1` | None | - | Public | `settlement_days >= 0` | Transit offset (e.g., T+2). |
| `resolved_settlement_date`|`date`| NO | None | None | - | Public | Valid date | Final target settlement day.|

---

## SECTION 3: SYSTEM CASH MOVEMENT TYPES

To enable accurate liquidity analysis, the system classifies cash movements into distinct transaction types.

```
                             [CASH MOVEMENT DOMAINS]
                             
             INFLOWS                                        OUTFLOWS
    - Customer Payment (AR settlement)             - Vendor Payment (AP ledger)
    - Interest Earned (Bank assets)                - Payroll disbursement (Direct costs)
    - Loan Proceeds (Financing inflow)             - Refund execution (Debit sales)
    - Capital Investment (Equity increase)          - Loan Repayment (Principal + Interest)
    - Bad Debt Recovery (Collections cash)          - Tax Payments (IRS/HM Revenue)
```

### 3.1 Cash Movement Dictionary

1.  **Customer Payment (`customer_payment`)**: Incoming cash from customers to settle outstanding accounts receivable balances.
2.  **Vendor Payment (`vendor_payment`)**: Outgoing cash to clear accounts payable liabilities.
3.  **Payroll (`payroll`)**: Employee salary payouts.
4.  **Internal Transfer (`internal_transfer`)**: Cash movement between internal accounts.
5.  **Refund (`refund`)**: Reversal of sales cash paid back to customers.
6.  **Chargeback (`chargeback`)**: Forced merchant card reversals, debited from company accounts.
7.  **Bank Fee (`bank_fee`)**: Operational transaction charges.
8.  **Interest Earned (`interest_earned`)**: Earnings posted to positive balances.
9.  **Interest Charged (`interest_charged`)**: Expense assessed on debt or overdraft balances.
10. **Loan Proceeds (`loan_proceeds`)**: Cash received from external debt financing.
11. **Loan Repayment (`loan_repayment`)**: Principal and interest debt service payments.
12. **Investment (`investment`)**: Inbound capital or equity injections.
13. **Dividend (`dividend`)**: Distributions paid to shareholders.
14. **Tax Payment (`tax_payment`)**: Quarterly or regional tax payments.
15. **Petty Cash (`petty_cash`)**: Cash allocations to physical on-site holdings.
16. **Cash Adjustment (`cash_adjustment`)**: Ledger reconciliations to match physical counts.
17. **FX Conversion (`fx_conversion`)**: Cash transferred through currency exchanges.
18. **Opening Balance (`opening_balance`)**: Initial account balances.
19. **Closing Adjustment (`closing_adjustment`)**: Adjustments to close a reporting period.

---

## SECTION 4: BANK RECONCILIATION ENGINE WORKFLOW

Reconciliation verifies that ledger transactions match bank activity, identifying discrepancies and adjusting balances to ensure ledger accuracy.

```
                         [BANK RECONCILIATION PIPELINE]
                         
      [ Imported ] ────────► RAW statement lines loaded.
           │
           ▼
      [ Matched ] ─────────► Automatic matching rules find exact balance-date match.
           │
           ▼
   [ Partially Matched ] ──► System matches multiple statement lines to one GL row.
           │
           ▼
     [ Exception ] ────────► Unmatched records flagged; requires manual review.
           │
           ▼
      [ Approved ] ────────► Controller signs off on matching run.
           │
           ▼
       [ Closed ] ─────────► Locked into permanent read-only archive status.
```

### 4.1 Detailed Matching Hierarchy

The Matching Engine applies validation rules in a strict, sequential hierarchy to automate matching runs:

```
Rule Level 1: Exact Match on Internal Transaction ID / Instruction ID
   ↳ IF bank_instruction_id == database transaction reference ID: Match. Stop.
   ↳ ELSE: Proceed to Level 2.

Rule Level 2: Invoice / Reference ID Parsing
   ↳ Parse memo field for invoice (e.g., "INV-2026-0004"). Match client AR.
   ↳ IF match is found: Match. Stop.
   ↳ ELSE: Proceed to Level 3.

Rule Level 3: Date & Value Tolerance Matching
   ↳ Match transaction amount exactly within a date window (e.g., +/- 2 business days).
   ↳ IF a unique matching transaction is found: Match. Stop.
   ↳ ELSE: Proceed to Level 4.

Rule Level 4: Customer Profile + Value Grouping Match
   ↳ Group open invoices for a customer to match a combined payment value.
   ↳ IF match is found: Match. Stop.
   ↳ ELSE: Proceed to Level 5.

Rule Level 5: Manual Reconciliation / AI-Assisted Recommendation
   ↳ Flag as 'exception' and queue for manual or system-assisted review.
```

---

## SECTION 5: MATCHING ENGINE RULES & SPECIAL SCENARIOS

Reconciling real-world banking activity requires robust handling for varied payment structures, timing differences, and transaction patterns.

### 5.1 Reconciliation Rule Catalog

*   **Exact Matches**: 1-to-1 matching where transaction amounts, dates, and references align perfectly.
*   **Tolerance Matches**: Date boundaries are configured (typically +/- 3 business days) to accommodate standard bank clearing delays.
*   **One-to-Many Matches (Combined Statement Payouts)**: Occurs when a credit card processor settles a single batch payout that covers multiple individual customer invoices. The engine matches the single statement credit line against multiple outstanding receivable invoices in the database.
*   **Many-to-One Matches (Split Payments)**: Occurs when a customer settles a single invoice via multiple bank wires. The engine aggregates multiple statement lines to match the single receivable ledger balance.
*   **Split Payments (Fees Deducted)**: Automatically resolves transactions where intermediary bank or gateway fees are deducted from the payment amount:
    *   *Example*: A $1,000 international customer wire arrives as a $980 credit line due to a $20 wire fee. The system matches the $980 credit to the $1,000 receivable, and automatically generates a $20 debit entry to the Bank Fees Expense account, balancing the ledger.
*   **Returned Payment Handling**: If a check bounces or a SEPA payment is returned, the matching engine reverses previous payment allocations, re-opens the original receivable balance, and flags the customer account for credit review.

---

## SECTION 6: BANK STATEMENT INGESTION ENGINE

The Bank Ingestion Engine parses statement uploads from diverse banking networks and translates them into unified database structures.

```
                          [BANK INGESTION PIPELINE]
                          
                          [ Ingest Inbound File ]
                                     │
                                     ▼
                        Calculate SHA-256 Checksum:
                        Compare hash against imported files.
                                     │
                    ┌────────────────┴────────────────┐
                    ▼                                 ▼
         [ Duplicate Detected ]               [ Unique File ]
          Reject upload immediately                  │
                                                     ▼
                                          Verify File Structure
                                          (Validate SWIFT/BIC tags)
                                                     │
                                                     ▼
                                          Decompose File to Lines
                                          - Extract amounts & references
                                          - Write to statement tables
```

### 6.1 Supported Bank Formats

1.  **SWIFT MT940**: The standard flat-file format used for international balance reporting. The parser reads structured tag blocks:
    *   `:61:`: Statement Line details (value date, entry date, amount, transaction code).
    *   `:86:`: Information to Account Owner (memo details, customer name, transaction reference).
2.  **CAMT.053**: XML-based ISO 20022 cash management reporting, parsing node blocks (`<Stmt>`, `<Ntry>`, `<TxDtls>`) to extract transaction metadata.
3.  **CSV / Excel Template Imports**: Supported for smaller institutions, validating fields against custom parser templates before ingestion.
4.  **Open Banking / PSD2 APIs**: Integrates with banking aggregators to automate ingestion via secure daily webhooks, bypassing manual file uploads.

---

## SECTION 7: TREASURY CONTROLS & RISK MANAGEMENT

To safeguard corporate liquidity and ensure financial compliance, Cash Management enforces strict risk controls and operating boundaries.

### 7.1 Treasury Risk Mitigation Policies

*   **Daily Cash Position Aggregations**: The system calculates the enterprise's total cash position dynamically across all bank accounts and currencies:
    $$\text{Total Liquidity (Base Currency)} = \sum_{i=1}^{n} \left( \text{Balance}_{\text{Account}_i} \times \text{Exchange Rate}_i \right)$$
*   **Minimum Balance Warnings**: The system monitors accounts against configured floor limits (`public.treasury_limits`). If a bank balance drops below its limit, the system blocks outgoing transfers and routes alerts to the treasurer.
*   **Outbound Payment Dual Authorization**: Large payments exceeding configured thresholds (default: $10,000) require maker-checker approval:
    *   A clerk proposes the transaction batch (the Maker).
    *   An authorized treasury manager or CFO must sign off (the Checker) before the batch can be transmitted to the bank.
*   **Large Exposure Alerts**: Monitored on bank, region, and entity levels to prevent concentration risk across individual institutions.

---

## SECTION 8: SETTLEMENT ENGINE CALCULATION MODELS

Because bank transfers do not clear instantly, the Settlement Engine tracks the timeline of outstanding payments to maintain cash forecast accuracy.

```
                         [SETTLEMENT TIMING PIPELINE]
                         
          [ Payment Initialized ] ──► System records expected value date (e.g., T+2).
                     │
                     ▼
          [ Calendar Processing ] ──► Evaluates regional bank holidays.
                     │
                     ▼
          [ Delay Offset Run ] ──► Extends timeline across weekends or closures.
                     │
                     ▼
          [ Cleared Ledger Date ] ──► Pushes cleared ledger entry date out.
```

*   **Settlement Timing Scenarios**:
    *   *Instant Payments*: Transactions clear immediately, posting ledger entries on the transaction date.
    *   *Same-Day Automated Clearing House (ACH)*: Settlements execute by day-end close.
    *   *Standard ACH (T+1 / T+2)*: Transactions clear 1-2 business days post-initiation.
*   **Weekend and Holiday Delay Offsets**: The engine parses the transaction type and target banking rails, adjusting expected settlement dates forward to account for regional holidays (`public.bank_holidays`) and weekend closures.
*   **Gateway Reserve Balances**: Some credit card processors withhold a portion of funds as a risk reserve (e.g., 5% rolling reserve). The system tracks these reserves within dedicated holding accounts (`deferred_receivables`), releasing balances to active cash accounts only upon formal settlement payout.

---

## SECTION 9: MULTI-CURRENCY TREASURY OPERATIONS

Managing liquidity across multi-currency operations requires precise tracking of foreign exchange translations and realized gains or losses.

### 9.1 Multi-Currency Rules

1.  **Monetary Asset Asset Revaluation**: Under GAAP and IFRS, foreign currency bank accounts are monetary assets. They must be revalued at the end of each reporting period using the closing exchange rate, with unrealized FX gains or losses posted to the Income Statement:
    $$\text{Unrealized FX Gain/Loss} = \text{Foreign Currency Balance} \times \left( \text{Exchange Rate}_{\text{Period\_End}} - \text{Exchange Rate}_{\text{Last\_Revaluation}} \right)$$
2.  **Settlement FX Gain/Loss**: Calculated when cash is converted between accounts:
    $$\text{Realized FX Gain/Loss} = \text{Base Value}_{\text{Settlement}} - \text{Base Value}_{\text{Historical}}$$
3.  **Exchange Spread Handling**: Foreign currency conversions involve transaction fees or bank spreads (the difference between the bank's exchange rate and the market spot rate). The system isolates these spreads, posting them directly to the **Exchange Transaction Cost** expense account to keep the asset account balanced.

---

## SECTION 10: PAYMENT GATEWAY & CLEARINGHOUSE RECONCILIATION

The Payment Gateway Reconciliation Engine coordinates digital transaction states with the general ledger without duplicating payment records.

```
                          [GATEWAY RECONCILIATION LAYER]
                          
                          [ Customer Pays Online $100 ]
                                      │
                                      ▼
                      [ payment_attempts / payment_receipts ]
                      - Status: Pending Settlement
                      - GL Posting: Debit Gateway Clearing, Credit AR
                                      │
                                      ▼
                        [ Bank Settlement Received $97 ]
                        - Stripe deducts $3 gateway fee.
                                      │
                                      ▼
                       [ Matching Engine Allocation ]
                       - Debit: Cash Bank Asset $97
                       - Debit: Merchant Processing Fees $3
                       - Credit: Gateway Clearing Asset $100
```

*   **No Duplicate Records**: Client checkout interactions are tracked in operational tables (`payment_attempts`, `payment_receipts`). They are decoupled from the general ledger until the settlement payout clears.
*   **Clearing Account Controls**: The system routes card transactions through a dedicated **Gateway Clearing** asset account. This account acts as a sub-ledger, holding card receivables until the processor deposits a settled payout, clearing the balance to the cash asset account.

---

## SECTION 11: SECURITY, AUDITING, & ROLE-BASED ACCESS CONTROL

Cash systems require strict authorization controls and immutable log capture to prevent internal fraud and ensure regulatory compliance.

### 11.1 Security Roles and Operational Matrix

| Operations Role | Create Bank Account | Import Statements | Override Auto-Match | Approve Transfers | Set Limit Safety | Run Audit Reports |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Cashier / Clerk** | No | Yes | Yes | No | No | No |
| **Treasurer** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Auditor / Assessor**| No | No | No | No | No | Yes |
| **Finance Controller**| Yes | Yes | Yes | Yes | Yes | Yes |
| **CFO / Director** | Yes | Yes | Yes | Yes | Yes | Yes |

---

### 11.2 Maker-Checker Approvals & Dual Controls

To satisfy SOC2 compliance standards, critical operations require dual control approvals:
*   **Large Outbound Transfers**: Payments exceeding $10,000 require a cashier to create the batch (Maker) and a treasurer or CFO to authorize transmission (Checker).
*   **Bank Account Configurations**: Creating or editing bank account parameters or GL mappings requires dual authorization from the Controller and CFO.
*   **Manual Override Audit Trails**: If an automatic match is manually overridden, the system prompts for a mandatory justification comment and records the event, user ID, and timestamp in the append-only table `public.reconciliation_items`.

---

## SECTION 12: DATABASE PERFORMANCE & INDEXING

High-volume transaction matching requires targeted indexes to maintain fast reporting times and prevent database locking bottlenecks.

### 12.1 Indexing and Query Optimization

```sql
-- 1. Composite index to accelerate transaction matching runs
CREATE INDEX bank_statement_lines_matching_idx 
  ON public.bank_statement_lines(organization_id, matching_status, booking_date)
  INCLUDE (transaction_amount, customer_reference);

-- 2. Index to optimize cash movement queries by bank account and date
CREATE INDEX cash_movements_lookup_idx 
  ON public.cash_movements(organization_id, bank_account_id, movement_date)
  INCLUDE (amount, movement_type);

-- 3. Unique index to block duplicate bank statement file imports
CREATE UNIQUE INDEX bank_statement_imports_file_hash_idx 
  ON public.bank_statement_imports(organization_id, file_checksum);
```

---

### 12.2 Partitioning & Concurrency Strategies
*   **Horizontal Partitioning**: High-volume tables (`bank_transactions`, `bank_statement_lines`, `cash_movements`) are partitioned by `organization_id` (using hash partitioning) to isolate tenant data and maintain rapid query performance.
*   **Optimistic Concurrency**: Optimistic locking checks (`version`) prevent concurrent matching workers from allocating multiple transactions to the same statement line simultaneously.

---

## SECTION 13: REAL-TIME CASH MANAGEMENT EVENTS

The Cash Management subsystem is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 13.1 Event Schemas

#### `bank.statement.imported`
Emitted immediately upon successfully importing and checksum-validating a bank statement file.

```json
{
  "event_id": "evt_cash_01A9382103",
  "event_type": "bank.statement.imported",
  "organization_id": "org_771829",
  "correlation_id": "corr_file_90112",
  "payload": {
    "import_id": "imp_8829103",
    "bank_account_id": "acc_44921",
    "file_name": "stmt_US_2026_06_28.xml",
    "format": "CAMT_053",
    "line_count": 142,
    "total_credit_amount": 145000.00,
    "total_debit_amount": 92000.00
  },
  "timestamp": "2026-06-28T22:00:00Z"
}
```

#### `payment.settled`
Emitted upon matching a bank statement credit line with an outstanding payment or AR transaction.

```json
{
  "event_id": "evt_cash_01A9382155",
  "event_type": "payment.settled",
  "organization_id": "org_771829",
  "correlation_id": "corr_recon_5521",
  "payload": {
    "reconciliation_item_id": "rec_44912",
    "bank_statement_line_id": "line_99182",
    "matched_transaction_id": "pay_22910",
    "settlement_amount": 1000.00,
    "fee_deducted": 3.00,
    "reconciled_at": "2026-06-28T22:05:00Z"
  },
  "timestamp": "2026-06-28T22:05:01Z"
}
```

---

## SECTION 14: ENTERPRISE CASH SYSTEM VALIDATION CHECKLIST

Before deploying the Banking, Cash Management, and Reconciliation Engine to production, verify that the following configurations and controls are in place.

- [ ] **Ledger-Bank Alignment Verified**: The system prevents reconciliation runs from closing unless the reconciled bank balance matches the GL ledger cash account balance.
- [ ] **Import Checksum Protection Active**: The parser database blocks duplicate imports of the same bank statement file via SHA-256 checks.
- [ ] **Locking Rules Configured**: Account row-locking order is implemented and verified to prevent database deadlocks.
- [ ] **Maker-Checker Roles Enforced**: Outbound transfers exceeding $10,000 require dual authorization signatures.
- [ ] **Cross-Currency Verification Blocked**: Direct cross-currency matching runs are blocked unless an explicit FX conversion rate is configured.
- [ ] **Reconciled Records Immutable**: Reconciliations marked as `Closed` are locked and protected from subsequent modifications.
- [ ] **No Orphan Matching Entries**: Foreign key constraints block deletion of bank accounts or transactions that are referenced in active reconciliations.
- [ ] **Cryptographic Verification Active**: Storage encryption for sensitive account details (`bank_accounts`, `payment_batch_items`) is active and verified.

---
**End of Specification.**
