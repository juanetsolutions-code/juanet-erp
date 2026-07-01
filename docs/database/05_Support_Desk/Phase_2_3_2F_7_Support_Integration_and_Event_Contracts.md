# JUANET Support Integration & Event Contracts Specification
## Phase 2.3.2F.7 — Support Integration & Event Contracts
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Event-Driven Architecture  

---

## 1. PURPOSE & DECOUPLING BOUNDARIES

The JUANET Support Integration & Event Contracts Specification defines the asynchronous, event-driven interfaces, decoupling boundaries, and operational integration patterns between the **Support Domain** and external systems (including CRM, Projects, Finance, AI, and Notifications).

To ensure high availability, horizontal scalability, and system fault-tolerance, **Support must remain loosely coupled**. Synchronous remote procedure calls (RPC) or shared database connections that block the operational runtime are strictly prohibited.

```
+---------------------------------------------------------------------------------+
|                                 SUPPORT DOMAIN                                  |
|  [Tickets]  [Conversations]  [SLA State]  [Knowledge Articles]  [QA & Surveys]  |
+---------------------------------------------------------------------------------+
                                         │
                        Commit to DB & Write Event Outbox
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                        TRANSACTIONAL OUTBOX PIPELINE                            |
|                 (Atomic DB commit guarantees zero event loss)                   |
+---------------------------------------------------------------------------------+
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                             ENTERPRISE EVENT BUS                                |
|                        (Kafka / RabbitMQ Message Broker)                        |
+---------------------------------------------------------------------------------+
                                         │
                ┌────────────────────────┼────────────────────────┐
                ▼                        ▼                        ▼
+-------------------------+   +---------------------+   +-------------------------+
|       CRM DOMAIN        |   |   FINANCE DOMAIN    |   |     PROJECTS DOMAIN     |
|   (Contact Sync)        |   |   (Billing Locks)   |   |     (Task Tracking)     |
+-------------------------+   +---------------------+   +-------------------------+
```

### 1.1 Core Decoupling Constraints

*   **Transactional Guarantees**: State mutations inside the Support domain must write outbound notifications to an event outbox log in the *same* database transaction. This ensures that an event is never dispatched if a database transaction rolls back, and conversely, an event is guaranteed to eventually publish if a transaction commits.
*   **Idempotency & Deduplication**: Because event delivery follows an **At-Least-Once** guarantee, downstream consumers are responsible for deduplicating incoming payloads using unique transaction keys.
*   **Asynchronous Processing**: Cross-domain activities (e.g., notifying billing of an escalation, or creating a Jira ticket from a support inquiry) must be executed by out-of-band workers subscribing to the event bus. No external network delays may block primary agent or customer browser connections.
*   **Retry & Exponential Backoff**: Failed event dispatches undergo randomized exponential backoff with jitter to protect downstream services from retry storms.

---

## 2. SUPPORT AS A SYSTEM OF RECORD (SoR)

The Support Domain holds authoritative, primary ownership over the following data categories. No external domain has write privileges or direct access to these schemas:

| Support Entity | System of Record (SoR) Parameters & Bounds |
| :--- | :--- |
| **Tickets** | Owns statuses, SLA mappings, assignments, and structural categorizations. |
| **Conversations** | Owns the sequence, timing, and structural metadata of raw customer-agent message exchanges. |
| **Internal Notes** | Private agent summaries, system annotations, and technical logs. Must never be exposed to clients. |
| **SLA State** | Tracks real-time response/resolution timers, calendars, and escalation milestones. |
| **Knowledge Articles**| Defines authoring versions, translations, approvals, and taxonomy indexes. |
| **QA Reviews** | Houses agent scorecards, review rubrics, calibrations, and coaching logs. |
| **Surveys** | Manages satisfaction metrics (CSAT, NPS, CES) and feedback comments. |
| **AI Suggestions** | Persists suggested replies, summaries, and sentiment metrics. Treated as advisory data. |

*   **Read Access**: Other domains (e.g., CRM dashboards, Project reporting) query Support data via secured read-only replicas, cached APIs, or event ingestion streams.
*   **Write Access Rules**: External domains must execute actions by raising event commands (e.g., `crm.contact.updated` which prompts Support to refresh contact metadata caches). Direct SQL updates across schema boundaries are strictly forbidden.

---

## 3. DOMAIN INTEGRATION MATRIX

The Support domain integrates with several internal and external systems. The matrix below defines the triggers, payloads, and execution strategies for each connection:

```
[Authentication] ────> (Login Token claims) ────> Enforce Tenant RLS
[CRM Domain]     ────> (Contact Updates)    ────> Update local Contact Cache
[Finance Domain] ────> (Payment Failure)    ────> Auto-generate Support Alert
[Project Domain] ────> (Ticket Escalated)   ────> Create Jira / DevOps Task
```

### 3.1 Domain Integrations and Failures Handling

#### 3.1.1 Authentication & Security (`security.users`)
*   **Trigger**: Agent or customer logs into the platform.
*   **Producer**: Auth Domain.
*   **Consumer**: Support Domain.
*   **Payload**: JWT carrying claims (`organization_id`, `user_id`, `roles`, `permissions`).
*   **Failure & Retry**: Standard OAuth failures reject the connection at the gateway layer. JWT validation is performed locally and statelessly.

#### 3.1.2 CRM & Client Contact Synchronizations (`crm.contacts`)
*   **Trigger**: A contact's profile, tier, or email changes in the CRM.
*   **Producer**: CRM Domain.
*   **Consumer**: Support Domain.
*   **Payload**: Contains modified profile values, organization ID, and VIP tier levels.
*   **Failure & Retry**: The Support worker retries processing for up to 5 attempts. If failure continues, the message is placed in the DLQ, and a technical warning is logged. Local caches maintain the last known contact values until resolved.

#### 3.1.3 Projects & Engineering Tasks Sync (`projects.tasks`)
*   **Trigger**: An agent escalates a bug ticket, requiring action from the engineering team.
*   **Producer**: Support Domain (`ticket.escalated.v1`).
*   **Consumer**: Projects Domain (Jira, GitHub, or internal task manager).
*   **Payload**: High-level problem details, reproduction logs, and the tracking ticket number.
*   **Failure & Retry**: Implements an exponential backoff retry. If the projects API remains unreachable after 10 attempts, the status is set to `sync_failed`, prompting the agent to retry the action manually.

#### 3.1.4 Finance & Subscriptions Control (`finance.ledgers`)
*   **Trigger**: Customer fails to pay their monthly invoice, resulting in account suspension.
*   **Producer**: Finance Domain.
*   **Consumer**: Support Domain.
*   **Payload**: Organization ID, suspension state, and restriction level.
*   **Failure & Retry**: Support updates the organization's SLA policy mapping and disables non-essential queues (e.g., Gold Support SLAs). The transaction is processed under a strict FIFO ordering queue to guarantee accurate suspension alignment.

#### 3.1.5 Notifications Dispatcher (`system.notifications`)
*   **Trigger**: A ticket receives a new reply or triggers an SLA warning alert.
*   **Producer**: Support Domain (`ticket.replied.v1`, `sla.warning.v1`).
*   **Consumer**: System Notifications Engine (Slack, email, SMS, push alerts).
*   **Payload**: Recipient ID, localized templates, context values, and delivery channels.
*   **Failure & Retry**: Retries with backoff. If delivery fails across all channels, the system saves the notification to the user's in-app inbox to preserve the message history.

#### 3.1.6 Audit & Compliance Logging (`system.audit`)
*   **Trigger**: A user overrides a security constraint or deletes account records under GDPR guidelines.
*   **Producer**: Support Domain.
*   **Consumer**: Central Auditing System.
*   **Payload**: Timestamp, user IP, trace ID, authorization details, and mutated values.
*   **Failure & Retry**: Write operations use a synchronous blocking append to local syslog endpoints. This ensures that if the audit logger fails, the corresponding database operation is rolled back, preventing un-audited state mutations.

---

## 4. TRANSACTIONAL OUTBOX PATTERN

To guarantee that events are successfully dispatched to the message bus after database changes, the platform implements the **Transactional Outbox Pattern** on the PostgreSQL layer.

```
       APPLICATION TRANS ACTION
  ┌─────────────────────────────────┐
  │                                 │
  │   INSERT INTO public.tickets    │
  │   INSERT INTO public.outbox     │
  │                                 │
  └────────────────┬────────────────┘
                   │
             Commit to DB
                   │
                   ▼
       [Outbox Polling Worker] ──(Dispatches payload)──> [Message Bus (Kafka)]
                   │
                   ▼
         Update Outbox Status
```

### 4.1 Schema Definition (SQL Standard)
The outbox ledger is represented inside the database as:

```sql
CREATE TABLE public.support_event_outbox (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    trace_id UUID NOT NULL,
    correlation_id UUID NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'sent', 'failed')),
    retry_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    processed_at TIMESTAMPTZ
);

-- Optimize polling query performance
CREATE INDEX outbox_polling_idx ON public.support_event_outbox (status, created_at) WHERE status = 'pending';
```

### 4.2 Processing Execution Logic
1.  **Atomic Commits**: Application logic executes core database modifications (e.g., updating a ticket's priority) and writes a corresponding event record to `public.support_event_outbox` in the same PostgreSQL transaction.
2.  **Worker Polling Loop**: A background worker process queries pending outbox records every 250ms:
    ```sql
    UPDATE public.support_event_outbox
    SET status = 'processing'
    WHERE id IN (
        SELECT id 
        FROM public.support_event_outbox
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT 50
        FOR UPDATE SKIP LOCKED
    )
    RETURNING id, organization_id, event_type, payload, trace_id, correlation_id;
    ```
3.  **Bus Dispatching**: The worker formats the payload and publishes it to the enterprise message bus (Kafka/RabbitMQ).
4.  **Completion Tracking**: Upon receiving a delivery acknowledgment from the message broker, the worker updates the outbox record's status to `sent` and sets `processed_at = now()`.
5.  **DLQ & Error Capture**: If dispatch fails repeatedly (e.g., after 5 attempts), the outbox status is updated to `failed`, and the payload is routed to the Dead Letter Queue (DLQ) for review.

---

## 5. IDEMPOTENT CONSUMER PATTERN

Downstream workers use the **Idempotent Consumer Pattern** to prevent processing duplicate messages from the message bus.

```
  [Event Ingest] ──> [Query Idempotency Key] ──> [Already Processed?] ──(YES)──> [Acknowledge & Drop]
                                                       │
                                                      (NO)
                                                       │
                                                       ▼
                                             [Process Event Transaction]
                                             [Insert Idempotency Key]
                                             [Acknowledge Message]
```

### 5.1 Idempotency Ledger Schema

```sql
CREATE TABLE public.support_processed_events (
    idempotency_key VARCHAR(255) PRIMARY KEY,
    consumer_group VARCHAR(100) NOT NULL,
    processed_at TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp()
);

-- Pruning index for historical cleanup
CREATE INDEX processed_events_cleanup_idx ON public.support_processed_events (processed_at);
```

### 5.2 Consumption Algorithm
When a consumer receives an event, it executes the following steps in a single database transaction:

1.  **Retrieve Event ID**: Extracts the unique event ID to use as the `idempotency_key`.
2.  **Verify Status**: Checks if a matching record exists in `public.support_processed_events` for the active consumer group:
    ```sql
    INSERT INTO public.support_processed_events (idempotency_key, consumer_group)
    VALUES (:idempotency_key, :consumer_group)
    ON CONFLICT (idempotency_key) DO NOTHING;
    ```
3.  **Process or Acknowledge**:
    *   *Insert Succeeded*: The consumer processes the payload, executes database updates, commits the transaction, and acknowledges the message on the broker.
    *   *Insert Failed (Duplicate detected)*: The consumer drops the message immediately and acknowledges the event on the broker, preventing duplicate database updates.

---

## 6. CANONICAL EVENT CATALOG

The Support domain publishes structured, version-controlled events to keep other systems aligned with ticket and communication updates.

### 6.1 Schema Contracts (JSON Standards)

#### 6.1.1 `ticket.created.v1`
*   **Purpose**: Dispatched when a new ticket is successfully initialized and validated.
*   **Producer**: Support Ticket Pipeline.
*   **Consumers**: CRM contact dashboard, notifications engine, analytics database.
*   **Schema**:
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "ticket.created.v1",
  "type": "object",
  "required": ["event_id", "event_type", "version", "timestamp", "organization_id", "trace_id", "payload"],
  "properties": {
    "event_id": { "type": "string", "format": "uuid" },
    "event_type": { "type": "string", "const": "ticket.created" },
    "version": { "type": "string", "const": "v1" },
    "timestamp": { "type": "string", "format": "date-time" },
    "organization_id": { "type": "string", "format": "uuid" },
    "trace_id": { "type": "string", "format": "uuid" },
    "payload": {
      "type": "object",
      "required": ["ticket_id", "ticket_number", "subject", "requester_id", "status", "priority"],
      "properties": {
        "ticket_id": { "type": "string", "format": "uuid" },
        "ticket_number": { "type": "string" },
        "subject": { "type": "string" },
        "requester_id": { "type": "string", "format": "uuid" },
        "status": { "type": "string" },
        "priority": { "type": "string" }
      }
    }
  }
}
```

#### 6.1.2 `ticket.status_changed.v1`
*   **Purpose**: Dispatched when a ticket's status transitions in the FSM.
*   **Producer**: Ticket State Machine.
*   **Consumers**: SLA calculations, customer portal notifier, analytics dashboards.
*   **Schema**:
```json
{
  "event_type": "ticket.status_changed",
  "version": "v1",
  "payload": {
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "previous_status": "open",
    "new_status": "assigned",
    "assigned_agent_id": "usr_7382f123-90ba-4cbd-a912-f04b2c39e120"
  }
}
```

#### 6.1.3 `message.received.v1`
*   **Purpose**: Dispatched when a new inbound message is successfully parsed and logged.
*   **Producer**: Omnichannel Ingestion Pipeline.
*   **Consumers**: Agent workspace alerts, push notifications, AI summarization engine.
*   **Schema**:
```json
{
  "event_type": "message.received",
  "version": "v1",
  "payload": {
    "conversation_id": "cnv_1234a567-b89c-12d3-a456-426614176000",
    "message_id": "msg_91a03f42-494a-4b05-9f1e-f3b140131109",
    "sender_type": "customer",
    "channel_type": "whatsapp",
    "body_preview": "Hi, I need help with my billing details..."
  }
}
```

#### 6.1.4 `sla.breached.v1`
*   **Purpose**: Dispatched when an active SLA target misses its response or resolution deadline.
*   **Producer**: SLA & Escalation Timer Engine.
*   **Consumers**: Supervisor alert queues, pager services, CRM account health scorecards.
*   **Schema**:
```json
{
  "event_type": "sla.breached",
  "version": "v1",
  "payload": {
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "sla_instance_id": "sla_731a89c2-20ba-4cbb-b21a-1f03bc189012",
    "metric_type": "resolution",
    "deadline_timestamp": "2026-06-29T07:15:00Z",
    "breach_timestamp": "2026-06-29T07:15:01Z"
  }
}
```

---

## 7. CROSS-DOMAIN BUSINESS FLOWS

This section outlines how events coordinate complex processes across different platform domains:

```
+---------------------------------------------------------------------------------+
|                                 TICKET ESCALATION                               |
|        [Support Escalation] ──> [Events Outbox] ──> [projects.task.v1]          |
|                                                          │                      |
|                                                          ▼                      |
|                                              [Eng Task created in Projects]     |
+---------------------------------------------------------------------------------+

+---------------------------------------------------------------------------------+
|                                 FINANCIAL HOLD                                  |
|        [Payment Fails in Finance] ──> [SLA policy adjusted in Support]          |
+---------------------------------------------------------------------------------+
```

### 7.1 Cross-Domain Workflows

#### 7.1.1 Ticket Escalation & Engineering Sync (Support ──> Projects)
1.  An agent escalates a bug ticket, and the system commits the status update alongside a `projects.task.v1` outbox event.
2.  The projects consumer worker ingests the event and uses theProjects API to create a matching issue in the engineering queue.
3.  The worker records the external issue ID in the projects reference schema and updates the corresponding ticket record with the active sync status.

#### 7.1.2 Financial Hold & SLA Degradation (Finance ──> Support)
1.  A customer's subscription billing fails repeatedly. The Finance domain commits the status update and dispatches a `finance.subscription.suspended` event.
2.  The Support domain's policy consumer processes the event and identifies the affected organization.
3.  The consumer database updates the organization's SLA level, mapping active tickets to standard fallback SLAs and pausing premium priority queues.

#### 7.1.3 AI Summarization & Classification (AI ──> Support)
1.  A new ticket is created, triggering a `ticket.created.v1` event.
2.  The AI analysis consumer ingests the event, extracts the ticket body, and sends it to the AI pipeline for classification and sentiment scoring.
3.  The pipeline processes the text and commits predicted category codes and sentiment scores directly to the advisory tables, alerting queue managers.

---

## 8. FAILURE HANDLING & DLQ TOPOLOGY

To maintain system stability during network outages or down downstream services, the platform implements a structured failure handling topology:

```
[Ingress Message] ──> [Primary Worker Process] ──(Error)──> [Queue Retry Loop]
                                                                  │
                                                        Max attempts exceeded?
                                                                  │
                                                        ┌─────────┴─────────┐
                                                        ▼ (YES)             ▼ (NO)
                                                [Dead Letter Queue]   [Incremental Backoff]
```

### 8.1 Resilience Controls & Fallback Pipelines

*   **Incremental Retry Policies**: Failed message deliveries are scheduled for re-processing using a standard incremental backoff schedule with added jitter:
    $$\text{Backoff Duration} = \min(\text{MaxBackoff}, \text{BaseInterval} \times 2^{\text{AttemptCount}}) \pm \text{Jitter}$$
*   **Poison Message Identification**: If an event fails to compile or parse (e.g., due to an invalid JSON schema), the message is flagged as poison, bypassed, and routed directly to the DLQ, preventing worker processing locks.
*   **Dead Letter Queue (DLQ)**:
    *   Each active message topic has a designated DLQ (e.g., `support.ticket-created.dlq`).
    *   The DLQ preserves the original message payload, retry log, error details, and trace IDs to assist with debugging.
*   **Manual Replay & Intervention**: Support administrators can review failed events in the DLQ dashboard and trigger a manual replay once the underlying issue (e.g., database lock contention or external API downtime) is resolved.

---

## 9. SECURITY & DATA PRIVACY IN EVENT STREAMS

Because events are broadcast across domain boundaries, strict security and data protection rules are enforced at the message layer:

*   **HMAC Payload Verification**: Webhook dispatches and external events carry HMAC-SHA256 headers computed with shared tenant secrets, allowing consumers to verify message authenticity.
*   **PII & Token Scrubbing**: Before writing events to the outbox, the pipeline scrubs or masks personal details (such as names, phone numbers, and emails) from event payloads, unless explicitly required for routing.
*   **Tenant Isolation**: Event payloads must explicitly contain `organization_id` properties. Consumers use this value to run database updates inside the appropriate tenant context, ensuring RLS rules are maintained.
*   **Traceability (Correlation & Trace IDs)**:
    *   Every event carries a `trace_id` (representing the overall user transaction) and a `correlation_id` (representing individual downstream steps).
    *   These IDs are logged across all domain boundaries, helping developers track requests and debug issues across different microservices.

---

## 10. PERFORMANCE & HIGH-VOLUME OPTIMIZATIONS

To support high transaction volumes without degrading database or network performance, the event engine applies the following optimization strategies:

*   **Partition-Key-Based Event Ordering**: Events are partitioned on the message bus using the ticket ID or conversation ID as the partition key. This guarantees that events for a specific ticket are processed chronologically in order of arrival, preventing out-of-order state updates.
*   **Batch Outbox Publishing**: The outbox worker queries and dispatches events in batches (e.g., 50 records per batch) using database locks (`FOR UPDATE SKIP LOCKED`), reducing transactional overhead.
*   **Compression Controls**: Event payloads exceeding 10KB are compressed using standard gzip algorithms before publishing, reducing network usage.

---

## 11. VERSIONING & SCHEMA EVOLUTION

To support platform upgrades without disrupting active downstream consumers, the event engine enforces strict schema versioning rules:

*   **SemVer-Compliant Topic Paths**: Event topics include major version numbers in their paths (e.g., `support.ticket.created.v1`).
*   **Non-Breaking Schema Changes (Backward Compatible)**: Non-breaking changes (such as adding optional metadata attributes) can be introduced to existing versions without updating the version number.
*   **Breaking Schema Changes (Backward Incompatible)**: Significant modifications (such as removing required fields or modifying core data structures) require creating a new major version of the event (e.g., `support.ticket.created.v2`).
*   **Transition and Deprecation Schedules**: When a new major version is introduced, the engine supports both versions in parallel for a 90-day deprecation period, giving consumer teams time to upgrade their integration code.

---

## 12. VALIDATION CHECKLIST

Before deploying integrations or activating event consumers, developers must verify the following checklist:

| Area | Verification Scenario / Validation Objective | Expected Result | Checked |
| :---: | :--- | :--- | :---: |
| **Outbox** | Database transaction rolls back during ticket creation. | Event is **NOT** written to the outbox table or published. | [ ] |
| **Ordering** | Inbound customer replies arrive out of order. | Bus partition keys enforce strict chronological processing order. | [ ] |
| **Idempotency** | Event bus dispatches a duplicate event to a consumer. | Consumer detects the duplicate key and drops the message. | [ ] |
| **Fault-Tolerance** | Downstream notification engine remains offline for 1 hour. | Backoff retries trigger, and messages queue safely without data loss. | [ ] |
| **Security** | Consumer tries to process event with a mismatched tenant ID. | Database RLS blocks the transaction, protecting tenant isolation. | [ ] |
| **Performance** | Ingestion worker queries outbox records during peak traffic. | `FOR UPDATE SKIP LOCKED` prevents database lock contention. | [ ] |

---

## 13. SUMMARY OF ARCHITECTURAL DECISIONS

This section summarizes the key architectural decisions implemented in this integration specification:

### 13.1 Key Architectural Decisions
1.  **Strict Decoupling via Asynchronous Events**: Shared databases and synchronous cross-domain writes are replaced with an event-driven model, ensuring other systems do not block core Support performance.
2.  **Zero-Loss Transactional Outbox Pattern**: Implements a dedicated outbox database ledger to guarantee event dispatching consistency, ensuring events are committed to the bus if and only if database updates succeed.
3.  **Consumer Idempotency Protection**: Enforces dedicated deduplication ledgers across consumer workers, preventing duplicate message processing and protecting transaction integrity.
4.  **Traceability across Domains**: Integrates unique Trace and Correlation IDs into all event headers, allowing teams to track transactions across multiple platform services.

### 13.2 Schema Additions
To support this integration model, the following schemas are introduced:
*   `public.support_event_outbox`: Persists pending outbound events during the transaction lifecycle, supporting atomic writes and worker polling loops.
*   `public.support_processed_events`: Tracks processed event IDs, supporting consumer deduplication and idempotency verification.

### 13.3 Documented Event Contracts
*   `ticket.created.v1`: Broadcasts new ticket creations to CRM, analytics, and notification services.
*   `ticket.status_changed.v1`: Communicates ticket status transitions to SLA and audit systems.
*   `message.received.v1`: Notifies workspace alerts and AI engines of incoming customer communication.
*   `sla.breached.v1`: Triggers escalation alerts and supervisor notifications when ticket deadlines are missed.

---

This document serves as the architectural reference for implementing event-driven integrations, transactional outbox flows, and system security controls within the JUANET Support Platform. All integrations must adhere strictly to these specifications.
