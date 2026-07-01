# JUANET Content Delivery & Headless API Architecture
## Phase 2.3.2G.5 — Headless Content Delivery, REST & GraphQL Engines, Edge Caching, CDN Invalidation, and API Gateway Performance
**Document Version:** 1.0  
**Author:** Chief Headless Architect, Principal API Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Delivery Specification  

---

## 1. CONTENT DELIVERY ARCHITECTURE

In a high-scale global enterprise SaaS platform, exposing core database transaction tables directly to client-facing web applications, mobile apps, or third-party integrations presents major security and performance risks. The **JUANET Content Delivery & Headless API Bounded Context** enforces a strict **Command Query Responsibility Segregation (CQRS)** architectural boundary. 

The Content Delivery layer operates purely as a read-only distribution model. It is completely decoupled from core transactional CMS tables (`public.content_items`, `public.localized_content`), querying instead optimized, index-backed replica nodes and edge caches. The delivery pipeline consumes transactional state events to keep edge servers updated, maintaining a stateless, horizontally scalable, and cache-first delivery architecture.

```
                         [JUANET EDGE DELIVERY PIPELINE]

   ┌────────────────────────────────────────────────────────────────────────┐
   │                        CMS TRANSACTIONAL WORKSPACE                     │
   │   - Normalized database tables: content_items, localized_content       │
   │   - Triggers publishing outbox records in: audit.outbound_events        │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (Async outbox events: content.published)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                       DELIVERY SYNC & WARMING ENGINE                   │
   │   - Processes outbox entries, denormalizing delivery structures        │
   │   - Pre-warms edge and regional caches asynchronously                 │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (Upserts & Cache Invalidation)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                          READ-ONLY REPLICA POOL                        │
   │   - PostgreSQL 16 Read Nodes: public.search_documents, routing_paths   │
   │   - Enforces Tenant Isolation via Row-Level Security (RLS) policies    │
   └───────────────────────────────────┬────────────────────────────────────┘
                                       │
                                       │ (REST/GraphQL Edge API Proxies)
                                       ▼
   ┌────────────────────────────────────────────────────────────────────────┐
   │                     DISTRIBUTED REDIS & EDGE CDN                       │
   │   - High-performance, low-latency edge servers (sub-15ms responses)    │
   │   - Cryptographic signatures and signed CDN URL asset delivery         │
   └────────────────────────────────────────────────────────────────────────┘
```

The system enforces the following core architectural rules:
*   **Read-Only Boundary**: The headless delivery APIs are strictly read-only. Under no circumstances can client delivery requests modify database tables, update schemas, or write records (except for localized, asynchronous usage telemetry).
*   **Complete Statelessness**: API gateway pods maintain zero in-memory session states. Every incoming request must contain complete credentials (JWT or cryptographic API key) and be fully self-contained.
*   **CDN-First Strategy**: All public read paths must be structured to maximize CDN cache hit ratios. Direct database hits are avoided by routing requests through regional Redis nodes and edge CDN caches.
*   **Decoupled Sync Workers**: Transaction updates trigger asynchronous workers, keeping transactional nodes free from asset-heavy delivery tasks.
*   **Strict Multi-Tenant Isolation**: Row-Level Security (RLS) is applied to all read tables, preventing cross-tenant data leaks at the database layer.

---

## 2. REST API ARCHITECTURE

The headless REST API engine is designed to deliver fast, highly structured, and flexible content payloads using standard JSON APIs:

### 2.1 Endpoint Resource Schemes
All headless delivery routes are prefixed with the target tenant identifier and API version:
*   `GET /api/v1/delivery/org_{organization_id}/content` — Lists localized published content documents.
*   `GET /api/v1/delivery/org_{organization_id}/content/{id_or_slug}` — Fetches a single published content document by ID or localized path slug.
*   `GET /api/v1/delivery/org_{organization_id}/navigation/{menu_code}` — Resolves localized hierarchical menus.

### 2.2 Advanced Query Parameters
To minimize network payload sizes and prevent over-fetching, the REST engine supports sparse fieldsets, object expansion, sorting, and pagination:

```
GET /api/v1/delivery/org_7e2b-4011/content
  ?locale=fr-CA
  &fields=title,slug,metadata.taxonomies.tags
  &expand=author_reference,associated_media
  &sort=-freshness_date,popularity_score
  &limit=25
  &offset=0
```

### 2.3 Normalized Error Model
To simplify error handling across integrations, API endpoints return standardized, strongly-typed JSON error payloads compliant with RFC 7807:

```json
{
  "type": "https://api.juanet.platform/errors/rate-limit-exceeded",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "IP address has exceeded the hourly rate limit allocation of 10,000 requests. Please backoff until reset.",
  "instance": "/api/v1/delivery/org_7e2b-4011/content/product-hero",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "meta": {
    "limit": 10000,
    "remaining": 0,
    "reset_at": "2026-06-30T06:55:00Z"
  }
}
```

---

## 3. GRAPHQL FEDERATION ARCHITECTURE

For complex, deeply nested, or dynamic frontend application layouts (such as bento grids, composite landing pages, or multi-module dashboards), the platform provides a production-grade GraphQL engine.

### 3.1 GraphQL Schema Design (Query Domain Only)
The GraphQL schema is restricted purely to query operations. Mutations are strictly administrative and managed via decoupled back-office microservices:

```graphql
# Headless Content Delivery Schema Definitions
type ContentItem {
  id: ID!
  locale: String!
  slug: String!
  title: String!
  description: String
  bodyContent: String
  metadata: JSON!
  popularity: Float!
  freshnessDate: DateTime!
  author: Author
  assets: [MediaAsset!]!
}

type MediaAsset {
  id: ID!
  filename: String!
  mimeType: String!
  fileSize: Int!
  cdnUrl: String!
  altText: String
}

type Author {
  id: ID!
  name: String!
  avatarUrl: String
}

type ContentConnection {
  edges: [ContentEdge!]!
  pageInfo: PageInfo!
}

type ContentEdge {
  node: ContentItem!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  endCursor: String
}

type Query {
  contentItem(id: ID, slug: String, locale: String!): ContentItem
  contentItems(
    locale: String!
    category: String
    first: Int
    after: String
  ): ContentConnection!
}
```

### 3.2 Query Complexity & Depth Limits
To prevent Denial-of-Service (DoS) attacks from malicious or poorly constructed recursive queries (e.g., fetching content referencing an author, referencing other articles, referencing the author again), the API gateway implements strict complexity calculators:
*   **Depth Limit**: Queries are restricted to a maximum depth of 5 nested object levels.
*   **Complexity Score**: Every field is assigned a base complexity point. If the calculated complexity of a query exceeds a threshold of 250 points, the gateway rejects the query immediately with a 400 Bad Request error.
*   **Persisted Queries**: High-traffic production clients use persisted queries, registering query hashes on the gateway. The gateway executes approved hashes directly, saving bandwidth and blocking unapproved queries.

---

## 4. REAL-TIME DELIVERY PIPELINE & WORKFLOW ENGINE

The delivery pipeline handles content publishing events, converting transactional updates into live, cached endpoints:

```
                         REAL-TIME DELIVERY PIPELINE
  [Publish Event Fired] ──► [Denormalize Delivery Cache] ──► [Update Redis Replica Paths]
                                                                      │
         ┌────────────────────────────────────────────────────────────┴────────────────────────┐
         ▼ (CDN Edge Cache)                                                                    ▼ (Client Delivery)
  [Dispatch CDN Purge Request] ──► [Update Global hreflang maps]                       [Serve Warm Request]
```

*   **Atomic Event Processing**: When an editor publishes an article, the CMS write-path commits the state change and writes a `content.published.v1` event to the outbox database table inside a single, unified database transaction.
*   **Asynchronous Denormalization**: A background worker consumes the event, denormalizes the content fields into a fast read format, and updates regional Redis replica databases.
*   **CDN Cache Invalidation**: The worker calculates the associated URL paths and sends precise purge requests to global CDN edge servers (e.g., purging `/fr/solutions-enterprise` and sitemaps), keeping page loads fast and consistent without hitting backend databases.

---

## 5. CONTENT RESOLUTION ENGINE

When an incoming request hits the delivery API, the Content Resolution Engine processes the parameters, resolving localized slugs and locale fallbacks to find the correct translation page variation:

```sql
-- DDL for Content Routing Mapping Indexes
CREATE TABLE public.delivery_routing_paths (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    slug_path VARCHAR(1024) NOT NULL, -- Normalized, localized path slug (e.g., 'fr/solutions-entreprise')
    locale_code VARCHAR(16) NOT NULL,
    content_item_id UUID NOT NULL, -- Target parent content ID
    localized_content_id UUID NOT NULL, -- Target translation variant
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_delivery_route UNIQUE (organization_id, slug_path)
);

-- Optimize routes for sub-millisecond prefix matching
CREATE INDEX idx_delivery_route_lookup ON public.delivery_routing_paths (organization_id, slug_path) WHERE is_active = TRUE;
```

### 5.1 Route and Language Resolution Logic
1.  **Extract Routing Path**: Parses the incoming URL path and extracts the target locale code and normalized slug.
2.  **Lookup Slug Route**: Queries the `public.delivery_routing_paths` index. If a direct match is found, the engine fetches the associated translation page variant.
3.  **Evaluate Fallback Paths**: If no direct translation page is found, the engine queries the locale fallback graph to find the nearest fallback language (e.g., falling back from `fr-CA` to `fr-FR` to `en-US`).
4.  **Confirm Launch Windows**: Checks embargo dates and publishing flags, blocking page access if the article is not yet live.
5.  **Serve Payload**: Returns the localized content payload along with its sitemap and SEO sitemap headers, caching the resolved route for future requests.

---

## 6. API GATEWAY SECURITY & AUTHORIZATION

The API gateway secures content delivery endpoints, protecting tenant data while keeping query latency under our 15ms SLA:

### 6.1 Unified Access Schemes
*   **Standard JSON Web Tokens (JWT)**: Authenticates client users, verifying their roles and group memberships before granting access.
*   **Cryptographic API Keys**: Authenticates headless applications or third-party integrations, mapping incoming keys to authorized tenants, permission scopes, and rate limits.
```
Header Format: Authorization: Bearer jnt_key_a82bc194dfa1...
```

### 6.2 Gateway Enforcement Policies
*   **Tenant Separation via Row-Level Security (RLS)**: Enforces strict tenant boundaries at the database layer, isolating delivery queries using PostgreSQL RLS policies:
```sql
ALTER TABLE public.delivery_routing_paths ENABLE ROW LEVEL SECURITY;

CREATE POLICY delivery_route_tenant_isolation ON public.delivery_routing_paths
    FOR SELECT TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```
*   **Rate Limiting Profiles**: Limits incoming API traffic based on the client's token level, protecting database clusters from unexpected traffic spikes:
    *   *Free Sandbox Key*: Maximum 60 requests/minute.
    *   *Enterprise Live Key*: Maximum 10,000 requests/minute.
*   **IP Whitelisting Restrictions**: Restricts sensitive enterprise routes (such as internal employee wikis or pre-release portals) to verified corporate IP ranges.

---

## 7. PERFORMANCE ENGINE & CDN EDGES

To deliver content with low latency across global regions, the architecture utilizes edge servers and optimized connection pipelines:

```
                            [EDGE DELIVERY SPEED]
  [Global Clients] ──► [Edge CDN Caches (sub-15ms)] ──► [Regional Redis Replicas]
```

*   **Global Edge Caching**: Caches static API JSON structures on global CDN edge servers, serving content from locations near the end user.
*   **Optimized Connection Pipelines**: Uses HTTP/3 multiplexing and connection pooling to reduce network handshake times.
*   **Automatic GZIP / Brotli Compression**: Compresses all JSON payloads on edge servers, minimizing response sizes and network transfer times.
*   **Conditional GET Requests**: Uses ETag hashes to serve conditional requests, allowing clients to reuse cached payloads if content hasn't changed.

---

## 8. MULTI-LEVEL CACHING ARCHITECTURE

To scale content delivery to over 1B+ annual requests without impacting database resources, the platform utilizes a multi-level caching hierarchy:

| Caching Layer | Target System Platform | Storage & Software | Average Response SLA | Cache Eviction Strategy |
| :--- | :--- | :--- | :--- | :--- |
| **Edge Cache** | Global Edge CDN | Cloudflare CDN | **< 15ms** | Event-driven instant path purging |
| **Regional Cache**| Regional Compute Nodes| Redis Replica Cluster | **< 20ms** | LRU eviction or explicit model invalidate |
| **In-Memory Cache**| Node.js API Gateways | Local In-Memory Store | **< 2ms** | Sliding TTL window (max 120s) |
| **Database Cache**| PostgreSQL Replica Nodes| Shared Buffers | **< 50ms** | LRU eviction of indexed table blocks |

### 8.1 Eviction and Cache Invalidation Rules
To prevent users from seeing outdated content, caches are invalidated using event-driven purges rather than relying purely on time-to-live (TTL) expiration windows:
```
Key Invalidation Format: org:{organization_id}:content:{content_item_id}
```
When an article is updated, a synchronization worker purges its specific cache key from Redis and CDN caches, keeping the rest of the edge caches warm.

---

## 9. INTEGRATED DAM & ASSET DELIVERY

Headless delivery payloads reference media assets by unique identifier, routing delivery requests through the digital asset manager (DAM) to ensure optimized asset serving:

*   **WebP/AVIF Format Optimization**: The edge delivery pipeline parses user-agent headers, automatically serving optimized AVIF or WebP versions based on browser compatibility.
*   **Time-Limited Signed URLs**: Protects private or sensitive documents (such as invoices or internal contracts) by delivering assets via cryptographically signed CDN URLs, preventing unauthorized downloads.
*   **On-Demand Transform Parameters**: Supports dynamic, on-demand image transformations (such as resizing, cropping, or watermarking) on CDN edge servers, caching the resulting variations for future requests:
```
https://cdn.juanet.platform/assets/7e2b-4011/photo.jpg?width=800&crop=16:9&quality=80
```

---

## 10. CACHE-EFFICIENT PERSONALIZATION

Exposing personalized content (such as localized marketing promotions or customer portal configurations) often conflicts with edge caching strategies. To handle personalization efficiently, the platform utilizes edge side includes (ESI) and localized client variations:

```
                          [PERSONALIZATION ENGINE]
  [Edge CDN Cache (Warm Static HTML)] ──► [Edge Worker parses Client Context JWT]
                                                  │
                                                  ▼
                        [Inject Personalized ESI Components (Fast APIs)]
```

Personalization strategies include:
*   **Edge Side Includes (ESI)**: Splits pages into static layouts and dynamic widgets, caching static sections on the edge while fetching dynamic personalized widgets from fast, local APIs.
*   **Context-Based JWT Personalization**: Edge workers parse client cookies and JWT payloads, dynamically swapping localized components or styling parameters at the edge without querying backend databases.
*   **Dynamic Feature Flags**: Keeps core page content cached, using lightweight client-side feature flags to show or hide targeted promotional content or localized banners.

---

## 11. DELIVERY ANALYTICS & AUDIT LOGS

To help administrators monitor system performance and track resource usage, the API gateway records delivery logs asynchronously:

```sql
-- DDL for Headless API Delivery Metrics
CREATE TABLE audit.delivery_analytics_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    request_path VARCHAR(1024) NOT NULL,
    api_key_id UUID, -- Logs the authenticated key or application
    locale_code VARCHAR(16) NOT NULL,
    cache_status VARCHAR(32) NOT NULL, -- 'HIT_CDN', 'HIT_REDIS', 'MISS_DB'
    response_time_ms INTEGER NOT NULL,
    response_size_bytes INTEGER NOT NULL,
    client_country CHAR(2) NOT NULL, -- ISO country code resolved from request IP
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_delivery_analytics_rollup ON audit.delivery_analytics_logs (organization_id, created_at);
```

Analytics dashboards aggregate delivery log data to track system metrics in real time:
*   **Cache Hit Ratio (CHR)**: The percentage of requests served by edge or Redis caches. Target SLA: > 95% cache hits.
*   **CDN Response Latency**: Monitors API performance to ensure page responses remain within our target 15ms SLA.
*   **API Usage rollups**: Tracks client API consumption to monitor usage quotas and rate limits.

---

## 12. DELIVERY CORE EVENT CONTRACTS

All delivery cache updates, CDN purges, and API rate limiting incidents write transactional logs to the outbox database table (`audit.outbound_events`), enabling background workers to coordinate caching tasks asynchronously:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [Cache State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 12.1 Delivery Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Delivery Requested** | `content.delivery.requested.v1`| `APIGateway` | Analytics Engine | `{ "content_id": "uuid", "locale": "fr-CA" }` |
| **Cache Invalidated**  | `content.cache.invalidated.v1` | `SyncEngine` | Redis Cluster, CDN | `{ "content_id": "uuid", "cache_key": "string" }`|
| **Cache Warmed**       | `content.cache.warmed.v1`      | `WarmingService`| APIGateway | `{ "content_id": "uuid", "elapsed_ms": 142 }` |
| **CDN Purged**         | `content.cdn.purged.v1`        | `CDNGateway` | Admin Dashboard | `{ "purge_paths": ["string"], "success": true }` |
| **API Rate Limited**   | `content.api.rate_limited.v1`  | `APIGateway` | Alerting System | `{ "org_id": "uuid", "api_key_id": "uuid" }` |

### 12.2 Delivery & Idempotency Rules
*   **At-Least-Once Delivery**: Events are written to the outbox table (`audit.outbound_events`) within the same database transaction as cache updates, preventing sync issues.
*   **Idempotency Keys**: Consumers track incoming events using unique composite hashes to prevent duplicate processing:
```
Idempotency Key: hash('content.cache.invalidated' + content_item_id + cache_key + version)
```

---

## 13. PERFORMANCE SCALING TO 1B+ ANNUAL REQUESTS

To scale content delivery endpoints to support over 1B+ annual requests, the system is designed to handle high transaction volumes efficiently:

*   **Multi-Region Replica Deployments**: Synchronizes delivery routing tables across read-only replica nodes located in different global regions, reducing response times for international users.
*   **Database Connection Pooling**: Uses database connection pools (such as PgBouncer) on read replica clusters, reducing database connection overhead during traffic spikes.
*   **Non-Blocking API Gateways**: Builds API gateways using fast, non-blocking frameworks (such as Node.js or Go), maximizing request throughput.
*   **Pre-Warmed Cache Queues**: Automatically pre-warms cache endpoints on database replication nodes when content updates are committed, ensuring high cache hit ratios even after major content deployments.

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **Only Published Served**| Request articles in Draft or Archived states. | Gateway rejects request, returning 404 page not found errors. | Access Control Audits |
| **Fallback Processing** | Request page translation with missing locales. | Resolution engine traverses graph, serving fallback language page variations. | L10n Fallback Tests |
| **Cache Invalidation** | Update an article and query the edge cache. | Verifies the CDN edge cache is cleared within our 1000ms SLA. | CDN Eviction Tests |
| **Complexity Safeguards**| Submit a recursive, complex GraphQL query. | Gateway rejects query, returning clear complexity limits. | GraphQL Hardening Suite |
| **Multi-Tenant Isolation**| Query routing tables without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Multi-Tenant Leakage Audits |
| **Rate Limiter Limits**| Send high-volume requests using a sandbox API key. | Gateway rate limits requests, returning 429 too many requests errors. | Rate Limiting Audits |
| **Outbox Atomic Rollback**| Inject a failure during content save transactions. | Database rolls back the transaction, reverting both the update and outbox entry. | Atomic Transaction Tests |

---

## 15. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.

---

*Authorized by the JUANET Headless Delivery Board & Global Infrastructure Council.*
