# Core Platform Architectural Reference (JUANET EOS)

This document describes the design patterns, software layers, database structures, and integration boundaries of the **JUANET Enterprise SaaS Platform**.

---

## 🏗 High-Level Architectural Pattern

JUANET uses a hybrid architecture combining **Domain-Driven Design (DDD)** and **Hexagonal Architecture (Ports and Adapters)**. This guarantees that core business rules remain independent of external delivery channels, database systems, or third-party web services.

```
       +--------------------------------------------------------------+
       |                     PRESENTATION LAYER                       |
       |                Vite-React SPA Dashboard Web Client           |
       +------------------------------+-------------------------------+
                                      | HTTP Requests (JSON)
                                      v
       +------------------------------+-------------------------------+
       |                     APPLICATION ROUTING                      |
       |                 Laravel Routing / Controllers                |
       +------------------------------+-------------------------------+
                                      | Decodes Tenancy & RBAC
                                      v
       +------------------------------+-------------------------------+
       |                      CORE DOMAIN SERVICES                    |
       |             Bounded Business Context Domain Rules            |
       +------------------------------+-------------------------------+
                                      | Leverages Domain Interfaces
                                      v
       +------------------------------+-------------------------------+
       |                      INFRASTRUCTURE LAYER                    |
       |  PostgreSQL RLS DB / Redis / MinIO Storage / External APIs    |
       +--------------------------------------------------------------+
```

### Layer Responsibilities
1.  **Presentation Layer (Vite & React)**: Visual displays, interface components, layout flows, and client state reactivity.
2.  **Application Layer (Laravel API Controllers)**: Restful request sanitization, routing logic, rate limiting, and standard middleware checks.
3.  **Domain Layer (Bounded Contexts)**: The core business models, service logic, state machines, and system events.
4.  **Infrastructure Layer (Concrete Adapters)**: External services such as Supabase, M-PESA Daraja APIs, Amazon S3, Mailgun SMTP, and Google Gemini.

---

## 🏢 Database Architecture & Tenant Isolation

JUANET is designed from the ground up as a secure **Multi-Tenant Software-as-a-Service (SaaS)** system. Tenant isolation is achieved using single-database logical isolation enforced by PostgreSQL constraints and scope patterns.

### Key Tenancy Rules
1.  **Mandatory Foreign Keys**: Every table containing tenant data must include an `organization_id` foreign key referencing the `organizations` table.
2.  **Global Query Scope Enforcements**: An Eloquent global scope automatically appends a tenant check `WHERE organization_id = CURRENT_ORGANIZATION_ID` to all SELECT queries, protecting tenant data from cross-contamination.
3.  **Database Row-Level Security (RLS)**: Under production configurations, native PostgreSQL Row-Level Security policies are enabled:
    ```sql
    CREATE POLICY tenant_isolation_policy ON client_proposals
        FOR ALL USING (organization_id = auth.current_tenant_id());
    ```

---

## 💸 Immutable Double-Entry General Ledger

To guarantee financial auditing standards, JUANET uses an **Immutable Double-Entry Bookkeeping Ledger Pattern** for all financial interactions. This prevents un-auditable updates to accounts.

### Data Model Entities
1.  **Chart of Accounts (`finance_accounts`)**: Defines standard credit and debit account categorizations (e.g., Accounts Receivable, Operating Expenses, Bank Treasury, Kenya Tax Vault).
2.  **Ledgerable Entries (`ledgerable_type` & `ledgerable_id`)**: A polymorphic relationship mapping external business actions (Paid Invoices, Purchases, Outgoing Operational Expenses) to ledger entries.
3.  **Transactions Ledger (`finance_transactions`)**: The immutable audit log of postings containing debits and credits:
    *   `id` (UUID): Primary key.
    *   `organization_id` (UUID): Tenant routing ID.
    *   `account_id` (UUID): Target chart account.
    *   `type` (Enum): Must be `debit` or `credit`.
    *   `amount` (Decimal): Numeric value.
    *   `reference_number` (String): External validation key (e.g. M-PESA Receipt ID).

### Reconciliation Rule
For any ledger transaction, the total sum of debits must match the total sum of credits, ensuring that the ledger balances at all times.

---

## ⚡ Asynchronous Event Queuing & Transactional Outbox

To maximize performance and prevent system timeouts, JUANET offloads heavy operations (such as PDF generation, payment processing, or notifications) to background queue workers.

### The Transactional Outbox Pattern
To prevent data inconsistency when dispatching asynchronous events, JUANET uses the **Transactional Outbox Pattern**:

```
[ Domain Operation Starts ]
       │
       ├──(Step 1: Write business record, e.g., Invoice created)
       ├──(Step 2: Write event payload to "outbox_events" database table)
       └──(Commit Database Transaction)
               │
               ▼ (Event database transaction committed successfully)
[ Outbox Listener / Daemon Runs ]
       │
       ├──(Step 3: Read un-dispatched events from "outbox_events" table)
       ├──(Step 4: Publish job into high-concurrency Redis Queue broker)
       └──(Step 5: Mark event record as "processed" in database)
```

This ensures that event dispatching is tied directly to database transaction success, preventing ghost events or silent dispatch failures if your Redis server goes offline.

---

## 🤖 Google Gemini AI Core Architecture

JUANET implements automated intelligence features using the modern **Google Gen AI TypeScript SDK** (`@google/genai`).

### Key Implementation Patterns
1.  **Strict Server-Side Execution**: All Gemini API calls are kept strictly server-side (`server.ts` or Laravel proxy endpoints) to prevent leaking critical API keys to the browser.
2.  **Intelligent Route Recommendation**: Leverage `gemini-2.5-flash` to process client support tickets, determine message intent, and automatically route issues to appropriate staff.
3.  **Automatic Proposal Scoping**: Uses Gemini's large context windows to analyze client inputs and auto-generate detailed, structured technical proposal templates.
