# JUANET Support Architecture Decision Records Specification
## Phase 2.3.2F.12 — Support Architecture Decision Records (ADRs)
**Document Version:** 1.0  
**Author:** Chief Software Architect & Enterprise Governance Lead  
**Classification:** Technical / Architecture Decision Records (ADR)  

---

## 1. PURPOSE & LIFE CYCLE OF ARCHITECTURAL DECISION RECORDS (ADRs)

The JUANET Support Architecture Decision Records (ADR) manual permanently logs the foundational engineering and architectural decisions made for the Support domain. This document preserves the context, trade-offs, consequences, and historical alternatives rejected for critical design patterns, physical data configurations, and domain boundary integrations.

```
                  ADR PROCESS LIFE CYCLE
  [Proposed] ──> [Review Committee] ──> [Approved / Rejected]
                       ▲
                       │ (Superceded By Newer ADR)
                 [Superceded]
```

### 1.1 The ADR Life Cycle

*   **Proposed**: The decision is drafted by a Lead Architect and opened for technical review.
*   **Active / Approved**: The decision has passed review, met all architectural constraints, and is authorized for active implementation.
*   **Superceded**: A subsequent design pattern replaces this decision. The previous ADR remains in the catalog marked "Superceded" with a link pointing directly to the successor ADR.
*   **Rejected**: The proposal was evaluated but did not meet performance, security, or isolation constraints. It remains logged as a historical reference to prevent future re-evaluation loops.

---

## 2. GOVERNANCE AND APPROVAL WORKFLOW

Architectural changes within the JUANET platform follow a structured governance flow to protect tenant isolation, preserve database performance, and maintain zero-trust security.

```
  Architect Proposes ADR
            │
            ▼
  [Verify Compliance & Security] ──(Fails)──> [Return to Draft / Reject]
            │ (Passes)
            ▼
  [DBA Performance Evaluation]   ──(Fails)──> [Return to Draft / Reject]
            │ (Passes)
            ▼
  [Chief Architect Final Sign-Off] ─────────> [Commit to Active Registry]
```

1.  **Drafting**: The author compiles an ADR using the standard enterprise template.
2.  **Compliance Verification**: The Security and Compliance Officer reviews the ADR against regulatory frameworks (GDPR, SOC2, HIPAA, PCI).
3.  **Performance Evaluation**: The Database Administrator (DBA) audits the proposed schema modifications, indexing strategies, and locking implications under maximum simulated scale.
4.  **Authorized Commits**: The Chief Software Architect signs off on the proposal, promoting the ADR to the active registry.

---

## 3. CORE ARCHITECTURAL CONSTRAINTS
All decisions within the Support domain must adhere to the following strict boundaries:

1.  **Strict Logical Multi-Tenant Isolation**: Row-Level Security (RLS) must run directly on the database layer. No cross-tenant reads or writes are permitted under any circumstances.
2.  **ACID-Compliant State Transitions**: State modifications must be transactional and deterministic.
3.  **Horizontal Scale-Out Capacity**: Single-node lock contentions are prohibited. Data structures must support scaling up to 10M+ tickets, 100M+ messages, and thousands of concurrent agents.
4.  **Advisory-Only AI Framework**: Machine models must never directly mutate the core state of transactional records (tickets, users, profiles) without explicit human review and authorization.

---

## 4. ARCHITECTURE DECISION RECORDS (ADRS)

---

### ADR-001: Support Tickets as the Primary System of Record (SoR)

*   **Status**: APPROVED  
*   **Context**: Customer queries, SLA obligations, and communication histories are central to support operations. External systems (CRM, Projects, Billing) must interact with these records continuously.
*   **Problem**: If external systems modify ticket states directly, it causes audit log discrepancies, race conditions on SLA timers, and inconsistent data formatting.
*   **Decision**: Declare the Support domain as the absolute System of Record (SoR) for tickets. No external service has write privileges or direct access to these schemas.
*   **Alternatives Considered**:
    *   *Shared Database Schema*: Rejected due to high coupling and database lock contention.
    *   *Event-Driven Virtual Views*: Rejected due to data synchronization delays.
*   **Trade-offs**: Requires building API proxies and asynchronous event pipelines, increasing initial development complexity.
*   **Consequences**: Guarantees data consistency, provides clean, auditable logs, and ensures SLA timers remain accurate.

---

### ADR-002: Immutability of Support Conversations

*   **Status**: APPROVED  
*   **Context**: Conversations track interactions between customers and agents, supporting audits and legal reviews.
*   **Problem**: If users or agents edit historical messages, it compromises forensic trails, invalidates QA scorecard evaluations, and corrupts training datasets for AI models.
*   **Decision**: Enforce complete immutability on all conversations. Message bodies cannot be modified once they are committed.
*   **Alternatives Considered**:
    *   *Versioned Messages*: Rejected due to added database complexity and index bloat.
    *   *Agent-Only Modifications*: Rejected because it compromises audit trail integrity.
*   **Trade-offs**: Limits flexibility, preventing users from correcting spelling mistakes or formatting errors after sending.
*   **Consequences**: Ensures secure, auditable, and compliance-ready communication histories.

---

### ADR-003: Append-Only Ticket Messages

*   **Status**: APPROVED  
*   **Context**: Communication streams experience highly concurrent database writes during peak service periods.
*   **Problem**: Updating or deleting message rows creates dead database tuples, causing index fragmentation and table bloat on high-volume tables.
*   **Decision**: Enforce an append-only write strategy for all message tables. Modifications are achieved by appending correction rows rather than editing existing records.
*   **Alternatives Considered**:
    *   *Standard SQL Updates*: Rejected due to high write locking contention.
*   **Trade-offs**: Increases storage consumption, requiring structured data tiering and archiving pipelines.
*   **Consequences**: Accelerates insertion performance and prevents database lock contention under heavy parallel loads.

---

### ADR-004: Advisory-Only Artificial Intelligence

*   **Status**: APPROVED  
*   **Context**: Artificial intelligence features (suggested replies, summarizations, sentiment metrics) are integrated into active agent workflows.
*   **Problem**: Directly authorizing AI models to close tickets, edit profiles, or send customer responses can lead to incorrect actions due to model hallucinations.
*   **Decision**: AI models are restricted to advisory-only roles. High-impact operations must pass through a Human-in-the-Loop (HITL) gate.
*   **Alternatives Considered**:
    *   *Automated Low-Risk Closures*: Rejected because even low-risk closures require strict auditability.
*   **Trade-offs**: Slightly increases agent handle times compared to fully automated responses.
*   **Consequences**: Eliminates the risk of automated AI errors, ensures strict compliance, and protects tenant records.

---

### ADR-005: Selecting Native PostgreSQL Full-Text Search (FTS)

*   **Status**: APPROVED  
*   **Context**: Support workflows require efficient, multi-tenant keyword searching across millions of documents and tickets.
*   **Problem**: Syncing data with external search engines (such as Elasticsearch or OpenSearch) can lead to indexing delays and data drift.
*   **Decision**: Leverage native PostgreSQL Full-Text Search using pre-compiled `tsvector` columns and GIN indexes.
*   **Alternatives Considered**:
    *   *External Elasticsearch Cluster*: Rejected due to increased infrastructure costs and synchronization complexities.
*   **Trade-offs**: Native FTS lacks advanced linguistic analysis capabilities compared to dedicated search engines.
*   **Consequences**: Maintains transactional consistency (ACID search updates), simplifies deployment, and respects tenant RLS policies.

---

### ADR-006: Applying pg_trgm for Partial Text Searches

*   **Status**: APPROVED  
*   **Context**: Users frequently search for tickets using partial subjects, incomplete email addresses, or alphanumeric tracking codes.
*   **Problem**: Standard B-Tree indexes and standard SQL `LIKE '%term%'` filters perform full table scans under load.
*   **Decision**: Utilize the native PostgreSQL `pg_trgm` extension, indexing high-frequency search fields with Trigram GIN indexes.
*   **Alternatives Considered**:
    *   *Fuzzy Search in Application code*: Rejected due to high RAM usage and slow speeds on large tables.
*   **Trade-offs**: GIN trigram indexes require significant disk space and can increase write times.
*   **Consequences**: Supports fast prefix and partial-string lookups directly on the database layer.

---

### ADR-007: Choosing pgvector for RAG Vector Embeddings

*   **Status**: APPROVED  
*   **Context**: RAG pipelines use dense vector embeddings to match user queries with relevant knowledge base documents.
*   **Problem**: Deploying a separate vector database (such as Pinecone, Qdrant, or Milvus) increases system complexity and raises security concerns regarding tenant isolation.
*   **Decision**: Store vector embeddings directly inside PostgreSQL using the `pgvector` extension.
*   **Alternatives Considered**:
    *   *Pinecone External SaaS*: Rejected because it bypasses database-level RLS isolation.
    *   *Milvus Dedicated Cluster*: Rejected due to high operational and infrastructure overhead.
*   **Trade-offs**: pgvector has slightly lower search speeds compared to dedicated vector databases on massive datasets.
*   **Consequences**: Enforces tenant RLS rules on all vector queries, maintains database consistency, and simplifies system architecture.

---

### ADR-008: Adopting UUIDv7 for Record Identifiers

*   **Status**: APPROVED  
*   **Context**: Database systems require unique identifiers for indexing and clustering high-volume data tables.
*   **Problem**: Traditional UUIDv4 values are randomly generated, leading to poor page-split performance and index fragmentation on high-write databases. Alphanumeric sequential IDs (`serial`, `bigserial`) can cause lock contention.
*   **Decision**: Use UUIDv7 as the primary identifier standard across all Support schemas.
*   **Alternatives Considered**:
    *   *Standard UUIDv4*: Rejected due to high disk fragmentation and slow write speeds.
    *   *Sequential Integers*: Rejected because they are difficult to manage in distributed multi-tenant systems.
*   **Trade-offs**: UUIDv7 values consume more storage space (128 bits) compared to standard integers.
*   **Consequences**: Combines time-ordered sequential indexing performance with stateless, distributed UUID generation.

---

### ADR-009: Row-Level Security (RLS) for Tenant Isolation

*   **Status**: APPROVED  
*   **Context**: SaaS platforms require absolute logical separation of tenant data to protect privacy and comply with regulations.
*   **Problem**: Enforcing tenant checks in application-level queries (`WHERE tenant_id = :id`) is error-prone and can lead to data leaks during development.
*   **Decision**: Enable Row-Level Security (RLS) on all database tables, enforcing tenant isolation directly on the PostgreSQL database engine.
*   **Alternatives Considered**:
    *   *Application-Level Filters*: Rejected because a single missing query clause can expose tenant data.
    *   *Separate Database per Tenant*: Rejected because it is expensive and difficult to scale across thousands of tenants.
*   **Trade-offs**: Slightly increases database planning and query overhead.
*   **Consequences**: Guarantees secure, automated tenant isolation on the database layer, protecting client data.

---

### ADR-010: Transactional Outbox Pattern for Outbound Events

*   **Status**: APPROVED  
*   **Context**: Modifying support states requires notifying downstream services (CRM, Billing, Projects, Notifications).
*   **Problem**: Dispatching events to external networks inside active database transactions can cause slow connections, transaction timeouts, and inconsistent states if database commits fail.
*   **Decision**: Implement the Transactional Outbox Pattern. State modifications write event records to `support_event_outbox` in the same database transaction.
*   **Alternatives Considered**:
    *   *Direct Publish from API Handlers*: Rejected because failed transactions can leave external events published.
*   **Trade-offs**: Requires building background polling workers, increasing codebase complexity.
*   **Consequences**: Guarantees at-least-once event delivery, avoids network delays, and protects transactional consistency.

---

### ADR-011: Idempotent Consumers

*   **Status**: APPROVED  
*   **Context**: Event bus architectures employ "at-least-once" delivery, which can result in duplicate event dispatches.
*   **Problem**: Duplicate processing of event payloads can cause corrupted statistics, duplicate messages, and inaccurate SLA logs.
*   **Decision**: Enforce the Idempotent Consumer Pattern across all background workers, logging event hashes in `support_processed_events`.
*   **Alternatives Considered**:
    *   *At-Most-Once Delivery Settings*: Rejected because it can result in silent event drops and inconsistent states.
*   **Trade-offs**: Slightly increases write times due to idempotency verification steps.
*   **Consequences**: Protects transaction integrity, ensuring exactly-once processing behaviors on downstream workers.

---

### ADR-012: Event-Driven Architecture (EDA) for Decoupling

*   **Status**: APPROVED  
*   **Context**: The Support domain integrates with several internal and external services (CRM, Projects, Finance, Notifications).
*   **Problem**: Direct synchronous API dependencies (such as blocking ticket saves until billing verification completes) degrade platform speed and availability.
*   **Decision**: Implement an Event-Driven Architecture, using asynchronous message topics to communicate changes across systems.
*   **Alternatives Considered**:
    *   *Synchronous REST/gRPC Calls*: Rejected due to high system coupling and risk of cascading failures.
*   **Trade-offs**: Increases system complexity, requiring developers to manage eventual consistency.
*   **Consequences**: Decoupled domain models, high fault-tolerance, and horizontal scalability.

---

### ADR-013: Materialized Views for Dashboard Cache

*   **Status**: APPROVED  
*   **Context**: Live dashboards query aggregated metrics (such as SLA compliance percentages, backlog sizes, and CSAT ratings) continuously.
*   **Problem**: Executing aggregate queries on large transaction tables degrades query performance and blocks active agent workloads.
*   **Decision**: Pre-compile dashboard metrics into PostgreSQL Materialized Views, refreshing them concurrently every 5 minutes on read replicas.
*   **Alternatives Considered**:
    *   *On-the-Fly SQL Aggregations*: Rejected because it degrades database speed during peak traffic.
*   **Trade-offs**: Introduces minor data staleness (up to 5 minutes) on reporting dashboards.
*   **Consequences**: Keeps query latency under 50ms, offloads computational work, and protects OLTP write master speeds.

---

### ADR-014: CQRS-Lite (Read/Write Split)

*   **Status**: APPROVED  
*   **Context**: Support platforms experience highly concurrent read and write operations simultaneously.
*   **Problem**: High-volume reporting queries create table lock contention, blocking active messaging and ticket writes.
*   **Decision**: Implement a CQRS-Lite model. Core state mutations run on primary master nodes, while reporting, exports, and dashboards are routed to read-only replicas.
*   **Alternatives Considered**:
    *   *Single-Node Database*: Rejected due to scalability bottlenecks and resource constraints.
*   **Trade-offs**: Requires managing replication lag and configure split-routing in application code.
*   **Consequences**: Safe horizontal scaling, high write throughput, and isolated analytical reporting queries.

---

### ADR-015: Asynchronous Background Worker Pools

*   **Status**: APPROVED  
*   **Context**: Tasks like evaluating SLAs, parsing emails, and dispatching webhooks can introduce latency or network delays.
*   **Problem**: Running high-overhead operations in the user request path degrades responsiveness and leads to client timeouts.
*   **Decision**: Offload intensive background tasks to dedicated, stateless background worker pools.
*   **Alternatives Considered**:
    *   *In-process Thread Spawning*: Rejected due to risk of memory leaks and worker thread exhaustion under heavy load.
*   **Trade-offs**: Requires managing background worker infrastructure and processing queues.
*   **Consequences**: Fast user API responses, predictable resource utilization, and isolated task processing.

---

### ADR-016: Database-Driven Business Hour & SLA Calculations

*   **Status**: APPROVED  
*   **Context**: SLA deadlines must factor in custom corporate business hours, holiday calendars, and timezone offsets.
*   **Problem**: Hardcoding business calendars in application engines makes it difficult to adjust policies without redeploying code.
*   **Decision**: Persist business hours, timezone configurations, and holiday tables directly in the database, evaluating SLA timers via SQL routines.
*   **Alternatives Considered**:
    *   *Application-Side Calculation Engines*: Rejected because they are difficult to maintain and sync across decentralized workers.
*   **Trade-offs**: Increases database computational load for calendar and timezone validations.
*   **Consequences**: Supports real-time SLA policy changes, ensures high accuracy, and simplifies multi-region calendar synchronization.

---

### ADR-017: Maker-Checker Approvals for Configuration Changes

*   **Status**: APPROVED  
*   **Context**: Adjusting global configurations (SLA targets, team assignments, system rules) can impact contractual compliance.
*   **Problem**: Unauthorized modifications or administrative typos can cause false SLA breaches, incorrect queue routing, and system instability.
*   **Decision**: Enforce Maker-Checker approvals (Dual-Authorization) on all major configuration tables, requiring proposed changes to be approved by a second manager.
*   **Alternatives Considered**:
    *   *Single-User Admin Access*: Rejected due to safety and regulatory compliance risks.
*   **Trade-offs**: Introduces operational delay, requiring second-person approvals for system adjustments.
*   **Consequences**: Prevents unauthorized modifications, minimizes administrative errors, and provides auditable logs.

---

### ADR-018: Optimistic Locking for Ticket State Operations

*   **Status**: APPROVED  
*   **Context**: Multiple support agents often review, tag, or assign the same ticket simultaneously.
*   **Problem**: Standard locking strategies (`FOR UPDATE`) block reader threads, creating queue lag and system timeouts.
*   **Decision**: Implement Optimistic Concurrency Control using version counters on all ticket updates.
*   **Alternatives Considered**:
    *   *Pessimistic Row Locking*: Rejected because it blocks reader threads and degrades concurrent workspace operations.
*   **Trade-offs**: If conflicts occur, agents must refresh their screens and re-apply changes.
*   **Consequences**: Fast, non-blocking reads and safe concurrent updates across active queues.

---

### ADR-019: Monthly Database Partitioning on Date Columns

*   **Status**: APPROVED  
*   **Context**: Logging, auditing, and message tables grow by millions of records each month, degrading index efficiency.
*   **Problem**: Massive B-Tree indexes waste RAM, slow down scans, and autovacuum operations can cause table locking loops.
*   **Decision**: Partition high-volume audit, logging, and message tables monthly on date columns using range partitioning.
*   **Alternatives Considered**:
    *   *Single Database Table*: Rejected due to indexing performance degradation and bloat over time.
*   **Trade-offs**: Composite primary keys must include the partitioning column, making unique index checks slightly more complex.
*   **Consequences**: Keeps active indexes small, speeds up cleanup operations, and supports fast partition pruning.

---

### ADR-020: JSONB Data Standard for Complex AI Metadata

*   **Status**: APPROVED  
*   **Context**: AI interactions generate diverse metadata structures (confidence statistics, citation lists, tokens counts, prompt variations).
*   **Problem**: Rigid relational tables require frequent schema migrations to support changing AI payload parameters.
*   **Decision**: Store complex AI metadata structures inside JSONB columns, supporting fast key-value querying.
*   **Alternatives Considered**:
    *   *Fully Normalized Relational Tables*: Rejected because of rigid schema structures and frequent migrations.
*   **Trade-offs**: Slightly larger disk footprints compared to strictly normalized relational rows.
*   **Consequences**: Unlocks schema flexibility, supports fast GIN index querying, and simplifies data model evolution.

---

## 5. DEPENDENCY AND INTERACTION MATRIX

The matrix below illustrates the dependency and integration points across active Architecture Decision Records:

```
                  ADR COOPERATIVE PIPELINES
  [UUIDv7 (ADR-008)] ─────> [Optimistic Locking (ADR-018)]
                                    │
                                    ▼
  [Outbox Event (ADR-010)] ──> [Idempotency (ADR-011)] ──> [EDA Decoupling (ADR-012)]
                                    │
                                    ▼
  [pgvector (ADR-007)] ─────> [AI Guardrails (ADR-004)]
```

*   **SLA Evaluations**: Depends on Database Calendars (ADR-016), background workers (ADR-015), and Outbox event dispatches (ADR-010) to update queues.
*   **Database Writes**: Combines UUIDv7 sequentially (ADR-008), Optimistic Locking (ADR-018), and monthly partitions (ADR-019) to maintain sub-15ms write times.
*   **Search and AI**: Integrates pgvector (ADR-007), Native FTS (ADR-005), and JSONB columns (ADR-020) to retrieve context while maintaining tenant RLS rules (ADR-009).

---

## 6. FUTURE ADR BACKLOG

The registry retains a structured backlog of future architectural evaluations scheduled for subsequent platform iterations:

1.  **Distributed pg_document_db Integration**: Evaluating native Document APIs inside PostgreSQL to handle massive semi-structured ticket payload exports.
2.  **Cross-Region Active-Active Replication**: Designing replication structures to support zero-latency writes for global multi-region support offices.
3.  **Real-Time CDC (Change Data Capture) via Debezium**: Upgrading outbox workers to use log-based CDC engines to stream database mutations directly to Kafka, bypassing active polling queries.

---

## 7. ARCHITECTURE EVOLUTION POLICY

To prevent architectural drift and ensure the ongoing stability of the platform, all architectural modifications must follow this evolution policy:

*   **Biannual Review Cycles**: The active ADR registry is reviewed by the architecture committee every 6 months to evaluate decision effectiveness and check for technical debt.
*   **Strict RLS Validation**: Any proposed ADR modifying database schema boundaries must prove that RLS tenant isolation is maintained.
*   **Zero-Downtime Migration Mandate**: Schema evolutions must support zero-downtime online migrations (e.g., using non-blocking index creations and dual-writing phases).

---

This document serves as the permanent architectural registry for the JUANET Support Platform. All physical schema designs, code patterns, and system integrations must align strictly with these records.
