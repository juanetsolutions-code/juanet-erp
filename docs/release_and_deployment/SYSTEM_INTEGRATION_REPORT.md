# JUANET EOS: System Integration Report

This document outlines the system integration architecture, bounded context mappings, message broker events, and data flow synchronization pathways of the **JUANET Enterprise SaaS Platform** (JUANET EOS) as verified in Phase 8.

---

## 🗺️ 1. Integrated Bounded Context Mapping

JUANET EOS utilizes a Domain-Driven Design (DDD) layout coupled with a clean, decoupled presentation layer. Each bounded context is isolated inside `app/Domain/`, communicating either via direct service injection (within transactional boundaries) or asynchronously via the **Transactional Outbox Event Bus**.

```
+------------------------------------------------------------------------------------------------------+
|                                          PRESENTATION LAYER                                          |
|                                 Vite-compiled React 18 SPA Client                                    |
+--------------------------------------------------┬---------------------------------------------------+
                                                   │ HTTPS JSON / API REST / SSE
                                                   ▼
+------------------------------------------------------------------------------------------------------+
|                                          BOUNDED CONTEXTS                                            |
|                                                                                                      |
|  +--------------------+      +--------------------+      +--------------------+      +------------+  |
|  |     CRM Domain     |      |  Contracts Domain  |      |   Finance Domain   |      | Workforce  |  |
|  |  * Lead funnels    |      |  * E-Signatures    |      |  * Double Ledger   |      |  * Leaves  |  |
|  |  * Pipeline        |      |  * Proposals       |      |  * Invoicing       |      |  * TimeLog |  |
|  +---------┬----------+      +---------▲----------+      +---------▲----------+      +-----▲------+  |
+------------│---------------------------│---------------------------│-----------------------│---------+
             │                           │                           │                       │
             │ Publishes Event           │ Listens                   │ Listens               │ Listens
             ▼                           │                           │                       │
+────────────────────────────────────────┴───────────────────────────┴───────────────────────┴─────────+
|                                  EVENT BUS & TRANSACTIONAL OUTBOX                                    |
|                                                                                                      |
|  1. Writes event state to `event_outbox` table during parent DB transactions.                         |
|  2. Daemon processes the outbox and publishes messages to Redis.                                     |
|  3. Registered Domain Listeners capture and execute follow-up business logic.                        |
+──────────────────────────────────────────────────────────────────────────────────────────────────────+
```

---

## 🔄 2. Core Operational Data Flows

The platform integrates individual domains into a single transaction-safe flow. The main sequence from customer acquisition to delivery and ledger posting is outlined below:

```
[ Lead Created in CRM ]
          │
          ▼
[ Proposal Generated & Shared ]
          │
          ▼
[ E-Signature Contract Approved ] ───► (Starts Transactional Auto-Onboarding)
          │
          ├──► Auto-Provisions Tenant Client Organization
          ├──► Auto-Creates Project Workspace & Default Milestones
          ├──► Auto-Dispatches Welcome Notifications (Email/SMS)
          ├──► Generates First Down-Payment Invoice (Accounts Receivable)
          └──► Posts Double-Entry Ledger Entry:
                 - DEBIT: Accounts Receivable Account
                 - CREDIT: Deferred Revenue Account
```

---

## 📳 3. External API Gateway Integrations

### I. Safaricom M-PESA Daraja Integration
*   **Workflow**: Integrates with the Safaricom API to process instant client invoice payments.
*   **Security**: Signature validation checks are run on incoming Safaricom payloads, and transaction reference numbers are verified to prevent duplicate callback processing.
*   **Action**: Successful payments update invoice statuses to `Paid`, write debits and credits to the general ledger, and trigger automated receipt generation.

### II. Google Gemini API Integration (@google/genai)
*   **Workflow**: Automates manual scoping and support routing tasks.
*   **Prompt Grounding**: Prompts are grounded with organization industry data, structural metadata, and client requirements to prevent hallucinations.
*   **Usage**: Powering the quote-wizard AI project summary and incoming client portal ticket routing.

---

## 📦 4. Multi-Tenant Data Isolation Strategy

To ensure absolute security and prevent tenant data leakage, JUANET EOS enforces multi-tenant isolation at three distinct layers:

1.  **Eloquent Global Scopes**: Every database query targeting a tenant-scoped table automatically appends a filter check:
    ```sql
    WHERE organization_id = CURRENT_TENANT_ID
    ```
2.  **PostgreSQL Row-Level Security (RLS)**: Production database configurations enforce native RLS policies on critical financial tables, rejecting any cross-tenant operations even if application-level checks are bypassed.
3.  **Encrypted Tenant Keys**: API credentials and secrets are encrypted at rest using AES-256-CBC with organization-specific salt hashes.
