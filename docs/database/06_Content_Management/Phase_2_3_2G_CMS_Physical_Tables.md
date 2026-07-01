# JUANET Content Management System Physical Tables Specification
## Phase 2.3.2G — Canonical Physical Database Schemas, Multi-Tenant Domain Structures, and Referential Integrity Constraints
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, Lead Database Administrator, and Technical Governance Council  
**Classification:** Public / Enterprise Architectural Standard, Domain Integration Map, and Database Design Spec  

---

## 1. CMS ARCHITECTURAL PHILOSOPHY

The **Content Management System (CMS) Bounded Context** is the authoritative **System of Record (SoR)** for all managed digital content, marketing assets, layout definitions, and digital publishing footprints within the JUANET Enterprise SaaS Platform.

To prevent structural coupling, avoid performance degradation on core transactional systems, and support omni-channel headless delivery, the CMS is built on a fundamental architectural separation of concerns:

```
                                [JUANET CMS ARCHITECTURAL DECOUPLING]

            ┌────────────────────────────────────────────────────────────────────────┐
            │                             PRESENTATION LAYER                         │
            │          - Headless Delivery Engines (REST / GraphQL APIs)             │
            │          - Static Site Generators, SPA/PWA Apps, Mobile Clients        │
            └───────────────────────────────────▲────────────────────────────────────┘
                                                │ (API / Event Consumed Only)
            ┌───────────────────────────────────┴────────────────────────────────────┐
            │                             CMS BOUNDED CONTEXT                        │
            │                                                                        │
            │  ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────┐  │
            │  │     CONTENT CORE     │  │      MEDIA CORE      │  │ TAXONOMY/SEO │  │
            │  │  - Content Items     │  │  - Central Assets    │  │  - Categories│  │
            │  │  - Templates/Types   │  │  - Folders/Versions  │  │  - Tags/Urls │  │
            │  └──────────┬───────────┘  └──────────┬───────────┘  └──────┬───────┘  │
            │             │                         │                     │          │
            │             └─────────────────────────┼─────────────────────┘          │
            │                                       ▼                                │
            │                            [POSTGRESQL 16 STORAGE ENGINE]              │
            │                            - Multi-Tenant Isolation (RLS)              │
            │                            - GIN & pgvector Vector Searches            │
            └────────────────────────────────────────────────────────────────────────┘
```

The design enforces the following architectural principles:
*   **Decoupled Content & Presentation**: Content represents raw structured data (such as JSON, text, attributes) with absolute decoupling from presentation components. Layout templates reference semantic content blocks rather than directly hardcoding text.
*   **Immutability After Publication**: Once a content version is approved and published, it is immutable. Any modification or translation creates a new record or a draft version, ensuring accurate historical auditing and point-in-time recovery.
*   **Media Centralization**: All media items are cataloged in a unified database registry with strong hash verification, CDN references, and virus scan statuses, preventing redundant uploads and guaranteeing security.
*   **Single Tenant Ownership**: Every content object, asset, layout, and language variant belongs to exactly one enterprise tenant (`organization_id`), enforced strictly at the database engine level via Row-Level Security (RLS).
*   **API/Event Consumed Operational Domains**: Operational modules (such as CRM, Projects, or Finance) never query or modify CMS tables directly. All interactions occur via dedicated service APIs or asynchronous system event subscriptions.

---

## 2. GLOBAL DATABASE STANDARDS

All physical tables within the CMS bounded context adhere strictly to the JUANET global database guidelines, ensuring standard metadata audit capabilities and database-level security policies.

### 2.1 Standard Column Definitions
All tables include the following columns as a baseline, except where explicitly noted:
*   `id` `UUID`: Primary Key. Sequentially ordered UUIDv7 identifiers are used to maintain indexing structure and prevent page splits.
*   `organization_id` `UUID`: Tenancy identifier, mapping to the tenant org. Enforced via Row-Level Security (RLS) policies.
*   `created_at` `TIMESTAMPTZ`: Auto-populated on row insertion (`DEFAULT CURRENT_TIMESTAMP`).
*   `updated_at` `TIMESTAMPTZ`: Auto-populated and updated via standard update triggers.
*   `deleted_at` `TIMESTAMPTZ`: Nullable. Indicates soft-deletion state.
*   `version` `INTEGER`: Version marker used for optimistic locking verification (`DEFAULT 1`).
*   `created_by` `UUID`: Identifier mapping to the actor who created the record.
*   `updated_by` `UUID`: Identifier mapping to the actor who last updated the record.

### 2.2 Schema Conventions & Constraints
*   **Naming Conventions**: Lowercase, plural snake_case for tables (e.g., `content_items`), snake_case for columns (e.g., `is_published`).
*   **Index Naming**: Prefix `idx_` followed by table name and targeted column names (e.g., `idx_content_items_org_status`).
*   **Foreign Key Naming**: Prefix `fk_` followed by target table and source table names (e.g., `fk_content_items_organization_id`).
*   **CHECK Constraints**: Explicitly used to validate state flags, enum boundaries, and numeric metrics directly within the database.
*   **Audit Triggers**: Each table binds to the audit logger trigger to automatically track operational modifications.

---

## 3. CMS SUBDOMAINS

The database schema is organized into 10 cohesive, modular subdomains:

*   **Subdomain A: Content Core**: Houses the core structural assets, custom field schemas, draft states, templates, and revision tracking tables.
*   **Subdomain B: Pages & Layouts**: Defines page hierarchies, reusable sections, layouts, and component configurations for decoupled web rendering.
*   **Subdomain C: Media Assets**: Manages S3-compatible cloud storage pointers, directory mapping structures, asset transformations, and usage tracking logs.
*   **Subdomain D: Navigation**: Controls localized main menus, sidebar structures, footer lists, and navigation links.
*   **Subdomain E: Taxonomy**: Governs category paths, global tags, and custom taxonomy mappings.
*   **Subdomain F: Localization**: Provides physical schemas to handle unlimited languages, translations, fallback hierarchies, and RTL flags.
*   **Subdomain G: SEO & Routing**: Manages custom slug registries, 301/302 URL redirects, canonical path definitions, and sitemap settings.
*   **Subdomain H: Content Relationships**: Maps direct references, content links, parent-child structures, and cross-references.
*   **Subdomain I: Publishing & Deployments**: Records publishing queues, deployment target settings, and active webhook dispatch registries.
*   **Subdomain J: Analytics Support**: Tracks raw content interaction views, search terms, sitemap clicks, and statistics.

---

## 4. PHYSICAL TABLES

---

### 4.1 Subdomain A: Content Core

#### 1. `public.content_statuses`
*   **Purpose**: Immutable lookup table for content states (e.g., `Draft`, `In_Review`, `Approved`, `Published`, `Archived`).
*   **Physical DDL**:
```sql
CREATE TABLE public.content_statuses (
    code VARCHAR(32) PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```
*   **Metadata**:
    *   *RLS*: Global read, write restricted to SuperAdmin.
    *   *Retention*: Infinite.
    *   *GDPR*: No PII present.

#### 2. `public.content_types`
*   **Purpose**: Defines content field schemas and validation rules (e.g., `Blog_Post`, `Product_Feature`, `Legal_Disclaimer`).
*   **Physical DDL**:
```sql
CREATE TABLE public.content_types (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    system_name VARCHAR(128) NOT NULL,
    display_name VARCHAR(128) NOT NULL,
    description TEXT,
    schema_definition JSONB NOT NULL, -- Defines custom field names, types, constraints
    is_reusable BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID,
    CONSTRAINT uq_content_types_org_name UNIQUE (organization_id, system_name)
);
CREATE INDEX idx_content_types_org ON public.content_types (organization_id) WHERE deleted_at IS NULL;
```
*   **Metadata**:
    *   *RLS*: Enforced via `organization_id`.
    *   *Retention*: Keep infinite, soft delete supported.
    *   *GDPR*: No PII.

#### 3. `public.content_templates`
*   **Purpose**: Maps design structure and variable slots to content types for headless page rendering.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_templates (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    content_type_id UUID NOT NULL REFERENCES public.content_types(id),
    name VARCHAR(128) NOT NULL,
    markup_definition TEXT NOT NULL, -- Decoupled representation layout layout rules
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID,
    CONSTRAINT fk_content_templates_content_type FOREIGN KEY (content_type_id) REFERENCES public.content_types(id)
);
CREATE INDEX idx_content_templates_type ON public.content_templates(content_type_id);
```
*   **Metadata**:
    *   *RLS*: Enforced on `organization_id`.
    *   *Retention*: Infinite.

#### 4. `public.content_items`
*   **Purpose**: The central physical container representing an instance of content, regardless of state or localized version.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_items (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    content_type_id UUID NOT NULL REFERENCES public.content_types(id),
    current_status_code VARCHAR(32) NOT NULL REFERENCES public.content_statuses(code),
    system_identifier VARCHAR(256) NOT NULL, -- Non-local slug reference used in code
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID,
    CONSTRAINT uq_content_items_system_id UNIQUE (organization_id, system_identifier)
);
CREATE INDEX idx_content_items_org_type ON public.content_items (organization_id, content_type_id);
CREATE INDEX idx_content_items_status ON public.content_items (current_status_code);
```
*   **Metadata**:
    *   *RLS*: Enforced on `organization_id`.
    *   *Retention*: 7 Years active database, soft delete supported.
    *   *GDPR*: No explicit PII; fields within localized extensions may contain PII.

#### 5. `public.content_versions`
*   **Purpose**: Append-only log tracking content modifications, revisions, and historical iterations.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_versions (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    content_item_id UUID NOT NULL REFERENCES public.content_items(id),
    revision_number INTEGER NOT NULL,
    status_code VARCHAR(32) NOT NULL REFERENCES public.content_statuses(code),
    structured_payload JSONB NOT NULL, -- Complete field data map defined by type schema
    change_comment VARCHAR(512),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by UUID NOT NULL,
    CONSTRAINT uq_content_versions_revision UNIQUE (organization_id, content_item_id, revision_number)
);
CREATE INDEX idx_content_versions_item ON public.content_versions (content_item_id);
```
*   **Metadata**:
    *   *RLS*: Enforced on `organization_id`.
    *   *Retention*: 10 Years, archive after 5.
    *   *GDPR*: High-risk fields are analyzed and processed during erasure workflows.

---

### 4.2 Subdomain B: Pages & Layouts

#### 1. `public.page_layouts`
*   **Purpose**: Defines top-level column structure, grid layout formats, and layout sections for pages.
*   **Physical DDL**:
```sql
CREATE TABLE public.page_layouts (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    name VARCHAR(128) NOT NULL,
    layout_definition JSONB NOT NULL, -- Grids, column rules, metadata configurations
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID
);
CREATE INDEX idx_page_layouts_org ON public.page_layouts (organization_id) WHERE deleted_at IS NULL;
```

#### 2. `public.pages`
*   **Purpose**: Represents active renderable pages mapped to specific paths.
*   **Physical DDL**:
```sql
CREATE TABLE public.pages (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    layout_id UUID NOT NULL REFERENCES public.page_layouts(id),
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    system_route VARCHAR(512) NOT NULL, -- Internal route lookup pattern
    publish_at TIMESTAMPTZ,
    expire_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID,
    CONSTRAINT uq_pages_route UNIQUE (organization_id, system_route)
);
CREATE INDEX idx_pages_org_route ON public.pages (organization_id, system_route) WHERE deleted_at IS NULL;
```

#### 3. `public.page_sections`
*   **Purpose**: Repetitive vertical zones within a page (e.g., Header, Hero, Body Section, Footer).
*   **Physical DDL**:
```sql
CREATE TABLE public.page_sections (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    page_id UUID NOT NULL REFERENCES public.pages(id),
    section_key VARCHAR(128) NOT NULL, -- Grid container identifier
    sort_order INTEGER NOT NULL,
    styles_definition JSONB, -- Tailwinds classes, background parameters, alignments
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_page_sections_order UNIQUE (organization_id, page_id, section_key, sort_order)
);
CREATE INDEX idx_page_sections_page ON public.page_sections (page_id);
```

#### 4. `public.page_components`
*   **Purpose**: Nested interactive blocks inside sections that bind content items to design components.
*   **Physical DDL**:
```sql
CREATE TABLE public.page_components (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    section_id UUID NOT NULL REFERENCES public.page_sections(id),
    content_item_id UUID REFERENCES public.content_items(id), -- Maps to raw content data
    component_type VARCHAR(128) NOT NULL, -- e.g. "Carousel", "CallToAction", "Form"
    custom_configuration JSONB, -- Overrides, behavior configurations, animation flags
    sort_order INTEGER NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_page_components_order UNIQUE (organization_id, section_id, sort_order)
);
CREATE INDEX idx_page_components_section ON public.page_components (section_id);
CREATE INDEX idx_page_components_content ON public.page_components (content_item_id);
```

---

### 4.3 Subdomain C: Media Assets

#### 1. `public.media_folders`
*   **Purpose**: Maps a logical folder directory structure for asset management within a tenant.
*   **Physical DDL**:
```sql
CREATE TABLE public.media_folders (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    parent_id UUID REFERENCES public.media_folders(id),
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_media_folders_name UNIQUE (organization_id, parent_id, name)
);
CREATE INDEX idx_media_folders_parent ON public.media_folders (parent_id);
```

#### 2. `public.media_assets`
*   **Purpose**: Database registry for physical file items stored in object storage arrays.
*   **Physical DDL**:
```sql
CREATE TABLE public.media_assets (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    folder_id UUID REFERENCES public.media_folders(id),
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    storage_provider VARCHAR(64) NOT NULL, -- e.g. "S3", "GCS"
    storage_path VARCHAR(1024) NOT NULL, -- Remote storage key location
    checksum_sha256 CHAR(64) NOT NULL, -- Cryptographic deduplication & validation signature
    virus_scan_status VARCHAR(32) NOT NULL DEFAULT 'Pending', -- "Pending", "Passed", "Failed"
    virus_scan_log TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID
);
CREATE INDEX idx_media_assets_org ON public.media_assets (organization_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_media_assets_folder ON public.media_assets (folder_id);
CREATE INDEX idx_media_assets_checksum ON public.media_assets (checksum_sha256);
```

#### 3. `public.media_versions`
*   **Purpose**: Records modifications, replacements, or crop configurations for a media asset.
*   **Physical DDL**:
```sql
CREATE TABLE public.media_versions (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    media_asset_id UUID NOT NULL REFERENCES public.media_assets(id),
    version_number INTEGER NOT NULL,
    storage_path VARCHAR(1024) NOT NULL,
    file_size BIGINT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by UUID NOT NULL,
    CONSTRAINT uq_media_versions_order UNIQUE (organization_id, media_asset_id, version_number)
);
```

#### 4. `public.media_usage`
*   **Purpose**: Tracks media usage across different pages and components to support referential integrity.
*   **Physical DDL**:
```sql
CREATE TABLE public.media_usage (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    media_asset_id UUID NOT NULL REFERENCES public.media_assets(id),
    usage_context VARCHAR(128) NOT NULL, -- e.g., "Page_Component", "User_Avatar"
    referencing_entity_id UUID NOT NULL, -- Dynamic reference key mapping target
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_media_usage_asset ON public.media_usage (media_asset_id);
```

---

### 4.4 Subdomain D: Navigation

#### 1. `public.navigation_menus`
*   **Purpose**: Represents main configurations for localized menu tracks (e.g., `Main_Header`, `Footer_Internal`).
*   **Physical DDL**:
```sql
CREATE TABLE public.navigation_menus (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    system_name VARCHAR(128) NOT NULL,
    display_name VARCHAR(128) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_nav_menus_system_name UNIQUE (organization_id, system_name)
);
```

#### 2. `public.navigation_items`
*   **Purpose**: Represents nodes within a navigation hierarchy.
*   **Physical DDL**:
```sql
CREATE TABLE public.navigation_items (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    menu_id UUID NOT NULL REFERENCES public.navigation_menus(id),
    parent_id UUID REFERENCES public.navigation_items(id),
    label VARCHAR(128) NOT NULL,
    target_url VARCHAR(512) NOT NULL, -- Target route mapping
    sort_order INTEGER NOT NULL,
    icon_name VARCHAR(64),
    is_new_tab BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_navigation_items_order UNIQUE (organization_id, menu_id, parent_id, sort_order)
);
CREATE INDEX idx_navigation_items_menu ON public.navigation_items (menu_id);
CREATE INDEX idx_navigation_items_parent ON public.navigation_items (parent_id);
```

---

### 4.5 Subdomain E: Taxonomy

#### 1. `public.categories`
*   **Purpose**: Logical category paths for cataloging and organizing content.
*   **Physical DDL**:
```sql
CREATE TABLE public.categories (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    parent_id UUID REFERENCES public.categories(id),
    slug VARCHAR(128) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512),
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_categories_slug UNIQUE (organization_id, parent_id, slug)
);
CREATE INDEX idx_categories_parent ON public.categories (parent_id);
```

#### 2. `public.tags`
*   **Purpose**: Flexible tag descriptors that can be applied to content items.
*   **Physical DDL**:
```sql
CREATE TABLE public.tags (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    slug VARCHAR(128) NOT NULL,
    name VARCHAR(128) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_tags_slug UNIQUE (organization_id, slug)
);
CREATE INDEX idx_tags_org_slug ON public.tags (organization_id, slug);
```

#### 3. `public.content_categories`
*   **Purpose**: Map table linking content items to operational categories.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_categories (
    content_item_id UUID NOT NULL REFERENCES public.content_items(id),
    category_id UUID NOT NULL REFERENCES public.categories(id),
    organization_id UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (content_item_id, category_id)
);
CREATE INDEX idx_content_categories_cat ON public.content_categories (category_id);
```

#### 4. `public.content_tags`
*   **Purpose**: Map table linking content items to tags.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_tags (
    content_item_id UUID NOT NULL REFERENCES public.content_items(id),
    tag_id UUID NOT NULL REFERENCES public.tags(id),
    organization_id UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (content_item_id, tag_id)
);
CREATE INDEX idx_content_tags_tag ON public.content_tags (tag_id);
```

---

### 4.6 Subdomain F: Localization

#### 1. `public.languages`
*   **Purpose**: Lookup registry for languages and regional variations (e.g., `en-US`, `es-ES`, `ar-SA`).
*   **Physical DDL**:
```sql
CREATE TABLE public.languages (
    locale_code VARCHAR(16) PRIMARY KEY,
    language_name VARCHAR(64) NOT NULL,
    native_name VARCHAR(64) NOT NULL,
    is_rtl BOOLEAN NOT NULL DEFAULT FALSE, -- Right-to-Left writing script flag
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. `public.localized_content`
*   **Purpose**: Stores translation text and payloads for content items.
*   **Physical DDL**:
```sql
CREATE TABLE public.localized_content (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    content_item_id UUID NOT NULL REFERENCES public.content_items(id),
    locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    version_number INTEGER NOT NULL DEFAULT 1,
    title VARCHAR(512) NOT NULL,
    excerpt TEXT,
    rich_body TEXT, -- Markdown, structural markup, or raw block representations
    custom_field_values JSONB NOT NULL, -- Field translations matching schema constraints
    is_translation_approved BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID,
    updated_by UUID,
    CONSTRAINT uq_localized_content_item UNIQUE (organization_id, content_item_id, locale_code)
);
CREATE INDEX idx_localized_content_lookup ON public.localized_content (content_item_id, locale_code) WHERE deleted_at IS NULL;
```

#### 3. `public.localized_pages`
*   **Purpose**: Stores localized text overrides and title values for page layouts.
*   **Physical DDL**:
```sql
CREATE TABLE public.localized_pages (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    page_id UUID NOT NULL REFERENCES public.pages(id),
    locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    page_title VARCHAR(256) NOT NULL,
    slug_path VARCHAR(512) NOT NULL, -- Localized path segment routing
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_localized_pages_slug UNIQUE (organization_id, locale_code, slug_path)
);
CREATE INDEX idx_localized_pages_lookup ON public.localized_pages (page_id, locale_code);
```

---

### 4.7 Subdomain G: SEO & Routing

#### 1. `public.seo_metadata`
*   **Purpose**: Manages localized search-engine settings, metadata tags, and robot crawler instructions.
*   **Physical DDL**:
```sql
CREATE TABLE public.seo_metadata (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    entity_context VARCHAR(128) NOT NULL, -- e.g. "Page", "Content_Item"
    referencing_entity_id UUID NOT NULL,
    locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    meta_title VARCHAR(256) NOT NULL,
    meta_description VARCHAR(512) NOT NULL,
    keywords VARCHAR(256)[],
    canonical_url VARCHAR(1024),
    og_title VARCHAR(256),
    og_description VARCHAR(512),
    og_image_id UUID REFERENCES public.media_assets(id),
    robots_instructions VARCHAR(128) DEFAULT 'index, follow',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_seo_metadata_entity UNIQUE (organization_id, entity_context, referencing_entity_id, locale_code)
);
CREATE INDEX idx_seo_metadata_lookup ON public.seo_metadata (entity_context, referencing_entity_id, locale_code);
```

#### 2. `public.redirect_rules`
*   **Purpose**: Manages SEO-safe redirect rules (e.g., 301 Permanent, 302 Temporary redirects).
*   **Physical DDL**:
```sql
CREATE TABLE public.redirect_rules (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    source_url_path VARCHAR(1024) NOT NULL,
    target_url_path VARCHAR(1024) NOT NULL,
    redirect_code INTEGER NOT NULL DEFAULT 301, -- 301, 302, 307, 308
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT chk_redirect_rules_code CHECK (redirect_code IN (301, 302, 307, 308)),
    CONSTRAINT uq_redirect_rules_path UNIQUE (organization_id, source_url_path)
);
CREATE INDEX idx_redirect_rules_org ON public.redirect_rules (organization_id) WHERE is_active = TRUE AND deleted_at IS NULL;
```

#### 3. `public.url_aliases`
*   **Purpose**: Maps dynamic system routes to clean, user-friendly urls.
*   **Physical DDL**:
```sql
CREATE TABLE public.url_aliases (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    system_path VARCHAR(1024) NOT NULL, -- e.g., "/content/items/123-abc"
    alias_path VARCHAR(1024) NOT NULL,  -- e.g., "/marketing/solutions"
    locale_code VARCHAR(16) NOT NULL REFERENCES public.languages(locale_code),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_url_aliases_alias UNIQUE (organization_id, locale_code, alias_path)
);
CREATE INDEX idx_url_aliases_lookup ON public.url_aliases (organization_id, alias_path, locale_code) WHERE is_active = TRUE AND deleted_at IS NULL;
```

---

### 4.8 Subdomain H: Content Relationships

#### 1. `public.related_content`
*   **Purpose**: Maps associative structures between items to support content recommendation widgets.
*   **Physical DDL**:
```sql
CREATE TABLE public.related_content (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    source_item_id UUID NOT NULL REFERENCES public.content_items(id),
    target_item_id UUID NOT NULL REFERENCES public.content_items(id),
    relation_weight NUMERIC(3, 2) NOT NULL DEFAULT 1.00, -- Relevance score weight
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_related_content_pair UNIQUE (organization_id, source_item_id, target_item_id),
    CONSTRAINT chk_related_content_self CHECK (source_item_id <> target_item_id)
);
CREATE INDEX idx_related_content_source ON public.related_content (source_item_id);
```

#### 2. `public.content_references`
*   **Purpose**: Manages strict integrity dependencies between dynamic parent-child references.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_references (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    parent_item_id UUID NOT NULL REFERENCES public.content_items(id),
    child_item_id UUID NOT NULL REFERENCES public.content_items(id),
    field_identifier VARCHAR(128) NOT NULL, -- Field property identifier defined by parent schema
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_content_references_order UNIQUE (organization_id, parent_item_id, field_identifier, sort_order)
);
CREATE INDEX idx_content_references_parent ON public.content_references (parent_item_id);
CREATE INDEX idx_content_references_child ON public.content_references (child_item_id);
```

---

### 4.9 Subdomain I: Publishing & Deployments

#### 1. `public.publish_targets`
*   **Purpose**: Defines environments where content can be deployed (e.g., `Staging`, `Production`, `Mobile_App_Store`).
*   **Physical DDL**:
```sql
CREATE TABLE public.publish_targets (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    system_name VARCHAR(128) NOT NULL,
    target_url VARCHAR(1024) NOT NULL,
    access_token_encrypted TEXT NOT NULL, -- Encrypted target API credentials
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT uq_publish_targets_name UNIQUE (organization_id, system_name)
);
```

#### 2. `public.publish_jobs`
*   **Purpose**: Records deployment job histories, processing runs, and publishing tasks.
*   **Physical DDL**:
```sql
CREATE TABLE public.publish_jobs (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    publish_target_id UUID NOT NULL REFERENCES public.publish_targets(id),
    job_status VARCHAR(64) NOT NULL DEFAULT 'Scheduled', -- "Scheduled", "Running", "Completed", "Failed"
    schedule_at TIMESTAMPTZ NOT NULL,
    completed_at TIMESTAMPTZ,
    execution_log TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by UUID NOT NULL
);
CREATE INDEX idx_publish_jobs_status ON public.publish_jobs (job_status, schedule_at);
```

---

### 4.10 Subdomain J: Analytics Support

#### 1. `public.content_statistics`
*   **Purpose**: Logs visitor metrics and raw content views to support performance reporting.
*   **Physical DDL**:
```sql
CREATE TABLE public.content_statistics (
    organization_id UUID NOT NULL,
    content_item_id UUID NOT NULL REFERENCES public.content_items(id),
    tracking_date DATE NOT NULL,
    view_count INTEGER NOT NULL DEFAULT 0,
    unique_visitor_count INTEGER NOT NULL DEFAULT 0,
    average_dwell_time_seconds INTEGER DEFAULT 0,
    PRIMARY KEY (organization_id, content_item_id, tracking_date)
);
CREATE INDEX idx_content_statistics_date ON public.content_statistics (tracking_date);
```

#### 2. `public.page_statistics`
*   **Purpose**: Logs visitor metrics, entry paths, and view counts for renderable pages.
*   **Physical DDL**:
```sql
CREATE TABLE public.page_statistics (
    organization_id UUID NOT NULL,
    page_id UUID NOT NULL REFERENCES public.pages(id),
    tracking_date DATE NOT NULL,
    view_count INTEGER NOT NULL DEFAULT 0,
    bounce_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (organization_id, page_id, tracking_date)
);
```

#### 3. `public.search_keywords`
*   **Purpose**: Logs internal search terms, click volumes, and sitemap keywords.
*   **Physical DDL**:
```sql
CREATE TABLE public.search_keywords (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    search_term VARCHAR(256) NOT NULL,
    search_date DATE NOT NULL,
    execution_count INTEGER NOT NULL DEFAULT 1,
    no_results_returned BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_search_keywords_lookup ON public.search_keywords (organization_id, search_term, search_date);
```

---

## 5. MEDIA STORAGE INTEGRATION

To support headless integrations and optimize global content delivery, the CMS leverages decoupled object storage pointer structures:

```
                          MEDIA STORAGE DATA BOUNDARY
  ┌──────────────────────┐      S3 / Cloud Storage      ┌──────────────────────┐
  │  Media Ingestion GW  ├─────────────────────────────►│ Managed S3 Bucket    │
  └──────────┬───────────┘                              └──────────┬───────────┘
             │                                                     │
             │ (Calculates SHA-256 Hash & Scan)                    │ (Uploads / Links Asset)
             ▼                                                     ▼
  ┌────────────────────────────────────────────────────────────────────────────────────┐
  │                          POSTGRESQL MEDIA CONTROL SCHEMAS                          │
  │   - public.media_assets (Checksum, Storage Provider, Virus Scan Status)            │
  │   - public.media_versions (Immutable, tracking variant files)                      │
  │   - public.media_usage (Integrity lookup, preventing orphaned attachments)          │
  └────────────────────────────────────────────────────────────────────────────────────┘
```

The pointer schema configuration supports the following capabilities:
*   **S3-Compatible Metadata**: Tracks bucket regions, S3 storage classes, unique file paths, and CDN-linked URL endpoints.
*   **SHA-256 Deduplication**: Pre-computes file checksum hashes before saving records. If a file checksum already exists, the database references the existing asset record, optimizing storage space.
*   **Attachment Usage Tracking**: Uses `public.media_usage` records to track how files are referenced by layout components. This prevents files that are actively in use from being deleted, protecting database referential integrity.
*   **Virus Scanning Registers**: Tracks scan state flags (`Pending`, `Passed`, `Failed`) and stores engine outputs inside audit-log columns before assets are marked as active in user interfaces.
*   **System Lifecycle Management**: Configures custom retention policies for draft folders and soft-deleted assets, triggering automated cleanup processes.

---

## 6. MULTI-LANGUAGE FOUNDATIONS

To support global deployments, the CMS incorporates a decoupled multi-language translation architecture:

```
                      LOCALIZATION RELATIONSHIP ENGINE
  ┌────────────────────────────────────────────────────────────────────────┐
  │                       public.content_items                             │
  │     - Master System Identifier (e.g., "marketing_q2_banner")           │
  └───────────────────────────┬────────────────────────────────────────────┘
                              │ (1-to-Many Locale Branches)
     ┌────────────────────────┼────────────────────────┐
     ▼ (locale_code: en-US)   ▼ (locale_code: es-ES)   ▼ (locale_code: ar-SA)
┌───────────┐            ┌───────────┐            ┌───────────┐
│Localized  │            │Localized  │            │Localized  │ (RTL Script Enabled)
│"marketing"│            │"mercado"  │            │"تسويق"    │ (Meta-Tags Synced)
└───────────┘            └───────────┘            └───────────┘
```

The database structures provide the following multi-language capabilities:
*   **Unlimited Language Registries**: Uses standard ISO locale tracking configurations to scale localized variations without modifying table structures.
*   **RTL Layout Script Flags**: Sets language direction properties (`is_rtl` flags) to automatically configure user interface alignments.
*   **Decoupled Translation Structs**: Decouples translated strings (`localized_content`) from the main content container (`content_items`). This design enables separate editing and approval workflows for different languages.
*   **Localized Slugs & SEO Settings**: Supports custom SEO meta tags, URL aliases, and page routes for each language to optimize search engine performance.
*   **Validation Fallbacks**: Links fallback locale configurations to ensure the system serves default language assets if a translation is missing.

---

## 7. SEARCH FOUNDATIONS

To deliver sub-15ms text search and support headless semantic searches, our physical database schema configures native PostgreSQL GIN index structures:

```
                         SEARCH INDEX ARCHITECTURE
  [Text Body & Titles] ──► [Precomputed tsvector Column] ──► [GIN Text Search Index]
                                                                     │
         ┌───────────────────────────────────────────────────────────┴─────────────────────┐
         ▼ (Keyword-Based Query)                                                           ▼ (Trigram Partial Search)
  [FTS ts_query matches] ──► [Fast Index Returns]                         [trgm GIN partial matches]
```

*   **Native PostgreSQL FTS Indexing**: Calculates and caches `tsvector` columns, generating search indices across translated body texts and titles.
*   **Trigram Partial-String Search**: Utilizes `pg_trgm` GIN indices on url slug pathways and names to optimize partial-string auto-complete queries.
*   **Embedding Search Integrations**: Implements HNSW indexes using the `pgvector` database extension to enable semantic vector search on article embeddings.
*   **Search Intent Logging**: Stores keyword search inputs and tracking logs to monitor search performance and identify content gaps.

---

## 8. DATABASE SECURITY & PRIVACY CONTROLS

The security framework below enforces multi-tenant isolation, protects personal data, and logs system actions directly within the database engine:

### 8.1 Multi-Tenant Row-Level Security (RLS)
Every table within the CMS schema has Row-Level Security enabled, isolating data using verified tenant context values:
```sql
ALTER TABLE public.content_items ENABLE ROW LEVEL SECURITY;

CREATE POLICY cms_item_tenant_isolation ON public.content_items
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 8.2 Ownership & Access Controls
*   **Field-Level Access Boundaries**: Restricts access to sensitive content (such as internal employee resources or unreleased announcements) using visibility configurations (`Public`, `Internal`, `Confidential`).
*   **Encryption Handlers**: Uses database-level KMS keys to encrypt publishing target secrets and access credentials.
*   **Audit Logger Pipelines**: Saves transaction histories, operational updates, and tenant actions to append-only logs.
*   **Right to be Forgotten (GDPR)**: Automatically masks personal information in logs and runs data retention processes to clear expired draft revisions.

---

## 9. COUPLING DECOUPLING AND BOUNDARY CROSS-REFERENCES

The CMS bounded context integrates with adjacent enterprise services through decoupled event contracts, preventing cross-domain database locks:

*   **Authentication Core**: Validates contributor roles and permissions using user accounts mapped in `Phase_2_3_2B_Authentication_Physical_Tables.md`.
*   **CRM Schemas**: References customer attributes (`Phase_2_3_2C_CRM_Physical_Tables.md`) to personalize website banners.
*   **Project Management**: Maps article writing tasks to developer milestones using project structures in `Phase_2_3_2D_Projects_Physical_Tables.md`.
*   **Financial Ledgers**: Links content usage statistics to financial billing records using transaction structures in `Phase_2_3_2E_Finance_Physical_Tables.md`.
*   **Support Desk**: Feeds help documentation articles to agent dashboards using KB article schemas in `Phase_2_3_2F_Support_Physical_Tables.md`.

---

## 10. HIGH GROWTH TABLES & PARTITIONING PLAN

The tables below are designed to scale efficiently under heavy write volumes:

### 10.1 `public.content_versions` (Historical Log)
*   **Growth**: High growth. Every content revision adds a new version record.
*   **Partitioning Plan**: Hash partitioning using 32 logical hash buckets on `content_item_id` values to prevent primary write bottlenecks.
*   **Archiving**: Archives records older than 2 years to Amazon S3 Parquet tables, preserving current transactional performance.

### 10.2 `public.content_statistics` (Analytical Views)
*   **Growth**: High growth. Tracks daily view counts and interactions for all published content.
*   **Partitioning Plan**: Range partitioning monthly on `tracking_date` columns.
*   **Archiving**: Consolidates daily data into monthly summaries and deletes detailed daily logs after 1 year.

### 10.3 `public.search_keywords` (Visitor Search Logs)
*   **Growth**: High growth. Logs customer search terms and click data.
*   **Partitioning Plan**: Range partitioning monthly on `search_date` columns.
*   **Archiving**: Purges detailed transaction logs after 90 days, keeping anonymized search totals for analysis.

---

## 11. CLASSIFICATION MATRIX

This directory catalogs all CMS tables, detailing their expected growth, partitioning strategies, data attributes, and retention rules:

| Physical Table Name | Expected Growth | Partitioning Strategy | JSONB Usage | PII Presence | Column Encryption | Audit Level | Retention Period | Search Participation | Localization |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: | :--- | :---: | :---: |
| `public.content_statuses` | None | None | None | No | No | None | Infinite | No | No |
| `public.content_types` | Low | None | Active schema validation | No | No | Full | Infinite | No | Yes |
| `public.content_templates` | Low | None | None | No | No | Full | Infinite | No | No |
| `public.content_items` | Medium | None | None | No | No | Full | 7 Years active database | Yes (FTS) | Yes |
| `public.content_versions` | High | Hash on `content_item_id` | Version payload definitions | Yes (Conditional) | No | Strict | 10 Years, active archive | Yes (FTS) | Yes |
| `public.page_layouts` | Low | None | Grid rules layout definitions | No | No | Full | Infinite | No | No |
| `public.pages` | Medium | None | None | No | No | Full | 7 Years active database | Yes (FTS) | Yes |
| `public.page_sections` | Medium | None | Styles, configurations | No | No | Full | 7 Years active database | No | Yes |
| `public.page_components` | Medium | None | Component definitions | No | No | Full | 7 Years active database | No | Yes |
| `public.media_folders` | Low | None | None | No | No | Full | Infinite | No | No |
| `public.media_assets` | Medium | None | Metadata characteristics | No | No | Full | 10 Years active database | Yes (Filename) | No |
| `public.media_versions` | Low | None | None | No | No | Full | Synced with asset | No | No |
| `public.media_usage` | Medium | None | None | No | No | Low | Synced with referencing entity | No | No |
| `public.navigation_menus` | Low | None | None | No | No | Full | Infinite | No | Yes |
| `public.navigation_items` | Low | None | None | No | No | Full | Infinite | No | Yes |
| `public.categories` | Low | None | None | No | No | Full | Infinite | Yes (Name) | Yes |
| `public.tags` | Low | None | None | No | No | Low | Infinite | Yes (Name) | Yes |
| `public.content_categories` | Medium | None | None | No | No | None | Synced with content | No | No |
| `public.content_tags` | Medium | None | None | No | No | None | Synced with content | No | No |
| `public.languages` | None | None | None | No | No | None | Infinite | No | No |
| `public.localized_content` | Medium | None | Translated fields structure | Yes (Conditional) | No | Full | Synced with content | Yes (FTS) | Yes |
| `public.localized_pages` | Medium | None | None | No | No | Full | Synced with page | Yes (Title) | Yes |
| `public.seo_metadata` | Medium | None | Meta definitions array | No | No | Full | Synced with entity | Yes (Keywords)| Yes |
| `public.redirect_rules` | Low | None | None | No | No | Full | 5 Years, archive | No | No |
| `public.url_aliases` | Medium | None | None | No | No | Full | 7 Years, archive | Yes (Trigram) | Yes |
| `public.related_content` | Medium | None | None | No | No | None | Synced with source item | No | No |
| `public.content_references` | Medium | None | None | No | No | None | Synced with parent item | No | No |
| `public.publish_targets` | Low | None | None | No | Yes (Access credentials) | Full | Infinite | No | No |
| `public.publish_jobs` | Medium | Range on `schedule_at` | Execution details, errors | No | No | Full | 5 Years, archive | No | No |
| `public.content_statistics` | High | Range on `tracking_date` | None | No | No | None | 1 Year daily, summarize | No | No |
| `public.page_statistics` | High | Range on `tracking_date` | None | No | No | None | 1 Year daily, summarize | No | No |
| `public.search_keywords` | High | Range on `search_date` | None | Yes (Conditional) | No | None | 90 Days detail, summarize | No | No |

---

## 12. CONSTITUTIONAL DATABASE PRINCIPLES

All database development, system integrations, and code updates must adhere strictly to the **JUANET CMS Constitution**:

1.  **Content is the System of Record**: All structural text, localized configurations, and asset metadata must reside within the CMS schemas.
2.  **Decoupled Presentation Layouts**: Page rendering, visual components, and style hierarchies must remain decoupled from raw content data.
3.  **Unified Media Assets**: All media uploads are cataloged in a central database registry with hash validation to eliminate file duplication.
4.  **Independent Localization**: Translation branches must be decoupled from main content containers to simplify translation workflows.
5.  **Version-Driven Publishing**: All publish operations must target specific content revisions to guarantee precise rollout logs.
6.  **No Direct Cross-Domain Updates**: Operational services are strictly barred from directly updating CMS tables. All data access must execute via verified REST/GraphQL APIs or transactional event subscriptions.

---

*Authorized by the JUANET Architecture Review Board & Enterprise Security Council.*
