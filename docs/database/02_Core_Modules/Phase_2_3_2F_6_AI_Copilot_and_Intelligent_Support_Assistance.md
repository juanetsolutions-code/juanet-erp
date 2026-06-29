# JUANET AI Copilot & Intelligent Support Assistance Specification
## Phase 2.3.2F.6 — AI Copilot and Intelligent Support Assistance
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & AI Systems Architecture  

---

## 1. AI ASSISTANCE PHILOSOPHY

The JUANET AI Copilot & Intelligent Support Assistance Engine is engineered upon the foundation of **Advisory AI**. The platform rejects direct machine-to-customer authoritative actions for operations with high blast radiuses or regulatory impacts. It enforces strict, auditable, human-in-the-loop (HITL) gates.

```
       [Raw Event / Message Ingress]
                    │
                    ▼
       [Stateless AI Request Pipeline]
                    │
                    ▼
   +──────────────────────────────────+
   |   Advisory-Only Generation       |
   |   - Vector Retrieval (RAG)       |
   |   - Confidence Score Calculated  |
   +──────────────────────────────────+
                    │
                    ▼
   +──────────────────────────────────+
   |      Human-in-the-Loop Gate      | <── [Enforces Verification / Edits]
   +──────────────────────────────────+
                    │
                    ▼
       [Operational Action / Ledger Commit]
```

### 1.1 Core Architecture Principles

*   **AI as Advisory, Never Authoritative**: The AI engine acts as an intelligence amplifier for human operators. It lacks the administrative privilege to transition ticket states, edit customer profiles, or alter transaction ledgers directly without explicit, validated human action.
*   **Human-in-the-Loop (HITL) Architecture**: High-impact actions (such as publishing public knowledge base articles, executing account lockouts, or dispatching customer resolution messages) must pass through a validated UI/API checkpoint where a human reviewer must explicitly accept, modify, or reject the AI-suggested payload.
*   **Explainability (XAI)**: Every AI-generated output must be accompanied by its logical lineage. This includes vector database search scores, exact source document citations, prompt template versions, and confidence thresholds. Agents must see *why* the AI made a recommendation.
*   **Confidence Scoring**: Every inference request must compute a deterministic confidence score (ranging from `0.00` to `1.00`). If a score falls below the capability-specific threshold, the system automatically suppresses the recommendation and logs a telemetry warning.
*   **Auditability**: Every AI inference, recommendation, acceptance, edit, or rejection must be logged in an immutable database audit trail (`public.ai_interaction_logs`). This ensures compliance under GDPR, CCPA, and regional AI governance directives.
*   **Deterministic Workflows**: AI models are strictly forbidden from executing unconstrained logic. Prompts are treated as code, tracked in version control, and combined with deterministic validation checks (e.g., regex pattern matching, schema enforcement) to guarantee safe outputs.
*   **Model Independence & Provider Abstraction**: The core engine communicates with external LLMs via a stateless, polymorphic adapter layer. The system must remain entirely vendor-neutral, allowing hot-swapping between Google Gemini, Anthropic Claude, OpenAI GPT, or local models (such as LLaMA) without modifying business logic.
*   **Zero Business Logic Inside Models**: LLM prompts must focus purely on syntax, summarization, and formatting. Operational constraints (such as entitlement rules, tenant routing, and security policies) are strictly enforced by the PostgreSQL database layer using RLS and application-level business guards.

### 1.2 Separation of AI Suggestions and System of Record (SoR)
To preserve ledger integrity, AI-generated outputs are persisted in dedicated, isolated tables (such as `public.ai_suggested_responses`) and are **strictly forbidden from directly modifying the core state of tickets, customers, or financial records**. This guarantees that:
1.  **Tamper-Proof Audit Logging**: System actions are attributed strictly to authenticated users or verified system daemons, preserving the forensic trail for SOC2 and ISO 27001 audits.
2.  **No AI-Induced Race Conditions**: Decoupling prevents external model latency from blocking Postgres transactions, avoiding database deadlock loops under high message volumes.
3.  **Data Quality Preservation**: Avoids pollutes CRM records with hallucinated entities or invalid formatting.

---

## 2. AI CAPABILITY MATRIX

The engine supports a structured catalog of intelligent capabilities. Each capability operates with defined input payloads, deterministic outputs, minimum confidence levels, and mandatory human authorization.

| Capability | Inbound Inputs | Outbound Outputs | Confidence Threshold | Human Approval Required? | Failure / Fallback Handling |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **Ticket Summarization** | Ticket metadata, messages stream | Chronological summary, key entities | `0.70` | No (Internal View Only) | Fallback to basic text concat of the last 3 messages. |
| **Suggested Replies** | Inbound message, ticket context, RAG hits | Draft reply, source citations | `0.80` | **YES** (Before Send) | Suppress draft; show default macro button to the agent. |
| **Knowledge Article Recs**| Message body, KB metadata index | Top 3 relevant article links, match scores | `0.65` | No (Suggestive UI) | Fallback to keyword-based Elasticsearch/Postgres FTS. |
| **Ticket Categorization**| Inbound customer message, historical logs| Predicted category ID, Confidence | `0.85` | No (Triage automation) | Assign to `triage_required` default queue. |
| **Priority Prediction** | Customer sentiment, historical SLA logs | Priority (`low`, `medium`, `high`, `urgent`)| `0.85` | No (Triaged automatically) | Default to `medium` priority. |
| **Sentiment Analysis** | Message text, metadata headers | Sentiment rating (`-1.00` to `+1.00`), indicators | `0.75` | No (Internal telemetry) | Default to `0.00` (neutral) score. |
| **Language Detection** | raw UTF-8 string | ISO 639-1 language code, Confidence | `0.90` | No (System routing) | Default to organization's fallback language (e.g., `en`).|
| **Translation** | Message text, source lang, target lang | Translated text string, source detected | `0.80` | **YES** (Before sending to client) | Display original untranslated text with warning banner. |
| **Duplicate Detection** | New ticket body, past 30 days active tickets| List of matching ticket IDs, similarity % | `0.85` | **YES** (Before merging) | Log warning flag internally; do not auto-merge tickets. |
| **Escalation Prediction** | Sentiment trend, SLA timestamps, keywords | Escalation flag, risk percentage | `0.80` | No (Alerts supervisor) | Log neutral escalation risk flag. |
| **SLA Breach Prediction** | Current ticket state, queue density, historical ART| Estimated resolution time, breach alert flag | `0.75` | No (FSM trigger) | Disable prediction flag; maintain standard SLA countdown. |
| **Root-Cause Clustering** | Survey comments, ticket logs (monthly) | Clustered themes, sentiment weights | `0.75` | **YES** (Before saving report) | Fallback to keyword-based manual reporting dashboard. |
| **Trend Detection** | Real-time message volume, anomaly metrics | Spike warning alert, trigger keywords | `0.80` | No (Dashboard alert) | Log baseline volume parameters. |

---

## 3. RETRIEVAL-AUGMENTED GENERATION (RAG)

The RAG engine connects unstructured organization data with LLM prompts, providing contextual accuracy and preventing model hallucinations.

```
                  +----------------------------------------------+
                  |  ORGANIZATIONAL DOCUMENTATION SOURCES        |
                  |  - public.kb_articles   - Product Specs      |
                  |  - Internal Policies    - Ticket History     |
                  +----------------------------------------------+
                                         │
                                         ▼
                  +----------------------------------------------+
                  |  DOCUMENT PIPELINE                           |
                  |  - Tokenizer Chunking (Max 512 tokens)       |
                  |  - Hierarchical Overlap (10% slice)          |
                  +----------------------------------------------+
                                         │
                                         ▼
                  +----------------------------------------------+
                  |  EMBEDDING MODEL                             |
                  |  - Input text to float vector (e.g. 1536d)   |
                  +----------------------------------------------+
                                         │
                                         ▼
                  +----------------------------------------------+
                  |  HYBRID SEARCH & RE-RANKING                  |
                  |  - pgvector Cosine similarity                |
                  |  - Full Text Search (FTS) / Keyword BM25     |
                  +----------------------------------------------+
                                         │
                                         ▼
                  +----------------------------------------------+
                  |  CONTEXT ASSEMBLY & SECURITY FILTERS         |
                  |  - Tenant Row-Level Security (RLS)           |
                  |  - Agent Permissions Validation              |
                  +----------------------------------------------+
```

### 3.1 Content Ingestion and Processing Pipeline

```
Raw Text ──> Clean (Remove HTML/Markdown) ──> Tokenize ──> Chunk (512 tokens) ──> Vector Embed ──> PG Vector Index
```

1.  **Knowledge Sources**: Data is extracted in real-time from active knowledge bases, release notes, enterprise policy documents, historical tickets, FAQs, and public product specs.
2.  **Hierarchical Overlapping Chunking**:
    *   Documents are processed into structured chunks with a maximum size of **512 tokens**.
    *   To maintain context across boundaries, chunks are generated with a **10% sliding overlap** (approximately 50 tokens).
    *   Code blocks, table structures, and visual JSON formats are preserved within singular chunks where possible to prevent formatting corruption.
3.  **Metadata Indexing**:
    *   Each chunk is assigned metadata properties stored in `public.kb_chunks`:
        ```json
        {
          "article_id": "kb_1234a567-b89c-12d3-a456-426614175000",
          "tenant_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
          "access_level": "public",
          "last_modified_at": "2026-06-29T07:15:00Z"
        }
        ```
4.  **Vector Embeddings**:
    *   Chunks are converted to dimensional float vectors (e.g., using a standard 1536-dimension embedding model) and stored in `public.kb_chunks.embedding` using PostgreSQL's `pgvector` extension.
5.  **Hybrid Vector-Keyword Retrieval**:
    *   Retrieval queries execute a combined search strategy:
        *   *Semantic Search*: Measures vector similarity using cosine distance (`<=>` operator):
            ```sql
            SELECT id, content, cosine_similarity
            FROM (
              SELECT id, content, 1 - (embedding <=> :query_vector) AS cosine_similarity
              FROM public.kb_chunks
              WHERE tenant_id = :tenant_id AND access_level = :access_level
            ) sub
            WHERE cosine_similarity > 0.70;
            ```
        *   *Keyword Search*: Runs standard PostgreSQL Full Text Search (FTS) using `tsvector` matching.
    *   Results are merged using **Reciprocal Rank Fusion (RRF)** to generate a final ranked context list.
6.  **Access Control and Tenant Isolation Filtering**:
    *   **CRITICAL CONSTRAINT**: The vector search query must explicitly enforce RLS.
    *   The retrieval process must filter results on the database layer to ensure chunks are never pulled from another organization's workspace, preventing cross-tenant leakage.

### 3.2 Citation & Freshness Standards
*   **Source Citations**: AI suggestions must include direct citations linking to source articles. Chunks are returned with `citation_source` parameters (e.g., `[KB Article: #1029]`).
*   **Freshness Thresholds**: Chunks associated with outdated articles (e.g., documents flagged with `is_deprecated = true` or those with no activity logs for over 180 days) are excluded from the RAG context.

---

## 4. AI REQUEST LIFECYCLE

The AI request pipeline operates as a sequence of stateless, secure validations and inferences managed by an event-driven orchestrator:

```
[Inbound API Request]
       │
       ▼
[Validation Phase]
  - Check schema format
  - Verify Tenant token
  - Rate limiting verification
       │
       ▼
[Context Assembly & RAG Retrieval]
  - Query pgvector index with active Tenant RLS
  - Redact customer PII (credit cards, passwords)
       │
       ▼
[Prompt Construction]
  - Load system prompt version
  - Inject context & sanitization wrappers
       │
       ▼
[Stateless Model Invocation]
  - Call external model (Timeout: 10s)
       │
       ▼
[Post-Response Safety Validation]
  - Strip markdown injection vectors
  - Validate output JSON structure
       │
       ▼
[Audit Logging]
  - Log token counts, latency, and model info
       │
       ▼
[Human Review Gate] ───────(Edits / Accepts)───────> [Dispatch Response]
```

### 4.1 Step-by-Step Request Lifecycle Transitions

#### 4.1.1 Validation
*   Validates the incoming JSON schema.
*   Verifies that the request carries a valid tenant token and that the user's role has permission to run the capability.
*   Applies token-bucket rate limits on the active user session.

#### 4.1.2 Context Assembly and Masking
*   Queries vector databases and historical tickets to assemble the prompt context.
*   **PII Masking**: Passes unstructured text through a regex-based pre-processor to replace sensitive data (such as emails, phone numbers, and credentials) with obfuscated placeholders (e.g., `[REDACTED_EMAIL]`).

#### 4.1.3 Prompt Engineering and Model Invocation
*   Assembles the prompt template using verified versions stored in `public.ai_prompts`.
*   Invokes the active model adapter. The orchestrator enforces a strict **10,000ms socket timeout**.
*   *Fallback Path*: If the primary LLM fails or times out, the system retries once using a secondary fallback model before logging a failure event.

#### 4.1.4 Post-Response Safety Audits
*   Inspects the model's response for prompt injection, markdown injection vectors, or unmasked confidential metadata.
*   Validates that the output matches the required schema (e.g., checking that categorization outputs map to valid database category IDs).

#### 4.1.5 Dispatch & Logging
*   Saves the suggestion to `public.ai_suggested_responses` and updates performance metrics.
*   Logs the interaction to `public.ai_interaction_logs` with a unique `correlation_id` for auditing.

---

## 5. AGENT COPILOT

The Agent Copilot integrates intelligence features directly into the active ticket workspace, helping agents respond faster and with greater accuracy.

```
  +---------------------------------------------------------------------------------+
  |                             AGENT COPILOT PANELS                                |
  +---------------------------------------------------------------------------------+
          │                                 │                                │
          ▼                                 ▼                                ▼
  +---------------+                 +---------------+                +---------------+
  | Live Drafting |                 | Summarization |                | Next-Best-Act |
  | - Autocomplete|                 | - Quick facts |                | - Suggests KB |
  | - Tone shifts |                 | - Key entities|                | - Route tips  |
  +---------------+                 +---------------+                +---------------+
```

### 5.1 Copilot Capabilities and Features
*   **Live Drafting & Autocomplete**: Suggests real-time autocomplete predictions as the agent types in the ticket editor, helping accelerate response times.
*   **Tone Adjustment & Grammar Improvements**: Allows agents to highlight text in the editor and adjust the tone (e.g., shift from "Technical/Direct" to "Empathetic/Reassuring") or fix grammar errors with a single click.
*   **Suggested Replies**: RAG-driven replies are automatically generated when a ticket is opened, providing agents with a pre-written starting draft.
*   **Contextual Summarizations**: Condenses long conversations into a concise bulleted summary, helping transitioning agents catch up on ticket context instantly.
*   **Next-Best-Action (NBA) Engine**: Recommends operational actions based on historical patterns (e.g., "This customer's request requires billing approval. Suggest routing the ticket to the Finance queue").

### 5.2 Acceptance and Edit Tracking
To evaluate Copilot performance, the system tracks how agents interact with suggested responses in the ticket workspace:
*   **Suggestion Accepted**: The agent inserts the draft into the editor unchanged.
*   **Suggestion Edited**: The agent inserts the draft but modifies the text before sending. The system calculates the edit distance using the Levenshtein algorithm:
    $$\text{Edit Distance \%} = \left( 1 - \frac{\text{Levenshtein}(S_{\text{suggested}}, S_{\text{sent}})}{\max(|S_{\text{suggested}}|, |S_{\text{sent}}|)} \right) \times 100$$
*   **Suggestion Rejected**: The agent closes the suggestion panel and writes their own response from scratch.

---

## 6. CUSTOMER SELF-SERVICE AI

Self-service capabilities allow customers to find answers autonomously using conversational interfaces, reducing queue wait times.

### 6.1 Conversational Chatbots and Intake Routines
*   **Intent-Driven Routing**: The chatbot processes user input to detect user intent (e.g., "Check order status", "Report service outage") and routes the session to the appropriate virtual flow.
*   **RAG Self-Service Assistance**: The bot queries the public knowledge base to answer incoming questions directly. If a high-confidence match is found, the system displays the solution with a link to the complete article.
*   **Unsupported Request Escalation**: If the user's inquiry cannot be resolved with a confidence score above 0.80, or if the user explicitly asks for help, the chatbot routes the session to a live agent.

### 6.2 Context-Preserved Handoffs
When escalating a self-service session, **the system must preserve the complete conversation history**. This prevents customer frustration by ensuring that:
1.  The agent receives the full transcript of the customer's interaction with the chatbot.
2.  The chatbot's detected intent, sentiment scores, and article suggestions are transferred to the agent's workspace.

---

## 7. AI SAFETY, GOVERNANCE & TENANT ISOLATION

Strict guardrails are enforced at the database and application levels to protect tenant isolation, prevent data leaks, and secure AI interactions.

```
       RAW USER INPUT
             │
             ▼
+-------------------------+
|  Prompt Injection Check | ──(Pattern Match/Abuse Found)──> [Block Request]
+-------------------------+
             │
             ▼
+-------------------------+
|  PII Scrubbing Gate     | ──(Masks SSNs, CCs, Passwords)
+-------------------------+
             │
             ▼
+-------------------------+
|  Tenant Isolation Check | ──(Enforces Database-level RLS context)
+-------------------------+
             │
             ▼
       SECURED INFERENCE
```

### 7.1 Security & Safety Control Specifications

*   **Prompt Injection Protection**: Ingress filters scan user messages for common prompt injection patterns (e.g., "Ignore previous instructions", "System override", "Act as a root user"). Flagged inputs are immediately blocked.
*   **Tenant Isolation Validation**: The system appends tenant-specific routing rules directly to database-level vector queries. This ensures that retrieval operations can never access files or context belonging to other organizations.
*   **PII & Sensitive Data Redaction**: Real-time scanners inspect outbound payloads to external AI endpoints, replacing sensitive entities (such as social security numbers, credit card details, and credentials) with safe placeholder blocks.
*   **Prompt Template Versioning**: System prompts are stored in `public.ai_prompts` and treated as immutable code records. This allows engineers to track changes and roll back prompt modifications when needed.
*   **Human Override Authority**: The system maintains a human override architecture. Agents can modify AI suggestions or manually re-route tickets, ensuring that final control rests with human team members.

---

## 8. AI OBSERVABILITY & QUALITY TELEMETRY

The AI engine tracks operational performance, usage costs, and response accuracy using structured telemetry tables:

```
  +---------------------------------------------------------------------------------+
  |                           OBSERVABILITY INDEXES                                 |
  +---------------------------------------------------------------------------------+
          │                                 │                                │
          ▼                                 ▼                                ▼
  +---------------+                 +---------------+                +---------------+
  | Usage Costs   |                 | Performance   |                | Drift Audits  |
  | - Token counts|                 | - Latency ms  |                | - Hallucinations|
  | - Cost estimation|              | - Accept rate |                | - Shift warning|
  +---------------+                 +---------------+                +---------------+
```

### 8.1 Observability Indicators and KPIs
*   **Token Usage Tracking**: Records input, output, and reasoning tokens consumed during each inference call, helping teams track operational costs.
*   **Latency Monitoring**: Logs response times across different models, helping identify performance bottlenecks.
*   **Accuracy and Acceptance Metrics**: Tracks suggestion acceptance and rejection rates per agent, helping identify prompts or models that require optimization.
*   **Hallucination Audits**: Flags discrepancies between generated responses and source citations, helping developers refine retrieval parameters.
*   **Drift Detection**: Analyzes semantic changes in customer inquiries over time, alerting engineers when model prompts may need updates to match shifting customer behaviors.

---

## 9. EVENT CONTRACTS

The AI platform uses event-driven integration contracts to broadcast completed actions and metrics updates to other internal modules:

### 9.1 Core Inferences & Suggestion Event Contracts

#### 9.1.1 `ai.reply.suggested`
*   **Trigger**: The AI engine generates a response suggestion for a ticket.
*   **Payload Schema**:
```json
{
  "event_id": "evt_7710a938-18ba-4abc-9922-51a221f110c5",
  "event_type": "ai.reply.suggested",
  "timestamp": "2026-06-29T07:20:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "suggestion_id": "sgg_1234a567-b89c-12d3-a456-426614178000",
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "confidence_score": 0.89,
    "model_utilized": "gemini-3.5-flash",
    "prompt_version": "v2.1"
  }
}
```

#### 9.1.2 `ai.summary.generated`
*   **Trigger**: A conversation summary is generated.
*   **Payload Schema**: Includes `ticket_id`, `summary_length_tokens`, and references to the source messages used.

#### 9.1.3 `ai.category.predicted`
*   **Trigger**: A ticket's category is predicted.
*   **Payload Schema**: Includes `ticket_id`, `predicted_category_id`, and the calculated confidence score.

#### 9.1.4 `ai.escalation.predicted`
*   **Trigger**: A high-risk escalation flag is detected.
*   **Payload Schema**: Includes `ticket_id`, `escalation_risk_score`, and key trigger indicators.

---

## 10. SECURITY, ACCESS CONTROL & GDPR COMPLIANCE

Security rules are enforced on all data pipelines to protect sensitive customer data and comply with global privacy rules.

*   **Row-Level Security (RLS)**: Enforces database-level isolation. AI search queries must explicitly carry the tenant's `organization_id` context, blocking unauthorized access to peer organizational data.
*   **Role-Based Access Control (RBAC)**: Restricts access to sensitive AI configurations (such as system prompt management and model definitions) to administrators and team leaders.
*   **GDPR Right-to-be-Forgotten Implementation**:
    *   **Data Scrubbing Workflows**: Upon receiving a valid GDPR deletion request, the system runs a database routine to purge personal data.
    *   Purges corresponding interaction logs from `public.ai_interaction_logs`.
    *   Scrubs PII from RAG chunk indexes, replacing names and identifiers with safe placeholder blocks.

---

## 11. PERFORMANCE ARCHITECTURE & COST OPTIMIZATION

The AI engine uses asynchronous processing, batching, and caching to support high transaction volumes while managing API costs:

*   **Asynchronous Inference Pipelines**: Heavy processing tasks (such as document embedding generation, multi-ticket categorization, and trend analysis) run on background workers, preventing blocking issues on user-facing threads.
*   **Response Streaming**: Supports streaming outputs (SSE) for agent autocomplete and drafting features, reducing perceived latency in the agent workspace.
*   **Request Caching**: Commonly requested data (such as product specs, policy documents, and generic FAQs) is cached in memory, reducing unnecessary and costly API calls to external models.
*   **Vector Index Tuning**: Uses IVFFlat or HNSW index strategies on PostgreSQL `pgvector` columns to speed up vector similarity searches on large datasets.

---

## 12. VALIDATION MATRIX

Below is the verification checklist to ensure the AI Copilot and Support Assistance Engine functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **Security** | Attacker attempting prompt injection inside customer message | **Blocked**. Injection detection triggers, blocking the input and logging an incident. |
| **Data Privacy** | Retrieval query executed without active tenant context | **Access Denied**. RLS triggers database-level blocks, preventing cross-tenant leakage. |
| **RAG Engine** | Querying with deprecated document sources | **Success**. Filters exclude outdated and deprecated knowledge chunks from prompts. |
| **Orchestration** | External LLM API timeout (>10,000ms) | **Graceful Fallback**. Aborts the request, runs fallback models, or displays error notices. |
| **Agent Copilot** | Agent edit tracking and distance calculation | **Calculated**. Correctly computes Levenshtein distances between suggested and sent texts. |
| **Compliance** | Deleting account and scrubbing RAG indexes | **Redacted**. purging PII data and metadata from interaction logs and chunk indexes. |

---

This document serves as the architectural reference for implementing AI Copilot assistance, RAG pipelines, safety guardrails, and quality telemetry within the JUANET Support Platform. All AI features must adhere strictly to these specifications.
