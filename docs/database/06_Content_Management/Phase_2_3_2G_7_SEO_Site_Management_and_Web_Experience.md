# JUANET SEO, Site Management & Web Experience Engine Implementation Manual
## Phase 2.3.2G.7 — Multi-Site Inheritance, Custom Domain Verification, Localized URL Normalization, and Automated SEO Schema Engines
**Document Version:** 1.0  
**Author:** Chief Web Experience Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Web Experience Specification  

---

## 1. SITE ARCHITECTURE & BRAND TOPOLOGY

In a modern enterprise SaaS platform, managing multiple websites, global brands, regional landing pages, and micro-campaign spaces within a single multi-tenant database requires a highly flexible site topology. The **JUANET SEO, Site Management & Web Experience Engine Bounded Context** decouples logical content items from physical web layouts. 

The Site Management engine does not act as the System of Record (SoR) for content payloads. Content remains owned and managed by the core CMS (`public.content_items`). Instead, the site management engine maps those items to physical domains, custom route trees, and localized navigational elements.

```
                            [JUANET MULTI-SITE TOPOLOGY]

                             ┌────────────────────────┐
                             │    Tenant Portfolio    │ (Organization SoR)
                             └───────────┬────────────┘
                                         │
                   ┌─────────────────────┴─────────────────────┐
                   ▼                                           ▼
       ┌───────────────────────┐                   ┌───────────────────────┐
       │   Global Brand A      │                   │    Global Brand B     │
       └───────────┬───────────┘                   └───────────┬───────────┘
                   │                                           │
         ┌─────────┴─────────┐                       ┌─────────┴─────────┐
         ▼                   ▼                       ▼                   ▼
  ┌─────────────┐     ┌─────────────┐         ┌─────────────┐     ┌─────────────┐
  │  US Site    │     │  EU Site    │         │ APAC Site   │     │ Microsite X │
  │ (en-US Dom) │     │ (fr/de Dom) │         │ (ja-JP Dom) │     │ (Promo Dom) │
  └─────────────┘     └─────────────┘         └─────────────┘     └─────────────┘
```

The system enforces the following core architectural boundaries:
*   **Decoupled Experience Layer**: Site definitions represent logical containers for domains, custom styling tokens, sitemap rules, and navigation trees. Content is assigned to sites dynamically using reference relationships.
*   **Stateless Host Routing**: The delivery engine routes requests based on host headers (e.g., `brand-a.co.uk` vs. `brand-a.com`), resolving sites, languages, and settings without maintaining session states.
*   **Multi-Brand Partitioning**: Organizations can manage multiple brands, websites, and custom domains within a single workspace, sharing assets and taxonomies while maintaining independent SEO controls.
*   **Headless-Compatible Delivery**: Web experiences can be rendered dynamically via headless delivery channels (REST, GraphQL, Next.js, Nuxt) or served directly through our optimized edge proxy engine.
*   **Strict Row-Level Security (RLS)**: Because site directories and domains are co-located within shared multi-tenant database tables, RLS isolates sites dynamically based on verified tenant session context parameters.

---

## 2. DOMAIN MANAGEMENT & CUSTOM SSL LIFECYCLE

The domain management engine registers, verifies, and routes requests across primary domains, secondary domains, and vanity campaign routes:

```sql
-- DDL for Site Profiles
CREATE TABLE public.web_sites (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    brand_code VARCHAR(64) NOT NULL, -- Logical brand identifier (e.g., 'juanet_enterprise')
    site_code VARCHAR(64) NOT NULL, -- Logical site identifier (e.g., 'us_corporate')
    display_name VARCHAR(128) NOT NULL,
    default_locale VARCHAR(16) NOT NULL DEFAULT 'en-US',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_site_code_per_org UNIQUE (organization_id, site_code)
);

-- DDL for Custom Domains and SSL Lifecycle
CREATE TABLE public.site_domains (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    domain_name VARCHAR(256) NOT NULL, -- e.g., 'www.juanet-solutions.com'
    is_primary BOOLEAN NOT NULL DEFAULT FALSE, -- Only one primary domain per site
    is_https_enforced BOOLEAN NOT NULL DEFAULT TRUE,
    verification_token CHAR(64) NOT NULL, -- TXT verification token
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMPTZ,
    ssl_status VARCHAR(32) NOT NULL DEFAULT 'Pending', -- 'Pending', 'Issued', 'Expired', 'Revoked'
    ssl_expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_domain_name UNIQUE (domain_name)
);
CREATE INDEX idx_site_domains_lookup ON public.site_domains (organization_id, domain_name) WHERE is_verified = TRUE;
```

### 2.1 Domain Verification Protocol
To verify custom domains, tenants must add a TXT record to their DNS settings matching their unique `verification_token` (e.g., `_juanet-challenge.domain.com TXT jnt_val_749e81b...`). An asynchronous background worker queries the DNS record to verify ownership, updates the `is_verified` flag, and triggers Let's Encrypt SSL certificate provisioning automatically.

---

## 3. URL ROUTING & REDIRECT HISTORY ENGINE

The URL routing engine maps incoming requests to content assets. To preserve SEO rankings, modifying localized path slugs automatically records redirect paths to prevent broken links:

```sql
-- DDL for Live URL Route Mappings
CREATE TABLE public.site_routes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    slug_path VARCHAR(1024) NOT NULL, -- Normalized path slug (e.g., 'solutions/enterprise-database')
    locale_code VARCHAR(16) NOT NULL,
    content_item_id UUID NOT NULL, -- References the target parent content record
    canonical_override VARCHAR(1024), -- Custom canonical tag override
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_site_route UNIQUE (organization_id, site_id, slug_path)
);
CREATE INDEX idx_site_routes_lookup ON public.site_routes (organization_id, site_id, slug_path) WHERE is_active = TRUE;

-- DDL for Permanent Redirect History (SEO Preservation)
CREATE TABLE public.site_redirects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    source_path VARCHAR(1024) NOT NULL, -- Old URL path
    target_path VARCHAR(1024) NOT NULL, -- New URL path
    redirect_type INTEGER NOT NULL DEFAULT 301, -- 301 (Permanent) or 302 (Temporary)
    hits_count INTEGER NOT NULL DEFAULT 0,
    last_hit_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_site_redirect UNIQUE (organization_id, site_id, source_path)
);
CREATE INDEX idx_site_redirects_lookup ON public.site_redirects (organization_id, site_id, source_path);
```

### 3.1 URL Normalization Rules
To maximize edge cache hit ratios and prevent duplicate content indexing, all slug paths undergo a strict normalization routine:
*   **Lowercase conversion**: Normalizes all characters to lowercase.
*   **Trailing slash removal**: Strips trailing slashes from the end of paths.
*   **Accent folding**: Normalizes accents and special characters (e.g., converting `é` to `e`).
*   **Hyphen substitution**: Replaces spaces and non-alphanumeric characters with hyphens, preventing URL encoding issues.

---

## 4. NAVIGATION TREE ENGINE

The Navigation Engine builds hierarchical menus, mega-menus, and footers, and resolves navigation trees across languages and devices:

```sql
-- DDL for Navigation Trees
CREATE TABLE public.site_navigations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    menu_code VARCHAR(64) NOT NULL, -- e.g., 'header_main', 'footer_legal'
    display_name VARCHAR(128) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_navigation_menu UNIQUE (organization_id, site_id, menu_code)
);

-- DDL for Navigation Items (Self-Referencing Adjacency List)
CREATE TABLE public.site_navigation_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    navigation_id UUID NOT NULL REFERENCES public.site_navigations(id) ON DELETE CASCADE,
    parent_item_id UUID REFERENCES public.site_navigation_items(id) ON DELETE CASCADE,
    display_order INTEGER NOT NULL,
    label_text VARCHAR(128) NOT NULL, -- Display label
    target_url VARCHAR(1024), -- External URL path or localized slug mapping
    target_window VARCHAR(16) NOT NULL DEFAULT '_self', -- '_self' or '_blank'
    icon_code VARCHAR(64), -- Lucide-react icon identifier mapping
    is_highlighted BOOLEAN NOT NULL DEFAULT FALSE,
    required_roles VARCHAR(64)[] DEFAULT NULL, -- Audience-specific routing permissions
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_navigation_item_order UNIQUE (navigation_id, parent_item_id, display_order)
);
CREATE INDEX idx_navigation_items_render ON public.site_navigation_items (navigation_id, parent_item_id, display_order);
```

---

## 5. AUTOMATED SEO SCHEMA ENGINE

To optimize search engine visibility, the SEO Engine automatically generates metadata, meta tags, and structured JSON-LD schemas:

```sql
-- DDL for Localized SEO Metadata Assets
CREATE TABLE public.site_seo_metadata (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    route_id UUID NOT NULL UNIQUE REFERENCES public.site_routes(id) ON DELETE CASCADE,
    meta_title VARCHAR(256) NOT NULL,
    meta_description VARCHAR(512) NOT NULL,
    meta_keywords VARCHAR(256)[],
    
    -- Open Graph Metadata (Facebook/LinkedIn Optimization)
    og_title VARCHAR(256),
    og_description VARCHAR(512),
    og_image_id UUID, -- References central DAM media asset ID
    
    -- Twitter Card Metadata
    twitter_card_type VARCHAR(32) DEFAULT 'summary_large_image',
    twitter_title VARCHAR(256),
    twitter_description VARCHAR(512),
    
    -- Automated JSON-LD Structured Schema (Schema.org compliant)
    structured_schema JSONB NOT NULL DEFAULT '{}'::jsonb, -- e.g., Article, Organization, Product schemas
    robots_directive VARCHAR(128) NOT NULL DEFAULT 'index, follow',
    
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### 5.1 Automated Structured Data Generation
When an article is published, the metadata parser extracts properties (such as title, author, date, and thumbnail) and generates a structured Schema.org JSON-LD tag automatically:

```json
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Enterprise Multi-Site Architecture on PostgreSQL 16",
  "datePublished": "2026-06-30T06:00:00Z",
  "image": "https://cdn.juanet.platform/assets/7e2b-4011/banner.webp",
  "author": {
    "@type": "Person",
    "name": "David Miller"
  },
  "publisher": {
    "@type": "Organization",
    "name": "JUANET Solutions",
    "logo": "https://cdn.juanet.platform/assets/logo.png"
  }
}
```

---

## 6. SITEMAP CONFIGURATION & ENGINE

The Sitemap Engine automatically generates sitemap indices for search engines, updating paths incrementally as content changes:

```sql
-- DDL for Registered Sitemap Index Logs
CREATE TABLE public.site_sitemaps (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    locale_code VARCHAR(16) NOT NULL,
    sitemap_xml TEXT NOT NULL, -- Compressed XML payload
    urls_count INTEGER NOT NULL DEFAULT 0,
    generated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_sitemap_locale UNIQUE (site_id, locale_code)
);
CREATE INDEX idx_site_sitemaps_lookup ON public.site_sitemaps (organization_id, site_id, locale_code);
```

### 6.1 Incremental XML Generation
To optimize sitemap generation on high-scale sites (100,000+ pages), the system uses incremental updates rather than generating sitemaps from scratch on every change. Adding, updating, or deleting a route triggers a sitemap update task, compiling sitemaps in the background and keeping edge caches warm.

---

## 7. ROBOTS DIRECTIVES & CRAWLER MANAGEMENT

The Robots Engine configures search crawler access permissions, applying environment-specific rules to protect staging and dev environments:

```sql
-- DDL for robots.txt configurations
CREATE TABLE public.site_robots (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL UNIQUE REFERENCES public.web_sites(id) ON DELETE CASCADE,
    user_agent VARCHAR(128) NOT NULL DEFAULT '*', -- Targeted crawler agent
    allow_rules VARCHAR(256)[] DEFAULT ARRAY['/']::VARCHAR[],
    disallow_rules VARCHAR(256)[] DEFAULT ARRAY['/api/', '/admin/']::VARCHAR[],
    crawl_delay INTEGER, -- Target crawl-delay limit
    custom_directives TEXT, -- Custom rules string
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_site_robots_lookup ON public.site_robots (organization_id, site_id);
```

---

## 8. MULTI-SITE INHERITANCE & OVERRIDE PROTOCOLS

To streamline content management across international markets, the multi-site engine supports template, asset, and sitemap inheritance across sites:

```
                         [INHERITANCE PIPELINE DIRECTION]
  [Global Site Template (Root)] ──► [Regional Brand Site] ──► [Local Marketplace Site]
                                            │
                                            ▼
                             [Apply Local Content Overrides]
```

Inheritance rules:
*   **Template Inheritance**: Child sites inherit layouts, structures, and schemas defined on the parent site, ensuring brand consistency.
*   **Local Content Overrides**: Local editors can override inherited metadata, page tags, and descriptions to optimize for their local target market.
*   **Taxonomy Inheritance**: Global product taxonomy lists (categories, tags) are shared across child sites while allowing local market variations.

---

## 9. EXPERIENCE PERSONALIZATION & A/B TESTING HOOKS

*The Site Management engine provides personalization integration hooks without owning personalization or tracking logic.*

To support personalized content experiences, routing payloads include target audience parameters:
*   **Audience Segmentation Flags**: Pages can configure target user segments (e.g., `Enterprise_Customer`, `Developer_Sandbox`), showing customized hero images or navigation options.
*   **A/B Testing Variants**: Pages can register alternate design layout versions, distributing traffic across variants to track page performance:
```json
{
  "ab_test": {
    "experiment_id": "exp_q2_redesign",
    "variants": [
      { "id": "var_control", "weight": 0.50 },
      { "id": "var_redesign_b", "weight": 0.50 }
    ]
  }
}
```
*   **Context-Based JWT Swapping**: Edge workers evaluate feature flags and audience cookies, swapping localized banners or pricing cards at the edge to keep page responses fast.

---

## 10. WEB EXPERIENCE INSIGHTS & CORE WEB VITALS

To help teams monitor page performance and search rankings, the analytics engine records page view metrics and Core Web Vitals:

```sql
-- DDL for Page Experience Metrics
CREATE TABLE audit.site_experience_metrics (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    site_id UUID NOT NULL REFERENCES public.web_sites(id) ON DELETE CASCADE,
    route_id UUID REFERENCES public.site_routes(id) ON DELETE SET NULL,
    user_agent VARCHAR(512) NOT NULL,
    is_mobile BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Core Web Vitals Metrics (Sourced from Real User Monitoring)
    largest_contentful_paint_ms INTEGER, -- LCP (Target: < 2500ms)
    first_input_delay_ms INTEGER, -- FID (Target: < 100ms)
    cumulative_layout_shift NUMERIC(4,3), -- CLS (Target: < 0.1)
    
    -- Network performance metrics
    time_to_first_byte_ms INTEGER, -- TTFB
    dns_resolution_time_ms INTEGER,
    
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_experience_metrics_reporting ON audit.site_experience_metrics (organization_id, site_id, created_at);
```

---

## 11. PERFORMANCE OPTIMIZATION & REDIS EDGE LAYOUTS

To serve web experiences with low latency, page routing paths, navigation menus, and sitemaps are cached in Redis:

### 11.1 Cache Key Design
*   **Navigation Cache Key**:
```
Key: org:{organization_id}:site:{site_id}:nav:{menu_code}:locale:{locale_code}
```
*   **Route Mapping Cache Key**:
```
Key: org:{organization_id}:site:{site_id}:route:hash({slug_path})
```

### 11.2 Edge CDN Cache Warming
Publishing updates triggers an asynchronous worker to pre-warm the page's route mapping cache, ensuring fast response times for international users.

---

## 12. SECURITY, DOMAIN VALIDATION & AUDITING

To prevent domain hijack attacks and ensure secure access, the site management engine applies role-based access controls and row-level security:

*   **Multi-Tenant Isolation**: Row-Level Security policies are applied to all site tables, preventing cross-tenant data exposure:
```sql
ALTER TABLE public.web_sites ENABLE ROW LEVEL SECURITY;

CREATE POLICY site_tenant_isolation ON public.web_sites
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```
*   **Domain Ownership Validation**: Users cannot route requests to a custom domain until the domain TXT verification check succeeds, protecting brands from domain hijack attempts.
*   **Audit Logging**: The system logs all domain creations, DNS modifications, and routing changes with complete user context.

---

## 13. WEB EXPERIENCE SYSTEM EVENTS

All site creations, domain verifications, and route modifications write transactional records to the outbox table (`audit.outbound_events`), enabling background workers to coordinate caching and routing tasks asynchronously:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [Web State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 13.1 Web Experience Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Site Created** | `site.created.v1` | `SiteService` | Domain Provisioner | `{ "site_id": "uuid", "default_locale": "en-US" }` |
| **Site Updated** | `site.updated.v1` | `SiteService` | Routing Engine, CDN | `{ "site_id": "uuid", "brand_code": "string" }` |
| **Domain Verified** | `domain.verified.v1` | `DNSWorker` | SSL Provisioner | `{ "domain_id": "uuid", "ssl_renew": true }` |
| **Sitemap Generated**| `sitemap.generated.v1`| `SitemapEngine`| Search Console Ping | `{ "site_id": "uuid", "urls_count": 4890 }` |
| **Robots Updated** | `robots.updated.v1` | `RobotsService`| CDN Cache Edge | `{ "site_id": "uuid", "has_noindex": false }` |
| **SEO Metadata Mod** | `seo.metadata.updated.v1`| `SEOService` | Search Indexer, CDN | `{ "route_id": "uuid", "meta_title": "string" }` |
| **Navigation Mod** | `navigation.updated.v1`| `NavService` | Redis Invalidation | `{ "menu_code": "string", "site_id": "uuid" }` |
| **Redirect Created** | `redirect.created.v1` | `RoutingEngine`| CDN Edge, Proxy | `{ "source": "string", "target": "string" }` |

### 13.2 Delivery & Idempotency Rules
*   **At-Least-Once Delivery**: Events are written to the outbox table (`audit.outbound_events`) within the same database transaction as the site update, preventing sync issues.
*   **Idempotency Keys**: Consumers track incoming events using unique composite hashes to prevent duplicate processing:
```
Idempotency Key: hash('redirect.created' + site_id + source_path + target_path)
```

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **Verifications** | Run DNS TXT validation check on unverified domains. | Verification worker processes TXT record, updating states automatically. | Domain Verification Tests|
| **Slug Uniqueness** | Attempt to register duplicate routing paths. | Database index constraints block the transaction, logging an error. | Route Collision Audits |
| **Redirect Integrity**| Modify an active path slug and request old URL. | System generates redirect record and routes request with 301 headers. | Redirect Precision Tests |
| **Sitemap Processing**| Register route modifications and check sitemaps. | Background worker compiles sitemap xml updates incrementally. | Sitemap Generator Tests |
| **hreflang Injections**| Fetch localized SEO metadata and check headers. | Gateway includes matching hreflang and alternate tags automatically. | SEO Integration Audits |
| **Multi-Tenant Isolation**| Query site tables without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Tenant Leakage Audits |
| **Outbox Atomic Rollback**| Inject a failure during site save transactions. | Database rolls back the transaction, reverting both the update and outbox entry. | Atomic Transaction Tests |

---

## 15. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.
*   **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**: Manages CDN delivery networks, edge caches, and headless GraphQL query interfaces.
*   **Workflow & Collaboration (`Phase_2_3_2G_6_Workflow_Editorial_Collaboration_and_Content_Governance.md`)**: Coordinates collaborative pipelines, role assignments, parallel approvals, and compliance logs.

---

*Authorized by the JUANET Web Experience Board & Technical Infrastructure Council.*
