# JUANET Media and Digital Asset Management Implementation Manual
## Phase 2.3.2G.2 — Operational Governance, Asset Lifecycle State Machines, Rendition Engines, and DAM Architecture
**Document Version:** 1.0  
**Author:** Chief Media Architect, Principal Systems Engineer, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and DAM Specification  

---

## 1. DIGITAL ASSET PHILOSOPHY

Within high-scale multi-tenant enterprise architectures, digital media assets form the visual core of user experience, communications, and brand presence. The **JUANET Digital Asset Management (DAM) Bounded Context** designates **Media Assets as the Enterprise System of Record (SoR) for all binary content**. 

To deliver assets efficiently across global channels while maintaining multi-tenant security and system integrity, the DAM architecture is structured around a strict separation of concerns:

```
                            [JUANET DAM DECOUPLED ARCHITECTURE]

       ┌──────────────────────┐  (Upload Metadata)  ┌──────────────────────┐
       │   Asset Ingestion    ├────────────────────►│    PostgreSQL DB     │
       │   (Chunked Upload)   │                     │    (Metadata SoR)    │
       └──────────┬───────────┘                     └──────────▲───────────┘
                  │                                            │
                  │ (Binary Upload)                            │ (State Updates)
                  ▼                                            │
       ┌──────────────────────┐                     ┌──────────┴───────────┐
       │  Object Storage S3   │────────────────────►│  Processing Pipeline │
       │  (Immutable Storage) │ (Triggers Workers)  │  (Malware, Crops)    │
       └──────────┬───────────┘                     └──────────┬───────────┘
                  │                                            │
                  ▼ (Cached Origins)                           ▼ (Publishes Events)
       ┌──────────────────────┐                     ┌──────────────────────┐
       │   Edge Delivery CDN  │                     │   Integration Bus    │
       │   (Global WebP/AVIF) │                     │   (outbound_events)  │
       └──────────────────────┘                     └──────────────────────┘
```

The system enforces the following core architectural rules:
*   **Immutability of Binary Objects**: Once a binary file is successfully uploaded, scanned, and saved to S3-compatible object storage, its binary data is immutable. Any modification, cropping, or conversion creates a new rendition record or a separate asset version.
*   **Independent Evolution of Metadata**: Metadata—including titles, descriptions, categories, tags, licensing rights, accessibility tags, and AI analysis data—can evolve independently without requiring modifications to the underlying binary file.
*   **Global Reusability**: Assets are stored centrally and cataloged with unique identifiers, enabling different pages, layout components, and tenant workspaces to reference the same asset without duplicating files.
*   **Reference-by-Identifier Only**: Downstream systems (such as CMS, CRM, Projects, Finance, or Support) store only the unique asset identifier (`media_asset_id`). They never embed raw file paths, direct CDN URLs, or local storage routes.
*   **Tenant Isolation via Row-Level Security (RLS)**: Every asset, directory, version, and rendition belongs to exactly one tenant (`organization_id`), enforced strictly at the database layer via PostgreSQL Row-Level Security.

---

## 2. ASSET LIFECYCLE STATE MACHINE (FSM)

Every media asset undergoes a deterministic lifecycle, transitioning through validated states to guarantee file safety, processing consistency, and multi-tenant security boundaries:

```
                                [ASSET LIFECYCLE FSM]

              ┌──────────────────► [ 1. Uploading ]
              │                           │
              │                           ▼
              │                    [ 2. Uploaded ]
              │                           │
              │                           ▼
              │                    [ 3. Scanning ] ───────► [ Quarantined ] (Virus Found)
              │                           │
              │                           ▼
              │                    [ 4. Processing ] ─────► [ Corrupted ] (Format Error)
              │                           │
              │                           ▼
              │                     [ 5. Ready ] ◄────────┐
              │                           │               │
              │                           ▼               │
              └─────────────────── [ 6. Published ] ──────┘ (Unpublish)
                                          │
                                          ▼
                                   [ 7. Deprecated ]
                                          │
                                          ▼
                                    [ 8. Archived ]
                                          │
                                          ▼
                                     [ 9. Deleted ]
```

### 2.1 State Specifications and Mutation Invariants

*   **1. Uploading**
    *   *Purpose*: Initial upload initialization. Represents an active upload session.
    *   *Entry Criteria*: Client requests upload authorization; system creates the asset record shell and issues signed S3 upload URLs.
    *   *Exit Criteria*: Client completes the file transfer and verifies the payload size.
    *   *Allowed Transitions*: `Uploaded`, `Corrupted`.
    *   *Forbidden Transitions*: `Ready`, `Published`, `Archived`.
*   **2. Uploaded**
    *   *Purpose*: The file is successfully stored in temporary object storage.
    *   *Entry Criteria*: S3 upload notification triggers the system.
    *   *Exit Criteria*: Background worker initiates virus and checksum validations.
    *   *Allowed Transitions*: `Scanning`, `Corrupted`.
    *   *Forbidden Transitions*: `Ready`, `Published`.
*   **3. Scanning**
    *   *Purpose*: File security validation (virus scans, malware detection, mime-type verification).
    *   *Entry Criteria*: Background validation worker locks the asset.
    *   *Exit Criteria*: Scan results are written to the database.
    *   *Allowed Transitions*: `Processing` (Passed), `Quarantined` (Failed/Malware), `Corrupted`.
    *   *Automatic Timeout*: Transitions to `Corrupted` if scanning takes longer than 15 minutes.
*   **4. Processing**
    *   *Purpose*: Asset optimization, metadata extraction, and rendition generation.
    *   *Entry Criteria*: Malware scan passes.
    *   *Exit Criteria*: System extracts metadata and generates thumbnails.
    *   *Allowed Transitions*: `Ready`, `Corrupted`.
*   **5. Ready**
    *   *Purpose*: Asset is processed and available for internal use.
    *   *Entry Criteria*: System finishes generating thumbnails and metadata.
    *   *Exit Criteria*: Editor triggers publishing workflow.
    *   *Allowed Transitions*: `Published`, `Archived`, `Deleted`.
*   **6. Published**
    *   *Purpose*: Asset is available for public delivery via CDN.
    *   *Entry Criteria*: Editor publishes the asset or referencing page.
    *   *Exit Criteria*: Editor unpublishes the asset.
    *   *Allowed Transitions*: `Ready` (Unpublished), `Deprecated`, `Archived`.
*   **7. Deprecated**
    *   *Purpose*: Asset is replaced by a newer version but remains active for existing references.
    *   *Entry Criteria*: Editor uploads a newer version of the asset.
    *   *Exit Criteria*: Usage monitors confirm the asset has no active references.
    *   *Allowed Transitions*: `Archived`.
*   **8. Archived**
    *   *Purpose*: Asset is retired from active use and moved to cold storage.
    *   *Entry Criteria*: Usage monitors confirm no active references; retention policy is met.
    *   *Exit Criteria*: Administrator restores the asset.
    *   *Allowed Transitions*: `Ready`, `Deleted`.
*   **9. Deleted**
    *   *Purpose*: Soft-deleted state. Asset is removed from user interfaces.
    *   *Entry Criteria*: User triggers delete action.
    *   *Exit Criteria*: Retention window expires; system purges physical files from S3.
    *   *Allowed Transitions*: None (Permanent purge).
*   **10. Quarantined**
    *   *Purpose*: Security lock. File is blocked from downloads and access.
    *   *Entry Criteria*: Virus scan detects malware.
    *   *Exit Criteria*: System administrator reviews and purges or clears the file.
    *   *Allowed Transitions*: `Deleted`, `Ready` (Manual override).
*   **11. Corrupted**
    *   *Purpose*: Processing failure state.
    *   *Entry Criteria*: Upload fails, file is truncated, or processing triggers format errors.
    *   *Exit Criteria*: User re-initiates upload.
    *   *Allowed Transitions*: `Deleted`, `Uploading` (Retry).

---

## 3. ASSET TYPES & METADATA STANDARDS

The DAM supports a wide range of asset classes, extracting and validating metadata parameters for each type on ingestion:

### 3.1 Supported Asset Types
*   **Images**: Raster graphics (`JPEG`, `PNG`, `WebP`, `AVIF`, `GIF`) and vector graphics (`SVG`).
*   **Video**: Video formats (`MP4`, `WebM`, `MOV`) and dynamic streaming profiles.
*   **Audio**: Audio files (`MP3`, `WAV`, `AAC`, `OGG`).
*   **Documents**: Portable Document Format (`PDF`) and presentation/sheet formats (`DOCX`, `XLSX`, `PPTX`).
*   **Assets & Fonts**: Packaging archives (`ZIP`, `TAR`, `GZ`) and typography fonts (`WOFF`, `WOFF2`, `TTF`, `OTF`).
*   **Advanced Formats**: 3D design files (`GLTF`, `GLB`) and vectorized icons.

### 3.2 Canonical Metadata Schema

All metadata extracted by the processing pipeline is stored within strongly-typed JSON schemas in `public.media_assets.metadata` to ensure indexing flexibility:

```json
{
  "file_system": {
    "filename": "q2_product_launch_hero.png",
    "original_filename": "DSC_5821_edited_v2.png",
    "extension": "png",
    "mime_type": "image/png",
    "file_size": 2489102,
    "checksum_sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  },
  "dimensions": {
    "width": 3840,
    "height": 2160,
    "aspect_ratio": "16:9"
  },
  "media_stream": {
    "duration_seconds": null,
    "codec": null,
    "frame_rate": null,
    "bitrate": null,
    "color_profile": "sRGB IEC61966-2.1"
  },
  "accessibility": {
    "alt_text": "An enterprise team collaborating over software wireframes in a modern boardroom.",
    "caption": "Team work session during Q2 planning.",
    "language_code": "en-US"
  },
  "licensing": {
    "copyright_owner": "JUANET Solutions Inc.",
    "license_type": "Internal Proprietary",
    "expiration_date": "2031-12-31T23:59:59Z",
    "retention_class": "Confidential"
  },
  "custom": {
    "campaign_code": "Q2-LAUNCH-2026",
    "department": "Global Marketing"
  }
}
```

---

## 4. STORAGE & CDN ARCHITECTURE

The DAM decouples logical asset records from physical storage volumes, supporting cloud-agnostic deployments, storage cost optimization, and global asset delivery:

```
                          STORAGE TIER & DEPLOYMENT GRAPH
  [Edge Clients] ──────► [Edge CDN Cache Origins]
                                │ (Cache Miss)
                                ▼
  [S3 Hot Bucket] ──────► [AWS S3 Standard Storage (Ready / Published)]
                                │ (Unused > 90 Days)
                                ▼
  [S3 Cold Bucket] ─────► [AWS S3 Glacier Instant Retrieval (Archived Assets)]
                                │ (Retention Window Expired)
                                ▼
  [Purged Assets] ──────► [Permanent File Erasure]
```

### 4.1 Storage Tiers and Lifecycle Policies
*   **Hot Tier (S3 Standard)**: Holds assets in `Uploading`, `Uploaded`, `Processing`, `Ready`, and `Published` states. Optimized for low-latency writes and immediate access.
*   **Cold Tier (S3 Glacier Instant Retrieval)**: Stores assets in `Archived` or `Deprecated` states. Reduces storage costs while keeping historical files accessible within seconds.
*   **Archive Tier (Glacier Deep Archive)**: Retains compliance logs, legal-hold attachments, and historical audit copies. Optimized for ultra-low storage cost.
*   **Physical Deletion**: Assets in `Deleted` state remain in soft-deleted storage for 30 days. When this retention window expires, a background process permanently deletes the S3 object keys.

### 4.2 Multi-Region Replication
To maintain high availability and comply with local data residency laws, upload tasks write files to regional S3 buckets based on the tenant's data center region (`organization_id`). Replicas are synchronized asynchronously across regional buckets to ensure fast, local delivery.

---

## 5. PIPELINE DE TRAITEMENT DES ACTIFS (PROCESSING PIPELINE)

The asset processing pipeline runs inside asynchronous workers to analyze, validate, and optimize media files on ingestion:

```
                         ASSET INGESTION PIPELINE
  [S3 Upload Event] ──► [Virus Scan & Verification] ──► [Metadata Extraction]
                                                                │
         ┌──────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Images)                                                                    ▼ (Documents / Video)
  [Responsive Renditions] ──► [WebP / AVIF Optimizations]                     [Previews & OCR Preps]
```

1.  **Ingestion Event**: S3 triggers an event notification when an upload finishes, writing a task to the background processing queue.
2.  **Virus Scan & Verification**: A security worker scans the uploaded file for malware using ClamAV. If malware is detected, the file is immediately quarantined and isolated in S3.
3.  **Checksum Validation**: Calculates the file's SHA-256 hash. If the hash matches an existing asset within the organization, the system deduplicates the upload, mapping the new record to the existing S3 file to save storage space.
4.  **Metadata Extraction**: Extracts file attributes, dimensions, EXIF profiles, and embedded tags, writing the extracted properties to the database asset record.
5.  **Rendition Generation**: Automatically generates responsive image crops, optimized WebP/AVIF variations, PDF previews, and video thumbnails.
6.  **Pipeline Completion**: The worker transitions the asset status to `Ready` and dispatches the `asset.ready` integration event.

---

## 6. MULTI-FORMAT RENDITION ENGINE

To optimize page loading times and reduce bandwidth consumption, the multi-format rendition engine automatically creates responsive image variations and optimized video previews:

```
                            [IMAGE RENDITION TREE]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           Original Asset (RAW)                         │
  │   - File: company_hq_raw.png (12 MB, 6000x4000 pixels)                 │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Processed by Rendition Engine)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│WebP Desktop Large│         │WebP Mobile Small │         │AVIF Thumbnail    │
│- 1920x1080       │         │- 640x360         │         │- 150x150         │
│- Optimized (180KB)│        │- Optimized (42KB)│         │- Crop (8KB)      │
└──────────────────┘         └──────────────────┘         └──────────────────┘
```

### 6.1 Automated Image Conversions
For every uploaded image, the engine generates WebP and AVIF variations optimized for different display resolutions:
*   *Large (Desktop)*: 1920px width, WebP/AVIF format, quality 82.
*   *Medium (Tablet)*: 1200px width, WebP/AVIF format, quality 80.
*   *Small (Mobile)*: 640px width, WebP/AVIF format, quality 75.
*   *Thumbnail*: 150px square crop, WebP/AVIF format, quality 70.

### 6.2 Lazy Rendition Generation
To speed up asset ingestion under heavy loads, only core thumbnails are generated synchronously. Larger responsive variations and previews are generated lazily on first request and cached on CDN edge servers.

---

## 7. FOLDERS & VIRTUAL COLLECTIONS

The DAM provides flexible organization structures, allowing users to group assets logically without duplicating binary files in storage:

```
                           [HYBRID ORGANIZATION MAP]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                        Physical Folder Directory                       │
  │   - Hierarchical structure stored in public.media_folders              │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Contains Files)
                                      ▼
                         ┌──────────────────────────┐
                         │   public.media_assets    │
                         │   (Physical File Assets) │
                         └────────────┬─────────────┘
                                      │
                                      ▼ (Referenced By Virtual Collections)
         ┌────────────────────────────┴────────────────────────────┐
         ▼                                                         ▼
┌──────────────────┐                                      ┌──────────────────┐
│Virtual Collection│                                      │ Smart Collection │
│- Marketing Assets│                                      │ - Recent Images  │
└──────────────────┘                                      └──────────────────┘
```

*   **Hierarchical Directories**: Physical directory trees are stored in `public.media_folders`, providing structured organization with permission inheritance down the folder path.
*   **Virtual Collections**: Reusable asset lists stored in `public.media_collections`. Collections contain references to assets rather than physical files, enabling users to add an asset to multiple collections without duplicate file copies.
*   **Smart Collections**: Dynamic collections defined by query filters (e.g., "All images tagged with 'Q2' uploaded within the last 30 days").
*   **Folder Soft Delete**: Deleting a folder moves its contents to the soft-deleted state. Folder deletion checks child assets, blocking the action if any contained asset has active external references.

---

## 8. REFERENTIAL INTEGRITY & ASSET USAGE TRACKING

To prevent broken links across pages, websites, and user workspaces, the DAM tracks every external asset reference in real time:

```
                            [REFERENTIAL INTEGRITY LOOP]
  ┌────────────────────────────────────────────────────────────────────────┐
  │                          public.media_assets                           │
  └───────────────────────────────────┬────────────────────────────────────┘
                                      │ (Tracks References)
        ┌─────────────────────────────┼─────────────────────────────┐
        ▼                             ▼                             ▼
┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│   Pages Engine   │         │  Knowledge Base  │         │   Support Desk   │
│ - Homepage Hero  │         │ - Article Attach │         │ - Ticket Upload  │
└──────────────────┘         └──────────────────┘         └──────────────────┘
        │                             │                             │
        └─────────────────────────────┼─────────────────────────────┘
                                      ▼
                     ┌──────────────────────────────────┐
                     │       public.media_usage         │
                     │ (Ensures Safe Deletion Controls) │
                     └──────────────────────────────────┘
```

### 8.1 Usage Verification Rules
Before executing a delete action on a media asset, the deletion service queries `public.media_usage` to verify active references:
```sql
-- Check if the asset is currently used by any page or component
SELECT COUNT(*) 
FROM public.media_usage
WHERE media_asset_id = :media_asset_id;
```
If active references exist, the deletion service blocks the transaction, returning a referential integrity error containing a list of referencing pages and components to prevent broken links.

---

## 9. SEARCH & DISCOVERY ARCHITECTURE

The DAM leverages PostgreSQL 16 index structures and trigram engines to deliver fast, scalable asset search:

*   **Full-Text Search (FTS)**: Pre-computes search vectors (`tsvector` format) on asset titles, descriptions, and user tags, enabling fast, multi-keyword queries:
```sql
-- Weighted full-text search across media assets
CREATE INDEX idx_media_assets_fts ON public.media_assets USING GIN (
    (setweight(to_tsvector('english', COALESCE(filename, '')), 'A') ||
     setweight(to_tsvector('english', COALESCE(virus_scan_status, '')), 'B'))
);
```
*   **Trigram GIN Indexing**: Configures `pg_trgm` GIN indexes on filename paths to support fast partial-string search and auto-complete filters.
*   **Color Search Indexing**: Extracted color palettes are stored as arrays in database columns, enabling users to filter search results by hex values or dominant colors.

---

## 10. AI METADATA FOUNDATIONS

To automate asset categorization and tagging, the database schema provides advisory-only AI metadata columns:

```json
{
  "ai_analysis": {
    "captions": [
      { "text": "Aerial view of city skyscrapers at sunset", "confidence": 0.94 }
    ],
    "detected_objects": [
      { "label": "Skyscraper", "box": [0.1, 0.2, 0.8, 0.9], "confidence": 0.98 },
      { "label": "Sunset", "box": [0.0, 0.1, 0.5, 0.3], "confidence": 0.91 }
    ],
    "ocr_text": "COMMERCE INC",
    "suggested_tags": ["city", "skyline", "urban", "sunset", "downtown"],
    "embeddings": [0.125, -0.482, 0.892, -0.012],
    "moderation": {
      "nsfw_score": 0.01,
      "copyright_risk_score": 0.05,
      "passed_policy": true
    }
  }
}
```

The database structures support the following AI integration paths:
*   **Auto-Tagging & Captions**: Stores AI-generated image captions, suggested tags, and detected objects to automate categorization.
*   **Optical Character Recognition (OCR)**: Indexes extracted text from scanned PDF invoices and documents, enabling full-text keyword search.
*   **Semantic Vector Embeddings**: Stores vector representations of image attributes inside embedding columns, enabling similarity searches using the `pgvector` database extension.
*   **Automated Content Moderation**: Logs policy evaluations, safety indicators, and copyright risks before assets are published.

---

## 11. SECURITY & DATA PRIVACY

The security framework protects asset access, enforces tenant boundaries, and records administrative actions directly within the database engine:

### 11.1 Row-Level Security Policies
Row-Level Security (RLS) is enabled on all media schemas, isolating directories and assets based on verified tenant session context values:
```sql
ALTER TABLE public.media_assets ENABLE ROW LEVEL SECURITY;

CREATE POLICY dam_asset_tenant_isolation ON public.media_assets
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 11.2 Signed CDN URLs
Direct public S3 bucket access is blocked. To deliver private or secure assets (such as customer invoices or sensitive contracts), the system generates time-limited, cryptographically signed URLs:
```
https://cdn.juanet.platform/assets/7e2b-4011?signature=abc123xyz&expires=1782806400
```
CDN edges parse and validate signatures before serving assets, preventing unauthorized file downloads and URL guessing attacks.

---

## 12. DAM INTEGRATION EVENT CONTRACTS

All upload completions, virus scans, and asset deletions write detailed integration records to the transactional outbox table (`audit.outbound_events`), enabling asynchronous downstream service coordination:

```
                         EVENT PIPELINE DISPATCH CYCLE
  [Ingestion State Mutation] ──► [Transactional Outbox] ──► [Asynchronous Job Worker]
                                                                    │
         ┌──────────────────────────────────────────────────────────┴──────────────────────┐
         ▼ (Delivered)                                                                     ▼ (Fails 5x)
  [Downstream Services] ──► [Idempotency Checked]                                 [Dead-Letter Queue]
```

### 12.1 DAM Event Catalog

| System Event Name | Event Identifier | Source Service | Main Consumers | Payload Structure |
| :--- | :--- | :--- | :--- | :--- |
| **Asset Uploaded** | `asset.uploaded` | `UploadGateway` | Virus Scan Worker | `{ "asset_id": "uuid", "file_size": 2489102 }` |
| **Asset Scanned** | `asset.scanned` | `SecurityWorker`| Processing Pipeline | `{ "asset_id": "uuid", "scan_passed": true }` |
| **Asset Processed**| `asset.processed` | `ProcessingService`| Search Indexer, CDN | `{ "asset_id": "uuid", "renditions": 4 }` |
| **Asset Ready** | `asset.ready` | `ProcessingService`| CMS, CRM, Support Desk | `{ "asset_id": "uuid", "status": "Ready" }` |
| **Asset Updated** | `asset.updated` | `AssetService` | CDN Cache Origin | `{ "asset_id": "uuid", "new_version": 2 }` |
| **Asset Published**| `asset.published` | `PublishEngine` | CDN Edge, Search | `{ "asset_id": "uuid", "cdn_path": "string" }`|
| **Asset Archived** | `asset.archived` | `AssetService` | S3 Cold Storage Worker | `{ "asset_id": "uuid", "archive_tier": "GLACIER" }`|
| **Asset Deleted** | `asset.deleted` | `AssetService` | S3 Purge Scheduler | `{ "asset_id": "uuid", "soft_delete": true }` |
| **Asset Quarantined**| `asset.quarantined` | `SecurityWorker`| Admin Alert System | `{ "asset_id": "uuid", "malware_signature": "string" }`|

### 12.2 Event Delivery & Idempotency
*   **At-Least-Once Delivery**: Outbox entries are committed within the same database transaction as asset state modifications, guaranteeing event capture even during system crashes.
*   **Idempotency Protection**: Subscribers track incoming events using composite keys to prevent duplicate operations:
```
Idempotency Key: hash('asset.processed' + media_asset_id + checksum_sha256)
```

---

## 13. PERFORMANCE AND STORAGE OPTIMIZATION

The DAM schema is optimized to handle large files and maintain fast response times under heavy transaction volumes:

*   **Multipart Chunked Uploads**: Large media files (such as high-resolution videos or archives) are split into 5MB chunks during client uploads. This reduces memory usage and allows the system to recover gracefully if network connections drop.
*   **S3 Storage Deduplication**: Validates checksum hashes before writing files to object storage. If the hash matches an existing file, the system references the existing object key, reducing storage costs.
*   **Read-Write Separation**: Read-heavy public asset metadata queries are routed to read-only replicas, preventing database locks on primary write volumes.
*   **Background Worker Queues**: Heavy processing tasks (such as generating responsive image variations, transcoding video files, and updating vector search indexes) are offloaded to background worker queues, keeping user interfaces fast and responsive.

---

## 14. ENGINEERING VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **FSM Transitions** | Run valid and forbidden transition paths on asset records. | Block incorrect transitions, raising clean state validation errors. | State Machine Unit Tests |
| **Malware Quarantining**| Upload simulated malware test signatures. | Scan detects file, quarantines asset, and writes audit record. | Malware Ingestion Suite |
| **Checksum Validation** | Upload duplicate asset binary files. | Database detects matching hash, deduplicating file storage. | Deduplication Tests |
| **Responsive Renditions**| Process image ingestion through the rendition engine. | Verifies all target crops, responsive sizes, and WebP versions are generated. | Rendition Verification Tests |
| **Referential Integrity** | Attempt to delete a file actively referenced by page components. | Database blocks the action, returning a list of active referrers. | Referential Integrity Audits |
| **Tenant Data Isolation** | Query assets without setting tenant session context values. | RLS policies block access, preventing cross-tenant data exposure. | Multi-Tenant Leakage Tests |
| **Outbox Atomic Rollback**| Inject a database failure during asset save transactions. | Rollback reverts both the asset record and the outbox event entry. | Atomic Transaction Tests |

---

## 15. CONSTITUTIONAL ENGINEERING PRINCIPLES

All DAM implementations within the JUANET platform adhere strictly to the following architectural guidelines:

*   **Binary Assets are Immutable Once Saved**: Physical media files are unchangeable. Cropping, converting, or resizing creates a separate rendition record, protecting master assets.
*   **Metadata May Evolve Independently**: Asset titles, descriptions, categories, and AI tags can be updated without modifying the underlying binary file.
*   **Every Asset is Globally Reusable**: Files are cataloged with unique identifiers, enabling multiple components and pages to reference the same asset without duplication.
*   **Operational Systems Never Own Binary Files**: Downstream systems reference assets by unique ID, decoupling transactional databases from storage buckets.
*   **Every Storage Operation is Event-Driven**: Asset uploads, state transitions, and file purges are recorded in outbox event tables to guarantee decoupled coordination.
*   **Every Derived Rendition is Reproducible**: Rendition parameters, crop dimensions, and configuration files are registered in the database, ensuring any crop or size variant can be recreated.
*   **Every Change is Fully Auditable**: System configurations and version records guarantee that all asset updates are tracked with complete user context.

---

*Authorized by the JUANET Content Architecture Review Board & Technical Governance Council.*
