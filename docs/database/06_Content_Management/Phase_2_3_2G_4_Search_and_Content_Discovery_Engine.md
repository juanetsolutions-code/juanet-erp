# JUANET Search and Content Discovery Engine Implementation Manual
## Phase 2.3.2G.4 — Read-Model Architecture, Multi-Tenant Indexing Pipelines, Fuzzy Matching, and Hybrid Semantic Retrieval
**Document Version:** 1.0  
**Author:** Chief Search Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Discovery Specification  

---

## 1. SEARCH ARCHITECTURE (CQRS SEPARATION)

In a high-scale, multi-tenant enterprise CMS, executing full-text, fuzzy, and semantic searches directly against normalized transactional tables (`public.content_items`, `public.localized_content`) introduces serious write-path locking issues and degrades query latency. The **JUANET Search & Content Discovery Engine** establishes a strict **Command Query Responsibility Segregation (CQRS)** pattern.

The search engine does not act as the System of Record (SoR). Instead, it acts as an optimized, denormalized read model. The transactional CMS write path processes structural edits and persists normalized content tables, while the search model asynchronously consumes transactional database outbox events to build, optimize, and serve high-performance searchable document records.

```
                              [JUANET CQRS SEARCH BOUNDARY]

   ┌────────────────────────────────────────────────────────────────────────┐
   │                       CMS TRANSACTIONAL WRITE PATH                     │
   │   - Normalized Entities: content_items, media_assets, taxonomies       │
   │   - Triggers outbox event log: audit.outbound_events                   │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (Asynchronous Event Dispatch)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                     EVENT-DRIVEN INDEXING PIPELINE                     │
   │   - Search Consumer Worker parses events: content.published, etc.     │
   │   - Resolves localized graphs, relationships, and taxonomies          │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (Batch Upserts)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                        SEARCH READ MODEL (POSTGRES)                    │
   │   - Denormalized records stored in: public.search_documents            │
   │   - Native PG16 indexing: Weighted FTS GIN, pg_trgm GIN, pgvector HNSW │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (Low-Latency Read Queries)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                        HEADLESS DELIVERY ENGINE                        │
   │   - Fast API queries utilizing tenant-isolated RLS filters             │
   └─────────────────────────────────────────────────────────────────────────┘
```

The system enforces the following core architectural rules:
*   **Search as a Read Model**: The search index is purely derived from transactional CMS states. Under no circumstances does the Search Engine modify, update, or locking-guard the core transactional tables.
*   **Asynchronous Eventual Consistency**: Modifications to content items or taxonomies trigger async event handlers. The search index must be updated within an SLA of under 1000ms from the transactional commit.
*   **Decoupled Search Workers**: Dedicated background worker pools consume the transactional outbox logs, separating search processing loads from the main API thread pool.
*   **Zero Impact on Transactability**: If the search indexing queue or a vector embedding generator fails, transactional content modifications must still complete without interruption.
*   **Strict Row-Level Security (RLS)**: Because search documents are stored inside unified multi-tenant database tables, PostgreSQL Row-Level Security isolates searches dynamically based on verified tenant session context parameters.

---

## 2. SEARCH DOCUMENT MODEL

To enable fast searches, the engine denormalizes normalized relational data (such as content fields, localized variations, media attachments, and category tags) into a single, unified search record structure within `public.search_documents`.

### 2.1 The Unified Search Schema
The physical document model is structured using a combination of fast scalar index attributes and flexible, queryable JSON schemas:

```sql
-- DDL for Search Read Model Document Store
CREATE TABLE public.search_documents (
    id UUID PRIMARY KEY, -- Maps directly to the target localized_content_id or media_asset_id
    organization_id UUID NOT NULL, -- Core tenant partitioning key
    document_type VARCHAR(64) NOT NULL, -- 'content_item', 'media_asset', 'kb_article', etc.
    locale_code VARCHAR(16) NOT NULL, -- ISO language code (e.g., 'en-US', 'fr-FR')
    slug VARCHAR(512) NOT NULL, -- Localized path slug
    
    -- Text fields optimized for full-text, fuzzy, and prefix indexing
    title TEXT NOT NULL,
    description TEXT,
    body_content TEXT, -- Extracted raw textual copy, stripped of HTML layout elements
    
    -- Strongly-typed JSON schema containing metadata, tags, and category taxonomies
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    
    -- Security & Publishing flags
    publish_status VARCHAR(32) NOT NULL DEFAULT 'Published',
    visibility VARCHAR(32) NOT NULL DEFAULT 'Public', -- 'Public', 'Internal', 'Private'
    embargo_ends_at TIMESTAMPTZ,
    allowed_roles VARCHAR(64)[] DEFAULT ARRAY['Guest']::VARCHAR[], -- Fine-grained RBAC list
    
    -- Numerical search performance parameters
    popularity_score NUMERIC(5,2) NOT NULL DEFAULT 1.00,
    freshness_date TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- System timestamps
    indexed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ
);
```

### 2.2 Canonical JSON Metadata Structure
The `metadata` field maps taxonomies, authors, structural relationships, and custom metadata, supporting fast multi-faceted aggregations:

```json
{
  "taxonomies": {
    "categories": ["Enterprise Software", "Cloud Architecture"],
    "tags": ["PostgreSQL", "CQRS", "AI Search"],
    "author_name": "Sarah Jenkins",
    "department": "Technical Content"
  },
  "media_references": [
    {
      "media_asset_id": "7e2b4011-0000-4000-a000-000000000001",
      "thumbnail_path": "/assets/company_hq_thumb.webp"
    }
  ],
  "seo": {
    "meta_title": "Enterprise CQRS Search Implementations using PostgreSQL 16",
    "meta_description": "A comprehensive analysis of read-model sync strategies in multi-tenant environments."
  },
  "custom_attributes": {
    "campaign_code": "TECH-SERIES-2026",
    "read_time_minutes": 8
  }
}
```

---

## 3. INDEXING PIPELINE

The search indexing pipeline processes content mutations asynchronously, routing database events into structured search updates.

```
                         SEARCH INDEXING PIPELINE
  [CMS Event Received] ──► [Filter & Validation Check] ──► [Denormalize Document Payload]
                                                                     │
         ┌───────────────────────────────────────────────────────────┴─────────────────────────┐
         ▼ (Standard Text Search)                                                              ▼ (AI Semantic Search)
  [Update Weighted tsvectors] ──► [Verify pg_trgm Indices]                             [Fetch Embeddings & HNSW]
```

### 3.1 Pipeline Processing States
1.  **Event Capture**: Background worker services consume outbox queue entries (e.g., `content.published`, `media.ready`, `translation.published`).
2.  **Validation**: Evaluates event payloads, dropping temporary drafts and checking block lists.
3.  **Denormalization**: Runs fast database read queries, combining localized content fields, taxonomy keywords, and author meta parameters into a unified search document payload.
4.  **AI Vector Generation (Asynchronous)**: Dispatches text blocks to embedding APIs (e.g., text-embedding models) to calculate semantic vector float arrays.
5.  **Index Persistence**: Writes the finalized search document to the `public.search_documents` catalog inside a unified upsert transaction.
6.  **Pipeline Completion**: Dispatches `search.index.updated.v1` to trigger CDN and Redis edge cache invalidations.

### 3.2 Error & Retry SLA Strategies
*   **Retry Policy**: Network errors or API timeouts trigger exponential-backoff retries (e.g., 5 retry attempts, backoff factor = 2s, maximum delay = 60s).
*   **Dead-Letter Queue (DLQ)**: Failing processing runs are written to `audit.failed_search_indexes` with detailed error logs, allowing administrators to debug pipeline issues without losing event data.
*   **Deduplication Keys**: Compares event identifiers (`event_id`, `version`) to prevent duplicate indexing steps under heavy transaction loads.

---

## 4. POSTGRESQL NATIVE FULL-TEXT SEARCH (FTS)

The platform utilizes native PostgreSQL 16 text search configurations, generating weighted `tsvector` arrays on the read model to deliver fast, highly accurate keyword search:

### 4.1 Weighted tsvector Generation & Indexing
To ensure relevance accuracy, search fields are weighted by priority:
*   **Weight A (Highest)**: Document Title (`title`)
*   **Weight B**: Description / Short Summary (`description`)
*   **Weight C**: Categorization Tags (`metadata -> taxonomies -> tags`)
*   **Weight D**: Full Page Text Payload (`body_content`)

The system creates the `tsvector` column and optimizes lookups using a Generalized Inverted Index (GIN):

```sql
-- Add the combined, weighted full-text search column
ALTER TABLE public.search_documents 
ADD COLUMN fts_vector tsvector GENERATED ALWAYS AS (
    setweight(to_tsvector('english', COALESCE(title, '')), 'A') ||
    setweight(to_tsvector('english', COALESCE(description, '')), 'B') ||
    setweight(jsonb_to_tsvector('english', COALESCE(metadata->'taxonomies'->'tags', '[]'::jsonb), '["string"]'), 'C') ||
    setweight(to_tsvector('english', COALESCE(body_content, '')), 'D')
) STORED;

-- Build GIN search index
CREATE INDEX idx_search_docs_fts ON public.search_documents USING GIN (fts_vector);
```

### 4.2 Advanced Native FTS Query Formulation
The query parser processes user search queries, handling prefix matching, phrase search, and Boolean parameters:

```sql
-- Native FTS Query with Dynamic Weight Ranking and Highlighting
SELECT 
    id,
    title,
    ts_rank_cd(fts_vector, query) AS rank_score,
    ts_headline('english', body_content, query, 'StartSel = <mark>, StopSel = </mark>, MaxWords = 35, MinWords = 15') AS highlight_snippet
FROM public.search_documents,
     to_tsquery('english', 'enterprise & cloud & architecture:*') AS query
WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
  AND locale_code = 'en-US'
  AND publish_status = 'Published'
  AND visibility = 'Public'
  AND fts_vector @@ query
ORDER BY rank_score DESC, popularity_score DESC
LIMIT 15;
```

---

## 5. FUZZY SEARCH, TYPO TOLERANCE, AND DICTIONARIES

To handle spelling errors and provide autocomplete suggestions, the system utilizes trigram matching and native spelling dictionaries.

### 5.1 Trigram GIN Indexes & Similarity Thresholds
The `pg_trgm` extension splits words into three-character sequences (e.g., "cloud" ──► `{"  c"," cl","clo","lou","oud","ud "}`), enabling the system to match terms even with typos or partial strings:

```sql
-- Enable PostgreSQL Trigram Extension
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Index title and tags for fast partial-string lookups
CREATE INDEX idx_search_docs_title_trgm ON public.search_documents USING GIN (title gin_trgm_ops);
```

Fuzzy lookups query values using the pg_trgm similarity function:
```sql
-- Query with custom similarity threshold (e.g., match words within 40% distance)
SET pg_trgm.similarity_threshold = 0.40;

SELECT id, title, similarity(title, 'enterpris cloud') AS match_score
FROM public.search_documents
WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
  AND title % 'enterpris cloud'
ORDER BY match_score DESC
LIMIT 5;
```

### 5.2 Autocomplete, Suggestions, and Synonym Expansion
*   **Autocomplete**: Queries the trigram index dynamically on every keystroke, matching document titles and taxonomies.
*   **Synonym Mapping**: Maps matching terms (such as 'bill' and 'invoice') to return consistent search results across countries.
*   **"Did You Mean?" Spell Correction**: If a query returns zero results, the system queries a tenant-isolated word dictionary (`public.search_vocabulary`) to suggest alternate spellings.

---

## 6. HYBRID SEMANTIC VECTOR SEARCH (PGVECTOR)

To support semantic search and match search intent, the search engine utilizes vector embeddings stored within the PostgreSQL database using the `pgvector` extension.

*   **Advisory-Only Vector Generation**: AI models represent search intent and attributes as vector float arrays. The core search engine stores, indexes, and queries these embeddings inside Postgres.

```sql
-- Enable pgvector Extension
CREATE EXTENSION IF NOT EXISTS pgvector;

-- Add a standard embedding column for text representation (e.g. 1536 float dimensions)
ALTER TABLE public.search_documents 
ADD COLUMN text_embedding vector(1536);
```

### 6.1 HNSW Vector Indexing
The system uses Hierarchical Navigable Small World (HNSW) indexes to deliver fast, low-latency similarity searches under heavy query loads:

```sql
-- Build HNSW Vector Index with Cosine Distance Operator
CREATE INDEX idx_search_docs_hnsw ON public.search_documents USING hnsw (text_embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);
```

### 6.2 Reciprocal Rank Fusion (RRF) Hybrid Search
The query engine uses Reciprocal Rank Fusion (RRF) to combine traditional text search scores and vector similarity scores, returning highly relevant results:

```sql
-- Hybrid Search Query combining Keyword (FTS) and Semantic (Vector) relevance scores
WITH fts_results AS (
    SELECT id, row_number() OVER (ORDER BY ts_rank_cd(fts_vector, to_tsquery('english', 'cloud & optimization')) DESC) AS fts_rank
    FROM public.search_documents
    WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
      AND locale_code = 'en-US'
      AND fts_vector @@ to_tsquery('english', 'cloud & optimization')
    LIMIT 100
),
vector_results AS (
    SELECT id, row_number() OVER (ORDER BY text_embedding <=> '[0.012, -0.45, 0.89, ...]'::vector) AS vector_rank
    FROM public.search_documents
    WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
      AND locale_code = 'en-US'
    ORDER BY text_embedding <=> '[0.012, -0.45, 0.89, ...]'::vector ASC
    LIMIT 100
)
SELECT 
    sd.id, 
    sd.title,
    COALESCE(1.0 / (60 + f.fts_rank), 0.0) + COALESCE(1.0 / (60 + v.vector_rank), 0.0) AS hybrid_score
FROM public.search_documents sd
LEFT JOIN fts_results f ON sd.id = f.id
LEFT JOIN vector_results v ON sd.id = v.id
WHERE f.id IS NOT NULL OR v.id IS NOT NULL
ORDER BY hybrid_score DESC
LIMIT 15;
```

---

## 7. SEARCH QUERY ENGINE

The search API endpoint processes requests, parsing search strings into structured query parameters:

```json
{
  "tenant_id": "7e2b4011-0000-4000-a000-000000000001",
  "query_string": "scalable database systems",
  "filters": {
    "locale": "en-US",
    "categories": ["Software Engineering"],
    "tags": ["SQL", "Postgres"],
    "publish_date_start": "2026-01-01T00:00:00Z"
  },
  "pagination": {
    "limit": 10,
    "offset": 0
  },
  "options": {
    "enable_hybrid": true,
    "search_mode": "strict_published"
  }
}
```

The search query parser is designed to handle query parameters efficiently, returning results within our 50ms latency SLA.

---

## 8. MULTI-FACETED AGGREGATION ENGINE

The aggregation engine generates dynamic facet counts, allowing users to filter search results by category, author, language, and tag:

```sql
-- Perform fast multi-faceted aggregations on JSONB tags array
SELECT 
    tag_element AS facet_value, 
    COUNT(*) AS match_count
FROM public.search_documents,
     jsonb_array_elements_text(COALESCE(metadata->'taxonomies'->'tags', '[]'::jsonb)) AS tag_element
WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
  AND locale_code = 'en-US'
  AND publish_status = 'Published'
GROUP BY tag_element
ORDER BY match_count DESC, tag_element ASC
LIMIT 10;
```

The system optimizes facets using the following guidelines:
*   **Dynamic Count Adjustments**: Counts adjust dynamically as users select filters, helping them drill down into results without hitting zero-match pages.
*   **Sub-Aggregation Trees**: Supports nested category structures (e.g., `Hardware` ──► `Servers` ──► `Racks`).
*   **Optimized Bucket Sorting**: Aggregations use fast b-tree indexes on date fields to optimize date bucket sorting.

---

## 9. RANKING AND BOOSTING ARCHITECTURE

To ensure high-quality search results, document relevance rankings combine text matching scores with custom, real-time metrics:

$$\text{Final Score} = (\text{Relevance Score} \times \alpha) + (\text{Popularity Score} \times \beta) + (\text{Freshness Score} \times \gamma) + \text{Manual Boosts}$$

### 9.1 Core Ranking Signals
*   **Text Matching Relevance (Relevance Score)**: The base text match score calculated by the database search engine.
*   **Document Freshness (Freshness Score)**: Adds a temporary score boost to newly published articles.
*   **Document Popularity (Popularity Score)**: Incorporates user interaction metrics (such as page views, clicks, and social shares) to boost popular content.
*   **Manual Boosts**: Allows administrators to boost specific articles or search terms manually (e.g., pinning promotional articles to the top of search results).

---

## 10. REAL-TIME AUTOCOMPLETE & TRENDING SUGGESTIONS

To guide users during search, the autocomplete service delivers quick suggestions with sub-10ms response times:

```
                            [AUTOCOMPLETE FEED]
  [User Type: "clou"] ──► [Query GIN Prefix Index] ──► [Fetch Popular Terms] ──► [Deliver Suggestions]
```

To optimize autocomplete performance, the system uses prefix matching:
*   **Prefix GIN Indexes**: Indexes word prefixes using `pg_trgm` to match terms as soon as users type the first three characters.
*   **Trending Search Aggregation**: Background workers aggregate historical search data to compile a list of trending terms, displaying popular suggestions to users when they focus the search bar.

---

## 11. ENTERPRISE ROLE-BASED ACCESS CONTROL (RBAC) & PERSONALIZATION

To prevent data leaks and maintain tenant isolation, the query engine applies security filters and personalization rules to search queries:

*   **Row-Level Security (RLS)**: Enforces strict isolation boundaries at the database layer, isolating documents based on verified tenant session context parameters.
*   **Visibility Filters**: Automatically filters out draft pages, embargoed articles, and archived content from public search results:
```sql
-- Secure access control query checking user role permissions
SELECT id, title
FROM public.search_documents
WHERE organization_id = '7e2b4011-0000-4000-a000-000000000001'
  AND (visibility = 'Public' OR allowed_roles && :user_roles_array);
```
*   **Personalization**: Boosts search results based on user preferences (such as previous page views or locale-specific content) without exposing cross-tenant data.

---

## 12. SEARCH INSIGHTS & ANALYTICS

To help administrators monitor search performance and optimize search terms, the engine logs search queries to the analytics tables:

```sql
-- DDL for Search Analytics Logs
CREATE TABLE audit.search_analytics_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    user_id UUID, -- Optional user identifier for personalized auditing
    query_text TEXT NOT NULL,
    locale_code VARCHAR(16) NOT NULL,
    results_returned_count INTEGER NOT NULL,
    execution_duration_ms INTEGER NOT NULL,
    clicked_document_id UUID, -- Logs click-through events
    abandoned_search BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_search_analytics_reporting ON audit.search_analytics_logs (organization_id, created_at);
```

Reporting modules use analytics logs to compile search insights, tracking:
*   **Top Queries**: The most frequent search terms.
*   **Zero-Result Searches**: Search terms that returned zero results, helping editors identify gaps in content.
*   **Click-Through Rate (CTR)**: The percentage of searches that resulted in document clicks.
*   **Search Latency**: Search response times, ensuring queries stay within the target 50ms SLA.

---

## 13. PERFORMANCE ENGINE AND REDIS CACHING

The search architecture is optimized to deliver low-latency responses under heavy concurrent query loads:

### 13.1 Multi-Tenant Sharding & Partitioning
Search tables are partitioned by organization ID, enabling fast query execution times even as document counts grow past 100M+ records.

### 13.2 Distributed Redis Cache Key Formatting
Frequently executed queries are cached in Redis to bypass database queries entirely:
```
Key Format: org:{organization_id}:search:hash({query_string}+filters+page)
Example: org:7e2b-4011:search:82ba34d19f01aefbc6
```
Updating or deleting a document invalidates associated search caches, ensuring query results remain accurate and up to date.

---

## 14. COMPLIANCE & SECURITY HARDENING

The search infrastructure is designed to meet strict enterprise compliance and security standards:

*   **Strict Multi-Tenant Isolation**: Row-Level Security policies are applied to all search tables, preventing cross-tenant data exposure.
*   **Audit Logging**: The system logs index rebuilds, document deletions, and configuration changes to audit tables (`audit.search_audit_logs`).
*   **GDPR Right-to-be-Forgotten Compliance**: Deleting a content item or user profile automatically triggers a cascading delete across search tables, permanently purging metadata and vector embeddings from storage.

---

## 15. SEARCH COMPATIBLE SYSTEM EVENTS

All document creations, index rebuilds, and query executions write transactional records to the outbox tables, allowing asynchronous worker queues to coordinate indexing tasks:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [Index State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 15.1 Search Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Index Created** | `search.index.created.v1`| `IndexingWorker`| Search Engine, CDN | `{ "document_id": "uuid", "doc_type": "content" }`|
| **Index Updated** | `search.index.updated.v1`| `IndexingWorker`| Search Engine, CDN | `{ "document_id": "uuid", "doc_type": "content" }`|
| **Index Deleted** | `search.index.deleted.v1`| `IndexingWorker`| Redis Cache, CDN | `{ "document_id": "uuid" }` |
| **Rebuild Started**| `search.rebuild.started.v1`| `IndexAdmin` | Admin Dashboard | `{ "trigger_user": "uuid", "scope": "full" }` |
| **Rebuild Complete**| `search.rebuild.completed.v1`| `IndexAdmin`| Admin Dashboard | `{ "duration_ms": 2489000, "docs_indexed": 120400 }`|
| **Query Executed** | `search.query.executed.v1` | `SearchService` | Analytics Engine | `{ "query": "string", "results_count": 48 }` |

### 15.2 Delivery & Idempotency Rules
*   **At-Least-Once Delivery**: Events are written to the outbox table (`audit.outbound_events`) within the same database transaction as the index update, preventing sync issues.
*   **Idempotency Keys**: Consumers track incoming events using composite hashes to prevent duplicate processing:
```
Idempotency Key: hash('search.index.updated' + document_id + version)
```

---

## 16. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **Index Accuracy** | Query a newly published article. | Document appears in search results within the 1000ms SLA. | Index Integration Tests |
| **Weighted Ranking**| Query for keyword matches across title and body content. | Documents matching keywords in the title rank higher than body matches. | Ranking Precision Audits |
| **Hybrid Search** | Combine keyword and semantic search queries. | RRF algorithms merge text and vector similarity scores accurately. | Hybrid Retrieval Tests |
| **Fuzzy Spelling** | Search for keywords containing typos or spelling errors. | Trigram similarity matching returns correct results. | Fuzzy Validation Suite |
| **Multi-Tenant Isolation**| Query search tables without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Tenant Leakage Audits |
| **Cascasding Deletes**| Delete a parent article and verify search index states. | Associated search indexes are automatically purged from the catalog. | Index Cleanup Tests |
| **Outbox Atomic Rollback**| Inject a failure during document indexing transactions. | Database rolls back the transaction, reverting both the index and outbox entry. | Atomic Transaction Tests |

---

## 17. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.

---

*Authorized by the JUANET Search Architecture Review Board & Technical Governance Council.*
