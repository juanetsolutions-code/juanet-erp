# JUANET ERP Finance Integration and Event Contracts Manual
## Phase 2.3.2E.5 — Finance Integration & Event-Driven Contracts Specification
**Document Version:** 1.0  
**Author:** Chief ERP Integration Architect, JUANET Enterprise SaaS Platform  
**Classification:** Technical Integration Architecture / Event Contracts, Transactional Outbox & Idempotency Specifications  

---

## SECTION 1: ARCHITECTURAL PHILOSOPHY & INTEGRATION PRINCIPLES

In a highly distributed, multi-tenant enterprise ERP, the **Finance Domain** is the ultimate **System of Record (SoR)**. All other domains (CRM, Projects, Support, Subscriptions, Marketplace, and External Gateways) represent operational workflows that eventually result in a financial transaction. 

To maintain strict financial integrity, prevent data corruption, and ensure audits are reproducible and mathematically precise, the Finance Domain is isolated from other domains. This isolation is governed by nine core architectural tenets:

```
                  [EVENT-DRIVEN FINANCIAL ISOLATION]
                  
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Operational Domains                          │
  │    (CRM, Projects, Support, Marketplace, Subscriptions, etc.)          │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ Emits Business Event
                                      ▼
  ┌────────────────────────────────────────────────────────────────────────┐
  │                      Transactional Outbox Pipeline                     │
  │     (Guarantees exactly-once/at-least-once delivery with order)        │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │
                                      ▼
  ┌────────────────────────────────────────────────────────────────────────┐
  │                            Finance Domain                              │
  │   - Local Idempotency Guard (Dedup)                                    │
  │   - Subledger Draft Ledger Entries                                     │
  │   - Immutable General Ledger (Final Postings)                          │
  └────────────────────────────────────────────────────────────────────────┘
```

1.  **Finance as the System of Record (SoR)**: The Finance General Ledger (`public.ledger_entries` and related subledgers) is the authoritative, final source of truth for all corporate balances, transactions, and compliance reporting. No other database, cache, or operational store can override ledger balances.
2.  **No Direct Writes to GL**: Operational domains (e.g., Projects, CRM, Support) are **strictly forbidden** from writing, updating, or deleting rows directly within the General Ledger or Invoice tables. Operational systems must interact with the Finance Domain exclusively by emitting business events or calling protected, idempotent internal APIs.
3.  **Event-Sourced Financial Mutation**: Every mutation of financial state (e.g., invoice generation, cash allocation, currency hedging, depreciation) must originate from a verified business event or a formal user approval workflow. Direct database adjustments are forbidden.
4.  **Immutability of Financial Events**: Once a financial event is written to the message broker or the database outbox, it becomes immutable. If a correction is needed, a separate reversing or adjusting event must be emitted. Under no circumstances is an event edited or deleted in place.
5.  **Transactional Outbox Pattern**: To prevent dual-write anomalies (where a database commit succeeds but the event broker publication fails, or vice versa), all domain state changes and their corresponding event messages must be committed atomically within the same database transaction. Events are written to a physical `outbox_events` table before being dispatched to the event broker by a background publisher.
6.  **Idempotent Consumer Guarantees**: All downstream financial message handlers must be idempotent. If a consumer receives a message multiple times (due to network retries, broker duplicate delivery, or operator-initiated replay), it must process the message exactly once, producing the identical side-effects without duplicate ledger postings.
7.  **Deterministic Event Ordering**: Events affecting a single bank account, ledger account, or invoice must be processed in the strict sequential order in which they occurred. The architecture enforces event ordering at the producer and broker levels by utilizing deterministic partitioning keys (e.g., `tenant_id` combined with `account_id`).
8.  **Event Replay Support**: The integration platform must support replaying historical event logs from a cold-start to rebuild subledger state or recalculate financial models (e.g., re-running cash forecast models with modified historic scenarios), without modifying production general ledger records.
9.  **Fault Isolation and Resilience**: Operational failures (e.g., database lockups in CRM, high latency in Payment Gateways, or missing metadata in Project Tasks) must never block or corrupt critical ledger posting processes. If an operational message contains invalid metadata, it is routed to a Dead-Letter Queue (DLQ) for manual correction while the main financial pipeline continues running.

---

## SECTION 2: INTEGRATION ARCHITECTURE

The diagram below outlines how the core Finance subsystem coordinates state across all auxiliary platform domains:

```
                            ┌─────────────────┐
                            │   CRM (Sales)   │
                            └────────┬────────┘
                                     │ (Won Deal / Contract)
                                     ▼
                            ┌─────────────────┐
                            │    Finance      │
                            │   Subsystem     │
                            └────┬──────┬─────┘
                                 │      │
          ┌──────────────────────┘      └──────────────────────┐
          ▼ (Project Costs / Timesheets)                       ▼ (Subscriptions)
┌───────────────────┐                                ┌───────────────────┐
│     Projects      │                                │  Subscriptions /  │
└───────────────────┘                                │   Billing / Pay   │
                                                     └───────────────────┘
```

The interaction model for each auxiliary domain is defined below:

*   **CRM (Sales)**: Handles corporate deals, pipelines, and customer accounts. Upon reaching a `"closed_won"` state, the CRM emits a `deal.closed_won` event. Finance consumes this event to create a customer billing profile, register credit terms, and generate draft sales invoices.
*   **Projects**: Tracks delivery, project milestones, timesheets, and contractor expenses. Projects emits `milestone.completed` or `timesheet.approved` events. Finance processes these to calculate billable milestones and generate draft professional services invoices.
*   **Support**: Manages billing disputes, customer returns, service level agreement (SLA) violations, and credit requests. Support emits `refund_request.approved` events. Finance consumes these to issue formal credit notes, update accounts receivable, and schedule disbursements.
*   **Marketplace**: Governs multi-vendor platform sales. Emits `order.placed` and `order.settled` events. Finance processes these to manage split payments, allocate marketplace commissions to the corporate revenue ledger, and create payables for vendors.
*   **CMS (Content Management)**: Provides customer and tenant profiles, tax document portals, and localization settings. CMS emits `tenant.localization_updated` events (e.g., VAT number registration, changes in corporate tax jurisdiction). Finance consumes these to update tax engines and tax rates.
*   **Automation**: Coordinates scheduled workflows, automated sweeps, and automated reconciliation rules. Emits system triggers that instruct Finance to execute automated processes, such as matching cash records or triggering cash sweeps in the background.
*   **AI Engine**: Provides anomaly detection, predictive cash forecasting, and translation layers for audit logs. The AI Engine reads read-only data replicas of ledger histories to construct models, generating insights without modifying core transactional databases.
*   **Notifications**: Directs system alerts, payment reminders, overdue invoices, and workflow approvals to users. Finance emits notifications events (such as `invoice.overdue` or `covenant.breach`), which are picked up by the Notifications domain to dispatch emails, SMS, or Slack alerts.
*   **Authentication (Auth)**: Manages users, user groups, and Role-Based Access Control (RBAC). Emits updates on user access rights, which Finance consumes to enforce Maker-Checker limits and audit roles.
*   **Billing & Subscriptions**: Governs automated contract renewals, recurring SaaS plans, and customer usage tracking. Emits periodic subscription invoices. Finance converts these subscription records into formal ledger invoices and balances.
*   **Payments (Payment Gateway)**: Manages third-party gateway interactions (e.g., Stripe, Adyen). Emits payment success or dispute events. Finance reconciles these cash clearances, separating transaction fees from gross sales receipts.
*   **Webhooks**: Dispatches outward real-time event updates to customer-configured URLs. Finance pushes events to the Webhook worker queue, ensuring business systems are updated of payment clearances.
*   **Audit Engine**: Maintains global security logging. Finance routes critical audit lines, such as dual-authorization approvals, to the central immutable Audit log.
*   **Reporting**: Collects high-volume financial data to render analytics, income statements, and balance sheets. Consumes real-time events to update analytical databases and Materialized Views.

---

## SECTION 3: EVENT CATEGORIES & NAMESPACES

To avoid event collisions and establish clear domain boundaries, all integration events must follow a hierarchical, dot-separated naming convention:

$$\text{Namespace} = \langle\text{domain}\rangle.\langle\text{entity}\rangle.\langle\text{action}\rangle\_\text{v}\langle\text{version}\rangle$$

| Namespace Prefix | Primary Responsibility | Example Business Event |
| :--- | :--- | :--- |
| `invoice.*` | Sales and purchase invoices, debit/credit notes. | `invoice.issued_v1` |
| `payment.*` | Payment collections, clearances, allocations, refunds. | `payment.cleared_v1` |
| `journal.*` | Double-entry journal vouchers, adjustments. | `journal.posted_v1` |
| `ledger.*` | General Ledger actual balances, account adjustments. | `ledger.balance_updated_v1` |
| `receivable.*`| Aging metrics, debtor collections, credit-hold flags. | `receivable.aged_v1` |
| `payable.*` | Vendor bills, payment runs, creditor updates. | `payable.disbursement_scheduled_v1` |
| `bank.*` | Bank statements, transaction matching, reconciliations.| `bank.statement_imported_v1` |
| `treasury.*` | Liquidity concentration, physical/notional sweeps. | `treasury.sweep_completed_v1` |
| `budget.*` | Budget limits, approvals, actuals-vs-budget alerts.| `budget.limit_warned_v1` |
| `forecast.*` | Cash forecasting snapshots, version lockouts. | `forecast.version_finalized_v1` |
| `report.*` | Balance Sheet, Income Statement, tax reports. | `report.consolidation_compiled_v1` |
| `subscription.*`| Contract renewals, monthly recurring revenue changes. | `subscription.billing_cycle_run_v1` |
| `tax.*` | Sales tax, GST, VAT calculations, audit reports. | `tax.filing_compiled_v1` |
| `currency.*` | Spot rate adjustments, currency hedging. | `currency.rate_adjusted_v1` |
| `organization.*`| Corporate subsidiary creation, legal mergers. | `organization.subsidiary_linked_v1` |
| `user.*` | User permissions, Maker-Checker authorization level. | `user.approval_limit_assigned_v1` |
| `workflow.*` | Workflow steps, approvals, stage adjustments. | `workflow.stage_escalated_v1` |
| `approval.*` | Four-eyes signatures, CFO overrides. | `approval.signature_applied_v1` |

---

## SECTION 4: EVENT CONTRACTS

This section details the formal, production-ready schemas and event contracts for core finance integration events.

### 4.1 Event Contract: `invoice.issued_v1`

*   **Producer**: Finance Sub-System (Accounts Receivable Module).
*   **Consumers**: CRM (Sales), Projects, Tax Engine, Notifications (Customer Alerts), Reporting (Materialized Views).
*   **Trigger Condition**: A draft invoice is reviewed, tax is calculated, and the Treasury Controller issues the invoice.
*   **Expected Retries**: 5 attempts with exponential backoff.
*   **Failure Behavior**: Retry until exhausted, then route to the Dead-Letter Queue (DLQ) and flag the outbox event as `'failed'`.
*   **Replay Behavior**: Safe to replay; downstream consumers must check for invoice deduplication using the `invoice_id` identifier.

#### Event Schema:
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "InvoiceIssuedV1",
  "type": "OBJECT",
  "required": [
    "event_id",
    "event_type",
    "version",
    "timestamp",
    "tenant_id",
    "correlation_id",
    "causation_id",
    "idempotency_key",
    "payload"
  ],
  "properties": {
    "event_id": { "type": "STRING", "format": "uuid" },
    "event_type": { "type": "STRING", "const": "invoice.issued_v1" },
    "version": { "type": "INTEGER", "const": 1 },
    "timestamp": { "type": "STRING", "format": "date-time" },
    "tenant_id": { "type": "STRING", "format": "uuid" },
    "correlation_id": { "type": "STRING", "format": "uuid" },
    "causation_id": { "type": "STRING", "format": "uuid" },
    "idempotency_key": { "type": "STRING" },
    "payload": {
      "type": "OBJECT",
      "required": [
        "invoice_id",
        "invoice_number",
        "subsidiary_id",
        "customer_id",
        "issue_date",
        "due_date",
        "currency",
        "subtotal_amount",
        "tax_amount",
        "total_amount",
        "line_items"
      ],
      "properties": {
        "invoice_id": { "type": "STRING", "format": "uuid" },
        "invoice_number": { "type": "STRING" },
        "subsidiary_id": { "type": "STRING", "format": "uuid" },
        "customer_id": { "type": "STRING", "format": "uuid" },
        "issue_date": { "type": "STRING", "format": "date" },
        "due_date": { "type": "STRING", "format": "date" },
        "currency": { "type": "STRING", "minLength": 3, "maxLength": 3 },
        "subtotal_amount": { "type": "NUMBER", "minimum": 0.0 },
        "tax_amount": { "type": "NUMBER", "minimum": 0.0 },
        "total_amount": { "type": "NUMBER", "minimum": 0.0 },
        "line_items": {
          "type": "ARRAY",
          "items": {
            "type": "OBJECT",
            "required": [
              "line_id",
              "product_id",
              "quantity",
              "unit_price",
              "net_amount",
              "tax_code_id",
              "tax_amount"
            ],
            "properties": {
              "line_id": { "type": "STRING", "format": "uuid" },
              "product_id": { "type": "STRING", "format": "uuid" },
              "quantity": { "type": "NUMBER", "minimum": 0.001 },
              "unit_price": { "type": "NUMBER", "minimum": 0.0 },
              "net_amount": { "type": "NUMBER", "minimum": 0.0 },
              "tax_code_id": { "type": "STRING", "format": "uuid" },
              "tax_amount": { "type": "NUMBER", "minimum": 0.0 }
            }
          }
        }
      }
    }
  }
}
```

---

### 4.2 Event Contract: `payment.cleared_v1`

*   **Producer**: Payment Gateway Integration / Bank Reconciliation Module.
*   **Consumers**: Accounts Receivable Ledger, CRM (Updates Customer credit balance), Subscriptions (Updates active plan status), General Ledger (Cash posting), Cash Forecasting (Updates 13-week model).
*   **Trigger Condition**: The payment gateway completes a settlement transaction or a bank transfer matches an open invoice in a manual or automated reconciliation cycle.
*   **Expected Retries**: 5 attempts with exponential backoff.
*   **Failure Behavior**: Retry until exhausted, then route to the Dead-Letter Queue (DLQ) and flag the outbox event as `'failed'`.
*   **Replay Behavior**: Safe to replay; downstream consumers must check for payment deduplication using the `payment_id` identifier.

#### Event Schema:
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "PaymentClearedV1",
  "type": "OBJECT",
  "required": [
    "event_id",
    "event_type",
    "version",
    "timestamp",
    "tenant_id",
    "correlation_id",
    "causation_id",
    "idempotency_key",
    "payload"
  ],
  "properties": {
    "event_id": { "type": "STRING", "format": "uuid" },
    "event_type": { "type": "STRING", "const": "payment.cleared_v1" },
    "version": { "type": "INTEGER", "const": 1 },
    "timestamp": { "type": "STRING", "format": "date-time" },
    "tenant_id": { "type": "STRING", "format": "uuid" },
    "correlation_id": { "type": "STRING", "format": "uuid" },
    "causation_id": { "type": "STRING", "format": "uuid" },
    "idempotency_key": { "type": "STRING" },
    "payload": {
      "type": "OBJECT",
      "required": [
        "payment_id",
        "transaction_reference",
        "cleared_amount",
        "processing_fee",
        "currency",
        "clearing_bank_account_id",
        "payment_date",
        "allocated_invoices"
      ],
      "properties": {
        "payment_id": { "type": "STRING", "format": "uuid" },
        "transaction_reference": { "type": "STRING" },
        "cleared_amount": { "type": "NUMBER", "minimum": 0.01 },
        "processing_fee": { "type": "NUMBER", "minimum": 0.0 },
        "currency": { "type": "STRING", "minLength": 3, "maxLength": 3 },
        "clearing_bank_account_id": { "type": "STRING", "format": "uuid" },
        "payment_date": { "type": "STRING", "format": "date-time" },
        "allocated_invoices": {
          "type": "ARRAY",
          "items": {
            "type": "OBJECT",
            "required": ["invoice_id", "allocated_amount"],
            "properties": {
              "invoice_id": { "type": "STRING", "format": "uuid" },
              "allocated_amount": { "type": "NUMBER", "minimum": 0.01 }
            }
          }
        }
      }
    }
  }
}
```

---

### 4.3 Event Contract: `journal.posted_v1`

*   **Producer**: Core General Ledger Subsystem.
*   **Consumers**: Reporting Engine, Consolidation Subsystem, Auditor Logs.
*   **Trigger Condition**: A multi-line double-entry journal is balanced and formally posted to the ledger, modifying the actual ledger balance.
*   **Expected Retries**: 3 attempts with progressive delay.
*   **Failure Behavior**: Alert on-duty systems administrators immediately. Journal postings cannot fail silently or go unprocessed.
*   **Replay Behavior**: Safe to replay; ledger tracking engines must prevent double postings by checking for duplicate `journal_header_id` values.

#### Event Schema:
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "JournalPostedV1",
  "type": "OBJECT",
  "required": [
    "event_id",
    "event_type",
    "version",
    "timestamp",
    "tenant_id",
    "correlation_id",
    "causation_id",
    "idempotency_key",
    "payload"
  ],
  "properties": {
    "event_id": { "type": "STRING", "format": "uuid" },
    "event_type": { "type": "STRING", "const": "journal.posted_v1" },
    "version": { "type": "INTEGER", "const": 1 },
    "timestamp": { "type": "STRING", "format-time": "date-time" },
    "tenant_id": { "type": "STRING", "format": "uuid" },
    "correlation_id": { "type": "STRING", "format": "uuid" },
    "causation_id": { "type": "STRING", "format": "uuid" },
    "idempotency_key": { "type": "STRING" },
    "payload": {
      "type": "OBJECT",
      "required": [
        "journal_header_id",
        "subsidiary_id",
        "posting_date",
        "fiscal_year",
        "fiscal_period",
        "total_debit",
        "total_credit",
        "lines"
      ],
      "properties": {
        "journal_header_id": { "type": "STRING", "format": "uuid" },
        "subsidiary_id": { "type": "STRING", "format": "uuid" },
        "posting_date": { "type": "STRING", "format": "date" },
        "fiscal_year": { "type": "INTEGER", "minimum": 2000 },
        "fiscal_period": { "type": "INTEGER", "minimum": 1, "maximum": 12 },
        "total_debit": { "type": "NUMBER", "minimum": 0.0 },
        "total_credit": { "type": "NUMBER", "minimum": 0.0 },
        "lines": {
          "type": "ARRAY",
          "items": {
            "type": "OBJECT",
            "required": [
              "line_id",
              "chart_of_accounts_id",
              "gl_account_number",
              "debit_amount",
              "credit_amount",
              "currency"
            ],
            "properties": {
              "line_id": { "type": "STRING", "format": "uuid" },
              "chart_of_accounts_id": { "type": "STRING", "format": "uuid" },
              "gl_account_number": { "type": "STRING" },
              "debit_amount": { "type": "NUMBER", "minimum": 0.0 },
              "credit_amount": { "type": "NUMBER", "minimum": 0.0 },
              "currency": { "type": "STRING", "minLength": 3, "maxLength": 3 }
            }
          }
        }
      }
    }
  }
}
```

---

## SECTION 5: DOMAIN INTEGRATION SPECIFICATIONS

The integration architecture governs specific, transactional paths between domains, maintaining clean physical boundaries.

```
+─────────────────────────+                         +─────────────────────────+
│   Operational Domain    │                         │     Finance Domain      │
│                         │                         │                         │
│  1. Business Event      ├────────────────────────►│  2. Local Idempotency   │
│     (Outbox Pattern)    │                         │     Verification Guard  │
│                         │                         │                         │
│  4. Process Result      │◄────────────────────────┤  3. Database Subledger  │
│     Notification        │                         │     Record Execution    │
+─────────────────────────+                         +─────────────────────────+
```

### 5.1 CRM → Finance
When a corporate deal moves to a completed state, CRM writes a transaction to its database outbox, emitting the `deal.closed_won` event:
*   **Payload Context**: Includes the customer's legal corporate details, contract valuation, terms, billing contact, and payment cycles.
*   **Process**: Finance captures the event, creates a profile in `public.customers`, assigns credit limits, and processes the first contract invoice in draft mode.

### 5.2 Projects → Finance
Upon milestone completion or timesheet approval, the Projects domain commits the record and triggers a `projects.timesheet_approved_v1` or `projects.milestone_completed_v1` outbox event:
*   **Payload Context**: Details project ID, contract rules, task items, approved timesheet hours, billing rates, and manager signatures.
*   **Process**: Finance captures the event, validates the billable hours against contract parameters, and issues a sales invoice linked to the customer's accounts receivable ledger.

### 5.3 Marketplace → Finance
When a platform consumer places an order, the Marketplace domain records the transaction and writes the `marketplace.order_placed_v1` event:
*   **Payload Context**: Aggregates gross order value, items purchased, commission structures, vendor IDs, and tax breakdowns.
*   **Process**: Finance processes the event to generate split postings. It records the marketplace platform commission as revenue and assigns the remaining balance as a payable liability within the respective vendor's subledger.

### 5.4 Support → Finance
If a support customer is granted a refund or discount, Support logs the transaction and writes the `support.refund_approved_v1` outbox event:
*   **Payload Context**: Details customer ID, original invoice reference, authorized refund value, dispute description, and manager approval metadata.
*   **Process**: Finance consumes the event, creates an approved credit note, updates the accounts receivable ledger, and schedules a payment.

### 5.5 Subscriptions → Finance
When a subscription billing cycle runs, the Billing engine commits the renewals and emits `subscription.cycle_completed_v1` events:
*   **Payload Context**: Details plan codes, subscription identifiers, usage metrics, recurring amounts, and tax calculations.
*   **Process**: Finance receives the event to issue invoices and record accrued or deferred revenue balances, aligning subscription revenue with accounting periods.

### 5.6 Payment Gateway → Finance
Upon successful payment settlement, the gateway integration logs the record and writes the `gateway.payment_cleared_v1` outbox event:
*   **Payload Context**: Captures checkout session IDs, processing fee breakdowns, gateway transaction references, and settlement currencies.
*   **Process**: Finance consumes the event to post a cleared payment to the cash ledger. It separates the processing fees, records them as an administrative expense, and reconciles the payment against outstanding accounts receivable invoices.

### 5.7 Banking → Finance
When standard bank statements (e.g., MT940, CAMT.053) are imported, the Banking module processes them and emits `bank.statement_imported_v1` outbox events:
*   **Payload Context**: Lists bank account routing numbers, transaction entries, value dates, and remitter references.
*   **Process**: Finance matches these bank transactions against open accounts receivable invoices and general ledger cash ledger accounts.

### 5.8 Treasury → Finance
When liquidity concentration transfers are initiated, Treasury writes a record and emits the `treasury.sweep_executed_v1` event:
*   **Payload Context**: Details physical sweep paths, source and target bank account numbers, transferred values, and pool parameters.
*   **Process**: Finance updates the treasury balance positions and records the transfer in the cash subledger, ensuring cash balances remain accurate.

### 5.9 AI Usage Billing → Finance
If corporate customers are billed based on AI usage (e.g., token consumption, model compute hours), the telemetry subsystem writes usage records and emits the `ai_usage.metric_recorded_v1` event:
*   **Payload Context**: Details tenant ID, API key reference, model ID, resource metrics, and rate factors.
*   **Process**: Finance collects these usage events to calculate dynamic monthly billing adjustments, generating metered usage lines on active invoices.

### 5.10 Automation → Finance
If automated systems process scheduled actions (e.g., recurring depreciation calculations, automated debt amortization), the scheduling subsystem writes records and emits the `automation.trigger_fired_v1` event:
*   **Payload Context**: Details task classifications, parameters, schedules, and run identifiers.
*   **Process**: Finance consumes the event to execute financial computations, posting adjusting journal lines to ledger entries automatically.

### 5.11 Notifications → Finance
When critical financial thresholds are breached, Finance emits alerting outbox events such as `finance.limit_exceeded_v1` or `finance.covenant_breached_v1`:
*   **Payload Context**: Contains tenant details, covenant parameters, measured indicators, thresholds, and breach values.
*   **Process**: The central Notifications domain consumes these events to dispatch targeted notifications (e.g., Slack messages or emails to the CFO) automatically.

### 5.12 Reporting → Finance
To maintain up-to-date reports, the Reporting engine consumes all core transactional events (`invoice.issued_v1`, `payment.cleared_v1`, `journal.posted_v1`):
*   **Payload Context**: Read-only copies of active transaction data and metadata.
*   **Process**: The Reporting engine updates reporting models and Materialized Views, keeping financial dashboards accurate and current.

---

## SECTION 6: TRANSACTIONAL OUTBOX ENGINE

To prevent dual-write anomalies across distributed services, all systems must adopt the **Transactional Outbox Pattern**. Under this pattern, database writes and their associated outbound messages are committed atomically within the same database transaction.

```
 [ BUSINESS TRANSACTION PIPELINE ]
 
 ┌────────────────────────────────────────────────────────┐
 │ 1. Start Database Transaction (SERIALIZABLE)           │
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 2. Mutate Local Business Entity Tables                 │
 │    - Insert record into public.invoices                │
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 3. Write Outbox Record atomically                      │
 │    - Insert event payload into public.outbox_events    │
 └───────────────────────────┬────────────────────────────┘
                             │
                             ▼
 ┌────────────────────────────────────────────────────────┐
 │ 4. Commit Database Transaction                         │
 └────────────────────────────────────────────────────────┘
```

### 6.1 Schema: `public.outbox_events`

```sql
-- PostgreSQL 16 Target Compliant Outbox Schema
CREATE TABLE public.outbox_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    correlation_id UUID NOT NULL,
    causation_id UUID NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending' 
        CONSTRAINT chk_outbox_status CHECK (status IN ('pending', 'processing', 'published', 'failed')),
    retry_count INTEGER NOT NULL DEFAULT 0 CONSTRAINT chk_outbox_retries CHECK (retry_count >= 0),
    error_log TEXT,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
) PARTITION BY RANGE (created_at);

-- Generate Index and Unique Constraints inside individual Partition targets
CREATE UNIQUE INDEX uq_outbox_id_date ON public.outbox_events (id, created_at);
CREATE INDEX idx_outbox_status_publish ON public.outbox_events (status, created_at) WHERE status = 'pending';
```

### 6.2 Publishing Workflow & Delivery Guarantees
1.  **Publishing Process**: A background worker queries the active partition for events where `status = 'pending'`, sorting by `created_at` to preserve ordering.
2.  **Message Dispatch**: The worker publishes these messages to the event broker (e.g., Apache Kafka, RabbitMQ, Google Cloud Pub/Sub).
3.  **Status Confirmation**: Upon receiving a broker acknowledgment, the worker marks the outbox status as `'published'`. If the publish step fails, the worker logs the error, increments the retry counter, and schedules a retry using an exponential backoff algorithm.
4.  **Delivery Guarantee**: This outbox workflow guarantees **at-least-once delivery**. Downstream consumers are responsible for verifying idempotency keys to prevent duplicate transactions.
5.  **Poison-Pill Handling**: If an event fails to publish after 5 attempts, the publisher marks its status as `'failed'`, logs the error detail, and routes the message to the Dead-Letter Queue (DLQ). This ensures invalid payloads do not block the active outbox pipeline.

---

## SECTION 7: IDEMPOTENCY STANDARDS

Because the Transactional Outbox pattern guarantees **at-least-once delivery**, consumers may receive duplicate event messages. To prevent duplicate database writes or ledger postings, consumers must process events using an **Idempotence Guard Engine**.

```
                           [IDEMPOTENT EVENT CONSUMPTION]
                           
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 1. Message Received by Consumer                                        │
   │    - Inspect incoming header for payload.idempotency_key.              │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ 2. Insert Idempotency Key in Database                                  │
   │    - INSERT INTO public.idempotent_consumers (idempotency_key, status) │
   │      ON CONFLICT DO NOTHING;                                           │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                 ┌─────────────────────┴─────────────────────┐
                 │                                           │
                 ▼ Row Inserted Successfully                 ▼ INSERT Failed (Duplicate Key)
   ┌──────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
   │ 3. Execute Transaction & Mutate Database │  │ 3. Skip Execution                        │
   │    - Apply changes (e.g., post ledger)   │  │    - Retrieve cached process result.     │
   │    - Mark idempotency key status 'done'. │  │    - Return success status to broker.    │
   └──────────────────────────────────────────┘  └──────────────────────────────────────────┘
```

### 7.1 Schema: `public.idempotent_consumers`

```sql
-- PostgreSQL 16 Target Compliant Idempotent Tracker Schema
CREATE TABLE public.idempotent_consumers (
    idempotency_key VARCHAR(255) NOT NULL,
    organization_id UUID NOT NULL,
    consumer_name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'running'
        CONSTRAINT chk_idempotent_status CHECK (status IN ('running', 'done', 'failed')),
    response_payload JSONB,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
    completed_at TIMESTAMP WITH TIME ZONE,
    PRIMARY KEY (idempotency_key, organization_id)
);

CREATE INDEX idx_idempotent_cleanup ON public.idempotent_consumers (created_at) WHERE status = 'done';
```

### 7.2 deduplication Safeguards
*   **Ledger Postings**: Every journal entry transaction uses the unique combination of `journal_header_id` and `organization_id` as its unique idempotency key inside `idempotent_consumers`. This enforces a hard constraint that prevents posting the same journal ledger voucher twice.
*   **Invoicing**: Before generating a draft invoice, the billing engine must lock and verify the `idempotency_key` (derived from the subscription run date and customer ID). This prevents duplicate invoice issues if network issues occur during checkout cycles.
*   **Payment Cleared Allocations**: Cash allocations are tied to a unique `payment_id` idempotency key. This ensures that cash balances cannot be allocated or posted to an invoice multiple times.

---

## SECTION 8: SCHEMA EVOLUTION & VERSIONING

As enterprise systems scale, event schemas will evolve. The Integration platform must manage event updates systematically to prevent breaking downstream integrations.

### 8.1 Schema Evolution Guidelines
1.  **Additive Changes**: Adding new, optional properties to an event payload is considered a non-breaking, backward-compatible change. Existing consumers must be configured to ignore unrecognized properties.
2.  **Destructive Changes**: Removing existing properties, renaming fields, or changing data types represent breaking, backward-incompatible modifications. These changes require incrementing the event's major version number (e.g., upgrading an event from `invoice.issued_v1` to `invoice.issued_v2`).
3.  **Active Version Support**: When a new major event version is released, the producer must continue emitting the older version alongside the new version for an agreed deprecation window. This allows downstream teams to migrate their consumers on their own schedule without causing service disruptions.

```
                      [EVENT SCHEMA EVOLUTION PATH]
                      
   ┌────────────────────────────────────────────────────────────────────────┐
   │ Additive Change (Backward Compatible)                                  │
   │ - E.g., Adding "tax_registration_number" to invoice.issued_v1          │
   │ - Action: Safe to release within v1. No changes required for consumers.│
   └────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │ Destructive Change (Backward Incompatible)                             │
   │ - E.g., Removing "subtotal_amount" or renaming fields in v1            │
   │ - Action: Create invoice.issued_v2 schema. Maintain parallel support.  │
   └────────────────────────────────────────────────────────────────────────┘
```

---

## SECTION 9: INTEGRATION SECURITY & TENANT ISOLATION

Financial integrations demand strict security, network controls, and verification guarantees.

*   **Service Authentication**: All internal platform traffic moving between domains must be authenticated via secure, short-lived JSON Web Tokens (JWT) or internal service-to-service mutual TLS (mTLS) parameters.
*   **HMAC Webhook Signatures**: To secure outbound webhooks, the Webhook dispatcher must compute a SHA-256 Hash-based Message Authentication Code (HMAC) of the payload using a tenant's private signature secret, transmitting this hash in the `X-Juanet-Signature` header:
    $$\text{HMAC} = \text{HMAC-SHA256}(\text{Tenant Secret}, \text{Payload String})$$
*   **Replay Attack Protection**: Every webhook transaction must include an accurate epoch timestamp inside the header. Consumers should compare this timestamp with their local clock and reject requests if the difference exceeds 5 minutes, mitigating replay attack risks.
*   **Tenant Separation**: Every transaction in `outbox_events` and `idempotent_consumers` must include a valid `organization_id` partition column. Database queries must explicitly filter on this tenant ID, ensuring strict tenant isolation and preventing cross-tenant data leaks.

---

## SECTION 10: PERFORMANCE & SCALE OPTIMIZATIONS

To handle high transaction volumes (such as mass billing runs or year-end closures), the event-driven subsystem implements several performance optimizations:

1.  **Batch Outbox Publishing**: Instead of loading and updating outbox records one by one, the publishing worker processes events in batches of 100 to 500 rows. This reduces network roundtrips and optimizes database transaction performance.
2.  **Outbox Partitioning**: The `outbox_events` table is partitioned by date (e.g., using monthly ranges). This keeps active index sizes small and allows archival tasks to drop historical tables instantly without incurring table-level write locks.
3.  **Parallel Multi-Consumer Processing**: High-volume event channels are divided into distinct partitions using consistent routing keys (such as `organization_id`). This allows multiple consumer instances to process event partitions in parallel while maintaining strict ordering guarantees within each tenant.

---

## SECTION 11: SYSTEM HEALTH MONITORING & TELEMETRY

To monitor integration health and identify issues quickly, the system tracks several key performance indicators (KPIs) and telemetry metrics:

*   **Delivery Latency**: Measures the total time elapsed from an outbox record's initial creation until its successful publication to the event broker:
    $$\text{Delivery Latency} = \text{Broker Publish Timestamp} - \text{Outbox Record Created Timestamp}$$
*   **Consumer Processing Latency**: Tracks the time elapsed from an event's publication until a consumer successfully finishes processing it:
    $$\text{Processing Latency} = \text{Process Completed Timestamp} - \text{Event Created Timestamp}$$
*   **DLQ Poison-Pill Ratios**: Monitors the ratio of failed, dead-lettered events compared to successful event transactions, providing early alerts for schema version mismatches.
*   **Database Queue Depth**: Tracks the number of pending records inside `outbox_events`. High queue depths indicate broker bottlenecks or system latency issues.

---

## SECTION 12: TECHNICAL VALIDATION MATRIX

The technical validation matrix defines the rules used to verify the correctness and reliability of the integration architecture:

| Validation Rule ID | Target System | Check Condition | Error Mitigation Action |
| :--- | :--- | :--- | :--- |
| `VAL-INT-001` | Transactional Outbox | Ensure outbox inserts and business entity mutations are wrapped in the same transaction. | Rollback the entire database transaction and log a high-severity alert. |
| `VAL-INT-002` | Idempotence Guard | Verify incoming `idempotency_key` is unique and does not exist in `idempotent_consumers`. | Skip message processing, return the cached result, and acknowledge the message. |
| `VAL-INT-003` | Schema Integrity | Validate payload structure against the JSON Schema model defined in Section 4. | Reject the message immediately, route it to the DLQ, and write a detailed error log. |
| `VAL-INT-004` | Event Ordering | Verify events for a specific ledger account are processed sequentially based on sequence IDs. | Pause partition consumption, re-fetch missing sequence records, and alert administrators. |
| `VAL-INT-005` | Tenant Isolation | Ensure `payload.organization_id` exactly matches the outer header envelope's `tenant_id`. | Reject the message immediately, log a security breach alert, and route to the DLQ. |
| `VAL-INT-006` | Outbox Deadlock | Ensure the background outbox publisher uses `SELECT ... FOR UPDATE SKIP LOCKED` when fetching. | Prevents database row locking and avoids worker blocking in multi-threaded systems. |
| `VAL-INT-007` | Signature Verification| Verify incoming webhook signatures match the computed HMAC value using the shared secret. | Reject the webhook request immediately with a `401 Unauthorized` status. |
| `VAL-INT-008` | Replay Time Window | Validate that incoming webhook timestamps are within 5 minutes of the current system clock. | Reject the request with a `400 Bad Request` status to mitigate replay attacks. |

---

## SECTION 13: END-TO-END VERIFICATION PLAN

To ensure the reliability of the event-driven integration system, teams must execute and verify the following test suites:

### 13.1 Outbox Transaction Test
*   **Objective**: Confirm that outbox records and business database mutations are committed atomically.
*   **Test Action**: Simulate an intentional database failure (e.g., throwing a database constraint error) during a sales invoice insertion.
*   **Expected Outcome**: The database transaction is rolled back completely. No invoice is written to `public.invoices`, and no event is written to `public.outbox_events`.

### 13.2 Consumer Deduplication Test
*   **Objective**: Verify that duplicate event deliveries do not result in duplicate database writes.
*   **Test Action**: Send two identical `payment.cleared_v1` event messages containing the same `idempotency_key` to the consumer in rapid succession.
*   **Expected Outcome**: The first message is processed normally, posting a single payment allocation to the ledger. The second message is recognized as a duplicate, skipped, and acknowledged without creating any duplicate ledger entries.

### 13.3 Broken Schema Handling Test
*   **Objective**: Verify that invalid payloads are identified and isolated without blocking the main event pipeline.
*   **Test Action**: Send an invalid `invoice.issued_v1` event missing the required `total_amount` field to the event consumer.
*   **Expected Outcome**: The consumer rejects the invalid message, logs a validation error, and routes the payload to the Dead-Letter Queue (DLQ). The consumer then continues processing subsequent valid messages in the queue without interruption.
