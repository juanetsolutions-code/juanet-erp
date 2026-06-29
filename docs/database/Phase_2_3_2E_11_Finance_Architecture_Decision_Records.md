# JUANET ERP Finance Architecture Decision Records
## Phase 2.3.2E.11 — Permanent Architectural Memory & Technical Decision Log
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, Principal ERP Architect, & Lead Software Governance Engineer  
**Classification:** Public / Enterprise Architectural Record and Technical Governance Manual  

---

## SECTION 1: PURPOSE & ROLE OF ARCHITECTURE DECISION RECORDS

In an enterprise-grade ERP system like JUANET, architectural design decisions have long-term consequences that affect database performance, security controls, and regulatory compliance. Codebases are refactored, dependencies are upgraded, and engineering teams change over time. Without a permanent record of the reasoning behind critical design choices, institutional knowledge is lost, leading to architectural drift and technical debt.

This **Architecture Decision Records (ADR)** manual serves as the permanent, authoritative memory for the JUANET Finance domain. It is designed to achieve the following:
*   **Preserve Institutional Knowledge**: Documents *why* specific architectures, data models, and protocols were selected, saving future engineers from repeating past investigations or breaking core invariants.
*   **Enforce Technical Governance**: Establishes a formal, auditable process for proposing, evaluating, and adopting major architectural changes.
*   **Accelerate Impact Analysis**: Helps development and database teams quickly determine the downstream consequences of modifying schemas, event contracts, or security profiles.
*   **Support Regulatory Audits**: Provides corporate compliance officers and external auditors (such as SOC 2 Type II or financial auditors) with clear evidence of security-by-design and compliance-by-design implementations.

### 1.1 ADRs vs. Technical Specifications
Unlike physical schemas or functional design specifications—which document *what* the system is and *how* it is configured—ADRs focus on the **strategic context, problem definitions, trade-offs, and reasoning** behind the selected paths.

---

## SECTION 2: ARCHITECTURE DECISION GOVERNANCE

To maintain consistency and technical rigor, all modifications to the JUANET Finance architecture must follow the standard ADR lifecycle and governance guidelines.

```
                          [ADR LIFECYCLE PATH]
                          
       Proposed ──► Under Review ──► Accepted ──► Deprecated
                                         │
                                         ▼ (Superseded by new ADR)
                                     Superseded
```

### 2.1 ADR Lifecycle Status Values
1.  **Proposed**: The record has been drafted and is undergoing initial engineering review and impact analysis.
2.  **Accepted**: The ARB has approved the decision, and the development teams must implement and maintain the design as documented.
3.  **Superseded**: A subsequent ADR has replaced this design, rendering it obsolete for future development while preserving historical context.
4.  **Deprecated**: The decision is still active in legacy systems but is flagged for removal in the next major release.
5.  **Rejected**: The proposal was evaluated and determined to be incompatible with platform standards or goals.

### 2.2 Governance and Approval Workflow
*   **Submission**: Engineers submit proposed ADRs as Markdown files within pull requests.
*   **Impact Review**: The Architecture Review Board (ARB) evaluates the proposal against security, performance, and compliance matrices.
*   **Sign-Off**: Approvals require unanimous sign-off from the Lead Database Architect, Principal security Engineer, and Director of Quality Engineering.
*   **Cross-Referencing**: All subsequent code files, database schemas, and integration contracts must reference the specific ADR IDs they implement.

---

## SECTION 3: STANDARD ADR TEMPLATE

Every Architecture Decision Record must use the following standardized structure to ensure clarity and consistency:

```
──────────────────────────────────────────────────────────────────
[ADR Identifier (e.g., ADR-001)]: [Title]
Status: [Proposed | Accepted | Superseded | Deprecated | Rejected]
Date: YYYY-MM-DD
Authors: [Names] | Approvers: [Names]

1. Context & Problem Statement
Describe the background, architectural constraints, and the specific 
engineering challenge that requires a design decision.

2. Decision & Technical Rationale
Document the selected solution clearly. Explain why this approach was 
chosen and how it resolves the challenge.

3. Alternatives Considered
* Alternative A: [Brief description and reasons for rejection]
* Alternative B: [Brief description and reasons for rejection]

4. Trade-offs, Benefits & Risks
* Benefits: [Direct technical advantages]
* Risks: [Downstream challenges or complexities introduced]
* Consequences: [Long-term impacts on performance or storage]

5. Related Specifications & Future Review Date
List downstream documents and define the next architecture audit schedule.
──────────────────────────────────────────────────────────────────
```

---

## SECTION 4: CORE ARCHITECTURE DECISION RECORDS

---

### ADR-001: UUID Primary Keys
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: High-scale multi-tenant SaaS ERP platforms require data distribution across distributed storage networks. Sequential integer IDs (BigInt auto-increment) are vulnerable to enumeration attacks and trigger write collisions during database migrations or cross-region database mergers.
*   **Decision**: Enforce Universally Unique Identifiers (UUIDv4) as the primary key standard across all financial tables.
*   **Alternatives Considered**: BigInt sequential serial keys (rejected due to multi-tenant merging conflicts).
*   **Consequences**: Eliminates primary key collisions during multi-entity database consolidation runs. Introduces slight B-Tree fragmentation, managed through custom fillfactors and regular concurrent reindexing.

---

### ADR-002: No PostgreSQL ENUM Types
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Financial document lifecycles and transaction states evolve over time. PostgreSQL native ENUM types are difficult to alter, modify, or extend without locking tables or executing risky ALTER commands on production databases.
*   **Decision**: Enforce standard VARCHAR(50) columns paired with lookup database tables or application-layer enum validations instead of native PostgreSQL ENUM types.
*   **Alternatives Considered**: Native PostgreSQL database ENUMs (rejected due to lock-risks during schema upgrades).
*   **Consequences**: Enables zero-downtime additions of new document states and transaction categories without locking active transaction tables.

---

### ADR-003: Lookup Tables Instead of Hardcoded Statuses
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Multi-country payroll, local tax policies, and financial compliance frameworks require localized and dynamic status definitions. Hardcoding transaction categories or status labels in application code makes localizations difficult and slows down compliance updates.
*   **Decision**: Use relational lookup tables containing unique keys, descriptions, and localization metadata to manage document states and classifications dynamically.
*   **Alternatives Considered**: Hardcoding statuses in application validation files (rejected due to localization barriers).
*   **Consequences**: Allows system-wide configuration updates, language translations, and compliance mappings to be applied at the database layer without code changes.

---

### ADR-004: General Ledger as the Single Source of Truth
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: ERP systems maintain various subledgers and trackers (Accounts Receivable, Inventory, Projects). Discrepancies between operational counts and financial bookkeeping lead to reporting errors and audit failures.
*   **Decision**: Enforce the General Ledger (GL) as the ultimate, authoritative system of record for all financial balances. All subsidiary subledgers must reconcile to GL records, and GL balances take precedence in audits.
*   **Alternatives Considered**: Distributed ledger models where subledgers compute independently (rejected due to reconciliation drift).
*   **Consequences**: Guarantees financial report consistency, simplifies statutory compliance audits, and prevents balance drift.

---

### ADR-005: Append-Only Financial Ledgers
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Financial accounting standards require audit logs of all historical transaction paths. Deleting or updating posted transaction lines is illegal under GAAP/IFRS and exposes systems to fraud.
*   **Decision**: Enforce append-only, write-once schemas for General Ledger and journal tables. Database triggers and security profiles must block all UPDATE and DELETE statements on these tables.
*   **Alternatives Considered**: Allowing logical deletion flags on ledger rows (rejected due to fraud risks).
*   **Consequences**: Secures transaction histories, simplifies compliance verification, and ensures complete, immutable audit logs.

---

### ADR-006: Double-Entry Bookkeeping Enforcement
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Ledger integrity depends on mathematical balance. Posting single-sided or unbalanced journal entries leads to reporting discrepancies and balance sheet imbalances.
*   **Decision**: Enforce strict double-entry balance checks at the database layer. Every posted transaction must verify that the sum of debit entries equals the sum of credit entries, rejecting unbalanced postings:
    $$\sum \text{Debits} - \sum \text{Credits} = 0$$
*   **Alternatives Considered**: Relying purely on client-side or application-layer balance validations (rejected due to database bypass risks).
*   **Consequences**: Ensures ledger integrity and guarantees trial balances always reconcile.

---

### ADR-007: Event-Driven Financial Architecture
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Monolithic, synchronous API calls between operational modules (Sales Checkout, Project Billings) and the ledger engine create tight coupling and increase performance bottlenecks under heavy transaction loads.
*   **Decision**: Decouple operational subsystems from the core ledger engine using an asynchronous, event-driven architecture. Subsystems emit business events, which the posting engine consumes and translates to ledger entries.
*   **Alternatives Considered**: Direct synchronous REST API mutations on the ledger tables (rejected due to coupling and performance risks).
*   **Consequences**: Enhances write scalability and isolates transactional failures, preventing operational outages from blocking core ledger writes.

---

### ADR-008: Transactional Outbox Pattern
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: In event-driven systems, database writes and event dispatches must succeed or fail together. If an invoice is saved but the event dispatcher fails, downstream systems (such as CRM or Tax) will go out of sync.
*   **Decision**: Implement the Transactional Outbox Pattern. Business mutations and corresponding outbox events are written atomically to active tables and `public.outbox_events` tables within a single database transaction.
*   **Alternatives Considered**: Direct, inline dispatching of events inside application API calls (rejected due to split-brain risks).
*   **Consequences**: Guarantees event delivery, preserves transactional integrity, and prevents cross-service synchronization drift.

---

### ADR-009: Idempotent Event Consumers
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Message brokers guarantee at-least-once delivery, meaning duplicate events will occur during network disruptions or cluster failovers. If a payment clearing event is processed twice, it will result in duplicate ledger postings.
*   **Decision**: Enforce idempotent event consumption. Consumers must log incoming message identifiers in `public.idempotent_consumers` tables within the processing transaction, skipping already-processed messages.
*   **Alternatives Considered**: Relying on upstream brokers to guarantee unique delivery (rejected due to distributed system constraints).
*   **Consequences**: Secures ledgers against duplicate updates, ensures transaction integrity, and simplifies message processing.

---

### ADR-010: Ledger Posting Rule Engine
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Operational developers are often unfamiliar with complex accounting standards. Allowing application-layer code to define accounting ledger distributions directly leads to misclassifications and compliance errors.
*   **Decision**: Implement a centralized, database-driven Posting Rule Engine. The engine captures business events and translates them into double-entry postings based on pre-authorized accounting configurations.
*   **Alternatives Considered**: Hardcoding ledger distributions directly inside operational code (rejected due to compliance risks).
*   **Consequences**: Decouples business logic from accounting policies, simplifies regulatory updates, and secures ledger classification accuracy.

---

### ADR-011: Financial Dimensions Instead of Chart of Accounts Explosion
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Tracking transaction metrics across departments, cost centers, regions, and projects can cause the Chart of Accounts (CoA) to balloon to tens of thousands of accounts, slowing search performance and complicating financial management.
*   **Decision**: Enforce a flat, structured Chart of Accounts and track organizational metrics using independent Financial Dimensions linked to ledger entries.
*   **Alternatives Considered**: Segmented account structures with hardcoded division prefixes (rejected due to scalability bottlenecks).
*   **Consequences**: Keeps the Chart of Accounts clean, enables granular reporting, and adapts easily to reorganizations.

---

### ADR-012: Revenue Recognition Engine (ASC 606 / IFRS 15)
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Modern contracts combine subscriptions, support, services, and hardware. Recognizing cash collections as immediate earnings violates ASC 606 and results in financial misstatements.
*   **Decision**: Implement an automated Revenue Recognition Engine. The engine structures deferred revenue schedules and manages monthly amortization runs based on contract performance obligations.
*   **Alternatives Considered**: Spreadsheet-based manual revenue calculations (rejected due to compliance risks).
*   **Consequences**: Ensures compliance with ASC 606/IFRS 15, automates audit reporting, and tracks contract lifecycles.

---

### ADR-013: Performance Obligations
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Revenue recognition under ASC 606 requires contracts to be split into distinct performance obligations, with revenue recognized as each obligation is met.
*   **Decision**: Enforce explicit tracking of performance obligations inside contract databases, linking each obligation to specialized revenue recognition rules.
*   **Alternatives Considered**: Treating contracts as single, unified billing items (rejected due to regulatory non-compliance).
*   **Consequences**: Enables accurate revenue recognition for complex multi-element arrangements, supporting global financial audits.

---

### ADR-014: Gapless Sequential Numbering
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Many tax authorities (such as European VAT regulators) require invoices and ledger journal postings to use continuous, gapless sequence numbers to prevent tax evasion or unrecorded cash transactions.
*   **Decision**: Enforce gapless sequential numbering for all posted billing documents and journal entries using pessimistic database locks during sequence generation.
*   **Alternatives Considered**: Relying on database identity sequences or client-side random numbers (rejected due to gap risks).
*   **Consequences**: Secures compliance with local tax regulations, preventing audit failures and transaction omissions.

---

### ADR-015: Immutable Invoice Snapshots
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Invoices are legally binding tax documents. If customer records or company addresses change, historical invoices must remain unaltered to preserve the original tax transaction state.
*   **Decision**: Generate and store static, immutable JSON snapshots of billing details on the invoice record at the time of issuance, preventing updates from altering historical documents.
*   **Alternatives Considered**: Rebuilding invoices dynamically on-the-fly using current relational tables (rejected due to data drift risks).
*   **Consequences**: Secures invoice immutability, preserves historical tax records, and ensures accurate document reproduction during audits.

---

### ADR-016: Multi-Currency Historical Exchange Rate Locking
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Multi-currency transactions must be converted using historical rates active on the transaction date (IAS 21). Relying on live rate updates for historical reporting introduces currency fluctuations and reporting drift.
*   **Decision**: Lock the exchange rate on the transaction record at the moment of booking, using the rate to convert transaction values for GL reporting.
*   **Alternatives Considered**: Calculating rates dynamically during report runs (rejected due to balance fluctuation risks).
*   **Consequences**: Prevents balance sheet fluctuations, secures historical transaction states, and complies with international accounting standards.

---

### ADR-017: Deferred Revenue Architecture
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Deferred revenue represents a liability until services are rendered, requiring specialized tracking and reconciliation to prevent double-counting or pre-mature recognition.
*   **Decision**: Structure deferred revenue as dedicated liability balances within GL ledger schemas, with amortization runs generating balancing debit entries to recognize revenues.
*   **Alternatives Considered**: Tracking deferred revenue purely on client-side dashboards (rejected due to balance sheet non-compliance).
*   **Consequences**: Guarantees accounting balance, simplifies audit validation, and tracks liability lifecycle states accurately.

---

### ADR-018: Accounting Period Locking
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Once financial statements are published, the accounting period must be locked. Back-posting transactions to closed periods modifies prior-year statements, violating corporate regulatory requirements.
*   **Decision**: Implement accounting period locks. The ledger engine must reject all postings to locked periods, with adjustments restricted to pre-authorized administrative override workflows.
*   **Alternatives Considered**: Relying on application-layer flags or policy agreements to prevent historical postings (rejected due to database bypass risks).
*   **Consequences**: Secures historical financial reporting, prevents prior-year balance modifications, and meets global accounting standards.

---

### ADR-019: Maker-Checker Approval Workflow
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: High-risk financial operations (manual entries, payments, risk policies) are susceptible to internal fraud if executed unilaterally.
*   **Decision**: Enforce Maker-Checker dual authorization controls. High-risk transactions are saved in a `'pending'` queue, requiring a separate, authorized checker to review and approve the transaction.
*   **Alternatives Considered**: Single-sign-off models for all transaction values (rejected due to fraud risk).
*   **Consequences**: Mitigates internal fraud risks, secures audit trails, and aligns with standard internal banking controls.

---

### ADR-020: Four-Eyes Authorization
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Large cash outlays or policy changes require authorization levels that scale based on transaction values.
*   **Decision**: Implement a dynamic Four-Eyes Authorization framework. Transaction approval hierarchies scale based on transaction thresholds, requiring multiple sign-offs for high-value operations.
*   **Alternatives Considered**: Static approval structures for all transaction values (rejected due to flexibility bottlenecks).
*   **Consequences**: Integrates security controls with corporate policy, reducing risk exposures.

---

### ADR-021: Cryptographic Hash Chaining
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: High-privilege attackers or compromised database administrators can alter database values directly, bypassing application audits.
*   **Decision**: Implement cryptographic SHA-256 hash chaining across ledger rows. Every ledger entry must contain a hash computed from its row contents and the hash of the preceding record in the ledger chain.
*   **Alternatives Considered**: Relying purely on standard database write logs (rejected due to administrator override risks).
*   **Consequences**: Provides tamper-detection capabilities, securing the audit trail against database-level modifications.

---

### ADR-022: Row-Level Security (RLS)
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Multi-tenant SaaS databases require strict data isolation to prevent cross-tenant data leaks.
*   **Decision**: Enforce PostgreSQL Row-Level Security (RLS) on all financial tables. All queries must filter by the active tenant session context, with database-level policies blocking access to other tenants' data.
*   **Alternatives Considered**: Filtering data purely using application-layer WHERE clauses (rejected due to developer coding error risks).
*   **Consequences**: Guarantees multi-tenant isolation, secures sensitive financial data, and complies with international privacy regulations.

---

### ADR-023: Optimistic Concurrency Control
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Concurrent balance updates (such as rapid customer checkouts updating available credit) can cause race conditions, resulting in inaccurate balances.
*   **Decision**: Implement Optimistic Concurrency Control (OCC) using version columns. Queries must verify the record version before updating, rolling back transactions if conflicts are detected.
*   **Alternatives Considered**: Pessimistic row-locking for all reads (rejected due to performance and lockup risks).
*   **Consequences**: Prevents balance race conditions while maintaining high query throughput.

---

### ADR-024: JSONB Usage Guidelines
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Unstructured metadata and configurations require schema flexibility. However, using JSONB columns for core relational fields (such as debit/credit amounts) slows down queries and breaks relational integrity.
*   **Decision**: Restrict the use of JSONB columns to unstructured metadata and configurations (e.g., event payloads). Core financial transactions and relational identifiers must use structured database columns.
*   **Alternatives Considered**: Fully unstructured NoSQL-style document databases (rejected due to relational integrity requirements).
*   **Consequences**: Preserves database query performance and relational integrity, while maintaining schema flexibility.

---

### ADR-025: Declarative Partitioning
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: High-volume tables (ledger, audit logs) can grow to hundreds of millions of rows, slowing indexes and degrading write performance.
*   **Decision**: Implement PostgreSQL Declarative Partitioning on high-volume tables. Range partitioning is applied to date-based tables, while hash partitioning is used for high-concurrency lookup tables.
*   **Alternatives Considered**: Keeping all data in single, massive unpartitioned tables (rejected due to scalability constraints).
*   **Consequences**: Optimizes index depths, speeds up date-range queries via partition pruning, and simplifies data retention processes.

---

### ADR-026: Materialized Financial Reporting
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Generating financial reports (P&L, Balance Sheet) by scanning millions of ledger rows dynamically saturates database CPU and memory, slowing performance.
*   **Decision**: Pre-compute and cache financial reports using PostgreSQL Materialized Views (MVs), refreshing views concurrently on periodic schedules.
*   **Alternatives Considered**: Live, dynamic querying of transaction tables for all report requests (rejected due to performance degradation risks).
*   **Consequences**: Delivers sub-second reporting performance while isolating reporting loads from transactional tables.

---

### ADR-027: CQRS for Reporting
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Running complex analytical queries on primary database nodes can lock tables, slowing transactional writes.
*   **Decision**: Enforce Command Query Responsibility Segregation (CQRS). Transactional writes write to primary OLTP nodes, while reporting queries read from materialized views or analytical schemas.
*   **Alternatives Considered**: Executing transactional and analytical operations on the same database tables (rejected due to lockup risks).
*   **Consequences**: Decouples write and read performance, securing transaction throughput.

---

### ADR-028: Read Replicas for Analytics
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: High-concurrency reporting, AI forecasting, and ad-hoc analytics can overwhelm primary database resources.
*   **Decision**: Route all ad-hoc queries, reports, and AI processes to dedicated read-only replicas, keeping the primary node isolated for transactional writes.
*   **Alternatives Considered**: Executing all analytical queries on primary database nodes (rejected due to performance degradation risks).
*   **Consequences**: Isolates transactional workloads, secures sub-second write latencies, and supports high-volume analytics.

---

### ADR-029: Financial Consolidation Strategy
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Multi-entity parent groups require consolidated financial statements, requiring cross-subsidiary data translation and intercompany transaction eliminations.
*   **Decision**: Implement a modular consolidation engine. The engine processes consolidation runs in a separate schema, pulling subsidiary data, running eliminations, and translating currencies.
*   **Alternatives Considered**: Merging all subsidiary ledgers into a single, shared database table (rejected due to data isolation and local compliance barriers).
*   **Consequences**: Preserves local subsidiary data isolation, while providing accurate group financial reporting.

---

### ADR-030: Treasury Decoupling
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Treasury operations (liquidity management, investments, debt covenants) use highly specialized models that differ from daily sales or project billings.
*   **Decision**: Decouple the treasury subsystem from daily billing operations, integrating modules via asynchronous event contracts.
*   **Alternatives Considered**: Integrating treasury features directly into billing modules (rejected due to domain coupling).
*   **Consequences**: Simplifies software maintenance and isolates treasury risk management.

---

### ADR-031: Bank Reconciliation Engine
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Manual reconciliation of bank statements with ERP ledger entries is slow, error-prone, and susceptible to fraud.
*   **Decision**: Implement an automated Bank Reconciliation Engine. The engine parses standard bank statement formats (such as ISO 20022 camt.053) and matches transactions based on configurable matching heuristics.
*   **Alternatives Considered**: Purely manual reconciliation workflows (rejected due to inefficiency and security risks).
*   **Consequences**: Accelerates period close timelines, reduces manual errors, and improves cash tracking accuracy.

---

### ADR-032: Tax Engine Decoupling
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Tax rates, jurisdictions, and rules change frequently across regions, requiring continuous software updates.
*   **Decision**: Decouple the tax engine from billing modules, exposing tax calculation services via distinct, localized APIs.
*   **Alternatives Considered**: Hardcoding tax logic inside billing modules (rejected due to maintenance overhead).
*   **Consequences**: Decouples billing systems from regional tax changes, simplifying platform updates.

---

### ADR-033: Performance Monitoring Strategy
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Database bottlenecks, slow queries, and lockups can occur as transaction volumes scale.
*   **Decision**: Implement a database telemetry monitoring strategy, tracking query latencies, lock contentions, and replica lag with automated alerting.
*   **Alternatives Considered**: Relying purely on basic, application-level error logs (rejected due to observability gaps).
*   **Consequences**: Delivers deep database observability, allowing operations teams to identify and resolve performance issues before they affect users.

---

### ADR-034: Compliance by Design
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Retrofitting compliance controls (such as audit logs or segregation of duties) after system launch is expensive and prone to architectural gaps.
*   **Decision**: Integrate international compliance controls (IFRS, GAAP, SOC 2, PCI DSS) directly into core database layouts, schemas, and API constraints.
*   **Alternatives Considered**: Managing compliance rules purely through manual audits and policy agreements (rejected due to enforcement risks).
*   **Consequences**: Enforces compliance at the platform layer, reducing audit overhead and risk exposures.

---

### ADR-035: Security by Design
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Financial data systems are primary targets for cyber attacks, requiring strong, integrated security controls.
*   **Decision**: Integrate security controls (such as database-level RLS, field encryption, and role isolation) directly into infrastructure and schema configurations, avoiding reliance on application-layer logic.
*   **Alternatives Considered**: Building security filters solely within client-facing code (rejected due to bypass risks).
*   **Consequences**: Minimizes attack surfaces, secures sensitive financial records, and protects systems against compromise.

---

### ADR-036: Testing Before Release
*   **Status**: Accepted | **Date**: 2026-06-29  
*   **Context**: Deploying untested updates to financial systems can trigger data corruption, balances imbalances, or compliance breaches.
*   **Decision**: Enforce automated testing validation gates in the CI/CD pipeline, blocking production deployments that do not pass unit, integration, and balance reconciliation checks.
*   **Alternatives Considered**: Relying on manual testing and post-deployment bug patching (rejected due to data corruption risks).
*   **Consequences**: Secures production deployment safety, preventing data imbalances and service disruptions.

---

## SECTION 5: DECISION DEPENDENCY MATRIX

Architectural decisions in the financial domain are highly integrated. The matrix below traces the prerequisites, dependencies, and cascading impacts of core decisions:

```
                  [CRITICAL DECISION HIERARCHY]
                  
                 [ ADR-005: Append-Only Ledgers ]
                                │
                                ▼
               [ ADR-006: Double-Entry Balancing ]
                                │
                                ▼
               [ ADR-021: Cryptographic Hash Chaining ]
                                │
                                ▼
               [ ADR-026: Materialized Reporting ]
```

*   **ADR-005 (Append-Only Ledgers)**:
    *   *Prerequisites*: None.
    *   *Downstream Dependencies*: ADR-006 (Double-Entry balancing), ADR-021 (Cryptographic Hash Chaining), ADR-026 (Materialized Reporting).
*   **ADR-007 (Event-Driven Architecture)**:
    *   *Prerequisites*: None.
    *   *Downstream Dependencies*: ADR-008 (Transactional Outbox), ADR-009 (Idempotent Event Consumers), ADR-010 (Posting Rule Engine).
*   **ADR-011 (Financial Dimensions)**:
    *   *Prerequisites*: ADR-004 (GL as Source of Truth).
    *   *Downstream Dependencies*: ADR-026 (Materialized Reporting), ADR-029 (Consolidation).
*   **ADR-022 (Row-Level Security)**:
    *   *Prerequisites*: ADR-001 (UUID Primary Keys).
    *   *Downstream Dependencies*: ADR-028 (Read Replicas), ADR-035 (Security by Design).

---

## SECTION 6: RETROSPECTIVE OF REJECTED ALTERNATIVES

To provide context for future architects, we document the major technical approaches that were evaluated and rejected during the design phase:

1.  **Native PostgreSQL ENUMs**:
    *   *Why Considered*: Enforces data consistency at the database engine level.
    *   *Why Rejected*: Modifying ENUM values requires database schema locks, risking downtime.
    *   *Risks Avoided*: Production table lockouts during routine status updates.
2.  **Direct Ledger Writes from Operational Modules**:
    *   *Why Considered*: Simplifies initial software development.
    *   *Why Rejected*: Couples business modules with accounting logic, leading to data drift.
    *   *Risks Avoided*: Accidental ledger postings and balance imbalances from operational bugs.
3.  **Mutable Journal Entries**:
    *   *Why Considered*: Simplifies the correction of data-entry errors.
    *   *Why Rejected*: Violates accounting standards (GAAP/IFRS) and exposes systems to fraud.
    *   *Risks Avoided*: Regulatory non-compliance and audit failures.
4.  **Database Triggers for Complex Business Workflows**:
    *   *Why Considered*: Guarantees validation execution directly in the database.
    *   *Why Rejected*: Complex database triggers are difficult to test, debug, and scale under heavy loads.
    *   *Risks Avoided*: Hidden performance bottlenecks and debugging difficulties.
5.  **Offset-Based Pagination for High-Volume Reporting**:
    *   *Why Considered*: Easy to implement using standard SQL query syntax.
    *   *Why Rejected*: Performance degrades linearly as offset values increase, saturating database memory.
    *   *Risks Avoided*: Database query timeouts and performance bottlenecks during large report runs.

---

## SECTION 7: FUTURE DECISION REGISTER

As the platform evolves, several planned modules will require formal Architectural Decision Records. The placeholders below trace the future roadmap:

*   **ADR-037: Fixed Asset Depreciation Algorithms**: Selecting depreciation models (Straight-Line, Declining Balance) and tracking asset lifetimes.
*   **ADR-038: Lease Valuation Mechanics (IFRS 16)**: Standardizing present-value calculation models for corporate lease liabilities.
*   **ADR-039: ESG & Carbon Accounting Ledger**: Structuring non-financial databases to track environmental impact metrics alongside financial records.
*   **ADR-040: Real-Time Continuous Close Engine**: Designing background reconciliation systems to automate close processes.
*   **ADR-041: Digital Asset and Cryptocurrency Subledger**: Managing cryptocurrency holdings, cost bases, and valuations.

---

## SECTION 8: ARCHITECTURE EVOLUTION POLICY

To prevent architectural drift while allowing systems to adapt to changing requirements, the platform enforces the **Architecture Evolution Policy**:

1.  **Superseding a Decision**: If an accepted ADR must be modified or replaced, engineers must draft a new ADR that explicitly references the old record, changing the legacy ADR status to `Superseded`.
2.  **Backward Compatibility**: New design proposals must include detailed migration plans, verifying that updates do not break legacy integrations or corrupt historical data.
3.  **Safe Deprecation Process**: Deprecated features must be maintained in read-only mode for one full release cycle, allowing dependent teams to transition to the new architecture.

---

## SECTION 9: ARCHITECTURAL DECISIONS MATURITY ASSESSMENT

An executive assessment of the platform's architectural maturity:

*   **Consistency of Decisions**: **High**. Design decisions are aligned with core principles (such as ledger immutability and multi-tenant isolation) across all modules.
*   **Governance Maturity**: **High**. Enforces formal ADR lifecycles, ARB review gates, and traceability tracking, securing architectural consistency.
*   **Extensibility**: **High**. Decouples core systems from localized configurations and external APIs, adapting easily to future business expansions.
*   **Risk Profile**: **Low**. Mitigates technical risks (such as data corruption, security breaches, or compliance failures) through robust, platform-layer controls.

---

## SECTION 10: APPENDIX & REFERENCE INDEX

### 10.1 Status Glossary
*   **Accepted**: Approved for development and active across all modules.
*   **Proposed**: Drafted and undergoing engineering review.
*   **Superseded**: Replaced by a subsequent design record.
*   **Deprecated**: Flagged for removal in upcoming releases.

### 10.2 Revision History

| Date | Document Version | Author / Reviewer | Summary of Changes | Approved By |
| :--- | :--- | :--- | :--- | :--- |
| **2026-06-29** | `1.0` | Solutions Architecture Team | Initial compilation and release of the ADR manual. | ARB Board |

### 10.3 Architecture Review Checklist
Before any financial software update or schema migration is approved, quality engineers must verify that the proposal passes the following checks:
*   [ ] The update does not execute direct `UPDATE` or `DELETE` operations on active ledger tables.
*   [ ] Multi-tenant Row-Level Security (RLS) is active and enforced on all new tables.
*   [ ] Relational integrity is preserved, with foreign keys indexed to prevent lock conflicts.
*   [ ] The update does not introduce synchronous REST calls inside transactional database blocks.
*   [ ] Mathematical balancing checks are active and validated.
*   [ ] The change has been logged, tracing to an accepted ADR record.
*   [ ] Automated unit, integration, and regression tests pass without errors.
