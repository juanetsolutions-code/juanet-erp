# JUANET Localization and Multi-Language Content Engine
## Phase 2.3.2G.3 — Localization Governance, Fallback Architecture, Translation Lifecycles, and Regional Publishing
**Document Version:** 1.0  
**Author:** Chief Localization Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and L10n Specification  

---

## 1. LOCALIZATION PHILOSOPHY

Within high-scale multi-tenant enterprise architectures, delivering content across international borders requires a localization engine that is decoupled from business logic and core content modeling. The **JUANET Localization & Multi-Language Content Engine Bounded Context** establishes **Localization as an independent, fully isolated domain**.

The system treats the original source content item as the master record (System of Record), while translations are independent localized variants linked to this master content entity. This architecture ensures the following key decoupling guidelines:

```
                            [JUANET LOCALIZATION ENGINE DECOUPLING]

        ┌─────────────────────────┐                     ┌─────────────────────────┐
        │  Content modeling core  │◄───────────────────►│    Media & DAM Core     │
        │ (Physical Schema SoR)   │                     │ (Centralized Asset ID)  │
        └───────────▲─────────────┘                     └───────────▲─────────────┘
                    │                                               │
                    │ (Master-Variant Linkage)                      │ (Shared / Localized Assets)
                    ▼                                               ▼
        ┌─────────────────────────────────────────────────────────────────────────┐
        │                 LOCALIZATION BOUNDED CONTEXT (L10n Engine)             │
        │                                                                         │
        │  ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────┐   │
        │  │     LOCALE GRAPHS    │  │     SYNCHRONIZER     │  │  L10N EVENTS │   │
        │  │  - Locale Fallbacks  │  │  - Field Diff Engine │  │  - Outbox Log│   │
        │  └──────────┬───────────┘  └──────────┬───────────┘  └──────┬───────┘   │
        └─────────────┼─────────────────────────┼─────────────────────┼───────────┘
                      ▼                         ▼                     ▼
        ┌─────────────────────────────────────────────────────────────────────────┐
        │                      DELIVERY, SEARCH & CACHING LAYERS                 │
        │         - Weighted Language FTS Indexes, Redis Locale Keys, Edge CDN    │
        └─────────────────────────────────────────────────────────────────────────┘
```

The system enforces the following core architectural rules:
*   **Single Source of Truth**: The master content item (`content_items`) represents the logical entity, managing global attributes, workflow states, and system identifiers. Localized details (titles, copy, custom fields) are stored in decoupled variant tables (`localized_content`).
*   **Translation Independence**: Each translation is a distinct physical record that undergoes its own review, approval, and publishing cycles without affecting other languages or the master copy.
*   **Zero Logic Duplication**: Field-level validation rules, routing configurations, and content types are defined once on the master schema and applied dynamically across all localized variants.
*   **Unlimited Scale & Regional Customization**: The system supports unlimited locales (language-country pairs, e.g., `en-US`, `en-GB`, `en-CA`) and enables regional overrides for localized pricing, marketing copy, and country-specific legal terms.
*   **RTL and Localized Navigation**: Fully supports Right-to-Left (RTL) script layouts (e.g., Arabic, Hebrew) via structural metadata flags and integrates localized sitemaps, SEO configurations, and navigation menus.

---

## 2. LOCALE ARCHITECTURE AND GRAPH STRUCTS

Locales are modeled as hierarchical trees rather than isolated lookup lines. This approach enables structured language fallback, regional overrides, and timezone-aware content delivery.

### 2.1 Logical Schema & Fallback Chain Representation
Fallback chains are represented as directed graphs where parent-child edges define fallback paths. For example, if a request seeks `fr-CA` but find no translation, the engine traverses the graph: `fr-CA` ──► `fr-FR` ──► `en-US` (Tenant Default).

```
                            [HIERARCHICAL FALLBACK GRAPH]
                                   ┌──────────────┐
                                   │    en-US     │ (Global System Default)
                                   └──────▲───────┘
                                          │
                        ┌─────────────────┴─────────────────┐
                        │                                   │
                 ┌──────┴───────┐                    ┌──────┴───────┐
                 │    en-GB     │                    │    fr-FR     │ (Base French)
                 └──────▲───────┘                    └──────▲───────┘
                        │                                   │
                 ┌──────┴───────┐                    ┌──────┴───────┐
                 │    en-AU     │                    │    fr-CA     │ (Canadian French)
                 └──────────────┘                    └──────────────┘
```

The database configures the fallback relationships using explicit foreign key mappings and ordinal hierarchy rankings:

```sql
-- DDL for Locale Registries
CREATE TABLE public.languages (
    locale_code VARCHAR(16) PRIMARY KEY, -- ISO 639-1 + ISO 3166-1 Alpha-2 (e.g., 'en-US', 'fr-CA')
    language_name VARCHAR(64) NOT NULL,
    native_name VARCHAR(64) NOT NULL,
    is_rtl BOOLEAN NOT NULL DEFAULT FALSE,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    pluralization_rule_code VARCHAR(32) NOT NULL DEFAULT 'standard', -- Key mapping for plural parsing
    date_format VARCHAR(32) NOT NULL DEFAULT 'YYYY-MM-DD',
    currency_code CHAR(3) NOT NULL DEFAULT 'USD',
    timezone_default VARCHAR(64) NOT NULL DEFAULT 'UTC',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Fallback Graph Schema
CREATE TABLE public.locale_fallback_paths (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    fallback_locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    evaluation_order INTEGER NOT NULL, -- Evaluation priority rank (e.g. 1st fallback, 2nd fallback)
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_locale_fallback_order UNIQUE (organization_id, locale_code, evaluation_order),
    CONSTRAINT chk_locale_fallback_self CHECK (locale_code <> fallback_locale_code)
);
CREATE INDEX idx_locale_fallback_lookup ON public.locale_fallback_paths (organization_id, locale_code, evaluation_order);
```

---

## 3. TRANSLATION LIFECYCLE STATE MACHINE (FSM)

Each localized translation behaves as an independent state machine linked to a master content item.

```
                              [TRANSLATION LIFECYCLE FSM]

              ┌──────────────────► [ 1. Draft ]
              │                           │
              │                           ▼
              │                [ 2. Awaiting_Translation ]
              │                           │
              │             ┌─────────────┴─────────────┐
              │             ▼ (Automatic)               ▼ (Manual)
              │       [ 3. Machine_Translated ]   [ 4. Human_Review ]
              │             │                           │
              │             └─────────────┬─────────────┘
              │                           ▼
              │                    [ 5. Legal_Review ]
              │                           │
              │                           ▼
              │                    [ 6. SEO_Review ]
              │                           │
              │                           ▼
              │                    [ 7. Approved ] ◄────────┐
              │                           │                 │
              │                           ▼                 │
              └─────────────────── [ 8. Published ] ────────┘ (Unpublish)
                                          │
                                          ▼
                                   [ 9. Outdated ] (Master Content Changed)
                                          │
                                          ▼
                                    [ 10. Archived ]
```

### 3.1 State Specifications and Mutation Invariants

*   **1. Draft**
    *   *Purpose*: The initial sandbox for creating localized copy.
    *   *Entry Criteria*: Creator adds a new translation branch or imports a draft payload.
    *   *Exit Criteria*: Translation fields are populated and submitted for review.
    *   *Allowed Transitions*: `Awaiting_Translation`, `Deleted`.
*   **2. Awaiting_Translation**
    *   *Purpose*: Queues translation tasks for background processors or translators.
    *   *Entry Criteria*: System validation checks confirm the translation is ready for processing.
    *   *Exit Criteria*: Worker assigns a translator or dispatches the payload to an AI translation queue.
    *   *Allowed Transitions*: `Machine_Translated`, `Human_Review`, `Draft`.
*   **3. Machine_Translated**
    *   *Purpose*: Intermediate state for AI-generated drafts before human validation.
    *   *Entry Criteria*: AI translator completes the translation payload and updates the asset.
    *   *Exit Criteria*: Editor accepts the machine draft and moves it to the review queue.
    *   *Allowed Transitions*: `Human_Review`, `Draft`.
*   **4. Human_Review**
    *   *Purpose*: Quality assurance review conducted by a native speaking linguist.
    *   *Entry Criteria*: Translator completes and submits the translation copy.
    *   *Exit Criteria*: Reviewer verifies and signs off on the translations.
    *   *Allowed Transitions*: `Legal_Review`, `SEO_Review`, `Draft` (Rejected).
*   **5. Legal_Review**
    *   *Purpose*: Verification check to ensure country-specific compliance and disclosures are met.
    *   *Entry Criteria*: Required flag triggers legal check for sensitive content types (e.g., terms, financial sheets).
    *   *Exit Criteria*: Legal officer approves and signs off on the copy.
    *   *Allowed Transitions*: `SEO_Review`, `Draft` (Rejected).
*   **6. SEO_Review**
    *   *Purpose*: Meta title, description, and localized slug path optimization check.
    *   *Entry Criteria*: Reviewers complete previous quality validation checkpoints.
    *   *Exit Criteria*: SEO engineer signs off on optimized slugs and tags.
    *   *Allowed Transitions*: `Approved`, `Draft`.
*   **7. Approved**
    *   *Purpose*: Signed-off translation, ready to be scheduled or published immediately.
    *   *Entry Criteria*: SEO and legal reviews are complete.
    *   *Exit Criteria*: User publishes the translation, or scheduled release job triggers.
    *   *Allowed Transitions*: `Published`, `Scheduled`, `Draft`.
*   **8. Published**
    *   *Purpose*: Active localized variant served via delivery APIs and cached on CDN edge servers.
    *   *Entry Criteria*: Scheduled job triggers or editor publishes immediately.
    *   *Exit Criteria*: Translation outdated event is received, or editor unpublishes.
    *   *Allowed Transitions*: `Outdated` (Master changed), `Draft` (Unpublished), `Archived`.
*   **9. Outdated**
    *   *Purpose*: Flagged state indicating the master record has changed, requiring translation synchronization.
    *   *Entry Criteria*: System triggers update when master content is updated.
    *   *Exit Criteria*: Sync process reconciles field modifications.
    *   *Allowed Transitions*: `Awaiting_Translation`, `Draft`.
*   **10. Archived**
    *   *Purpose*: Read-only state for retired localized content.
    *   *Entry Criteria*: Deletion queue processes old releases.
    *   *Exit Criteria*: Administrator restores the archived asset.
    *   *Allowed Transitions*: `Draft`, `Deleted`.

---

## 4. TRANSLATION SYNCHRONIZATION ENGINE

When master source content changes (e.g., a pricing update or a revised product feature description), associated translations can quickly fall out of sync. The translation synchronization engine manages this process, tracking delta modifications at the field level:

```
                         TRANSLATION SYNCHRONIZATION LOOP
  [Master Content Changed] ──► [Calculate Field Diff] ──► [Flag Outdated Translations]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Auto-Sync Fields)                                                              ▼ (Manual Overrides)
  [Copy Shared Properties] ──► [Auto-Approve Sync]                       [Translator Review Queue]
```

### 4.1 Master Change Detection Trigger
When an author updates master content, a database trigger compares change states, flags associated translations as `Outdated`, and writes a task entry to the synchronization outbox queue:

```sql
CREATE OR REPLACE FUNCTION public.fn_cms_detect_master_content_mutation()
RETURNS TRIGGER AS $$
BEGIN
    -- Only trigger validation checks if core field payloads are modified
    IF (NEW.version <> OLD.version) THEN
        -- Flag localized variants as Outdated, requiring sync reviews
        UPDATE public.localized_content
        SET is_translation_approved = FALSE,
            updated_at = CURRENT_TIMESTAMP
        WHERE content_item_id = NEW.id
          AND is_translation_approved = TRUE;
          
        -- Log outbox synchronization entry
        INSERT INTO audit.outbound_events (id, organization_id, event_name, payload)
        VALUES (
            uuid_generate_v7(),
            NEW.organization_id,
            'translation.outdated',
            jsonb_build_object('content_item_id', NEW.id, 'master_version', NEW.version)
        );
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_cms_master_mutation_sync
    AFTER UPDATE ON public.content_items
    FOR EACH ROW
    EXECUTE FUNCTION public.fn_cms_detect_master_content_mutation();
```

### 4.2 Synchronization Strategies
*   **Field-Level Drift Detection**: Compares differences across JSON payloads, identifying which localized values match older version structures.
*   **Automated Property Copying**: Automatically synchronizes fields marked as global (such as numbers, product IDs, or non-localized image paths) across all translation branches without requiring manual reviews.
*   **Delta Tracking**: Stores change tracking history, allowing reviewers to pinpoint precisely which sentences or blocks changed in the master file.

---

## 5. TRANSLATION MEMORY (TM) SCHEMA ENGINE

To reduce localization costs and ensure consistent brand terminology across countries, the system registers and reuse approved translations using an append-only Translation Memory (TM) segment database:

```
                           [TRANSLATION MEMORY LOOKUP]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                            New Segment Text                            │
  │   - Text: "Click here to upgrade your active enterprise account"       │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Queries Translation Memory)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼ (Exact Match Found)         ▼ (Fuzzy Match Found > 85%)   ▼ (No Match Found)
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│Auto-Inject Segment│        │Suggest Match     │         │Send Segment to   │
│- Confidence: 100%│         │- Confidence: 89% │         │Translation Queue │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The database structures support translation segments, fuzzy matching lookup arrays, and terminology dictionaries:

```sql
-- Core Translation Memory Segment Table
CREATE TABLE public.tm_segments (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    source_locale VARCHAR(16) NOT NULL,
    target_locale VARCHAR(16) NOT NULL,
    source_text_hash CHAR(64) NOT NULL, -- SHA-256 signature for exact-match lookups
    source_text TEXT NOT NULL,
    target_text TEXT NOT NULL,
    use_count INTEGER NOT NULL DEFAULT 1,
    last_used_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by UUID,
    CONSTRAINT uq_tm_segments_hash UNIQUE (organization_id, source_locale, target_locale, source_text_hash)
);
CREATE INDEX idx_tm_segments_lookup ON public.tm_segments (organization_id, source_locale, target_locale, source_text_hash);

-- Brand Terminology Dictionary
CREATE TABLE public.brand_glossary (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    term_key VARCHAR(128) NOT NULL, -- Case-insensitive term lookups
    source_locale VARCHAR(16) NOT NULL,
    term_text TEXT NOT NULL,
    is_translatable BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_brand_glossary_key UNIQUE (organization_id, term_key, source_locale)
);

-- Glossary Translations Table
CREATE TABLE public.brand_glossary_translations (
    id UUID PRIMARY KEY,
    glossary_id UUID NOT NULL REFERENCES public.brand_glossary(id) ON DELETE CASCADE,
    target_locale VARCHAR(16) NOT NULL,
    translated_text TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_brand_glossary_translation UNIQUE (glossary_id, target_locale)
);
```

---

## 6. AI TRANSLATION WORKFLOW INTEGRATION (ADVISORY)

*This section provides advisory-only AI workflow integration guidelines. Human validation remains mandatory for publishing.*

To speed up localization pipelines, authors can submit drafts to AI translators. The AI translation system operates according to the following design rules:
*   **Mandatory Human Validation**: AI-translated content is written to the database with state flags set to `Machine_Translated`. It is never published directly to live sites without human editor review and sign-off.
*   **Terminology Injection**: AI pipelines include associated brand terminology lists (`brand_glossary`) in translation requests to ensure key terms are translated consistently.
*   **System Prompt Isolation**: AI translation engines utilize structured templates to prevent prompt injection and keep translation contexts bounded:
```
Translate the following raw JSON structure from {source_language} to {target_language}.
Do not modify JSON keys, system identifiers, HTML layout structures, or placeholders.
Strictly adhere to the following terminology glossary definitions: {glossary_rules}
```
*   **Translation Auditing**: All machine translations log the engine name, model version, execution latency, and token consumption to audit tables (`public.ai_interaction_logs`).

---

## 7. LOCALIZED URL AND SLUG MANAGEMENT

Managing routing structures across countries is critical to prevent routing collisions and maintain search engine visibility. The localization engine handles URLs according to the following design rules:

```
                            [LOCALIZED URL TREE]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Parent Article Page                          │
  │   - system_path: "/company/careers"                                    │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Localized Routing Resolution)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼ (locale_code: en-US)        ▼ (locale_code: es-ES)        ▼ (locale_code: ar-SA)
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│/careers          │         │/es/empleo        │         │/ar/وظائف         │
│- Canonical Link  │         │- Canonical Link  │         │- Canonical Link  │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The localized URL engine features:
*   **Slug Normalization**: Strips accent marks, normalizes characters, replaces spaces with hyphens, and converts slug values to lowercase, preventing broken path lookups.
*   **Strict Path Uniqueness**: Enforces slug path uniqueness per organization and locale using database indexes, preventing routing collisions.
*   **Canonical Redirect Chains**: Modifying a localized slug automatically generates a permanent 301 redirect rule, preserving page authority and preventing broken links.

---

## 8. SEO LOCALIZATION AND HREFLANG GENERATION

The sitemap and routing engine automatically injects `hreflang` tags across page variations, indexing localized pages for search engines:

### 8.1 hreflang Tag Format
For every localized page variation, the system generates hreflang headers to mapping languages to regions:
```html
<link rel="alternate" hreflang="en-us" href="https://juanet.platform/marketing/features" />
<link rel="alternate" hreflang="en-gb" href="https://juanet.platform/uk/marketing/features" />
<link rel="alternate" hreflang="fr-fr" href="https://juanet.platform/fr/marketing/features" />
<link rel="alternate" hreflang="x-default" href="https://juanet.platform/marketing/features" />
```

### 8.2 Database Metadata Extraction Schema
SEO meta tags and crawl configurations are managed within dedicated localization tables:
```sql
-- SEO Meta Tag Localization Schema
CREATE TABLE public.seo_localization (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    localized_page_id UUID NOT NULL REFERENCES public.localized_pages(id) ON DELETE CASCADE,
    meta_title VARCHAR(256) NOT NULL,
    meta_description VARCHAR(512) NOT NULL,
    meta_keywords VARCHAR(256)[],
    og_title VARCHAR(256),
    og_description VARCHAR(512),
    canonical_override_url VARCHAR(1024),
    robots_instructions VARCHAR(128) DEFAULT 'index, follow',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_seo_localization_page UNIQUE (organization_id, localized_page_id)
);
```

---

## 9. REGIONAL PUBLISHING AND EMBARGO SYSTEM

To comply with local marketing rules and launch campaigns across timezones, the regional publishing engine coordinates deployments across target environments:

```
                            [REGIONAL CAMPAIGN RELEASE]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                            Localized Campaign                          │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Validates Launch Conditions)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│Publish en-GB     │         │Scheduled en-US   │         │Blacklisted en-AU │
│- Instant release │         │- Target Timezone │         │- Local regulatory│
│- Active now      │         │  release job     │         │  restrictions    │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

The regional publishing engine includes:
*   **Timezone-Aware Scheduled Releases**: Scheduled releases launch in each country according to the local timezone, ensuring synchronized campaigns.
*   **Compliance Embargo System**: Blocks publishing tasks and places assets on hold if they fail local regulatory checks or compliance audits.
*   **Country Blacklist Mapping**: Prevents content items from deploying in specific regions to comply with local laws and marketing restrictions.

---

## 10. SEARCH INFRASTRUCTURE LOCALE DICTIONARIES

To deliver accurate search results across languages, the search engine configures native PostgreSQL language dictionaries, filtering stop words, indexing synonyms, and ranking search terms:

```sql
-- FTS Query using Localized Stop Words and Dictionary Tuning
SELECT 
    id, 
    title, 
    ts_rank(
        setweight(to_tsvector('french', COALESCE(title, '')), 'A') ||
        setweight(to_tsvector('french', COALESCE(rich_body, '')), 'B'), 
        to_tsquery('french', 'solutions & entreprises')
    ) AS relevance_score
FROM public.localized_content
WHERE locale_code = 'fr-FR'
  AND (setweight(to_tsvector('french', COALESCE(title, '')), 'A') ||
       setweight(to_tsvector('french', COALESCE(rich_body, '')), 'B')) @@ to_tsquery('french', 'solutions & entreprises')
ORDER BY relevance_score DESC;
```

Search optimization highlights:
*   **Language-Specific GIN Indexing**: Creates distinct search indexes optimized for each language to ensure accurate keyword match scoring.
*   **Synonyms Support**: Maps matching terms (such as 'bill' and 'invoice' in English) to return consistent results.
*   **Accent Folding (Unaccent)**: Normalizes accents and special characters during query parsing, matching terms regardless of input accents.

---

## 11. PERFORMANCE & CACHING ARCHITECTURE

To deliver localized assets with sub-15ms latency, the localization engine utilizes structured database indexing and distributed Redis caching:

### 11.1 Composite Localization Indexes
Index structures are prefixed with the tenant and language fields to optimize localized page queries:
```sql
CREATE INDEX idx_localized_content_perf ON public.localized_content (organization_id, locale_code) 
WHERE deleted_at IS NULL AND is_translation_approved = TRUE;
```

### 11.2 Locale-Based Caching Keys
Redis and CDN caches use specific country prefix keys to prevent locale caching overlap:
```
Key Format: org:{organization_id}:locale:{locale_code}:path:{slug_path}
Example: org:7e2b-4011:locale:fr-CA:path:services-entreprise
```
Updating an article invalidates only its specific locale cache key, keeping other active translation caches warm.

---

## 12. LOCALIZATION SYSTEM EVENT CONTRACTS

All translation updates, language additions, and publishing tasks write transactional outbox records (`audit.outbound_events`), enabling decoupled services to process changes asynchronously:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [L10n State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 12.1 System Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Translation Created**| `translation.created.v1`| `L10nService` | Translation Memory | `{ "content_id": "uuid", "locale": "fr-FR" }` |
| **Translation Updated**| `translation.updated.v1`| `L10nService` | AI Translation Queue | `{ "content_id": "uuid", "locale": "es-ES" }` |
| **Translation Approved**| `translation.approved.v1`| `WorkflowEngine` | Release Scheduler | `{ "content_id": "uuid", "locale": "ar-SA" }` |
| **Translation Published**| `translation.published.v1`| `PublishEngine` | CDN Edge, Search, CRM | `{ "content_id": "uuid", "locale": "en-GB" }` |
| **Translation Outdated**| `translation.outdated.v1`| `SyncEngine` | Notification Service | `{ "content_id": "uuid", "source": "en-US" }` |
| **Translation Deleted**| `translation.deleted.v1`| `L10nService` | CDN Cache Origin | `{ "content_id": "uuid", "locale": "fr-CA" }` |
| **Locale Registered**  | `locale.created.v1`     | `TenantConfig` | Routing Engine | `{ "org_id": "uuid", "new_locale": "pt-BR" }` |

### 12.2 Delivery & Idempotency Rules
*   **At-Least-Once Delivery**: Events are written to the outbox table (`audit.outbound_events`) within the same database transaction as the translation update, preventing sync issues.
*   **Idempotency Keys**: Consumers track incoming events using unique composite hashes to prevent duplicate processing:
```
Idempotency Key: hash('translation.published' + content_item_id + locale_code + version)
```

---

## 13. SECURITY, RBAC & ROLE ISOLATION

To comply with enterprise auditing guidelines and protect localization data, the system restricts access using role-based controls and row-level security:

### 13.1 Multi-Tenant Row-Level Security (RLS)
RLS policies are enabled on all localization schemas, isolating directories and translations based on verified tenant session context values:
```sql
ALTER TABLE public.localized_content ENABLE ROW LEVEL SECURITY;

CREATE POLICY l10n_content_tenant_isolation ON public.localized_content
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 13.2 Localization Roles (RBAC)
*   **Translator**: Can edit draft translations and write translation memory entries, but cannot approve releases.
*   **Reviewer**: Can edit and review translations, moving items to the legal or SEO review queue.
*   **Legal Officer**: Approves compliance and country-specific disclosures, ensuring legal guidelines are met.
*   **SEO Editor**: Manages slug paths, canonical configurations, and localized sitemap settings.
*   **L10n Publisher**: Approves and deploys translations to target staging or production environments.

### 13.3 Data Privacy & GDPR Auditing
Personal information is stripped from translation memory segments during ingestion. System audit tables record all editor actions, translation updates, and approval changes with complete user context.

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **FSM Transition Paths**| Simulate transition sequences across states. | Validates translation steps, blocking unauthorized transition paths. | L10n FSM Test Suite |
| **Fallback Processing** | Request page translation with missing locales. | Traverses fallback paths, serving base locale translation values without errors. | Fallback Routing Tests |
| **Drift Change Flags**  | Update master content details and verify translation state. | Master trigger fires, marking translations as Outdated. | Change Synchronization Tests|
| **Terminology Glossary** | Run AI translation tasks with active glossary key rules. | AI translator uses defined glossary terms in translated output. | AI Translation Quality Tests|
| **Multi-Tenant Isolation**| Query localization tables without setting tenant context parameters. | RLS policies block the query, preventing tenant data exposure. | Tenant Leakage Audits |
| **URL Route Collisions**| Attempt to register duplicate localized slugs. | Unique index constraints block the transaction, returning error logs. | Slug Pathing Audits |
| **Outbox Atomic Rollback**| Inject a failure during translation updates. | Database rolls back the transaction, reverting both the update and outbox entry. | Atomic Transaction Tests |

---

## 15. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines the physical schemas, sequential UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs the core content lifecycle state machine, content types, and publishing queue workers.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Coordinates S3-compatible object storage pointers, asset transformations, and media usage tracking.

*Authorized by the JUANET Localization Architecture Board & Global Security Council.*
