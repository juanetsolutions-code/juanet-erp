# JUANET Omnichannel Communication & Conversation Engine Specification
## Phase 2.3.2F.4 — Omnichannel Communication Engine
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Communication Architecture  

---

## 1. COMMUNICATION ARCHITECTURE PHILOSOPHY

The JUANET Omnichannel Communication & Conversation Engine governs the ingestion, routing, delivery, and persistence of all inbound and outbound customer messages across the entire enterprise platform.

```
+---------------------------------------------------------------------------------+
|                         OMNICHANNEL INGESTION GATEWAY                           |
|       (Email Parser, Webhook Receivers, WebSocket Brokers, API Consumers)       |
+---------------------------------------------------------------------------------+
                                         │
                                         ▼
+---------------------------------------------------------------------------------+
|                    STATELESS PROCESSING & PIPELINE ENGINES                      |
| (Virus Scan, Sanitization, Threat Vector Extraction, SPF/DKIM/DMARC, De-dupe)   |
+---------------------------------------------------------------------------------+
                                         │
                        ┌────────────────┴────────────────┐
                        ▼                                 ▼
+-----------------------------------------------+ +-------------------------------+
|           CONVERSATION ENGINE (SoR)           | |    TICKET WORKFLOW ENGINE     |
|   - Conversation Continuity Layer             | |    - State Transitions (FSM)  |
|   - Immutable Message Store (Events)          | |    - SLAs & Routing Rules     |
|   - Multi-Tenant Row-Level Isolation (RLS)    | |    - Assignments & Queues     |
+-----------------------------------------------+ +-------------------------------+
```

The system is engineered upon the following architectural foundations:

### 1.1 Core Principles

*   **Conversation as the System of Record (SoR)**: The conversation is the authoritative ledger of customer interactions. It is represented as a stream of immutable, sequence-ordered events. Operational records (such as tickets) exist solely as workflow states layered on top of this conversation stream.
*   **Messages as Immutable Events**: Once a message is committed to `public.ticket_messages`, it can never be updated or physically deleted, except under specific compliance processes (such as GDPR). Typos, corrections, or edits are appended as new events, preserving the original audit trail.
*   **Ticket as Workflow**: A ticket is not a conversation; it is a metadata wrapper. A single conversation can span multiple tickets over time (e.g., closing a billing inquiry, then opening a follow-up ticket months later). The conversation history remains continuous, while ticket records transition through their workflow states.
*   **Channel Independence**: The core engine treats all incoming communications as standardized payloads. Channel-specific quirks (such as SMS character limits or WhatsApp template rules) are abstracted by inbound/outbound adapters at the edge, ensuring the core database remains clean and uniform.
*   **Conversation Continuity Across Channels**: A customer should be able to start an interaction on Live Chat, receive updates via WhatsApp, and reply via Email, with the engine unifying all messages into a single conversation timeline.
*   **Stateless Processing**: Edge nodes and worker services do not hold connection or message state in local memory. All routing, validation, and parsing logic is calculated using database parameters, allowing the engine to scale horizontally.
*   **Asynchronous, Event-Driven Architecture**: Processing pipelines (such as virus scanning, AI summarization, and translation) run asynchronously using an event outbox pattern. This ensures that the primary transactional thread is never blocked by external APIs or compute-heavy workloads.
*   **Comprehensive Auditability**: Every system decision (such as automated routing, template selection, and delivery state changes) is logged with a high-resolution timestamp, system identifier, and user attribution.

### 1.2 Separation of Channel State and Ticket State
In legacy systems, individual channels often "own" their ticket states, leading to fragmented customer experiences. Under the JUANET architecture, **channels are strictly forbidden from owning or altering ticket state directly**. This rule is enforced for several reasons:
1.  **Workflow Consistency**: Ticket state is governed by a unified Finite State Machine (FSM). Allowing external channels (like an email bounce or WhatsApp webhook retry) to modify ticket states directly bypasses validation rules, leading to state corruption.
2.  **SLA Integrity**: SLAs depend on clean, standardized transition timestamps. Letting a channel alter a ticket's state directly can result in inaccurate SLA breach alerts.
3.  **Audit Fidelity**: Ticket transitions must be verified, authorized, and logged alongside the user's role. Channel adapters operate as system processes and lack the context required to authorize core workflow modifications.

---

## 2. SUPPORTED COMMUNICATION CHANNELS

The gateway integrates with a variety of communication channels. The grid below outlines the specific capabilities, limitations, and requirements for each channel:

| Channel | Inbound Payload Capabilities | Outbound Delivery Capabilities | Attachment Limits / Support | Micro-Interactions Supported | Delivery / Read Signals |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Email** | MIME, HTML, Rich Text, Calendars | Plain Text, Sanitized HTML | Max 25MB (cumulative). Standard file types. | None | SMTP DSN, Bounce Detect |
| **Live Chat** | Markdown, UTF-8 Emojis | Markdown, Formatted Text | Max 10MB per file. Images, PDFs, Logs. | Typing Indicators, Reactions | Client WebSocket receipts |
| **WhatsApp** | Text, Location, Contact, Media | Template Messages, Media | Max 16MB. PNG/JPG, PDF, MP4. | Typing Indicators (Simulated) | Sent, Delivered, Read |
| **SMS** | Plain GSM-7 / UCS-2 Text | Plain Text (Segmented) | No attachments. | None | Provider delivery receipts |
| **Voice** | SIP Signaling, WAV streams | Audio streams, IVR inputs | Post-call Call Detail Records (CDRs) / Audio Logs | Call-state signaling | Connect, Disconnect logs |
| **Messenger** | Text, Quick Replies, Media | Text, Templates, Media | Max 25MB. Images, Video, Audio. | Typing Indicators, Reactions | Delivered, Read receipts |
| **Telegram** | Text, Custom Commands, Media | Markdown, Inline Buttons | Max 50MB. All file extensions. | Typing Indicators, Reactions | Sent, Read receipts |
| **MS Teams** | Adaptive Cards, Rich Text | Adaptive Cards, Rich Text | Max 100MB (OneDrive/SharePoint link).| Typing Indicators, Reactions | Sent, Read receipts |
| **Slack** | Block Kit JSON, Markdown | Block Kit JSON, Markdown | Max 100MB (via Slack files API). | Typing Indicators, Reactions | Sent, Read receipts |
| **Web Widget**| Markdown, Custom Forms | Markdown, Rich Text | Max 15MB. Standard file types. | Typing Indicators, Presence | Delivered, Read receipts |
| **Mobile SDK**| Markdown, JSON metadata | Markdown, Push Notifications | Max 20MB. Images, Logs, JSON. | Typing Indicators, Presence | Delivered, Read receipts |
| **API Clients**| Arbitrary JSON, Binary | Arbitrary JSON, Outbox | Max 50MB (streamed via S3). | None | Sync Response, Outbox status |

---

## 3. CONVERSATION ENTITY RELATIONSHIP MODEL

The conversation model utilizes a structured hierarchy to organize participants, messages, and delivery states:

```
                  +-----------------------------+
                  |     public.tickets          |
                  +-----------------------------+
                                 │
                                 ▼ 1:1
                  +-----------------------------+
                  |  public.conversations       |
                  +-----------------------------+
                                 │
         ┌───────────────────────┴───────────────────────┐
         ▼ 1:N                                           ▼ 1:N
+--------------------+                         +--------------------+
| public.participants|                         |  public.messages   |
+--------------------+                         +--------------------+
                                                         │
                                        ┌────────────────┴────────────────┐
                                        ▼ 1:N                             ▼ 1:N
                               +--------------------+            +--------------------+
                               | public.attachments |            | public.delivery_sts|
                               +--------------------+            +--------------------+
```

### 3.1 Entity Boundaries & Declarative Ownership

*   **`public.conversations`**:
    *   **Scope**: The root container for a unified stream of interactions.
    *   **Attributes**: `id`, `organization_id`, `channel_type`, `created_at`, `closed_at`.
    *   **Boundary**: Owns the lifecycle of participants and messages. It is linked to a ticket, but its existence is independent.
*   **`public.participants`**:
    *   **Scope**: Represents an entity engaged in the conversation.
    *   **Attributes**: `id`, `conversation_id`, `user_type` (`agent`, `customer`, `system_bot`, `third_party`), `user_id`, `joined_at`, `left_at`.
    *   **Boundary**: Maps internal user tables (`security.users`) or external client tables (`crm.contacts`) to the active conversation context.
*   **`public.ticket_messages`**:
    *   **Scope**: An individual message payload.
    *   **Attributes**: `id`, `conversation_id`, `sender_participant_id`, `body_text`, `body_html`, `body_markdown`, `metadata` (JSONB), `is_internal_note`, `created_at`.
    *   **Boundary**: Holds the immutable text payload and processing metadata.
*   **`public.ticket_attachments`**:
    *   **Scope**: File metadata associated with a specific message.
    *   **Attributes**: `id`, `message_id`, `file_id` (references centralized `public.files`), `file_name`, `file_size`, `content_type`.
    *   **Boundary**: Decouples binary file details from the core message table, referencing the platform's central storage system.
*   **`public.message_delivery_statuses`**:
    *   **Scope**: Real-time delivery status of a message.
    *   **Attributes**: `id`, `message_id`, `participant_id`, `status` (`pending`, `sent`, `delivered`, `read`, `failed`), `error_code`, `updated_at`.
    *   **Boundary**: Allows tracking the delivery status of a single outbound message across multiple recipients (e.g., in Slack or WhatsApp group chats).

---

## 4. CONVERSATION LIFECYCLE FINITE STATE MACHINE

Every conversation transitions through a controlled Finite State Machine (FSM) to ensure response speed, proper ownership, and secure closure.

```
       [Created] ──> [Waiting] ──> [Assigned] ──> [In Progress] ──> [Resolved] ──> [Closed]
                       │              │              │                 ▲
                       │              ▼              ▼                 │
                       └─────────> [Customer Replied] ─────────────────┘
```

### 4.1 State Catalog & Transition Rules

#### 4.1.1 `waiting`
*   **Purpose**: The conversation is newly ingested and awaiting queue allocation or manual triage.
*   **Allowed Entry Transitions**: None (origin state).
*   **Allowed Exit Transitions**: `assigned`, `closed`.
*   **Forbidden Transitions**: Direct transition to `resolved` or `archived`.
*   **Timeout Rules**: If a conversation remains in this state for more than 15 minutes, it escalates to the queue manager.

#### 4.1.2 `assigned`
*   **Purpose**: An agent or automated system has taken ownership of the conversation.
*   **Allowed Entry Transitions**: `waiting`, `customer_replied`.
*   **Allowed Exit Transitions**: `in_progress`, `resolved`, `closed`.
*   **Forbidden Transitions**: None.

#### 4.1.3 `customer_replied`
*   **Purpose**: The customer has sent a message, and the conversation is awaiting an agent's response.
*   **Allowed Entry Transitions**: `assigned`, `in_progress`.
*   **Allowed Exit Transitions**: `agent_replied`, `closed`.
*   **SLA Impact**: Response SLA timers are active.

#### 4.1.4 `agent_replied`
*   **Purpose**: An agent has responded, and the conversation is awaiting customer action or confirmation.
*   **Allowed Entry Transitions**: `customer_replied`, `assigned`.
*   **Allowed Exit Transitions**: `customer_replied`, `resolved`, `closed`.
*   **Timeout Rules**: If the conversation remains in this state for more than 72 hours without response, it auto-resolves.

#### 4.1.5 `resolved`
*   **Purpose**: The issue has been addressed, and the conversation is in a temporary cooling-off period.
*   **Allowed Entry Transitions**: `agent_replied`, `in_progress`.
*   **Allowed Exit Transitions**: `customer_replied` (reopened), `closed`.

#### 4.1.6 `closed`
*   **Purpose**: The conversation is complete. No further modifications are permitted.
*   **Allowed Entry Transitions**: `resolved`, `agent_replied`.
*   **Allowed Exit Transitions**: `archived`.
*   **Security Standard**: Message edits and new attachments are disabled.

#### 4.1.7 `archived`
*   **Purpose**: Read-only historical record. Removed from active operational views.
*   **Allowed Entry Transitions**: `closed`.
*   **Allowed Exit Transitions**: None.

---

## 5. EMAIL PROCESSING PIPELINE

The email processing gateway parses inbound messages, verifies sender records, sanitizes payloads, and assigns threads.

```
Inbound SMTP ──> SPF/DKIM/DMARC ──> Virus Scan ──> Thread Match ──> HTML Sanitizer ──> DB Commit
```

### 5.1 Step-by-Step Processing Pipeline

#### 5.1.1 Inbound SMTP Parsing & Validation
1.  **DMARC, SPF, and DKIM Checks**:
    *   The gateway extracts the sender's domain and verifies alignment using DNS checks.
    *   If SPF/DKIM validation fails, the email is routed to a quarantine queue with `security_failed: true` logged in its metadata.
2.  **Anti-Virus Scanning**:
    *   Attachments are streamed to a local virus scanner.
    *   If a threat is detected, the attachment is rejected, and a security warning is logged in the conversation history.
3.  **Spam Filtering**:
    *   Calculates a spam score based on header quality and text patterns.
    *   Emails with a spam score above 7.0 are automatically marked as `spam_classified` and archived.

#### 5.1.2 Thread Identification and Matching
To associate incoming emails with the correct conversation, the engine applies the following checks in order:

1.  **In-Reply-To Header**: Resolves the unique `Message-ID` of the previous email in the thread.
2.  **References Header**: Scans the list of parent message IDs to locate a matching conversation.
3.  **Subject-Line Pattern Matching**: Scans the subject text for ticket codes matching the pattern `\[JUANET-#\d+\]`.
4.  **Sender Validation**: If no thread markers are found, the engine creates a new conversation under the sender's organization ID.

#### 5.1.3 Content Extraction and Sanitization
*   **HTML Sanitization**: To protect against XSS and injection attacks, HTML bodies are processed using a whitelist-based sanitizer that strips `<script>`, `<iframe>`, and `<object>` tags.
*   **Signature Stripping**: Uses pattern matching (e.g., matching standard markers like `--` or common salutations) to separate the message body from the user's signature block.
*   **Quoted Reply Detection**: Strips previous message histories included in the email body to prevent duplicate text in conversation threads.
*   **Bounce Processing**: Scans for SMTP codes (e.g., `5.1.1 User Unknown`) to update delivery status and alert agents when outbound emails bounce.

---

## 6. LIVE CHAT ENGINE

The Live Chat Engine manages visitor sessions, agent queues, and real-time messaging using WebSockets.

### 6.1 Visitor Sessions & Authentication
*   **Anonymous Visitors**: Unauthenticated visitors receive a secure, short-lived session token stored in their browser's local storage. This allows tracking their interactions across page loads during a single session.
*   **Authenticated Clients**: Users logged into the client portal use JSON Web Tokens (JWT) to authenticate their chat session, giving them full access to their conversation history.

### 6.2 Routing and Capacity Management
*   **Agent Assignment**: Chats are routed to online agents using the *Least Loaded* or *Round Robin* algorithms.
*   **Queue Transitions**: If no agents are available, visitors are placed in a waiting queue. The system displays their estimated wait time and offers an option to submit their inquiry as an offline ticket.
*   **Agent Offline Handoff**: If an active agent goes offline unexpectedly, the session is placed back in the triage queue, and the customer is notified that they are being re-routed.

### 6.3 Presence & Session Maintenance
*   **Heartbeat Signals**: Client web browsers send periodic heartbeat signals to the WebSocket broker every 10 seconds to maintain an active connection.
*   **Connection Recovery**: If a client disconnects (e.g., due to network switching), the connection state is preserved for up to 60 seconds, allowing them to reconnect without losing their active session.
*   **Idle Timeout**: If a customer does not send a message for 10 minutes, the session is marked as idle. After 15 minutes, the system sends an automated closing message and resolves the session.

---

## 7. MESSAGING PLATFORM INTEGRATIONS

This section defines the webhook validation, rate limiting, and delivery standards for external messaging platforms.

### 7.1 Webhook Authentication and Security
*   **WhatsApp / Meta Platform**: Validates payloads using HMAC-SHA256 signatures computed with the Meta App Secret.
*   **Telegram**: Validates requests using token hashes supplied in webhook headers.
*   **SMS Providers**: Restricts webhook traffic to known provider IP addresses and validates request signatures.

### 7.2 Rate Limiting and Delivery Failovers
*   **Provider Failovers**: If delivery to WhatsApp fails with a provider error, the system automatically falls back to SMS for high-priority alerts.
*   **Rate-Limiting Guards**: Outbound message queues are managed by token-bucket limiters tailored to each provider's API limits, preventing rate-limiting blocks.
*   **Idempotency Checks**: Every incoming webhook payload is checked against a transaction cache to prevent duplicate processing from retried webhook requests.

---

## 8. ATTACHMENT ENGINE

All communication channels upload and retrieve binary files through a unified attachment gateway.

```
[Inbound Attachment] ──> [Central Files Module] ──> [MIME & Size Check] ──> [Virus Scan] ──> [S3 Commit]
```

### 8.1 Attachment Standards & Validation Rules

*   **Allowed MIME Whitelist**: Restricted to safe file types:
    *   *Images*: `image/png`, `image/jpeg`, `image/gif`, `image/webp`.
    *   *Documents*: `application/pdf`, `text/plain`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`.
    *   *Logs*: `text/csv`, `text/log`.
*   **Size Limits**:
    *   *Self-Service Portal / Chat*: Max 15MB per file.
    *   *API Integration / Email*: Max 25MB cumulative.
*   **Malware Scans**: Files are uploaded to a temporary sandbox, scanned for malware, and deleted immediately if a threat is detected.
*   **Access Control**: Files are stored in secure S3 buckets with access restricted to authenticated tenant users. Download links use short-lived, pre-signed URLs to prevent unauthorized sharing.

---

## 9. CONVERSATION THREAD RECONSTRUCTION

The engine uses a deterministic reconstruction routine to build unified conversation histories from out-of-order, late, or duplicated messages across channels.

```
Input: ConversationID, StartTime, EndTime
Output: Ordered, Deduplicated Conversation Stream
```

1.  **Retrieve Messages**: Queries all messages linked to the target conversation ID, including associated attachments.
2.  **Deduplicate**: Removes duplicate payloads using unique message IDs and deduplication hashes.
3.  **Order by Time**: Sorts the message stream by `created_at`.
4.  **Resolve Clock Skew**: If messages arrive with inaccurate client timestamps, the engine adjusts their position in the stream using server reception times.
5.  **Inject System Annotations**: Interleaves system notifications (e.g., assignment updates, status transitions) into the conversation timeline.

---

## 10. SEARCH & INDEXING ARCHITECTURE

To support rapid retrieval across millions of messages, the system utilizes PostgreSQL's native search capabilities:

```
[New Message] ──> [Generate tsvector] ──> [Update GIN Index] ──> [Fuzzy Text Query]
```

### 10.1 Search Index Specifications

*   **Vector Representations**: Message bodies are converted to `tsvector` formats using regional configurations (e.g., `english`, `spanish`).
*   **GIN Indexing**: The `tsvector` columns are indexed using Generalized Index (GIN) strategies, optimizing keyword searches on large datasets:
    ```sql
    CREATE INDEX message_body_fts_idx ON public.ticket_messages USING GIN (to_tsvector('english', body_text));
    ```
*   **Trigram Matching (`pg_trgm`)**: Applied to titles and keywords to support typo-tolerant searches.
*   **Weighting Rules**: Search results rank matches based on relevance:
    *   *Direct Subject Matches*: Weighted highest (Weight: `A`).
    *   *Message Body Matches*: Weighted standard (Weight: `B`).

---

## 11. AI GOVERNANCE & INTEGRATION BOUNDARIES

The platform integrates artificial intelligence to assist agents with draft recommendations, sentiment analysis, and automated translations.

### 11.1 Advisory-Only Principle
All AI outputs are stored in dedicated tables (`public.ai_suggested_responses`) and treated as advisory. Under no circumstances can the system send an AI-generated message directly to a customer without human review and approval.

### 11.2 Privacy and Security Gates
*   **PII Masking**: Before transmitting conversation text to external AI endpoints for processing, a local pre-processor redacts sensitive data like credit card numbers, passwords, and access keys.
*   **Hallucination Safeguards**: AI recommendations include confidence scores. If a recommendation falls below a 75% confidence threshold, it is hidden from the agent portal.

---

## 12. NOTIFICATION INTEGRATION & EVENT CONTRACTS

The Support domain publishes events to notify other systems of communication and lifecycle updates.

### 12.1 Core System Events

#### 12.1.1 `conversation.started`
*   **Trigger**: A new conversation session is successfully validated and initialized.
*   **Payload Schema**:
```json
{
  "event_id": "evt_5510a738-12ba-4abc-9922-41a221f110c3",
  "event_type": "conversation.started",
  "timestamp": "2026-06-29T06:24:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "conversation_id": "cnv_1234a567-b89c-12d3-a456-426614176000",
    "channel_type": "web_chat",
    "source_ip": "192.168.1.1",
    "customer_participant_id": "prt_731a89c2-20ba-4cbb-b21a-1f03bc189012"
  }
}
```

#### 12.1.2 `message.received`
*   **Trigger**: An inbound message is successfully parsed, validated, and saved.
*   **Payload Schema**: Includes `conversation_id`, `message_id`, `channel_type`, and `sender_type` (agent/customer).

#### 12.1.3 `message.sent`
*   **Trigger**: An outbound message is successfully dispatched to the provider.
*   **Payload Schema**: Includes `message_id`, `channel_type`, and `recipient_id`.

#### 12.1.4 `message.failed`
*   **Trigger**: Outbound dispatch fails after the maximum number of retry attempts.
*   **Payload Schema**: Includes `message_id`, `channel_type`, `error_code`, and `error_message`.

---

## 13. SECURITY, ACCESS CONTROL & GDPR COMPLIANCE

The system applies strict access controls and isolation protocols to protect tenant data and comply with global privacy regulations:

*   **Row-Level Security (RLS)**: Every query must carry an active `organization_id` context. Cross-tenant queries are blocked at the database engine level.
*   **Access Control**: External portal users can only access messages where `is_internal_note = false`. Internal notes and audit logs are hidden from client queries.
*   **GDPR "Forget Me" Workflows**:
    *   Upon receiving an approved deletion request, the system triggers a secure redaction process.
    *   Replaces the customer's name, email, phone number, and IP addresses with obfuscated placeholder values.
    *   Clears communication bodies (`body_text`, `body_html`, `body_markdown`) in messages, or replaces them with `[REDACTED]`.
    *   Unlinks and deletes associated files from storage.

---

## 14. PERFORMANCE & HIGH-VOLUME SCALABILITY

To support high transaction volumes without degrading system performance, the database applies the following strategies:

*   **Database Partitioning**: Historical communication logs and message activity records are partitioned monthly on the `created_at` column.
*   **Connection Pooling**: Uses database connection pools to manage database connections efficiently during high-traffic periods.
*   **Write Batching**: Low-priority analytics and metrics events are batched in memory and written to the database in bulk, reducing transactional overhead.

---

## 15. OPERATIONAL METRICS & KEY PERFORMANCE INDICATORS

The system aggregates communication logs to compute real-time operational performance metrics:

### 15.1 Core KPI Definitions

#### 15.1.1 Average Reply Time (ART)
Calculates the average duration for an agent to reply to a customer message:
$$\text{ART} = \frac{\sum(\text{AgentReplyTimestamp} - \text{CustomerMessageTimestamp})}{\text{Total Customer Messages}}$$

#### 15.1.2 Delivery Success Rate (DSR)
Calculates the percentage of outbound messages successfully delivered to customers:
$$\text{DSR} = \left( \frac{\text{Successfully Delivered Messages}}{\text{Total Sent Messages}} \right) \times 100$$

#### 15.1.3 Conversation Deflection Rate (CDR)
Calculates the percentage of customer sessions resolved by automated systems (e.g., self-service portals, chatbots) without agent intervention:
$$\text{CDR} = \left( \frac{\text{Sessions Resolved Autonomously}}{\text{Total Inbound Sessions}} \right) \times 100$$

---

## 16. VALIDATION MATRIX

Below is the verification checklist to ensure the Omnichannel Communication & Conversation Engine functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **Email Processing** | SPF/DKIM validation failure | **Quarantined**. Payloads with security failures are routed to the quarantine queue. |
| **Email Processing** | Quoted reply history detection | **Success**. Strips previous message histories, saving only the new reply. |
| **Live Chat** | WebSocket disconnect and reconnect | **Session Preserved**. Connection state is held for 60 seconds before timing out. |
| **Attachment Engine**| Block unsafe file uploads | **Rejected**. Access control blocks files not matching the MIME whitelist. |
| **Thread Reconstruction**| Late or out-of-order messages | **Re-ordered**. Chronologically orders the thread using server reception times. |
| **Security** | Guest attempting to access internal notes | **Access Denied**. Query filters block guest access to internal records. |
| **GDPR Compliance** | Process approved "Forget Me" request | **Redacted**. Replaces PII with placeholder values and deletes associated files. |

---

This document serves as the architectural reference for implementing omnichannel communications, message routing, and security protocols within the JUANET Platform. All components must adhere strictly to these specifications.
