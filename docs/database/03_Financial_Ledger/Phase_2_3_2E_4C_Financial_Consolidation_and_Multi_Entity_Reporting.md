# JUANET ERP Financial Consolidation & Multi-Entity Reporting Engine Specification
## Phase 2.3.2E.4C — Financial Consolidation & Multi-Entity Reporting Manual
**Document Version:** 1.0  
**Author:** Chief ERP Financial Systems Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Architecture / Consolidation, Elimination, Multi-Currency Translation, and Multi-Entity Analytics Core  

---

## SECTION 1: ARCHITECTURAL PHILOSOPHY & GUIDING PRINCIPLES

In a global enterprise holding company structure, operating entities typically function as distinct legal corporations, each possessing its own local regulatory mandates, operational processes, tax liabilities, and functional currencies. The **Financial Consolidation & Multi-Entity Reporting Engine** (the "Consolidation Engine") provides the authoritative framework for aggregating, adjusting, translating, and eliminating multi-ledger activity to deliver a unified, audit-ready consolidated view of the enterprise's financial state in compliance with **IFRS 10 (Consolidated Financial Statements)**, **IAS 21 / ASC 830 (The Effects of Changes in Foreign Exchange Rates)**, and **ASC 810 (Consolidations)**.

This system is governed by seven foundational architectural principles:

```
                  [CONSOLIDATION ARCHITECTURAL FLOW]

  ┌───────────────┐   ┌───────────────┐   ┌───────────────┐   ┌───────────────┐
  │ Local Ledger  │   │ Local Ledger  │   │ Local Ledger  │   │ Local Ledger  │
  │  Subsidiary A │   │  Subsidiary B │   │ Joint Venture │   │ Holding Corp  │
  │     (EUR)     │   │     (USD)     │   │     (GBP)     │   │     (USD)     │
  └───────┬───────┘   └───────┬───────┘   └───────┬───────┘   └───────┬───────┘
          │                   │                   │                   │
          ▼                   ▼                   ▼                   ▼
  ┌───────────────────────────────────────────────────────────────────────────┐
  │                           Currency Translation                            │
  │           (IAS 21 / ASC 830: Historical, Closing, Average Rates)          │
  └─────────────────────────────────────┬─────────────────────────────────────┘
                                        │
                                        ▼
  ┌───────────────────────────────────────────────────────────────────────────┐
  │                       Intercompany Elimination Engine                      │
  │               (Eliminating IC Receivables, Sales, Profits)                │
  └─────────────────────────────────────┬─────────────────────────────────────┘
                                        │
                                        ▼
  ┌───────────────────────────────────────────────────────────────────────────┐
  │                        Minority Interest Allocation                       │
  │               (NCI Profit/Loss and Equity Balance Sheet Attribution)      │
  └─────────────────────────────────────┬─────────────────────────────────────┘
                                        │
                                        ▼
  ┌───────────────────────────────────────────────────────────────────────────┐
  │                        Consolidated Reports Cache                         │
  │         (Consolidated Trial Balance, Balance Sheet, Income Statement)     │
  └───────────────────────────────────────────────────────────────────────────┘
```

1.  **Independent Ledger Autonomy**: Every subsidiary, associate, joint venture, and holding company within the corporate group maintains a physically and logically isolated general ledger (`public.ledger_entries`). The transactional entries of local entities are never altered, updated, or reclassified by downstream consolidation processes.
2.  **Derived, Non-Mutating Aggregation**: Consolidations are strictly derived analytical processes. The results are written to specialized multi-entity reporting snapshots (`public.consolidated_financial_reports`) and elimination ledgers. The source transactional data remains untouched and independently auditable.
3.  **Hierarchical Aggregation Structure**: The parent organization aggregates child entities recursively based on legal ownership matrices. Consolidation scopes (`public.consolidation_scopes`) establish the reporting group boundary, preventing multi-entity double-counting of equity or balances.
4.  **Historical Reproducibility & Audit Trail**: A consolidation run for any prior period (e.g., FY2025) must yield the exact same consolidated financial results, regardless of when it is run. The engine captures and freezes the exact ownership rates, exchange rates, elimination entries, and topside adjustments applied during the run.
5.  **Multi-Currency Temporal Consistency**: The translation of balances from functional currencies to the group presentation currency strictly forbids mixing rate dates. Every translation run must apply currency rates strictly mapped to closing dates for assets/liabilities, average historical rates for P&L items, and transaction-date historical rates for equity components.
6.  **Complete Local Auditability**: Any consolidated reporting row must be traceable down to its constituent ledger rows inside local subsidiary databases. Downward drill-downs must clearly separate: Local Ledger Base Balance + Topsides + Elimination Entries = Consolidated Balance.
7.  **No Direct Multi-Ledger Postings**: Transactions between entities must be recorded as matching local ledger entries (due-to/due-from) in both respective entities. Direct journal entry lines crossing from Entity A's general ledger to Entity B's general ledger in a single operational posting are strictly prohibited.

---

## SECTION 2: CONSOLIDATION HIERARCHIES & SCOPES

To support global corporations with nested subsidiaries, joint ventures, and minority-owned associates, the Consolidation Engine defines multi-tier reporting trees.

### 2.1 Entity Classifications
*   **Holding Company (Parent)**: The primary reporting entity exercising ultimate control over the group.
*   **Subsidiary**: An entity in which the parent holds > 50% voting rights or exercises de facto control, requiring full line-by-line consolidation.
*   **Minority Ownership / Non-Controlling Interest (NCI)**: Subsidiaries where external shareholders own a portion of equity, requiring non-controlling interest allocations on the balance sheet and income statement.
*   **Joint Venture (JV)**: An entity jointly controlled by the group and external partners, Consolidated using the **Equity Method** (IFRS 11 / ASC 323).
*   **Associate Entity**: An entity in which the group has significant influence (typically 20% to 50% voting rights), consolidated using the **Equity Method**.
*   **Branch / Division**: A localized operating unit without distinct legal incorporation, consolidated line-by-line with 100% ownership.

```
                    [ORGANIZATION CONSOLIDATION TREE]

                       [ JUANET Holding Corp (USD) ]
                                     │
             ┌───────────────────────┴───────────────────────┐
             ▼ (100% Control)                                ▼ (70% Control)
    [ Germany GmbH (EUR) ]                       [ UK Ltd (GBP) ]
             │                                    (30% Minority Interest NCI)
             ▼ (60% Control)
    [ France SAS (EUR) ]
```

### 2.2 Parent-Child Recursive Consolidation Scope
Consolidation boundaries are managed via *Consolidation Scopes*. The hierarchy represents a Directed Acyclic Graph (DAG) preventing circular holdings (e.g., Entity A owning Entity B, which owns Entity C, which owns Entity A). Circular loops are blocked at the schema validation level using structural checks during membership creation.

---

## SECTION 3: PHYSICAL DATABASE SCHEMAS

The following schemas define the tables governing organization groupings, exchange rates, elimination registers, adjustments, intercompany confirmations, and consolidation logs.

### 3.1 Table Name: `public.organization_groups`
Top-level metadata for consolidations (e.g., "Global Corporate Group", "European Division Consolidated").

*   **Purpose**: Stores top-level organizational groups for multi-entity reporting.
*   **Ownership**: Corporate Controller / Chief Financial Officer.
*   **Lifecycle**: Active -> Closed.
*   **Retention**: Permanent.
*   **Optimistic Locking**: Yes.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `group_name` | `varchar(150)`| NO | None | None | Public | Standard string | Descriptive group name. |
| `presentation_currency`|`varchar(3)` | NO | `'USD'` | Check Constraint | Public | ISO Code | Ultimate currency of consolidated views. |
| `is_active` | `boolean` | NO | `true` | None | Public | Valid boolean | Activation flag. |
| `version` | `integer` | NO | `1` | None | Public | `>= 1` | Optimistic locking field. |

---

### 3.2 Table Name: `public.organization_group_members`
Maps operating legal entities into consolidation groups with defined ownership types and equity weights.

*   **Purpose**: Mappings of entities to groups with exact equity and voting ownership rates.
*   **Ownership**: Corporate Controller.
*   **Lifecycle**: Active inside effective date range.
*   **Retention**: Permanent.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `group_id` | `uuid` | NO | None | FK -> `organization_groups(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent consolidation group. |
| `member_organization_id`|`uuid`| NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Target child legal entity. |
| `ownership_percentage`|`numeric(5,4)` | NO | `1.0000` | `CHECK (ownership_percentage >= 0 AND ownership_percentage <= 1.0)` | Public | Equity share | Ownership weight (e.g., 0.7500 for 75%). |
| `voting_percentage` | `numeric(5,4)` | NO | `1.0000` | `CHECK (voting_percentage >= 0 AND voting_percentage <= 1.0)` | Public | Voting rights | Determines control status (Full vs Equity). |
| `ownership_type` | `varchar(30)` | NO | `'subsidiary'`| Check Constraint | Public | `'subsidiary'`, `'associate'`, `'joint_venture'`, `'branch'` | Consolidation methodology to apply. |
| `effective_start_date`|`date` | NO | None | None | Public | Valid date | Start of ownership inclusion window. |
| `effective_end_date`|`date` | YES | `NULL` | None | Public | Valid date | End of ownership inclusion window. |

*   **Indexes**:
    *   `CREATE INDEX group_members_lookup_idx ON public.organization_group_members(organization_id, group_id, member_organization_id, effective_start_date, effective_end_date);`

---

### 3.3 Table Name: `public.consolidation_runs`
Records execution logs for consolidation pipelines.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `group_id` | `uuid` | NO | None | FK -> `organization_groups(id)`| Public | UUIDv4 | Consolidated group reference. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| Public | UUIDv4 | Targeted accounting fiscal period. |
| `run_status` | `varchar(30)` | NO | `'started'` | Check Constraint | Public | `'started'`, `'completed'`, `'failed'` | Execution status. |
| `executed_by` | `uuid` | NO | None | FK -> `users(id)` | Public | UUIDv4 | Executing user profile ID. |
| `started_at` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Start timestamp. |
| `completed_at` | `timestamp with time zone`| YES | `NULL` | None | Public | Valid timestamp | Completion timestamp. |
| `hash_checksum` | `varchar(64)` | YES | `NULL` | None | Public | SHA-256 Hash | Integrity verification signature. |

---

### 3.4 Table Name: `public.consolidation_entries`
Consolidated transaction lines aggregated from subsidiary records.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Source consolidation run. |
| `source_organization_id`|`uuid`| NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Originating local entity. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Unified chart of accounts ID. |
| `local_currency_code`|`varchar(3)` | NO | None | None | Public | ISO Code | Currency of source subsidiary ledger. |
| `local_debit` | `numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Original local currency debit. |
| `local_credit` | `numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Original local currency credit. |
| `translated_debit` | `numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Target presentation currency debit. |
| `translated_credit`|`numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Target presentation currency credit. |
| `exchange_rate_used`|`numeric(14,6)`| NO | None | None | Public | Rate factor | Rate applied for this account class. |

---

### 3.5 Table Name: `public.elimination_entries`
Immutable journal entries posted to eliminate intercompany balances.

*   **Purpose**: Holds journal lines written by the elimination engine to balance intercompany transactions.
*   **Ownership**: System Consolidation Service.
*   **Lifecycle**: Created once during a consolidation run.
*   **Retention**: Permanent.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Linked consolidation run. |
| `elimination_type` | `varchar(50)` | NO | None | Check Constraint | Public | `'receivable_payable'`, `'sales_purchases'`, `'inventory_profit'`, `'dividend'` | Type of intercompany elimination. |
| `debit_account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Target debit ledger account. |
| `credit_account_id`|`uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Target credit ledger account. |
| `amount` | `numeric(18,2)`| NO | None | `CHECK (amount > 0.00)` | Financial | Positive amount | Eliminated balance (presentation currency). |
| `source_partner_id`|`uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Trading entity initiating transaction. |
| `target_partner_id`|`uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Trading entity receiving transaction. |

---

### 3.6 Table Name: `public.consolidation_adjustments`
Topsides, audit adjustments, or manual journal entries recorded directly at the consolidation group level.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Linked consolidation run. |
| `adjustment_code` | `varchar(50)` | NO | None | None | Public | Standard string | Unique code (e.g., 'TOP-002'). |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Target chart of accounts ID. |
| `debit_amount` | `numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Topsides debit adjustment. |
| `credit_amount` | `numeric(18,2)`| NO | `0.00` | None | Financial | `>= 0.00` | Topsides credit adjustment. |
| `adjustment_type` | `varchar(30)` | NO | `'topside'` | Check Constraint | Public | `'topside'`, `'audit'`, `'tax_gaap'`, `'late_entry'` | Source category. |
| `explanation` | `text` | NO | None | None | Public | Standard string | Detailed reasoning for change. |
| `is_approved` | `boolean` | NO | `false` | None | Public | Valid boolean | Maker-checker approval status. |
| `approved_by` | `uuid` | YES | `NULL` | FK -> `users(id)` | Public | UUIDv4 | Approver profile ID. |

---

### 3.7 Table Name: `public.consolidated_financial_reports`
The final consolidated reporting snapshots used to generate financial statements.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent consolidation run. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| Public | UUIDv4 | Target fiscal period. |
| `account_id` | `uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Consolidated target account. |
| `base_actual_balance`|`numeric(18,2)`|NO | `0.00` | None | Financial | Standard numeric | Combined translated raw actual balance. |
| `eliminations_balance`|`numeric(18,2)`|NO| `0.00` | None | Financial | Standard numeric | Net eliminations applied. |
| `adjustments_balance`|`numeric(18,2)`|NO | `0.00` | None | Financial | Standard numeric | Net topside adjustments applied. |
| `final_consolidated_balance`|`numeric(18,2)`|NO| None | None | Financial | `base_actual_balance + eliminations_balance + adjustments_balance` | Final balance sheet/P&L output. |

---

### 3.8 Table Name: `public.consolidation_exchange_rates`
Frozen currency rates applied during a specific consolidation run.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent consolidation run. |
| `from_currency` | `varchar(3)` | NO | None | None | Public | ISO Code | Source currency (e.g. 'EUR'). |
| `to_currency` | `varchar(3)` | NO | None | None | Public | ISO Code | Target currency (e.g. 'USD'). |
| `rate_type` | `varchar(20)` | NO | `'closing'` | Check Constraint | Public | `'closing'`, `'average'`, `'historical'` | Rate classification type. |
| `conversion_rate` | `numeric(14,6)`| NO | None | `CHECK (conversion_rate > 0.000000)` | Public | Valid rate | Applied conversion rate. |

---

### 3.9 Table Name: `public.intercompany_balances`
Monitors and reconciles matched balances between trading entities in the group.

*   **Purpose**: Tracks matched balances between trading partners to support the elimination process.
*   **Ownership**: Corporate treasury.
*   **Lifecycle**: Active until reconciled.
*   **Retention**: 5 years.
*   **Optimistic Locking**: No.
*   **RLS**: Enabled; tenant-isolated.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `organization_id` | `uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Multi-tenant separator. |
| `accounting_period_id`|`uuid`| NO | None | FK -> `accounting_periods(id)`| Public | UUIDv4 | Target fiscal period. |
| `initiating_entity_id`|`uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Partner recording the transaction. |
| `receiving_entity_id` |`uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Partner receiving the transaction. |
| `receivable_account_id`|`uuid`| NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Debit GL account on initiating side. |
| `payable_account_id`|`uuid` | NO | None | FK -> `chart_of_accounts(id)`| Public | UUIDv4 | Credit GL account on receiving side. |
| `initiating_amount`|`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Value recorded by initiating entity. |
| `receiving_amount` |`numeric(18,2)`| NO | None | None | Financial | Standard numeric | Value recorded by receiving partner. |
| `difference_amount`|`numeric(18,2)`| NO | None | None | Financial | `initiating_amount - receiving_amount` | Discrepancy value. |

---

### 3.10 Table Name: `public.intercompany_reconciliations`
Logs resolutions for discrepancies between trading partners.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `intercompany_balance_id`|`uuid`| NO | None | FK -> `intercompany_balances(id)` ON DELETE CASCADE| Public | UUIDv4 | Target balance matched. |
| `reconciliation_method`|`varchar(50)`| NO | None | Check Constraint | Public | `'exchange_variance'`, `'timing_difference'`, `'write_off'`, `'manual_journal'` | Action taken to resolve. |
| `resolved_by` | `uuid` | NO | None | FK -> `users(id)` | Public | UUIDv4 | Executing accountant. |
| `resolution_date` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Date discrepancy resolved. |

---

### 3.11 Table Name: `public.intercompany_confirmations`
Maintains digital sign-offs for intercompany transaction balances.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `intercompany_balance_id`|`uuid`| NO | None | FK -> `intercompany_balances(id)` ON DELETE CASCADE| Public | UUIDv4 | Target balance matched. |
| `signoff_entity_id`|`uuid` | NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Confirming legal entity. |
| `is_confirmed` | `boolean` | NO | `false` | None | Public | Valid boolean | Status confirmation flag. |
| `confirmed_by_user`|`uuid` | YES | `NULL` | FK -> `users(id)` | Public | UUIDv4 | Confirming accountant. |

---

### 3.12 Table Name: `public.minority_interest_allocations`
Logs calculated profit and equity shares attributed to Non-Controlling Interests (NCI).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `run_id` | `uuid` | NO | None | FK -> `consolidation_runs(id)` ON DELETE CASCADE | Public | UUIDv4 | Parent consolidation run. |
| `subsidiary_entity_id`|`uuid`| NO | None | FK -> `organizations(id)` | Public | UUIDv4 | Subsidiary entity. |
| `nci_ownership_percentage`|`numeric(5,4)`|NO| None | `CHECK (nci_ownership_percentage > 0)` | Public | Standard percentage | External share (e.g., 0.3000). |
| `nci_share_equity` | `numeric(18,2)`| NO | None | None | Financial | Standard numeric | NCI allocation on balance sheet. |
| `nci_share_profit_loss`|`numeric(18,2)`|NO | None | None | Financial | Standard numeric | NCI allocation on income statement. |

---

### 3.13 Table Name: `public.consolidation_audit_logs`
Detailed system logs capturing actions, approvals, and signatures for consolidation runs.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Sensitivity | Validation / Rule | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | `PRIMARY KEY` | Public | UUIDv4 | Unique primary identifier. |
| `run_id` | `uuid` | YES | `NULL` | FK -> `consolidation_runs(id)` ON DELETE SET NULL | Public | UUIDv4 | Associated run. |
| `log_action` | `varchar(100)`| NO | None | None | Public | Standard action | Action (e.g., 'ELIMINATE_SALES').|
| `log_detail` | `text` | NO | None | None | Public | Standard string | Diagnostic log text. |
| `recorded_at` | `timestamp with time zone`| NO | `now()` | None | Public | Valid timestamp | Record timestamp. |

---

## SECTION 4: OWNERSHIP STRUCTURE MODULE

To manage multi-entity investments, the system models and processes transactions based on corporate ownership shares:

```
                          [OWNERSHIP MODELS]
                          
      [ Full Line-by-Line ]           [ Equity Method ]            [ Proportional Consolidation ]
      - 100% Wholly Owned             - Associates (20%-50%)       - Joint Ventures (IFRS 11)
      - Majority Owned (>50%)         - VIE / Minority Shares      - Shared Entities
```

1.  **100% Wholly Owned Subsidiaries**: The parent company incorporates all child balance sheet and income statement lines into the consolidated ledger.
2.  **Majority Owned Subsidiaries (> 50%)**: The parent company incorporates all child ledger lines at 100% value, subsequently calculating and posting a Non-Controlling Interest (NCI) offset to represent the minority shareholders' share of equity and profits.
3.  **Associates and Significant Influence (20% to 50%)**: Financial performance is calculated using the **Equity Method**. The parent company records its share of the associate's net income as a single asset adjustment on the balance sheet:
    $$\text{Investment Value}_{\text{Ending}} = \text{Investment Value}_{\text{Opening}} + (\text{Associate Net Income} \times \text{Ownership \%}) - \text{Dividends Received}$$
4.  **Joint Ventures (Shared Control)**: Managed using the **Equity Method** (IFRS 11) to record the parent's share of net assets on a single balance sheet line.
5.  **Variable Interest Entities (VIE)**: Consolidated based on operational control rather than equity shares, identifying the primary beneficiary using control metrics.
6.  **Nested & Indirect Ownership Structures**: Calculates the parent's effective control across nested holding chains (e.g., if Entity A owns 80% of Entity B, and Entity B owns 70% of Entity C, then Entity A owns an effective 56% share of Entity C):
    $$\text{Effective Share} = \text{Ownership Rate}_{\text{Link 1}} \times \text{Ownership Rate}_{\text{Link 2}}$$
7.  **Circular Ownership Blockers**: Checks membership updates to prevent circular ownership loops (e.g., Entity A owning Entity B, which owns Entity C, which owns Entity A), blocking loops before they post.

---

## SECTION 5: INTERCOMPANY ELIMINATION ENGINE

Intercompany transactions (such as cross-entity billing, internal loans, or transfer pricing) must be eliminated during consolidation to prevent double-counting.

```
                    [INTERCOMPANY ELIMINATION SEQUENCE]

  [ Germany GmbH (EUR) ]                                    [ France SAS (EUR) ]
  - Records $50,000 Sales                                   - Records $50,000 Purchase
  - Records $20,000 Receivable                              - Records $20,000 Payable
          │                                                         │
          └───────────────────────────┬─────────────────────────────┘
                                      │
                                      ▼
                        [ Intercompany Matcher ]
                        - Identifies matching $50,000 P&L Lines
                        - Identifies matching $20,000 Balance Sheet Lines
                                      │
                                      ▼
                        [ Elimination Entries Posted ]
                        - Debit intercompany revenue: $50,000
                        - Credit intercompany expense: $50,000
                        - Debit intercompany payable: $20,000
                        - Credit intercompany receivable: $20,000
```

### 5.1 Elimination Rules

*   **Intercompany Receivables & Payables**: Eliminates matching due-to and due-from balances. Discrepancies (such as timing differences or exchange rate variances) must be reconciled before the consolidation run completes.
*   **Intercompany Sales & Purchases**: Eliminates internal revenue and expenses, balancing the consolidated income statement.
*   **Intercompany Loans & Financing**: Eliminates internal loan balances and interest flows (interest income vs. interest expense).
*   **Intercompany Inventory Unrealized Profits**: Eliminates unrealized profit margins on goods transferred between entities that remain in inventory at the end of the period:
    $$\text{Elimination Amount} = \text{Ending Intercompany Inventory} \times \text{Transfer Markup \%}$$
*   **Intercompany Dividends**: Eliminates internal dividend distributions received from subsidiaries, preventing double-counting of group revenues.

---

## SECTION 6: CURRENCY TRANSLATION ENGINE (IAS 21 / ASC 830)

When consolidating multi-currency operations, the engine translates local currencies into the group presentation currency using rates from `public.consolidation_exchange_rates`.

### 6.1 Translation Methodologies

| Financial Statement Category | Translation Rate Applied | Regulatory Rule basis | Architectural Reason |
| :--- | :--- | :--- | :--- |
| **Balance Sheet: Assets & Liabilities**| **Closing Spot Rate** (At period end) | IAS 21.39(a) / ASC 830-30 | Reflects current exchange rates at the reporting date. |
| **Balance Sheet: Paid-In Capital** | **Historical Spot Rate** (At transaction date) | IAS 21.39(b) | Preserves original equity investment values. |
| **Income Statement: Revenues & Expenses**| **Average Period Rate** (Across fiscal period) | IAS 21.39(b) / ASC 830-30 | Approximates exchange rates across the operational period. |
| **Cash Flow Movements** | **Average Period Rate** (Or transaction date) | IAS 21.39 | Approximates cash flows across the operational period. |

---

### 6.2 Cumulative Translation Adjustment (CTA)

Applying different exchange rates across financial statements creates imbalances. The Consolidation Engine resolves these imbalances by calculating and posting a **Cumulative Translation Adjustment (CTA)** to **Other Comprehensive Income (OCI)** within the Equity section of the Balance Sheet:

$$\text{CTA} = \Delta \text{Assets}_{\text{Translated}} - \Delta \text{Liabilities}_{\text{Translated}} - \Delta \text{Equity}_{\text{Translated}} - \text{Net Income}_{\text{Translated}}$$

The calculated CTA is recorded as a separate entry in the equity section, ensuring the consolidated balance sheet balances.

---

## SECTION 7: THE CONSOLIDATION PIPELINE

The execution pipeline processes consolidations through a structured, multi-step workflow.

```
                            [CONSOLIDATION PIPELINE]
                            
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 1. Validate Scopes & Periods                                           │
   │    - Verifies ownership weights, periods, and exchange rates.          │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 2. Load Local Trial Balances                                           │
   │    - Retrieves local balances from operating databases.                │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 3. Currency Translation                                                │
   │    - Translates values into the presentation currency (Closing/Average).│
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 4. Intercompany Eliminations                                           │
   │    - Eliminates matching sales, purchases, and loan balances.          │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 5. Minority Interest (NCI) Allocations                                 │
   │    - Allocates minority interest shares of equity and profits.         │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 6. Balance Verification                                                │
   │    - Reconciles balances, calculates CTA, and logs snapshots.          │
   └────────────────────────────────────────────────────────────────────────┘
```

*   **Error Handling**: If a step fails (e.g., due to unbalanced intercompany transactions or missing exchange rates), the run is halted, marked `failed`, and logs diagnostic details to `public.consolidation_audit_logs`.

---

## SECTION 8: MINORITY INTEREST (NON-CONTROLLING INTEREST - NCI) ENGINE

When the parent company owns less than 100% of a consolidated subsidiary, the engine calculates the minority shareholders' share of equity and profits.

*   **Balance Sheet Allocation**: Computes the minority shareholders' share of the subsidiary's net assets, presenting it within the **Equity** section of the consolidated balance sheet:
    $$\text{NCI Balance Sheet Share} = \text{Subsidiary Net Assets} \times (1.0 - \text{Parent Ownership \%})$$
*   **Income Statement Allocation**: Computes the minority shareholders' share of the subsidiary's net income, presenting it as an allocation line on the consolidated income statement:
    $$\text{NCI Income Statement Share} = \text{Subsidiary Net Income} \times (1.0 - \text{Parent Ownership \%})$$
*   **Journal Generation**: The engine posts matching adjustments to log and trace minority interest allocations.

---

## SECTION 9: CONSOLIDATION ADJUSTMENTS & TOPSIDES

To address adjustments required only at the group level, the system supports posting topside adjustments directly to the consolidation ledger.

*   **Topside Journals**: Adjustments posted to `public.consolidation_adjustments` during consolidation that do not write back to local subsidiary ledgers.
*   **GAAP/IFRS Reconciliations**: Adjustments that address differences in accounting standards between subsidiary filings and group presentation requirements (e.g., translating local lease classifications).
*   **Maker-Checker Controls**: Topside journals must be prepared by an accountant and reviewed/approved by a financial controller before posting, creating an auditable record of adjustments.

---

## SECTION 10: CONSOLIDATED REPORTING OUTPUTS

The Consolidation Engine generates five standard consolidated financial statements, optimized using reporting caches:

1.  **Consolidated Trial Balance**: AggregatesTranslated local balances, adjustments, and eliminations by account code.
2.  **Consolidated Balance Sheet**: Displays corporate group assets, liabilities, and equity (including NCI and CTA lines).
3.  **Consolidated Income Statement**: Summarizes group sales, cost of goods sold, operating expenses, and tax lines.
4.  **Consolidated Cash Flow**: Reconciles cash flows across operating, investing, and financing activities for the entire group.
5.  **Consolidated Statement of Changes in Equity**: Tracks equity changes, including paid-in capital, retained earnings, NCI, and CTA lines.

---

## SECTION 11: ROLL-BASED SECURITY & ACCESS RULES

To protect sensitive corporate data and satisfy compliance audits, the Consolidation Engine enforces rigorous security controls.

*   **Legal Entity Visibility**: Restricts users' visibility to authorized legal entities or geographic divisions based on their business unit.
*   **Consolidation Operations Role**: Only authorized controllers and regional directors can trigger consolidation runs or post topside adjustments.
*   **Auditor Access**: Provides external auditors with read-only access to consolidation records, including source balances, exchange rates, and elimination logs.
*   **Audit Logging (SOC2 Compliance)**: Every operational action (including starting runs, approving adjustments, or exporting statements) generates an entry in `public.consolidation_audit_logs` that records the user, timestamp, IP address, and changed values.

---

## SECTION 12: PERFORMANCE ENGINEERING & LARGE SCALING

To scale reporting capabilities across high transaction volumes and complex enterprise models, the engine utilizes targeted performance designs.

```sql
-- 1. Materialized view caching consolidated balances by period and account
CREATE MATERIALIZED VIEW public.mv_consolidated_ledger_summary AS
SELECT 
    run_id,
    accounting_period_id,
    account_id,
    COALESCE(SUM(base_actual_balance), 0.00) AS total_base,
    COALESCE(SUM(eliminations_balance), 0.00) AS total_eliminations,
    COALESCE(SUM(adjustments_balance), 0.00) AS total_adjustments,
    COALESCE(SUM(final_consolidated_balance), 0.00) AS total_consolidated
FROM public.consolidated_financial_reports
GROUP BY run_id, accounting_period_id, account_id;

-- Create unique index to support concurrent refreshes
CREATE UNIQUE INDEX mv_consolidated_ledger_summary_uid 
  ON public.mv_consolidated_ledger_summary(run_id, accounting_period_id, account_id);
```

*   **Database Partitioning**: Tables are partitioned by `run_id` or `organization_id`, allowing the query planner to bypass unrelated tenant data and accelerate consolidation times.
*   **Parallel Currency Translation**: The engine translates local balances across multiple subsidiaries concurrently, reducing pipeline processing bottlenecks.

---

## SECTION 13: CONSOLIDATION AUDIT & DISCOVERY LOGS

To support regulatory audits, every consolidation run freezes its configuration to maintain a permanent record of the run context:

*   **Exchange Rates Log**: Captures the exact exchange rates used to translate subsidiary balances.
*   **Ownership Rates Log**: Records the effective ownership percentages and equity weights used.
*   **Source Report Verification Hash**: Generates SHA-256 hashes for all source snapshots, ensuring that subsequent adjustments to subsidiary databases do not alter historical consolidation records.
*   **Auditor Adjustment Ledger**: Stores a distinct ledger of all topside and auditor adjustments applied during consolidation.

---

## SECTION 14: REAL-TIME CONSOLIDATION EVENTS

The Consolidation Engine is fully event-driven, emitting structured events to coordinate processing across downstream systems.

### 14.1 System Events

#### `consolidation.completed`
Emitted immediately upon successfully compiling and locking a consolidated financial snapshot.

```json
{
  "event_id": "evt_con_01A9600101",
  "event_type": "consolidation.completed",
  "organization_id": "org_771829",
  "correlation_id": "corr_con_2291",
  "payload": {
    "run_id": "run_449102",
    "group_id": "grp_110293",
    "accounting_period_id": "per_11029",
    "consolidated_by_user": "usr_3391",
    "calculated_cta_amount": -14290.00,
    "has_unbalanced_reconciliations": false,
    "signature_hash": "sha256_ab1122...ef"
  },
  "timestamp": "2026-06-28T23:45:00Z"
}
```

#### `elimination.generated`
Emitted immediately upon identifying and posting intercompany elimination entries.

```json
{
  "event_id": "evt_con_01A9600155",
  "event_type": "elimination.generated",
  "organization_id": "org_771829",
  "correlation_id": "corr_con_2291",
  "payload": {
    "run_id": "run_449102",
    "elimination_entry_id": "elim_99210",
    "elimination_type": "receivable_payable",
    "source_partner_id": "org_de_910",
    "target_partner_id": "org_fr_911",
    "eliminated_amount": 20000.00
  },
  "timestamp": "2026-06-28T23:40:00Z"
}
```

---

## SECTION 15: PRODUCTION CONSOLIDATION VALIDATION MATRIX

Before deploying the Financial Consolidation & Multi-Entity Reporting Engine to production, verify that the following configurations and controls are in place.

- [ ] **Ownership Integrity Verified**: Ownership matrices are configured, with nested chains calculating correct effective control percentages.
- [ ] **Locking Rules Enforced**: Completed consolidation runs and snapshots are verified as read-only, blocking direct line updates or deletions.
- [ ] **Eliminations Balanced**: Intercompany elimination checks verify that debits equal credits for all posted elimination entries.
- [ ] **Currency Rates Confirmed**: Validation checks verify that the Closing Spot Rate is applied to assets and the Average Period Rate is applied to P&L items.
- [ ] **Topsides Checked**: All topside adjustments are routed through the maker-checker review pipeline before posting.
- [ ] **Reopened Period Protection Active**: Consolidation runs are blocked for closed accounting periods unless the period is formally re-opened by an authorized controller.
- [ ] **CTA Calculation Active**: Cumulative Translation Adjustment calculations are active, balancing the consolidated balance sheet.
- [ ] **Audit Trail Capture Active**: System logs record the configuration, exchange rates, and ownership rates used for all consolidation runs.

---
**End of Specification.**
