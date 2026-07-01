# JUANET PostgreSQL Support Table Specifications
## Phase 2.3.2F — Support Physical Tables
**Document Version:** 1.0  
**Author:** Chief PostgreSQL Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Database Schema Definition  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical database table definitions for the Support domain of the JUANET Enterprise SaaS Platform. All schemas, column constraints, parameters, and indexing strategies defined herein are binding and must be implemented exactly by migrations, DDL scripts, or ORM declarations.

These specifications conform strictly to:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`
*   `Phase_2_3_2A_Core_Physical_Tables.md`
*   `Phase_2_3_2B_Authentication_Physical_Tables.md`
*   `Phase_2_3_2C_CRM_Physical_Tables.md`
*   `Phase_2_3_2D_Projects_Physical_Tables.md`
*   `Phase_2_3_2E_Finance_Physical_Tables.md`

All Support domain tables reside within the `public` schema. They utilize Row-Level Security (RLS) keying based on `organization_id` to enforce logical multi-tenant isolation.

### 1.1 Mandatory Global Columns Standard
Every tenant-owned business entity within the `public` schema must define the following standard structural block of columns. No table is exempt. To preserve focus on domain-specific attributes, this standard structural block is represented as **`[MANDATORY GLOBAL COLUMNS]`** in each table catalog:

```sql
id              uuid                        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
organization_id uuid                        NOT NULL REFERENCES system.organizations(id) ON DELETE RESTRICT,
created_at      timestamp with time zone    NOT NULL DEFAULT now(),
updated_at      timestamp with time zone    NOT NULL DEFAULT now(),
deleted_at      timestamp with time zone    NULL DEFAULT NULL,
created_by      uuid                        NULL REFERENCES security.users(id) ON DELETE SET NULL,
updated_by      uuid                        NULL REFERENCES security.users(id) ON DELETE SET NULL,
version         integer                     NOT NULL DEFAULT 1
```

---

## 2. ARCHITECTURAL PRINCIPLES OF THE SUPPORT DOMAIN

The Support domain is engineered for extreme scale, omnichannel ingest, real-time SLA calculation, and AI-assisted agent co-piloting.

### 2.1 Multi-Tenant Isolation (RLS)
Every table is locked behind PostgreSQL Row-Level Security (RLS). The `organization_id` acts as the security boundary. Row traversal is impossible between tenant lines.

### 2.2 Omnichannel Communication Architecture
The ticket system supports threaded communications representing email, web forms, real-time chats, phone call transcripts, and developer API logs. Conversations are threaded via parent/child message relations, maintaining logical sequences.

### 2.3 Real-Time SLA Calculations & Calendars
The database engine evaluates SLA breaches by calculating dynamic response and resolution timers against custom holiday calendars and business-hour structures. 

### 2.4 Asynchronous Outbox Event Pattern
Every Support state change (such as ticket status mutations) generates transactional logs and publishes outbound integration events. Business workflows are triggered asynchronously to decoupling support operations from core system transactions.

### 2.5 Advisory-Only AI Engine
AI outputs (classifications, sentiment analysis, suggested replies, search weights) are tracked in specialized tables. These recommendations are advisory only; the agent remains the authoritative decision-maker.

---

## 3. SUBDOMAIN 1: TICKET MANAGEMENT

Governs ticket categorization, prioritization, workflows, queues, routing, assignments, and tags.

### 3.1 Table Name: `public.ticket_categories`
*   **Purpose**: Hierarchy of categories to classify customer inquiries (e.g., Billing, API Technical Support, Feature Requests).
*   **Business Responsibility**: SLA categorization & Process routing
*   **Expected Growth / Read-Write Profile**: Very Low / 98% Reads, 2% Writes
*   **Retention**: Retained indefinitely.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `parent_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_categories(id)` ON DELETE CASCADE | Valid UUID | None | Public | Tree structure support. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Descriptive tag. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Full context. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Soft-enable toggle. |
| `sort_order` | `integer` | NO | `0` | None | `sort_order >= 0` | None | Public | Sorting hierarchy. |
| `metadata` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON | None | Public | Extensible custom values. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_categories_org_name_parent_key UNIQUE (organization_id, parent_id, name)`
*   **Index**: `CREATE INDEX ticket_categories_org_sort_idx ON public.ticket_categories (organization_id, sort_order) WHERE deleted_at IS NULL;`

---

### 3.2 Table Name: `public.ticket_priorities`
*   **Purpose**: Standardized tier of urgency (e.g., Low, Medium, High, Urgent, Critical blocker).
*   **Business Responsibility**: Urgency definition & Priority weight SLA
*   **Expected Growth / Read-Write Profile**: Static / Read-only
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(50)` | NO | None | None | 2-50 characters | None | Public | Display name of priority. |
| `code` | `varchar(30)` | NO | None | None | Lowercase, Alphanumeric | None | Public | Immutable system key. |
| `weight` | `integer` | NO | `100` | None | `weight > 0` | None | Public | Priority routing weight. |
| `color_hex` | `char(7)` | NO | `'#64748B'`| None | Regexp matching hex | None | Public | UI styling color. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_priorities_org_code_key UNIQUE (organization_id, code)`
*   **Check**: `CONSTRAINT ticket_priorities_color CHECK (color_hex ~ '^#[0-9A-Fa-f]{6}$')`

---

### 3.3 Table Name: `public.ticket_statuses`
*   **Purpose**: States in the lifecycle of a ticket (New, Open, Pending, Solved, Closed, Canceled).
*   **Business Responsibility**: Workflow compliance & Lifecyle tracking
*   **Expected Growth / Read-Write Profile**: Static / Read-only
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(50)` | NO | None | None | 2-50 characters | None | Public | Display name of status. |
| `code` | `varchar(30)` | NO | None | None | Lowercase, Alphanumeric | None | Public | Immutable state engine key. |
| `state_category` | `varchar(30)` | NO | `'open'` | None | open, pending, solved, closed | None | Public | Universal lifecycle bucket. |
| `is_default` | `boolean` | NO | `false` | None | Boolean | None | Public | Marks landing state. |
| `sort_order` | `integer` | NO | `0` | None | `sort_order >= 0` | None | Public | Ordering on boards. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_statuses_org_code_key UNIQUE (organization_id, code)`
*   **Check**: `CONSTRAINT ticket_statuses_category CHECK (state_category IN ('new', 'open', 'pending', 'solved', 'closed', 'canceled'))`

---

### 3.4 Table Name: `public.ticket_types`
*   **Purpose**: Types of support requested (e.g., Incident, Problem, Question, Task).
*   **Business Responsibility**: Request categorization & ITIL alignment
*   **Expected Growth / Read-Write Profile**: Very Low / Read-heavy
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(50)` | NO | None | None | 2-50 characters | None | Public | Display name. |
| `code` | `varchar(30)` | NO | None | None | Lowercase, Alphanumeric | None | Public | System token. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Category scope description. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_types_org_code_key UNIQUE (organization_id, code)`

---

### 3.5 Table Name: `public.support_queues`
*   **Purpose**: Virtual queues dividing work routing profiles (e.g., Tier 1 Ingestion, Tier 3 Infrastructure Blocker, VIP Accounts).
*   **Business Responsibility**: Ingestion pipelines & Routing policies
*   **Expected Growth / Read-Write Profile**: Low / 90% Reads, 10% Writes
*   **Retention**: Retained indefinitely.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Queue name. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Queue target description. |
| `routing_rules` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON schema matching rules | None | Public | Auto-assignment logic. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Toggle flag. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT support_queues_org_name_key UNIQUE (organization_id, name)`

---

### 3.6 Table Name: `public.support_teams`
*   **Purpose**: Operational groups containing human agents (e.g., EMEA Billing Team, APAC Engineering, US Premium Success).
*   **Business Responsibility**: Workforce management & Capacity assignment
*   **Expected Growth / Read-Write Profile**: Low / 95% Reads, 5% Writes
*   **Retention**: Retained indefinitely.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Team name. |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Operational focus description. |
| `queue_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_queues(id)` ON DELETE SET NULL | Valid UUID | None | Public | Linked default queue. |
| `lead_agent_user_id`| `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE SET NULL | Valid UUID | None | Public | Team escalations lead. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT support_teams_org_name_key UNIQUE (organization_id, name)`

---

### 3.7 Table Name: `public.support_agents`
*   **Purpose**: Tracks profile settings, capacity metrics, and workload parameters for support professionals.
*   **Business Responsibility**: Resource allocation & Utilization checks
*   **Expected Growth / Read-Write Profile**: Medium / High reads, regular status changes
*   **Retention**: Retained indefinitely.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Link to corporate auth. |
| `team_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_teams(id)` ON DELETE SET NULL | Valid UUID | None | Public | Associated physical team. |
| `status` | `varchar(30)` | NO | `'offline'`| None | online, offline, away, break | None | Public | Active work state. |
| `max_capacity` | `integer` | NO | `5` | None | `max_capacity > 0` | None | Public | Maximum active ticket ceiling. |
| `current_load` | `integer` | NO | `0` | None | `current_load >= 0` | None | Public | Calculated active workload. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT support_agents_org_user_key UNIQUE (organization_id, user_id)`
*   **Check**: `CONSTRAINT support_agents_status_check CHECK (status IN ('online', 'offline', 'away', 'break', 'training'))`
*   **Index**: `CREATE INDEX support_agents_routing_idx ON public.support_agents (organization_id, team_id, status, current_load) WHERE deleted_at IS NULL;`

---

### 3.8 Table Name: `public.tickets`
*   **Purpose**: The master entity tracking a singular customer support issue, incident, or requests.
*   **Business Responsibility**: Customer satisfaction & Core incident SLA execution
*   **Expected Growth / Read-Write Profile**: High / 60% Reads, 40% Writes
*   **Retention**: Permanent (subject to GDPR scrubbing policies).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_number` | `varchar(40)` | NO | None | Unique per Org | Standard alphanumeric | None | Public | Customer reference code. |
| `subject` | `varchar(255)` | NO | None | None | 2-255 characters | None | Public | Summary title. |
| `requester_user_id`| `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | PII (Audit) | User experiencing the issue. |
| `reporter_user_id` | `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Person reporting the ticket. |
| `category_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_categories(id)` ON DELETE SET NULL | Valid UUID | None | Public | Classification path. |
| `priority_id` | `uuid` | NO | None | REFERENCES `public.ticket_priorities(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Linked priority level. |
| `status_id` | `uuid` | NO | None | REFERENCES `public.ticket_statuses(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Operational lifecycle status. |
| `type_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_types(id)` ON DELETE SET NULL | Valid UUID | None | Public | Class of service. |
| `queue_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_queues(id)` ON DELETE SET NULL | Valid UUID | None | Public | Residing routing queue. |
| `assigned_team_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_teams(id)` ON DELETE SET NULL | Valid UUID | None | Public | Current operational group. |
| `assigned_agent_id`| `uuid` | YES | `NULL` | REFERENCES `public.support_agents(id)` ON DELETE SET NULL | Valid UUID | None | Public | Human owner. |
| `source` | `varchar(30)` | NO | `'web'` | None | email, web, chat, api, internal | None | Public | Omnichannel ingestion point. |
| `first_response_sla_breached` | `boolean` | NO | `false` | None | Boolean | None | Public | SLA violation status. |
| `resolution_sla_breached` | `boolean` | NO | `false` | None | Boolean | None | Public | Resolution violation status. |
| `metadata` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON | None | Public | Integrations, metadata payloads. |
| `search_vector` | `tsvector` | YES | `NULL` | None | Generated tsvector | None | Public | Full-text search engine index. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT tickets_org_number_key UNIQUE (organization_id, ticket_number)`
*   **Check**: `CONSTRAINT tickets_source_check CHECK (source IN ('email', 'web', 'chat', 'api', 'phone', 'slack', 'internal'))`
*   **Index**: `CREATE INDEX tickets_dashboard_idx ON public.tickets (organization_id, status_id, priority_id) WHERE deleted_at IS NULL;`
*   **Index**: `CREATE INDEX tickets_assigned_agent_idx ON public.tickets (organization_id, assigned_agent_id) WHERE deleted_at IS NULL AND assigned_agent_id IS NOT NULL;`
*   **Index**: `CREATE INDEX tickets_search_gin_idx ON public.tickets USING GIN (search_vector);`

---

### 3.9 Table Name: `public.ticket_watchers`
*   **Purpose**: Standard junction tracking internal or external users receiving CC notifications for ticket activities.
*   **Business Responsibility**: Stakeholder collaboration & Transparency
*   **Expected Growth / Read-Write Profile**: Medium / Write-on-update
*   **Retention**: Inherits ticket lifetime.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Monitored ticket. |
| `user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE CASCADE | Valid UUID | None | Public | Notification recipient. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_watchers_ticket_user_key UNIQUE (organization_id, ticket_id, user_id)`

---

### 3.10 Table Name: `public.ticket_assignments`
*   **Purpose**: Formal record of ticket lifecycle assignments, supporting accountability audits.
*   **Business Responsibility**: Ownership handoffs & Workforce tracking
*   **Expected Growth / Read-Write Profile**: High / Append-only transactional mutations
*   **Retention**: Permanent (Audit compliance).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target ticket. |
| `team_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_teams(id)` ON DELETE SET NULL | Valid UUID | None | Public | Targeted group. |
| `agent_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_agents(id)` ON DELETE SET NULL | Valid UUID | None | Public | Targeted human handler. |
| `assigned_by_user_id`| `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE SET NULL | Valid UUID | None | Public | Executor of change. |
| `assigned_at` | `timestamp with time zone` | NO | `now()` | None | Date/time | None | Public | Accurate execution timestamp. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX ticket_assignments_ticket_idx ON public.ticket_assignments (organization_id, ticket_id, assigned_at DESC);`

---

### 3.11 Table Name: `public.ticket_tags`
*   **Purpose**: Flexible taxonomic categories used by agents to label special-case issues (e.g., `#q3-outage`, `#migration`, `#under-warranty`).
*   **Business Responsibility**: Taxonomy standardization & Reporting taxonomy
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(50)` | NO | None | None | Alphanumeric lower-kebab | None | Public | Visual tag label. |
| `color_hex` | `char(7)` | NO | `'#64748B'`| None | Valid hex validation | None | Public | UI visual representation. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_tags_org_name_key UNIQUE (organization_id, name)`

---

### 3.12 Table Name: `public.ticket_tag_assignments`
*   **Purpose**: High-throughput lookup linking dynamic tags directly to active tickets.
*   **Business Responsibility**: Logical categorizations
*   **Expected Growth / Read-Write Profile**: High / Quick join edits
*   **Retention**: Inherited.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `organization_id`| `uuid` | NO | None | REFERENCES `system.organizations(id)` ON DELETE RESTRICT | Valid UUID | - | Public | Isolation. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target. |
| `tag_id` | `uuid` | NO | None | REFERENCES `public.ticket_tags(id)` ON DELETE CASCADE | Valid UUID | None | Public | Link. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (organization_id, ticket_id, tag_id)`
*   **Index**: `CREATE INDEX ticket_tag_assign_tag_idx ON public.ticket_tag_assignments (organization_id, tag_id);`

---

## 4. SUBDOMAIN 2: TICKET COMMUNICATION

Manages public customer communications, internal discussions, message attachments, and visual reactions.

### 4.1 Table Name: `public.ticket_messages`
*   **Purpose**: Central conversation logs containing emails, web posts, chats, or transcript chunks between customer and support agents.
*   **Business Responsibility**: Omnichannel communications & History fidelity
*   **Expected Growth / Read-Write Profile**: Very High / 80% Reads, 20% Writes
*   **Retention**: Permanent (subject to GDPR PII scrubbing).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Associated ticket thread. |
| `sender_user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | PII (Audit) | Actor producing message. |
| `body_markdown` | `text` | YES | `NULL` | None | Valid markdown structures | None | PII (Audit) | Standard rendering format. |
| `body_text` | `text` | NO | None | None | Normalized strings | None | PII (Audit) | Raw plain text search basis. |
| `body_html` | `text` | YES | `NULL` | None | Sanitized HTML content | None | PII (Audit) | Legacy email render storage. |
| `is_internal` | `boolean` | NO | `false` | None | Boolean | None | Public | Hidden from customer toggle. |
| `parent_message_id`| `uuid` | YES | `NULL` | REFERENCES `public.ticket_messages(id)` ON DELETE CASCADE | Valid UUID | None | Public | Deep nested threading hook. |
| `message_type` | `varchar(30)` | NO | `'standard'`| None | standard, auto_response, transcript | None | Public | Type classification. |
| `search_vector` | `tsvector` | YES | `NULL` | None | Computed tsvector | None | Public | Full-text query target. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT ticket_messages_type_check CHECK (message_type IN ('standard', 'auto_response', 'transcript', 'system_event'))`
*   **Index**: `CREATE INDEX ticket_messages_thread_idx ON public.ticket_messages (organization_id, ticket_id, created_at ASC);`
*   **Index**: `CREATE INDEX ticket_messages_search_gin_idx ON public.ticket_messages USING GIN (search_vector);`

---

### 4.2 Table Name: `public.ticket_message_attachments`
*   **Purpose**: Junction mapping files directly to specific support messages without violating low-coupling rules of central files.
*   **Business Responsibility**: Secure attachment records & Document evidence
*   **Expected Growth / Read-Write Profile**: High / Write once, read occasionally
*   **Retention**: Governed by corporate retention files policies.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `message_id` | `uuid` | NO | None | REFERENCES `public.ticket_messages(id)` ON DELETE CASCADE | Valid UUID | None | Public | Associated message block. |
| `file_id` | `uuid` | NO | None | REFERENCES `public.files(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Central file identifier. |
| `file_name` | `varchar(255)` | NO | None | None | 1-255 characters | None | Public | Saved filename copy. |
| `file_size` | `bigint` | NO | None | None | `file_size > 0` | None | Public | Size replication cache. |
| `content_type` | `varchar(100)` | NO | None | None | Valid MIME standards | None | Public | Layout helper cache. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_message_attach_msg_file_key UNIQUE (organization_id, message_id, file_id)`

---

### 4.3 Table Name: `public.ticket_internal_notes`
*   **Purpose**: Internal agent-only workspace scratchpad, decoupled entirely from user visibility policies.
*   **Business Responsibility**: Collaboration & Technical debugging
*   **Expected Growth / Read-Write Profile**: High / 70% Reads, 30% Writes
*   **Retention**: Retained until archived.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Subject ticket thread. |
| `author_user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Executing support agent user. |
| `note_markdown` | `text` | NO | None | None | Markdown content strings | None | Public | Detailed notes. |
| `is_system_generated`|`boolean`| NO | `false` | None | Boolean | None | Public | Tracks automated notes. |
| `search_vector` | `tsvector` | YES | `NULL` | None | Generated tsvector | None | Public | Search indexing target. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX ticket_internal_notes_lookup_idx ON public.ticket_internal_notes (organization_id, ticket_id, created_at DESC);`

---

### 4.4 Table Name: `public.ticket_mentions`
*   **Purpose**: Captures user references (e.g. `@MaryKamau`) within communication blocks or internal notes to trigger push alerts.
*   **Business Responsibility**: Real-time collaborations
*   **Expected Growth / Read-Write Profile**: Medium / Write once, read on notification fetch
*   **Retention**: 1 Year.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `source_type` | `varchar(30)` | NO | None | None | message, note | None | Public | Discriminator type. |
| `source_id` | `uuid` | NO | None | None | Valid UUID | None | Public | Target text block ID. |
| `mentioned_user_id`| `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE CASCADE | Valid UUID | None | Public | User referenced. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT ticket_mentions_source_check CHECK (source_type IN ('message', 'note'))`
*   **Index**: `CREATE INDEX ticket_mentions_user_alert_idx ON public.ticket_mentions (organization_id, mentioned_user_id) WHERE deleted_at IS NULL;`

---

### 4.5 Table Name: `public.ticket_message_reactions`
*   **Purpose**: Lightweight reactions containing emoji markers, encouraging human micro-collaboration.
*   **Business Responsibility**: Dynamic engagement
*   **Expected Growth / Read-Write Profile**: Medium / Rapid interactive writes
*   **Retention**: Same as ticket.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `message_id` | `uuid` | NO | None | REFERENCES `public.ticket_messages(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target message item. |
| `user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE CASCADE | Valid UUID | None | Public | Emoji creator. |
| `reaction_unicode`| `varchar(20)` | NO | None | None | Unicode regex | None | Public | Actual emoji identifier. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_msg_reactions_user_key UNIQUE (organization_id, message_id, user_id, reaction_unicode)`

---

## 5. SUBDOMAIN 3: TICKET ACTIVITY (APPEND-ONLY)

Strictly append-only log tables monitoring audit footprints, status journeys, timesheets, and followup pathways.

### 5.1 Table Name: `public.ticket_activity_logs`
*   **Purpose**: Master immutable footprint tracking actions, actor parameters, and request contexts.
*   **Business Responsibility**: Regulatory compliance & Historical forensics
*   **Expected Growth / Read-Write Profile**: Extremely High / 10% Reads, 90% Writes (Append-Only)
*   **Retention**: 7 Years (Compliance regulation).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Ticket targeted. |
| `actor_user_id` | `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | PII (Audit) | Executor. |
| `action_type` | `varchar(50)` | NO | None | None | Alphanumeric lower-kebab | None | Public | Event category label. |
| `payload` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON | None | Public | State diff values block. |
| `ip_address` | `inet` | YES | `NULL` | None | Valid IP schema | None | PII (Audit) | Logging footprint. |
| `user_agent` | `text` | YES | `NULL` | None | Browser string | None | PII (Audit) | Client context capture. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Partitioning**: This table is range partitioned monthly on the `created_at` column.
*   **Index**: `CREATE INDEX ticket_activity_logs_lookup_idx ON public.ticket_activity_logs (organization_id, ticket_id, created_at DESC);`

---

### 5.2 Table Name: `public.ticket_status_history`
*   **Purpose**: Standard transition record tracking exact durations ticket spent within any given operational status.
*   **Business Responsibility**: SLA metrics analysis & Queue bottleneck tracking
*   **Expected Growth / Read-Write Profile**: High / Append-only transition writes
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Subject ticket. |
| `old_status_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_statuses(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Origin status. |
| `new_status_id` | `uuid` | NO | None | REFERENCES `public.ticket_statuses(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Destination status. |
| `changed_by_user_id`|`uuid`| YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Change agent user. |
| `duration_seconds` | `bigint` | YES | `NULL` | None | `duration_seconds >= 0` | None | Public | Time duration measurement. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX ticket_status_hist_ticket_idx ON public.ticket_status_history (organization_id, ticket_id, created_at DESC);`

---

### 5.3 Table Name: `public.ticket_assignment_history`
*   **Purpose**: Complete path log tracking handoffs between teams and agents.
*   **Business Responsibility**: Accountability logs & Load balance reviews
*   **Expected Growth / Read-Write Profile**: High / Append-only mutations
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target entity. |
| `old_team_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_teams(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Old team link. |
| `new_team_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_teams(id)` ON DELETE RESTRICT | Valid UUID | None | Public | New team link. |
| `old_agent_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_agents(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Old owner agent. |
| `new_agent_id` | `uuid` | YES | `NULL` | REFERENCES `public.support_agents(id)` ON DELETE RESTRICT | Valid UUID | None | Public | New owner agent. |
| `changed_by_user_id`|`uuid`| YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Action executor. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX ticket_assign_hist_ticket_idx ON public.ticket_assignment_history (organization_id, ticket_id, created_at DESC);`

---

### 5.4 Table Name: `public.ticket_time_entries`
*   **Purpose**: Tracks actual timesheet segments billing or recording human labor on specific ticket instances.
*   **Business Responsibility**: Labor accounting, Cost analysis & Cross-billing
*   **Expected Growth / Read-Write Profile**: High / Active transactional entry logging
*   **Retention**: 7 Years (Financial Audit).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Subject ticket. |
| `agent_user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | User logging hours. |
| `activity_type` | `varchar(40)` | NO | `'troubleshooting'`| None | Alphanumeric lower-kebab | None | Public | Troubleshooting, meeting, debug. |
| `duration_seconds` | `integer` | NO | None | None | `duration_seconds > 0` | None | Public | Measured labor segment. |
| `description` | `text` | YES | `NULL` | None | String length | None | Public | Detailed description. |
| `project_time_log_id`|`uuid`| YES | `NULL` | REFERENCES `public.task_time_logs(id)` ON DELETE SET NULL | Valid UUID | None | Financial | External Projects sync linkage. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT ticket_time_duration CHECK (duration_seconds > 0)`

---

### 5.5 Table Name: `public.ticket_followups`
*   **Purpose**: Maps relationships between duplicate tickets or followup threads (e.g., ticket_B is a duplicate of ticket_A, or B reopened A).
*   **Business Responsibility**: Lifecycle consistency & Thread deduplication
*   **Expected Growth / Read-Write Profile**: Medium / Write-on-merge actions
*   **Retention**: Same as ticket.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Active ticket. |
| `previous_ticket_id`| `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Historical source ticket. |
| `relation_type` | `varchar(35)` | NO | `'followup'`| None | followup, duplicate, merged | None | Public | Relationship descriptor. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_followups_source_dest_key UNIQUE (organization_id, ticket_id, previous_ticket_id)`
*   **Check**: `CONSTRAINT ticket_followups_type CHECK (relation_type IN ('followup', 'duplicate', 'merged', 'reopened'))`

---

## 6. SUBDOMAIN 4: SLA MANAGEMENT (REAL-TIME CALCULATION)

SLA policies, calendars, target metrics, schedules, active evaluation timelines, and escalation parameters.

### 6.1 Table Name: `public.sla_policies`
*   **Purpose**: Definitions specifying service criteria boundaries mapped to customers.
*   **Business Responsibility**: Customer contract compliance & Corporate SLA rules
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Indefinite.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | SLA tier title (Gold, Basic). |
| `description` | `text` | YES | `NULL` | None | None | None | Public | Contextual descriptions. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Live/disabled flag. |
| `match_rules` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON structure mapping customer tiers | None | Public | Dynamic matching criteria block. |
| `calendar_id` | `uuid` | NO | None | REFERENCES `public.sla_calendars(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Assigned operational calendar. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT sla_policies_org_name_key UNIQUE (organization_id, name)`

---

### 6.2 Table Name: `public.sla_targets`
*   **Purpose**: Specific metric objectives (First Response, Next Response, Resolution) assigned per Priority/Type tier.
*   **Business Responsibility**: Fine-grained SLA contract thresholds
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Indefinite.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `policy_id` | `uuid` | NO | None | REFERENCES `public.sla_policies(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent policy. |
| `priority_id` | `uuid` | NO | None | REFERENCES `public.ticket_priorities(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Urgent/Critical priority level. |
| `ticket_type_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_types(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Ticket class constraint. |
| `target_metric` | `varchar(40)` | NO | None | None | first_response, next_response, resolution | None | Public | Checked SLA metric type. |
| `target_minutes` | `integer` | NO | None | None | `target_minutes > 0` | None | Public | Metric timeline budget. |
| `warning_threshold_percent`|`numeric(5,2)`|NO| `80.00` | None | `1.00` to `99.99` | None | Public | Warn flag trigger point. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT sla_targets_policy_priority_metric_key UNIQUE (organization_id, policy_id, priority_id, ticket_type_id, target_metric)`
*   **Check**: `CONSTRAINT sla_targets_metric_check CHECK (target_metric IN ('first_response', 'next_response', 'resolution'))`
*   **Check**: `CONSTRAINT sla_targets_warning CHECK (warning_threshold_percent > 0.00 AND warning_threshold_percent < 100.00)`

---

### 6.3 Table Name: `public.sla_calendars`
*   **Purpose**: Standard tenant timezone definitions grouping regional operational hours and holidays.
*   **Business Responsibility**: Timezone mapping & regional operations
*   **Expected Growth / Read-Write Profile**: Very Low / Read-heavy
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Calendar name (US Central, EMEA). |
| `time_zone` | `varchar(50)` | NO | `'UTC'` | None | Valid tz database code | None | Public | Dynamic tz alignment value. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT sla_calendars_org_name_key UNIQUE (organization_id, name)`

---

### 6.4 Table Name: `public.business_hours`
*   **Purpose**: Weekly operational timelines mapped per calendar, allowing off-hours SLA calculation pausing.
*   **Business Responsibility**: Shift definitions & Active work hour checks
*   **Expected Growth / Read-Write Profile**: Low / Read-only
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `calendar_id` | `uuid` | NO | None | REFERENCES `public.sla_calendars(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent schedule calendar. |
| `day_of_week` | `smallint` | NO | None | None | `day_of_week` between `0` and `6` | None | Public | Standard day (Sunday=0, Saturday=6). |
| `start_time` | `time` | NO | None | None | 24 hour system | None | Public | Daily shift start. |
| `end_time` | `time` | NO | None | None | 24 hour system, must be after start | None | Public | Daily shift end. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT business_hours_day_check CHECK (day_of_week BETWEEN 0 AND 6)`
*   **Check**: `CONSTRAINT business_hours_time_order CHECK (end_time > start_time)`

---

### 6.5 Table Name: `public.holiday_calendars`
*   **Purpose**: Specific holiday dates where support shifts pause and SLAs freeze.
*   **Business Responsibility**: Operational schedule overrides & Non-work days
*   **Expected Growth / Read-Write Profile**: Low / Static reads
*   **Retention**: Retained for historical reports.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `calendar_id` | `uuid` | NO | None | REFERENCES `public.sla_calendars(id)` ON DELETE CASCADE | Valid UUID | None | Public | Linked calendar. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Holiday name (Christmas, Jamhuri Day). |
| `holiday_date` | `date` | NO | None | None | Valid date format | None | Public | Holiday date. |
| `is_recurring` | `boolean` | NO | `false` | None | Boolean | None | Public | Annual occurrence flag. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT holiday_calendars_date_key UNIQUE (organization_id, calendar_id, holiday_date)`

---

### 6.6 Table Name: `public.ticket_sla_instances`
*   **Purpose**: Real-time instance tracker monitoring active timelines, warnings, and achievement statuses for tickets.
*   **Business Responsibility**: Operational deadline execution & Active queue monitoring
*   **Expected Growth / Read-Write Profile**: High / Continuous reads and updates (Real-time cron evaluations)
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target ticket. |
| `target_id` | `uuid` | NO | None | REFERENCES `public.sla_targets(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Configured metric rule parameters. |
| `target_metric` | `varchar(40)` | NO | None | None | first_response, next_response, resolution | None | Public | Active evaluated metric. |
| `target_deadline` | `timestamp with time zone` | NO | None | None | Future date/time | None | Public | Ultimate target timestamp. |
| `warning_at` | `timestamp with time zone` | NO | None | None | Date/time | None | Public | Early warning alert point. |
| `achieved_at` | `timestamp with time zone` | YES | `NULL` | None | Date/time | None | Public | Actual achievement timestamp. |
| `status` | `varchar(30)` | NO | `'active'` | None | active, warned, breached, achieved, paused | None | Public | Active state category. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ticket_sla_instances_ticket_metric_key UNIQUE (organization_id, ticket_id, target_metric)`
*   **Check**: `CONSTRAINT ticket_sla_instances_status CHECK (status IN ('active', 'warned', 'breached', 'achieved', 'paused'))`
*   **Index**: `CREATE INDEX ticket_sla_instances_active_idx ON public.ticket_sla_instances (organization_id, status, target_deadline ASC) WHERE status IN ('active', 'warned');`

---

### 6.7 Table Name: `public.sla_escalation_rules`
*   **Purpose**: Rules mapping breaches or close-breach thresholds to automated escalations.
*   **Business Responsibility**: Automated operational remediation & Risk management
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Indefinite.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `policy_id` | `uuid` | NO | None | REFERENCES `public.sla_policies(id)` ON DELETE CASCADE | Valid UUID | None | Public | Linked policy context. |
| `target_metric` | `varchar(40)` | NO | None | None | first_response, next_response, resolution | None | Public | Subject SLA category. |
| `threshold_type` | `varchar(30)` | NO | None | None | before_breach, after_breach | None | Public | Escalation timing. |
| `threshold_minutes`| `integer` | NO | None | None | `threshold_minutes >= 0` | None | Public | Offsets in minutes. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Live execution toggle. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT sla_escalation_rules_threshold_check CHECK (threshold_type IN ('before_breach', 'after_breach'))`

---

### 6.8 Table Name: `public.sla_escalation_actions`
*   **Purpose**: Specific actions triggered by escalation rules (e.g., assign to Tier 3, notify Slack channel, trigger PagerDuty webhook).
*   **Business Responsibility**: Automation execution patterns
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Indefinite.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `rule_id` | `uuid` | NO | None | REFERENCES `public.sla_escalation_rules(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent escalation rule. |
| `action_type` | `varchar(40)` | NO | None | None | assign, email, webhook, tier_escalate | None | Public | Code of automation strategy. |
| `action_payload` | `jsonb` | NO | `'{}'::jsonb` | None | Valid JSON data | None | Public | Target keys, custom fields parameters. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT sla_escalation_actions_type CHECK (action_type IN ('assign', 'email', 'webhook', 'tier_escalate', 'pagerduty'))`

---

## 7. SUBDOMAIN 5: CUSTOMER SATISFACTION (CSAT, CES, NPS)

Tracks post-resolution surveys, survey responses, Net Promoter Scores (NPS), and raw customer feedback.

### 7.1 Table Name: `public.csat_surveys`
*   **Purpose**: Master envelope representing customer satisfaction surveys dispatched post-resolution.
*   **Business Responsibility**: Experience mapping & Service delivery metrics
*   **Expected Growth / Read-Write Profile**: High / Low write density, quick status edits
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Origin ticket. |
| `sender_user_id` | `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Dispatching system/agent. |
| `recipient_user_id`| `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | PII (Audit) | Customer targeted. |
| `sent_at` | `timestamp with time zone` | NO | `now()` | None | Date/time | None | Public | Dispatch timestamp. |
| `completed_at` | `timestamp with time zone` | YES | `NULL` | None | Date/time | None | Public | Completion timestamp. |
| `status` | `varchar(30)` | NO | `'sent'` | None | sent, opened, completed, expired | None | Public | Processing lifecycle states. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT csat_surveys_ticket_key UNIQUE (organization_id, ticket_id)`
*   **Check**: `CONSTRAINT csat_surveys_status CHECK (status IN ('sent', 'opened', 'completed', 'expired'))`

---

### 7.2 Table Name: `public.csat_questions`
*   **Purpose**: Definition of survey questions linked to dynamic feedback envelopes.
*   **Business Responsibility**: Question taxonomy standardization
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Indefinite.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `survey_id` | `uuid` | NO | None | REFERENCES `public.csat_surveys(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent survey template. |
| `question_text` | `varchar(255)` | NO | None | None | 5-255 characters | None | Public | Survey question displayed. |
| `question_type` | `varchar(30)` | NO | `'scale_5'` | None | scale_5, scale_10, text, yes_no | None | Public | Data validation format. |
| `sort_order` | `integer` | NO | `0` | None | `sort_order >= 0` | None | Public | Display ordering. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT csat_questions_type CHECK (question_type IN ('scale_5', 'scale_10', 'text', 'yes_no'))`

---

### 7.3 Table Name: `public.csat_responses`
*   **Purpose**: Raw customer feedback answers keyed back to structured questions.
*   **Business Responsibility**: Service score evaluation
*   **Expected Growth / Read-Write Profile**: High / Append-only feedback ingest
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `question_id` | `uuid` | NO | None | REFERENCES `public.csat_questions(id)` ON DELETE CASCADE | Valid UUID | None | Public | Question answered. |
| `score_value` | `integer` | YES | `NULL` | None | `0 <= score <= 10` | None | Public | Rating value. |
| `text_response` | `text` | YES | `NULL` | None | String length | None | PII (Audit) | Written feedback notes. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT csat_responses_question_key UNIQUE (organization_id, question_id)`

---

### 7.4 Table Name: `public.nps_surveys`
*   **Purpose**: Tracks relational Net Promoter Score (NPS) measurements, evaluating long-term customer loyalty independent of single tickets.
*   **Business Responsibility**: High-level customer experience (CX) metrics
*   **Expected Growth / Read-Write Profile**: Medium / Append-only transactional survey ingests
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `recipient_user_id`| `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | PII (Audit) | Customer target. |
| `score_value` | `integer` | NO | None | None | `0` to `10` score range | None | Public | NPS scale score. |
| `text_comment` | `text` | YES | `NULL` | None | String length | None | PII (Audit) | Feedback comments. |
| `source_touchpoint`|`varchar(50)`| NO | `'email'` | None | email, web_app, in_app, portal | None | Public | Channel survey used. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT nps_surveys_score CHECK (score_value BETWEEN 0 AND 10)`
*   **Check**: `CONSTRAINT nps_surveys_touchpoint CHECK (source_touchpoint IN ('email', 'web_app', 'in_app', 'portal'))`
*   **Index**: `CREATE INDEX nps_surveys_trend_idx ON public.nps_surveys (organization_id, created_at DESC, score_value);`

---

### 7.5 Table Name: `public.support_feedback`
*   **Purpose**: Summary satisfaction indices (CSAT, CES, NPS) aggregated directly on resolved tickets for rapid analytic queries.
*   **Business Responsibility**: Ticket-level performance indexing
*   **Expected Growth / Read-Write Profile**: High / Linked survey calculations writes
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Evaluated ticket. |
| `csat_score` | `integer` | YES | `NULL` | None | `1` to `5` score | None | Public | Satisfaction rating. |
| `ces_score` | `integer` | YES | `NULL` | None | `1` to `7` score | None | Public | Effort score. |
| `feedback_text` | `text` | YES | `NULL` | None | String length | None | PII (Audit) | Notes summary. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT support_feedback_ticket_key UNIQUE (organization_id, ticket_id)`
*   **Check**: `CONSTRAINT support_feedback_csat CHECK (csat_score BETWEEN 1 AND 5)`
*   **Check**: `CONSTRAINT support_feedback_ces CHECK (ces_score BETWEEN 1 AND 7)`

---

## 8. SUBDOMAIN 6: KNOWLEDGE BASE (MULTILINGUAL ARTICLE SYSTEMS)

Knowledge categories, articles, version history systems, commentary, and semantic article tags.

### 8.1 Table Name: `public.knowledge_categories`
*   **Purpose**: Categorized index structure grouping self-service assistance articles.
*   **Business Responsibility**: Self-service help governance
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `parent_id` | `uuid` | YES | `NULL` | REFERENCES `public.knowledge_categories(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent category hierarchical link. |
| `name` | `varchar(100)` | NO | None | None | 2-100 characters | None | Public | Visual title. |
| `slug` | `varchar(110)` | NO | None | Unique per Org | Lower-kebab slug standard | None | Public | SEO friendly routing key. |
| `description` | `text` | YES | `NULL` | None | String length | None | Public | Category description. |
| `sort_order` | `integer` | NO | `0` | None | `sort_order >= 0` | None | Public | Display ordering. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT knowledge_categories_org_slug_key UNIQUE (organization_id, slug)`

---

### 8.2 Table Name: `public.knowledge_articles`
*   **Purpose**: Self-service knowledge base articles containing solutions and documentation.
*   **Business Responsibility**: Self-service enablement & Case deflection
*   **Expected Growth / Read-Write Profile**: Medium / 99% Reads, 1% Writes
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `category_id` | `uuid` | YES | `NULL` | REFERENCES `public.knowledge_categories(id)` ON DELETE SET NULL | Valid UUID | None | Public | Linked classification category. |
| `title` | `varchar(200)` | NO | None | None | 5-200 characters | None | Public | Display heading. |
| `slug` | `varchar(210)` | NO | None | Unique per Org | Lower-kebab slug standard | None | Public | SEO article link. |
| `summary` | `varchar(255)` | YES | `NULL` | None | 5-255 characters | None | Public | Brief overview snippet. |
| `content_markdown` | `text` | NO | None | None | Markdown content strings | None | Public | Full body article. |
| `current_version_id`|`uuid` | YES | `NULL` | REFERENCES `public.article_versions(id)` ON DELETE SET NULL | Valid UUID | None | Public | Head version pointer. |
| `is_active` | `boolean` | NO | `true` | None | Boolean | None | Public | Enabled toggle. |
| `language_code` | `varchar(10)` | NO | `'en'` | None | en, es, fr, sw, etc. | None | Public | Multilingual ISO tag. |
| `search_vector` | `tsvector` | YES | `NULL` | None | Generated tsvector | None | Public | FTS search indexes target. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT knowledge_articles_org_slug_key UNIQUE (organization_id, slug)`
*   **Index**: `CREATE INDEX knowledge_articles_search_gin_idx ON public.knowledge_articles USING GIN (search_vector);`

---

### 8.3 Table Name: `public.article_versions`
*   **Purpose**: Formal version history system backing knowledge base articles, enabling rollbacks and translation controls.
*   **Business Responsibility**: Content governance & Accuracy compliance
*   **Expected Growth / Read-Write Profile**: High / Versioned historical append-only
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `article_id` | `uuid` | NO | None | REFERENCES `public.knowledge_articles(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target article. |
| `version_number` | `integer` | NO | `1` | None | `version_number > 0` | None | Public | Monotonically rising version index. |
| `title` | `varchar(200)` | NO | None | None | Title characters | None | Public | Saved title. |
| `summary` | `varchar(255)` | YES | `NULL` | None | Summary text | None | Public | Saved snippet. |
| `content_markdown` | `text` | NO | None | None | Markdown strings | None | Public | Body content copy. |
| `changed_by_user_id`|`uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Author. |
| `status` | `varchar(30)` | NO | `'draft'` | None | draft, review, published, archived | None | Public | Publication status. |
| `approved_by_user_id`|`uuid`| YES | `NULL` | REFERENCES `security.users(id)` ON DELETE RESTRICT | Valid UUID | None | Public | Reviewer. |
| `changelog` | `text` | YES | `NULL` | None | Description text | None | Public | Reason for edit. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT article_versions_num_key UNIQUE (organization_id, article_id, version_number)`
*   **Check**: `CONSTRAINT article_versions_status CHECK (status IN ('draft', 'review', 'published', 'archived'))`

---

### 8.4 Table Name: `public.article_comments`
*   **Purpose**: In-app comments submitted by authorized users or customer portals on articles.
*   **Business Responsibility**: Content verification & User discussions
*   **Expected Growth / Read-Write Profile**: Medium / Ingest logs
*   **Retention**: 3 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `article_id` | `uuid` | NO | None | REFERENCES `public.knowledge_articles(id)` ON DELETE CASCADE | Valid UUID | None | Public | Parent article. |
| `user_id` | `uuid` | NO | None | REFERENCES `security.users(id)` ON DELETE CASCADE | Valid UUID | None | Public | Author. |
| `comment_text` | `text` | NO | None | None | Text content strings | None | PII (Audit) | Written message. |
| `is_approved` | `boolean` | NO | `false` | None | Boolean | None | Public | Moderation control flag. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX article_comments_lookup_idx ON public.article_comments (organization_id, article_id, is_approved) WHERE deleted_at IS NULL;`

---

### 8.5 Table Name: `public.article_feedback`
*   **Purpose**: Qualitative feedback indicating if an article was helpful (e.g., thumbs up/down with comments).
*   **Business Responsibility**: Helpfulness scoring & Self-service analysis
*   **Expected Growth / Read-Write Profile**: High / Append-only feedback ticks
*   **Retention**: 5 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `article_id` | `uuid` | NO | None | REFERENCES `public.knowledge_articles(id)` ON DELETE CASCADE | Valid UUID | None | Public | Subject article. |
| `user_id` | `uuid` | YES | `NULL` | REFERENCES `security.users(id)` ON DELETE SET NULL | Valid UUID | None | Public | Feedback creator (optional). |
| `is_helpful` | `boolean` | NO | None | None | Boolean | None | Public | Helpfulness metric. |
| `feedback_text` | `text` | YES | `NULL` | None | String length | None | PII (Audit) | Written review notes. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Index**: `CREATE INDEX article_feedback_metric_idx ON public.article_feedback (organization_id, article_id, is_helpful);`

---

### 8.6 Table Name: `public.article_tags`
*   **Purpose**: Taxonomic category tags specifically for self-service support search categorizations.
*   **Business Responsibility**: Search optimization taxonomy
*   **Expected Growth / Read-Write Profile**: Low / Read-heavy
*   **Retention**: Permanent.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `name` | `varchar(50)` | NO | None | None | 2-50 characters | None | Public | Tag title. |
| `slug` | `varchar(60)` | NO | None | Unique per Org | Lower-kebab slug standard | None | Public | SEO friendly taxonomic slug. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT article_tags_org_slug_key UNIQUE (organization_id, slug)`

---

### 8.7 Table Name: `public.article_tag_assignments`
*   **Purpose**: Direct taxonomic links mapping catalog tags directly to individual active articles.
*   **Business Responsibility**: Taxonomy assignments
*   **Expected Growth / Read-Write Profile**: Medium / Quick transactional edits
*   **Retention**: Inherited.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `organization_id`| `uuid` | NO | None | REFERENCES `system.organizations(id)` ON DELETE RESTRICT | Valid UUID | - | Public | Isolation. |
| `article_id` | `uuid` | NO | None | REFERENCES `public.knowledge_articles(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target. |
| `tag_id` | `uuid` | NO | None | REFERENCES `public.article_tags(id)` ON DELETE CASCADE | Valid UUID | None | Public | Link. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (organization_id, article_id, tag_id)`
*   **Index**: `CREATE INDEX article_tag_assign_tag_idx ON public.article_tag_assignments (organization_id, tag_id);`

---

## 9. SUBDOMAIN 7: AI ASSISTANCE (ADVISORY & SENTIMENT ENGINE)

Advisory classification indexes, AI-driven summarizations, draft response proposals, sentiment tracking, and recommendation paths.

### 9.1 Table Name: `public.ai_ticket_summaries`
*   **Purpose**: AI-generated structural summaries updating agents on complex historical threads.
*   **Business Responsibility**: Rapid agent onboarding & Summarization
*   **Expected Growth / Read-Write Profile**: High / Write once, read on load
*   **Retention**: 2 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Target ticket entity. |
| `summary_text` | `text` | NO | None | None | AI summary strings | None | Public | Thread summary content. |
| `confidence_score` | `numeric(4,3)` | NO | None | None | `0.000` to `1.000` | None | Public | Model validation index. |
| `token_count` | `integer` | NO | `0` | None | `token_count >= 0` | None | Public | Usage/cost tracing. |
| `generated_at` | `timestamp with time zone` | NO | `now()` | None | Date/time | None | Public | Evaluation execution moment. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ai_ticket_summaries_ticket_key UNIQUE (organization_id, ticket_id)`
*   **Check**: `CONSTRAINT ai_ticket_summaries_confidence CHECK (confidence_score BETWEEN 0.000 AND 1.000)`

---

### 9.2 Table Name: `public.ai_suggested_responses`
*   **Purpose**: Advisory suggested draft responses proposed to agents.
*   **Business Responsibility**: Average Handling Time (AHT) reduction
*   **Expected Growth / Read-Write Profile**: Very High / Rapid generation and purge
*   **Retention**: 1 Month (Purged frequently).

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Subject ticket thread. |
| `suggested_markdown`| `text` | NO | None | None | Markdown draft string | None | Public | Draft reply content. |
| `prompt_context_ids`| `uuid[]` | YES | `NULL` | None | Array of reference UUIDs | None | Public | References used in prompt. |
| `confidence_score` | `numeric(4,3)` | NO | None | None | `0.000` to `1.000` | None | Public | Quality confidence index. |
| `is_used` | `boolean` | NO | `false` | None | Boolean | None | Public | Conversion rating index. |
| `model_version` | `varchar(50)` | NO | None | None | Model identification code | None | Public | Engine audit code. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check**: `CONSTRAINT ai_suggested_responses_confidence CHECK (confidence_score BETWEEN 0.000 AND 1.000)`
*   **Index**: `CREATE INDEX ai_suggested_responses_lookup_idx ON public.ai_suggested_responses (organization_id, ticket_id) WHERE is_used = false;`

---

### 9.3 Table Name: `public.ai_ticket_classifications`
*   **Purpose**: AI-calculated predictions suggesting categories, priorities, or tags upon ticket ingestion.
*   **Business Responsibility**: Automated categorization co-pilot
*   **Expected Growth / Read-Write Profile**: High / Written once on ticket creation
*   **Retention**: Same as ticket.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Classifying ticket. |
| `predicted_category_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_categories(id)` ON DELETE SET NULL | Valid UUID | None | Public | Model Category suggestion. |
| `predicted_priority_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_priorities(id)` ON DELETE SET NULL | Valid UUID | None | Public | Model Priority suggestion. |
| `confidence_score` | `numeric(4,3)` | NO | None | None | `0.000` to `1.000` | None | Public | Machine validation metric. |
| `auto_applied` | `boolean` | NO | `false` | None | Boolean | None | Public | Indicates if route was bypass automated. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ai_ticket_class_ticket_key UNIQUE (organization_id, ticket_id)`
*   **Check**: `CONSTRAINT ai_ticket_class_confidence CHECK (confidence_score BETWEEN 0.000 AND 1.000)`

---

### 9.4 Table Name: `public.ai_sentiment_analysis`
*   **Purpose**: Tracks customer sentiment score variations across incoming messages, enabling automated escalations for frustrated clients.
*   **Business Responsibility**: Customer frustration alerts & Sentiment monitoring
*   **Expected Growth / Read-Write Profile**: Very High / Rapid transactional updates
*   **Retention**: 2 Years.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Overall ticket context. |
| `message_id` | `uuid` | YES | `NULL` | REFERENCES `public.ticket_messages(id)` ON DELETE CASCADE | Valid UUID | None | Public | Specific message rated. |
| `sentiment_score` | `numeric(4,3)` | NO | None | None | `-1.000` to `1.000` | None | Public | Score range (-1=angry, 1=happy). |
| `sentiment_label` | `varchar(20)` | NO | None | None | positive, neutral, negative | None | Public | Quick categorization label. |
| `keyword_triggers` | `varchar[]` | YES | `NULL` | None | Text array of hotkeys | None | Public | Emotional words detected. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ai_sentiment_message_key UNIQUE (organization_id, message_id)`
*   **Check**: `CONSTRAINT ai_sentiment_score_range CHECK (sentiment_score BETWEEN -1.000 AND 1.000)`
*   **Check**: `CONSTRAINT ai_sentiment_label CHECK (sentiment_label IN ('positive', 'neutral', 'negative'))`

---

### 9.5 Table Name: `public.ai_article_recommendations`
*   **Purpose**: AI recommendations mapping relevant knowledge base articles to active tickets.
*   **Business Responsibility**: Agent documentation co-pilot
*   **Expected Growth / Read-Write Profile**: High / Read-heavy dynamic matching
*   **Retention**: 1 Year.

| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Foreign Keys | Validation / Range | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `[MANDATORY]` | Multiple | - | - | See Section 1.1 | - | - | Public | Standards compliance. |
| `ticket_id` | `uuid` | NO | None | REFERENCES `public.tickets(id)` ON DELETE CASCADE | Valid UUID | None | Public | Origin ticket. |
| `article_id` | `uuid` | NO | None | REFERENCES `public.knowledge_articles(id)` ON DELETE CASCADE | Valid UUID | None | Public | Suggested article. |
| `relevance_score` | `numeric(4,3)` | NO | None | None | `0.000` to `1.000` | None | Public | Matching relevance score. |
| `is_presented` | `boolean` | NO | `true` | None | Boolean | None | Public | Displayed to agent toggle. |
| `is_clicked` | `boolean` | NO | `false` | None | Boolean | None | Public | Feedback loop metric. |

#### Relational Constraints & Indexing Strategy
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraint**: `CONSTRAINT ai_art_rec_ticket_art_key UNIQUE (organization_id, ticket_id, article_id)`
*   **Check**: `CONSTRAINT ai_art_rec_relevance CHECK (relevance_score BETWEEN 0.000 AND 1.000)`

---

## 10. CENTRALIZED FILES SYSTEM INTEGRATION

Centralized document tracking enforces low coupling. Support attachments leverage the centralized registry:
1.  **File Registration**: Attachments are uploaded directly through the centralized `public.files` registry.
2.  **Referential Integrity**: Attachments use the `public.ticket_message_attachments` junction table, linking `file_id REFERENCES public.files(id) ON DELETE RESTRICT`.
3.  **Bypass Deletion Isolation**: Files are never physically stored in Support domain tables. When a message is deleted, its junction reference cascades, but the central file record is preserved until processed by the central retention engine.

---

## 11. AUTOMATION, WEBHOOKS & WORKFLOW INTEGRATIONS

The Support domain integrates with the central workflow, notification, and automation engines.

```
+--------------------+      State Event Trigger      +--------------------------+
|  Tickets/Messages  |  -------------------------->  |  audit.outbound_events   |
|   State Machine    |                               |    (Outbox Event Log)    |
+--------------------+                               +--------------------------+
                                                                  |
                                                                  | Asynchronous
                                                                  v Ingest
                                                     +--------------------------+
                                                     |    Central Automation    |
                                                     |      & Webhook Engine    |
                                                     +--------------------------+
                                                      /          |           \
                                                     /           |            \
                                                    v            v             v
                                           +------------+  +-----------+  +------------+
                                           | Slack/Mail |  | PagerDuty |  | AI Routing |
                                           +------------+  +-----------+  +------------+
```

### 11.1 Support Event Matrix
State mutations emit outbound events. The transactional boundary requires that events are written to `audit.outbound_events` in the same PostgreSQL transaction as the state change:

*   **`ticket.created`**: Fired upon customer ticket ingest. Triggers auto-responders, classification models, and routing rules.
*   **`ticket.assigned`**: Fired when `assigned_agent_id` or `assigned_team_id` changes. Triggers agent email alerts or Slack notifications.
*   **`ticket.replied`**: Fired when a customer or agent writes a message. Triggers SLA timer checks.
*   **`ticket.escalated`**: Fired when an SLA warning threshold is reached. Triggers escalation rules and alerts.
*   **`ticket.resolved`**: Fired when a ticket is marked solved. Emits CSAT survey requests.
*   **`ticket.closed`**: Fired when a resolved ticket is finalized. Freezes all data modifications.
*   **`sla.breached`**: Fired immediately when an SLA instance passes its deadline. Triggers escalation rules.
*   **`article.published`**: Fired when a knowledge base article version changes to published status. Invalidates search engine caches.

---

## 12. NATIVE POSTGRESQL SEARCH STRATEGY

JUANET implements high-performance native PostgreSQL search to avoid external engine synchronization overhead.

### 12.1 Full-Text Search (FTS) with `tsvector`
FTS capabilities are built-in for `public.tickets`, `public.ticket_messages`, `public.ticket_internal_notes`, and `public.knowledge_articles`:
*   **Search Vector Columns**: These tables include a native `search_vector tsvector` column.
*   **Automated Triggers**: Trigger procedures compile relevant textual elements whenever rows are written. For example, for `public.tickets`:
    ```sql
    CREATE FUNCTION public.tickets_search_trigger() RETURNS trigger AS $$
    BEGIN
      NEW.search_vector :=
        setweight(to_tsvector('english', coalesce(NEW.ticket_number, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(NEW.subject, '')), 'B') ||
        setweight(to_tsvector('english', coalesce(NEW.metadata->>'requester_name', '')), 'C');
      RETURN NEW;
    END
    $$ LANGUAGE plpgsql;
    
    CREATE TRIGGER tsvectorupdate BEFORE INSERT OR UPDATE ON public.tickets
    FOR EACH ROW EXECUTE FUNCTION public.tickets_search_trigger();
    ```

### 12.2 Trigram Tracing via `pg_trgm`
To support fuzzy matching and prefix auto-completion, we utilize the `pg_trgm` extension.
*   **GIN Indexes**: Trigram GIN indexes are applied to search targets. This enables fast partial matching (e.g. searching for "Daraj" returns "MPESA Daraja API").
    ```sql
    CREATE INDEX tickets_subject_trgm_idx ON public.tickets USING GIN (subject gin_trgm_ops) WHERE deleted_at IS NULL;
    ```

---

## 13. SECURITY, COMPLIANCE & PRIVACY (GDPR)

### 13.1 Row-Level Security Policies
Every table is secured via RLS, using `organization_id` to enforce logical tenant boundaries:
```sql
ALTER TABLE public.tickets ENABLE ROW LEVEL SECURITY;

CREATE POLICY tickets_tenant_isolation ON public.tickets
  FOR ALL
  USING (organization_id = (SELECT current_setting('request.jwt.claim.organization_id', true)::uuid));
```

### 13.2 RBAC Role Integration
We enforce Role-Based Access Control (RBAC) via security context claims:
*   **Internal Notes Visibility**: Access to `public.ticket_internal_notes` is restricted to agents and admins. Customer portal logins cannot read internal notes.
*   **Attachment Sandboxing**: Attached files inherit their parent message's security context. A customer cannot access attachments on internal notes or other tenants' messages.

### 13.3 GDPR Scrubbing & PII Protection
To comply with the "Right to be Forgotten", PII fields (like names, emails, and conversation logs) are scrubbed upon request:
*   **Soft Deletes**: Standard queries omit soft-deleted rows (`deleted_at IS NULL`).
*   **Cryptographic Scrubbing**: When a GDPR deletion request is received, a scrub procedure replaces sensitive text fields with random hashes or standard placeholders. This preserves transaction metrics for reporting while anonymizing PII.
*   **Audit Trail Compliance**: Regulatory audit logs are append-only. PII scrub procedures anonymize audit tables without dropping the transaction log rows.

---

## 14. DATABASE PERFORMANCE, GROWTH & PARTITIONING

### 14.1 Horizontal Range Partitioning
High-volume log tables are horizontally range-partitioned to maintain performance as growth occurs:
*   **`public.ticket_activity_logs`**: Range partitioned monthly on `created_at`.
*   **`public.ticket_status_history`**: Range partitioned monthly on `created_at`.
*   This enables old partitions to be detached, compressed, and archived seamlessly without blocking active transaction tables.

### 14.2 Index Optimization (Covering and Partial Indexes)
*   **Covering Indexes**: High-performance dashboard queries avoid page reads by leveraging covering indexes that store payload variables directly in the index structure (e.g. `INCLUDE (subject, status_id)`).
*   **Partial Indexes**: Operational search queries leverage partial indexes that filter out closed or soft-deleted records (`WHERE deleted_at IS NULL AND status_id != 'closed_id'`). This keeps active index sizes compact and highly cached.

---

## 15. SUPPORT DATABASE CLASSIFICATION MATRIX

Canonical reference summarizing security profiles, transaction patterns, encryption, partitioning, and primary system integrations:

| Table Name | Ownership Domain | Write Profile | Partitioned | JSONB Usage | Encryption | PII | Audit Level | Retention | Primary Integrations |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `ticket_categories` | Config Core | Low-Write | No | Extensible | None | No | Low | Indefinite | System Configurations |
| `ticket_priorities` | Config Core | Read-Only | No | No | None | No | Low | Indefinite | SLA Engine |
| `ticket_statuses` | Config Core | Read-Only | No | No | None | No | Low | Indefinite | State Machine |
| `ticket_types` | Config Core | Read-Only | No | No | None | No | Low | Indefinite | Ticket Routing |
| `support_queues` | Routing Core | Low-Write | No | Rules Logic | None | No | High | Indefinite | Routing Rules |
| `support_teams` | Workforce | Low-Write | No | No | None | No | High | Indefinite | Agent Assignment |
| `support_agents` | Workforce | Medium-Write | No | No | None | Yes | High | Indefinite | Auth, Agent Routing |
| `tickets` | Operational | High-Write | No | Metadata | None | Yes | High | Permanent | CRM, Projects, AI |
| `ticket_watchers` | Collaboration | Medium-Write | No | No | None | Yes | Low | Ticket Life | Notification Engine |
| `ticket_assignments` | Workforce | High-Write | No | No | None | No | Full | Permanent | Audit Logs |
| `ticket_tags` | Config Core | Low-Write | No | No | None | No | Low | Permanent | Search, Taxonomy |
| `ticket_tag_assignments`| Operational | High-Write | No | No | None | No | Low | Ticket Life | Ticket Search |
| `ticket_messages` | Ingest Core | High-Write | No | No | None | Yes | Full | Permanent | Omnichannel Mail, Portal |
| `ticket_msg_attachments`| Ingest Core | High-Write | No | No | None | No | High | File Life | Central Files Registry |
| `ticket_internal_notes` | Operational | High-Write | No | No | None | Yes | Full | Permanent | Agent Workspace |
| `ticket_mentions` | Collaboration | High-Write | No | No | None | Yes | Low | 1 Year | Notification Engine |
| `ticket_msg_reactions` | Collaboration | High-Write | No | No | None | No | Low | Ticket Life | Portal Interface |
| `ticket_activity_logs` | Audit Core | High-Write | Yes (Monthly) | JSON Diff | None | Yes | Full | 7 Years | Security compliance |
| `ticket_status_history` | Audit Core | High-Write | Yes (Monthly) | No | None | No | Full | 5 Years | Queue Performance |
| `ticket_assign_history` | Audit Core | High-Write | No | No | None | No | Full | 5 Years | SLA Reports |
| `ticket_time_entries` | Financial | High-Write | No | No | None | No | Full | 7 Years | Projects Billing, Ledger |
| `ticket_followups` | Operational | Medium-Write | No | No | None | No | Low | Ticket Life | Ticket Deduplication |
| `sla_policies` | SLA Engine | Low-Write | No | Rules Match | None | No | High | Indefinite | CRM Customer Contracts |
| `sla_targets` | SLA Engine | Low-Write | No | No | None | No | High | Indefinite | Target SLA calculations |
| `sla_calendars` | SLA Engine | Read-Only | No | No | None | No | Low | Permanent | Regional SLA Engine |
| `business_hours` | SLA Engine | Read-Only | No | No | None | No | Low | Permanent | Shift calculations |
| `holiday_calendars` | SLA Engine | Read-Only | No | No | None | No | Low | Permanent | Holiday exclusions |
| `ticket_sla_instances` | SLA Engine | High-Write | No | No | None | No | Full | 5 Years | Active dashboard queues |
| `sla_escalation_rules` | SLA Engine | Low-Write | No | No | None | No | High | Indefinite | Automation Alerts |
| `sla_escalation_actions`| SLA Engine | Low-Write | No | Config | None | No | High | Indefinite | Workflows, Webhooks |
| `csat_surveys` | Customer CX | Medium-Write | No | No | None | Yes | High | 5 Years | Outbound mailers |
| `csat_questions` | Customer CX | Low-Write | No | No | None | No | Low | Indefinite | Survey Engine |
| `csat_responses` | Customer CX | High-Write | No | No | None | Yes | Full | 5 Years | Performance analytics |
| `nps_surveys` | Customer CX | Medium-Write | No | No | None | Yes | Full | 5 Years | Executive Loyalty KPIs |
| `support_feedback` | Customer CX | High-Write | No | No | None | Yes | High | 5 Years | Ticket reporting |
| `knowledge_categories` | Knowledge | Low-Write | No | No | None | No | Low | Permanent | Portal Help Center |
| `knowledge_articles` | Knowledge | Low-Write | No | No | None | No | High | Permanent | Help center FTS, AI |
| `article_versions` | Knowledge | Medium-Write | No | No | None | No | Full | Permanent | Review Governance |
| `article_comments` | Knowledge | Medium-Write | No | No | None | Yes | Low | 3 Years | Portal communities |
| `article_feedback` | Knowledge | High-Write | No | No | None | Yes | Low | 5 Years | Article updates |
| `article_tags` | Knowledge | Low-Write | No | No | None | No | Low | Permanent | Help center taxonomies |
| `article_tag_assignments`| Knowledge | Medium-Write | No | No | None | No | Low | Article Life | Article taxonomies |
| `ai_ticket_summaries` | AI Advisor | High-Write | No | No | None | No | Low | 2 Years | AI Engine API |
| `ai_suggested_responses`| AI Advisor | High-Write | No | No | None | No | Low | 1 Month | In-app workspace |
| `ai_ticket_class` | AI Advisor | High-Write | No | No | None | No | Low | Ticket Life | Route automation |
| `ai_sentiment_analysis` | AI Advisor | High-Write | No | No | None | No | Low | 2 Years | Escalation triggers |
| `ai_article_recommend` | AI Advisor | High-Write | No | No | None | No | Low | 1 Year | Workspace sidebar |
