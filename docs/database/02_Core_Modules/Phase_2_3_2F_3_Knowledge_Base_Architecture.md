# JUANET Support Knowledge Base Architecture Specification
## Phase 2.3.2F.3 — Knowledge Base Architecture
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Knowledge Management Architecture  

---

## 1. ARCHITECTURAL PHILOSOPHY

The JUANET Knowledge Base (KB) Architecture operates as the authoritative enterprise knowledge management system for the Support domain. It is designed to empower customer self-service, facilitate rapid agent onboarding, and integrate with AI engines.

The architecture is built on the following core principles:

*   **Documentation as Single Source of Truth**: The Knowledge Base is the definitive source for troubleshooting steps, product configurations, policy standards, and reference documentation across the platform.
*   **Separation of Concerns**: Article content, templates, and revision histories are stored in dedicated tables (`knowledge_articles`, `article_versions`), decoupling knowledge assets from operational ticket data.
*   **Immutable Revision History**: All modifications create a new, immutable record in `public.article_versions`. Active articles reference a specific approved version snapshot, providing audit fidelity and simple rollback options.
*   **Event-Driven Ingestion and Sync**: Article publications, revisions, feedback, and search execution generate outbound transaction events (`knowledge.article.published`, `knowledge.feedback.received`, etc.) to keep search indexes and analytics caches in sync.
*   **Logical and Physical Separation**: The system separates raw text bodies and localization metadata from searchable vector representations, preventing lock contention during heavy traffic.
*   **Multi-Tenant Isolation & RLS**: All tables enforce Row-Level Security (RLS) using `organization_id` as the tenant partition key, ensuring complete isolation of intellectual property.
*   **Optimistic Concurrency Control**: Article updates track a `version` column. Concurrent edits trigger serialization errors if conflicts occur, prompting editors to merge changes.
*   **Advisory-Only AI Generation**: AI assistants help draft articles, generate summaries, detect translation gaps, and recommend tags, but every action remains advisory and requires manual review and approval.
*   **Maker-Checker Content Governance**: Standard security policies require that different users perform the writing and approval roles before publishing any customer-facing knowledge.

---

## 2. KNOWLEDGE ARCHITECTURE & RELATIONSHIPS

The Knowledge Base utilizes a modular, relational data model to manage taxonomy, versioning, localization, and feedback. The diagram below illustrates how these entities interact:

```
                  [public.knowledge_categories] (Recursive Hierarchy)
                               │
                               ▼
                  [public.knowledge_articles]
                               │
            ┌──────────────────┼──────────────────┐
            ▼                  ▼                  ▼
   [article_versions]  [article_comments]  [article_feedback]
            │                                     │
            ├──────────────┐                      ▼
            ▼              ▼               [csat_metrics]
    [article_tags]  [files (Media)]
            │
            ▼
   [tag_assignments]
```

### 2.1 Component Interactions

1.  **Categorization & Article Directory**: Articles (`public.knowledge_articles`) are organized within a tree structure in `public.knowledge_categories`.
2.  **Version Snapshots**: Text content, subjects, metadata, and attachment catalogs are stored within `public.article_versions`. The main article record points to the active, published version ID.
3.  **Governance & Approvals**: Transitions from drafts to published states require approvals, logged inside validation history tables.
4.  **Feedback Loops**: Customer evaluations (ratings, comments) are captured in `public.article_comments` and `public.article_feedback`, feeding deflection metrics and updating content quality scores.

---

## 3. ARTICLE LIFECYCLE (FINITE STATE MACHINE)

Knowledge base articles transition through a structured state machine to ensure editorial review, compliance checks, and secure publication.

```
 [Draft] ──> [In Review] ──> [Pending Approval] ──> [Approved] ──> [Published]
   │              │                 │                 │              │
   │              ▼                 ▼                 │              ▼
   └──────────────┴─────────────────┴─────────────────┴───────> [Deprecated]
                                                                     │
                                                                     ▼
                                                                 [Archived]
```

### 3.1 State Definitions and Transition Rules

#### 3.1.1 `draft`
*   **Purpose**: The initial authoring state. Content is actively written or modified by an editor.
*   **Entry Rules**: Transition permitted from scratch (new) or by branching/revising a `published` article.
*   **Exit Rules**: Transitions to `in_review` or `archived`.
*   **Forbidden Transitions**: Cannot transition directly to `published`, `deprecated`, or `approved`.

#### 3.1.2 `in_review`
*   **Purpose**: Undergoing initial editing, formatting, and media verification.
*   **Entry Rules**: Permitted from `draft` when the author submits the article for review.
*   **Exit Rules**: Transitions back to `draft` (needs work) or forward to `pending_approval`.
*   **Forbidden Transitions**: Cannot transition directly to `published`.

#### 3.1.3 `pending_approval`
*   **Purpose**: Locked for review by compliance, security, technical, or legal teams.
*   **Entry Rules**: Permitted from `in_review`.
*   **Exit Rules**: Transitions to `approved` or back to `draft` (rejected).
*   **Forbidden Transitions**: Cannot be edited while in this state.

#### 3.1.4 `approved`
*   **Purpose**: Validated and signed off. Awaiting immediate publication or its scheduled publication date.
*   **Entry Rules**: Permitted from `pending_approval` once all required approval rules are met.
*   **Exit Rules**: Transitions to `published` or `scheduled`.

#### 3.1.5 `published`
*   **Purpose**: Live and visible to authorized audiences (internal agents, specific clients, or the general public).
*   **Entry Rules**: Permitted from `approved` (manually triggered) or automatically on its scheduled publication date.
*   **Exit Rules**: Transitions to `deprecated`, `archived`, or back to `draft` (for minor revisions).

#### 3.1.6 `deprecated`
*   **Purpose**: Content is outdated or superseded, but remains visible with a deprecation warning while users transition to updated articles.
*   **Entry Rules**: Permitted from `published`.
*   **Exit Rules**: Transitions to `archived`.

#### 3.1.7 `archived`
*   **Purpose**: Removed from search indexes and general customer views, but preserved for historical audits.
*   **Entry Rules**: Permitted from `published`, `deprecated`, or `draft`.
*   **Exit Rules**: Transitions to `draft` (if restored).

#### 3.1.8 `deleted`
*   **Purpose**: Soft-deleted. Hidden from standard views but preserved in the database to maintain referential integrity.
*   **Entry Rules**: Permitted from any state.
*   **Exit Rules**: None.

---

## 4. AUTHORING WORKFLOW & CONTENT CREATION

The Knowledge Base authoring interface provides tools for manual, template-based, and AI-assisted content creation:

*   **Manual Authoring**: Built-in Markdown editors sanitize raw text input and convert inline images to secure attachments using the centralized `public.files` registry.
*   **Template Engines**: Authors can choose from pre-configured article templates (e.g., Troubleshooting guides, FAQs, Release notes, Policies). Templates define standard layouts, section headings, and metadata fields.
*   **AI-Assisted Drafting**: Integrates with the platform's AI models to help generate article outlines, summarize long technical specifications, translate content, and suggest relevant taxonomy tags based on ticket history. All AI-generated outputs require human review and approval.
*   **Draft Autosave & Recovery**: Auto-saves active drafts every 30 seconds to the browser's local storage and syncs draft checkpoints to the database periodically. If a session disconnects, authors can restore their work on reconnect.

---

## 5. VERSION CONTROL & REVISION MANAGEMENT

Every modification to an article creates an immutable revision record to preserve audit history and support rolling updates.

### 5.1 Versioning Rules and Mechanics

*   **Immutable Revision Records**: Edits do not overwrite existing data. Instead, they insert a new row in `public.article_versions`.
*   **Semantic Versioning**:
    *   **Major Revisions (e.g., v1.0 to v2.0)**: Used for significant updates, structural rewrites, or technical changes. Major versions require a full approval loop.
    *   **Minor Revisions (e.g., v1.0 to v1.1)**: Used for minor updates, typo corrections, or formatting fixes. Minor versions bypass formal approval workflows and can be published immediately.
*   **Version Comparison and Diffing**: A built-in diff engine compares differences between two versions of an article, highlighting additions, deletions, and metadata updates.
*   **Rollback Capability**: Authorized publishers can roll back an article to a previous version. This action clones the selected historical version, assigns it a new version number, and promotes it to the active `published` state.

---

## 6. APPROVAL WORKFLOWS & COMPLIANCE

Content must pass through configured approval workflows before publication:

*   **Maker-Checker Rule**: The author of a draft cannot approve or publish their own work. Approvals must be granted by a separate authorized reviewer.
*   **Multilateral Approvals**: Based on the article's category and risk rating, the system triggers parallel or sequential approval requests across several teams:
    *   **Technical Review**: Verifies troubleshooting commands, code snippets, and system diagnostics are accurate.
    *   **Security Review**: Ensures no sensitive network configurations, private API keys, or security vulnerabilities are exposed.
    *   **Compliance & Legal Review**: Confirms alignment with regional regulations, SLA promises, and customer disclosure policies.
*   **Emergency Publication**: Under extraordinary circumstances (e.g., major system outages), support directors can bypass standard approvals to publish critical instructions. This action requires logging an emergency justification, which dispatches immediate alerts to security and compliance teams for retro-audit reviews.

---

## 7. PUBLISHING ENGINE & VISIBILITY CONTROLS

The publishing engine controls when and to whom articles are visible, supporting targeted audience segments:

*   **Immediate Publishing**: Updates the article's active version reference immediately upon approval.
*   **Scheduled Publishing**: Authors can specify future release dates and times. A background scheduler monitors these schedules and updates the article status to `published` when the target timestamp is reached.
*   **Scheduled Expiration**: Articles can be configured to automatically transition to `deprecated` or `archived` on a specific expiration date (e.g., temporary system promotions, time-sensitive software instructions).
*   **Audience Visibility Segments**:
    *   **Public Visibility**: Visible to all users, anonymous portal guests, and search engines.
    *   **Authenticated Clients Only**: Visible only to authenticated customer users.
    *   **Internal Agents Only**: Visible only to internal support agents, billing teams, and engineers.
    *   **Role-Restricted Visibility**: Access is restricted to users with specific RBAC roles (e.g., Gold Support clients, System Administrators).

---

## 8. SEARCH & INDEXING ARCHITECTURE

To support rapid self-service deflection, the Knowledge Base utilizes PostgreSQL's native search features to index and retrieve articles.

```
Article Published ──> Generate tsvector ──> Store in GIN Index ──> Query using fuzzy match & pg_trgm
```

### 8.1 Text Indexing & Retrieval Standards

*   **Vector Search Representation**: Article titles, descriptions, and text bodies are compiled into `tsvector` formats using regional configurations (e.g., `english`, `french`, `swahili`).
*   **GIN Indexes**: The `tsvector` columns are indexed using Generalized Index (GIN) strategies, optimizing keyword searches on massive content repositories.
*   **Trigram Indexing (`pg_trgm`)**: Applied to article titles to support rapid auto-complete and typo-tolerant searches:
    ```sql
    CREATE INDEX article_title_trgm_idx ON public.article_versions USING GIST (title gist_trgm_ops);
    ```
*   **Weighted Search Ranking**: Search queries compute matching scores across article sections:
    *   **Title Matches**: Weighted highest (Weight: `A`).
    *   **Summary & Subject Matches**: Weighted second highest (Weight: `B`).
    *   **Body Text Matches**: Weighted standard (Weight: `C`).
*   **Synonym and Stemming Rules**: Stop-words are filtered, and stemming dictionaries are applied to match root terms (e.g., matching "configur" when searching "configuration", "configured", or "configuring").

---

## 9. TAXONOMY & CLASSIFICATION

Articles use a structured taxonomy to keep content organized and easily navigable:

*   **Hierarchical Categories**: Categories (`public.knowledge_categories`) support infinite nesting. An article is linked to a primary category node, which inherits parents up to the root level.
*   **Dynamic Tagging**: Articles can be tagged with keywords (`public.article_tags`). Tags are alphanumeric and help users locate related content across different categories.
*   **Metadata Blocks**: Dynamic, tenant-specific attributes (e.g., software version, hardware model, operating system) are stored in JSONB formats, allowing users to filter search results by specific dimensions.

---

## 10. LOCALIZATION ENGINE

To support international deployments, the Knowledge Base supports native localization across multiple languages.

### 10.1 Translation & Localization Standards

*   **Master Language**: Articles designate a master language (e.g., `en-US`).
*   **Translation Mapping**: Translated variants are linked back to the master article ID. Each translation manages its own title, body, metadata, and attachment references.
*   **Fallback Resolution**: If a user searches in a language that does not have an approved translation, the system resolves the request by falling back to the master language, displaying a warning notice (e.g., "This article is not available in French. Showing English version.").
*   **Localized Assets**: Allows attaching region-specific images, videos, and PDFs to translations, ensuring instructions are tailored to the local audience.

---

## 11. AI ASSISTANCE & GOVERNANCE BOUNDARIES

The system leverages artificial intelligence to streamline knowledge operations while maintaining human oversight.

### 11.1 AI Assistance Features
*   **Suggested Articles**: Analyzes ticket content and suggests matching knowledge base articles to agents in real time, helping speed up resolutions.
*   **Draft Generation**: Generates draft articles based on historical support tickets, helping document solutions for recurring issues.
*   **Knowledge Gap Detection**: Analyzes search terms that yielded no results alongside unresolved ticket categories, highlighting areas where new documentation is needed.
*   **Outdated Article Detection**: Flags articles that have not been updated for a long period of time or that have received low helpfulness ratings, alerting editors to review them.

---

## 12. TICKET INTEGRATION

The Knowledge Base is integrated with the Support Ticket Lifecycle Engine:

*   **Self-Service Deflection**: When a user begins typing a ticket subject in the client portal, the system runs active searches and displays matching articles, helping deflect tickets before they are created.
*   **Resolution Linking**: When resolving a ticket, agents can associate it with the article that helped solve the issue. This link is recorded in the ticket history, helping improve AI recommendations over time.
*   **Auto-Draft Proposals**: If a ticket is resolved with a unique, high-quality solution that has no matching article, the agent can click "Propose Article". This copies the ticket conversation to a new knowledge base draft, prompting the authoring team to review and publish it.

---

## 13. ANALYTICS & INSIGHTS ENGINE

The system aggregates article interactions, search executions, and user feedback to evaluate content quality:

### 13.1 Core Metrics and Calculations

#### 13.1.1 Deflection Rate
Measures the percentage of users who searched the knowledge base and successfully solved their issue without opening a ticket:
$$\text{Deflection Rate} = \left( \frac{\text{Unique Sessions with Search and No Ticket Creation}}{\text{Total Unique Search Sessions}} \right) \times 100$$

#### 13.1.2 Search Success Rate
Measures the percentage of search executions that resulted in article clicks:
$$\text{Search Success Rate} = \left( \frac{\text{Searches Resulting in Article Clicks}}{\text{Total Search Executions}} \right) \times 100$$

#### 13.1.3 Article Usefulness Rating
Calculates the rating score of an article based on user feedback (Yes/No helpfulness votes):
$$\text{Usefulness Score} = \left( \frac{\text{Positive Helpful Votes}}{\text{Total Helpful Votes}} \right) \times 100$$

---

## 14. PERFORMANCE & GROWTH OPTIMIZATIONS

To support heavy search traffic, the database applies the following performance optimization strategies:

*   **Monthly Partitioning**: Knowledge feedback logs and search analytics records are partitioned monthly on the `created_at` column.
*   **GIN and Trigram Indexes**: Full-text and auto-complete searches on titles and bodies use GIN and pg_trgm indexes to bypass slow runtime wildcard searches.
*   **Materialized Views**: Aggregated metrics (such as daily article views and search success rates) are queried from materialized views refreshed during low-traffic periods.

---

## 15. EVENT CONTRACTS

The Knowledge Base engine publishes transactional events to keep other systems aligned with article updates and content publishing status.

### 15.1 Core System Events

#### 15.1.1 `knowledge.article.published`
*   **Trigger**: An article's approved version is published and made live.
*   **Payload Schema**:
```json
{
  "event_id": "evt_4410a538-77ba-4abc-9922-31a221f110b2",
  "event_type": "knowledge.article.published",
  "timestamp": "2026-06-29T06:15:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "article_id": "kb_1234a567-b89c-12d3-a456-426614175000",
    "version_id": "ver_9921b384-90ba-4cbb-9f1e-f3a140131109",
    "title": "Resetting Database Passwords",
    "category_id": "cat_8831a123-bfbc-4122-a9b3-1f19f2a00d51",
    "visibility": "public",
    "language": "en-US"
  }
}
```

#### 15.1.2 `knowledge.feedback.received`
*   **Trigger**: A user submits a helpfulness vote or comment on an article.
*   **Payload Schema**: Includes `article_id`, `feedback_type` (helpful_yes/helpful_no), and `rating_score`.

#### 15.1.3 `knowledge.search.executed`
*   **Trigger**: A search query is executed against the knowledge base.
*   **Payload Schema**: Includes `search_term`, `results_returned_count`, and `clicked_article_id` (if any).

---

## 16. SECURITY & ACCESS CONTROL

Article management and visibility are restricted to protect sensitive intellectual property and preserve content governance.

*   **Role-Based Access Control (RBAC)**:
    *   **Authors**: Can create, edit, and save drafts of articles, but cannot approve or publish content.
    *   **Reviewers**: Can review drafts, add inline comments, and reject or approve submissions.
    *   **Publishers**: Can approve drafts, manage schedules, deprecate articles, and publish content.
*   **Row-Level Security (RLS)**: RLS policies partition content by `organization_id`, ensuring that customer users can only view articles belonging to their tenant organization.
*   **GDPR Compliance**: Feedback comments are subject to deletion workflows. Upon request, comments are purged or redacted to remove personal details.

---

## 17. VALIDATION MATRIX

Below is the verification checklist to ensure the Knowledge Base Architecture functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **State Machine** | Transition `draft` -> `published` | **Rejected**. Must transition through review and approval. |
| **State Machine** | Transition `published` -> `draft` | **Permitted**. Creates a new draft version, active version remains published. |
| **Governance** | Author attempting to approve their own draft | **Rejected**. Maker-checker rules require a separate reviewer. |
| **Search** | Match search term "configuration" | **Success**. Standard English stemming maps search to "configur". |
| **Localization** | Query translation fallback | **Success**. Shows warning, falls back to master language. |
| **SLA / Feedback** | Feedback helpfulness rating update | **Success**. Updates article helpfulness metrics and triggers events. |
| **Security** | Guest attempting to view role-restricted article | **Rejected**. Access control blocks unauthorized views. |

---

This document serves as the architectural reference for implementing knowledge management, localization, and search workflows within the JUANET Platform. All components must adhere strictly to these specifications.
