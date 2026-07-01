# JUANET CMS Security, Privacy & Compliance Implementation Manual
## Phase 2.3.2G.11 — Zero Trust Architecture, Row-Level Security (RLS), Cryptographic Envelopes, Immutable Audit Hash-Chains, and Regulatory Privacy Controls
**Document Version:** 1.0  
**Author:** Chief Security Officer, Principal Compliance Architect, and Technical Governance Council  
**Classification:** Public / Enterprise Implementation Standard, Domain Architecture Manual, and Security Specification  

---

## 1. SECURITY PHILOSOPHY & ZERO TRUST PRINCIPLES

In a global, multi-tenant enterprise SaaS platform, protecting customer content, media assets, localized pages, and system telemetry requires an enterprise-grade security framework. The **JUANET CMS Security, Privacy & Compliance Bounded Context** implements a strict **Zero Trust Architecture (ZTA)**, complying with **NIST SP 800-207** standards.

The platform assumes that threats exist both inside and outside network boundaries. No user, service account, or application node is trusted by default. Every access request is authenticated, authorized, and logged before access is granted.

```
                           [JUANET ZERO TRUST ACCESS PATTERN]

  [Incoming Access Request] ──► [Continuous Authentication (JWT/OIDC)]
                                                │
                                                ▼
  [Row-Level Security (RLS)] ◄── [Continuous Authorization (RBAC Scopes)]
            │
            ├─────────────────────────────────────────┐
            ▼ (Permitted)                             ▼ (Bypassed / Leaked)
  [Execute Query against Postgres]             [Request Blocked & Logged to SIEM]
```

The system enforces the following core security principles:
*   **Secure-by-Default Controls**: Enforces the most restrictive settings by default. Access to drafts, published pages, and media files is blocked unless explicitly granted.
*   **Principle of Least Privilege**: Users, worker nodes, and external integrations receive only the minimum access permissions required to complete their designated tasks.
*   **Defense in Depth**: Enforces security controls across multiple layers, including SSL/TLS network transport encryption, application-layer RBAC, Row-Level Security (RLS) at the database layer, and AES-256 encryption for data at rest.
*   **Continuous Authorization**: Authorizations are validated on every request. Client sessions are verified dynamically against active database locks and user status graphs.
*   **Separation of Duties (SoD)**: Critical business operations (such as legal compliance reviews or publishing approvals) require independent verification, preventing single-user compromise.
*   **Immutable Audit Logs**: All administrative actions, publishing decisions, and user access records are committed to append-only audit logs secured using cryptographic hash-chains.

---

## 2. SYSTEM DATA CLASSIFICATION MATRIX

To apply appropriate security controls, all CMS assets, telemetry data, and configuration logs are classified based on data sensitivity and compliance standards:

| Content / Asset Type | Data Sensitivity Level | Encryption at Rest Standards | Retention Schedule | Access Permissions (RBAC) | Audit Log Requirements |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **API Tokens & Secret Keys** | **CRITICAL** | Envelope Encryption (AES-256-GCM) | Rotated every 90 days | Security Administrators only | Log all reads, modifications, and rotation events |
| **Telemetry & SIEM Logs** | **HIGH** | Tablespace AES-256 Encryption | 90 days active, then archive | Platform Operators, Security Auditors | Log all access requests and retention purges |
| **Draft & In-Review Content** | **HIGH** | Tablespace AES-256 Encryption | Retained indefinitely during review | Content Authors, Editors, Reviewers | Log all drafts, revisions, and status updates |
| **Editorial Comments** | **MEDIUM** | Standard AES-256 Encryption | Matches parent item retention | Document Authors, Collaborators | Log all comment creations and resolutions |
| **Media Assets & DAM Files** | **MEDIUM** | S3 Server-Side Encryption (SSE-KMS) | Retained indefinitely unless purged | DAM Administrators, Content Authors | Log all asset uploads, modifications, and purges |
| **Published Pages & SEO Data** | **LOW (Public)** | Standard AES-256 Encryption | Retained indefinitely while live | Public Read, Guest, Content Authors | Log all publishing releases and CDN invalidations |

---

## 3. IDENTITY, AUTHENTICATION & SESSION CONTEXTS

The platform integrates with enterprise identity providers (IdPs) to authenticate and authorize users securely:

```
                            [IDENTITY VERIFICATION PATTERN]
  [Client Login Request] ──► [OIDC IdP (Auth, MFA Check)] ──► [Generate Cryptographic JWT]
                                                                        │
                                                                        ▼
                                                         [Validate Token Signature at Gateway]
```

*   **OAuth2 / OpenID Connect (OIDC)**: Integrates with corporate identity systems to manage user logins, supporting Multi-Factor Authentication (MFA) and single sign-on (SSO).
*   **Cryptographic Access Tokens**: Authenticated sessions generate secure JSON Web Tokens (JWT) signed using RS256 algorithms. Token claims specify the user's organization, roles, and allowed permission scopes:
```json
{
  "iss": "https://identity.juanet.platform",
  "sub": "usr_018f6001-ffff-7000-8000-000000000005",
  "org_id": "org_7e2b4011-0000-4000-a000-000000000001",
  "roles": ["Editor", "SEO_Manager"],
  "scopes": ["content:read", "content:write", "seo:publish"],
  "exp": 1782806400
}
```
*   **Service Account Authorization**: Asynchronous job workers and external integrations authenticate using cryptographically signed API keys. Keys are validated on every request, verifying IP whitelists and access scopes before granting access.

---

## 4. TENANT ISOLATION & ROW-LEVEL SECURITY (RLS)

To prevent cross-tenant data leaks in shared database environments, PostgreSQL Row-Level Security (RLS) is enabled on all tables. Database sessions query the context configuration to filter records dynamically:

```sql
-- DDL illustrating tenant-isolated Content Table with RLS
CREATE TABLE public.content_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    title VARCHAR(256) NOT NULL,
    body_content TEXT,
    publishing_status VARCHAR(32) NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Enable Row-Level Security explicitly
ALTER TABLE public.content_items ENABLE ROW LEVEL SECURITY;

-- Define tenant isolation policy matching the active session organization ID
CREATE POLICY content_tenant_isolation ON public.content_items
    FOR ALL TO authenticated
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

### 4.1 Enforcing Tenant Context in Application Gateways
Database connections retrieve the active organization ID from incoming JWT tokens, setting the session context parameter before executing queries:

```ts
// Enforcing tenant context on PostgreSQL connection sessions
import { PoolClient } from "pg";

export async function executeTenantQuery(client: PoolClient, tenantId: string, queryText: string, params: any[]) {
  // Set the session-level tenant parameter securely
  await client.query("SELECT set_config('app.current_organization_id', $1, true)", [tenantId]);
  
  // Execute the target query within the isolated tenant context
  return await client.query(queryText, params);
}
```

---

## 5. SYSTEM-WIDE ENCRYPTION STANDARDS

To protect customer data at rest and in transit, the platform enforces strict cryptographic standards across all storage subsystems:

*   **AES-256 Envelope Encryption**: Sensitive tenant configurations and access tokens are secured using envelope encryption:

$$\text{Encrypted Data} = \text{Encrypt}_{\text{AES-256-GCM}}(\text{Raw Data}, \text{Data Encryption Key})$$

$$\text{Encrypted DEK} = \text{Encrypt}_{\text{KMS-RSA}}(\text{Data Encryption Key}, \text{Tenant Master Key})$$

*   **Digital Asset Manager (DAM) File Security**: Files uploaded to S3-compatible buckets are secured using Server-Side Encryption with Customer-Provided Keys (SSE-KMS), rotating encryption keys automatically.
*   **TLS 1.3 Transport Encryption**: All external connections to API gateways are secured using TLS 1.3, disabling outdated encryption protocols (such as TLS 1.0 or TLS 1.1) to prevent transport intercept attacks.

---

## 6. IMMUTABLE SYSTEM AUDITING & GOVERNANCE

To comply with SOC 2 Type II auditing standards, the platform logs all database modifications and user access details to immutable audit tables:

```sql
-- DDL for Cryptographically Signed Immutable Audit Logs
CREATE TABLE audit.cms_audit_trail (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
    organization_id UUID NOT NULL,
    user_id UUID NOT NULL,
    action_type VARCHAR(64) NOT NULL, -- 'CONTENT_PUBLISHED', 'USER_ROLE_UPDATED'
    resource_id UUID NOT NULL,
    payload_snapshot JSONB NOT NULL,
    
    -- Hash-chain audit verification parameters
    previous_log_hash CHAR(64) NOT NULL, -- SHA-256 hash of the preceding audit record row
    row_signature CHAR(64) NOT NULL, -- SHA-256 hash of (previous_log_hash + current row values)
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_audit_integrity ON audit.cms_audit_trail (organization_id, created_at);
```

### 6.1 Cryptographic Hash-Chain Verification
The audit trail is protected against modification using a cryptographic hash-chain. Writing a new audit record calculates a SHA-256 hash combining the current record's values and the previous record's signature:

$$\text{Row Signature}_n = \text{Hash}_{\text{SHA-256}}(\text{Previous Signature}_{n-1} + \text{Payload}_n + \text{Timestamp}_n)$$

An automated background task scans the audit trail daily to verify hash signatures, alerting administrators if audit records have been modified or deleted.

---

## 7. ENTERPRISE COMPLIANCE & PRIVACY WORKFLOWS

The platform provides built-in tools and workflows to help compliance officers manage data privacy regulations (such as GDPR, CCPA, and HIPAA):

```
                            [PRIVACY REQUEST ROUTING]
  [Tenant Right-to-Erasure] ──► [Verify Identity & Holds] ──► [Anonymize Contributor Records]
                                                                        │
                                                                        ▼
                                                         [Purge Cached Files from CDN]
```

*   **Right to Access (SAR)**: Exports a tenant's data portfolio (including draft revisions, comment history, and media uploads) in structured JSON format, satisfying user access requests.
*   **Right to Erasure (Anonymization)**: Deletes personal data from tables, replacing author references with anonymized identifiers (e.g., `user_018f6001-ffff-7000-8000-000000000005` converted to `Anonymized_Contributor`), while preserving document histories.
*   **Data Residency Compliance**: Allows organizations to route and store database volumes within specific geographic regions (such as the EU or US) to comply with localized data hosting laws.
*   **Legal Compliance Holds**: Restricts content deletions during active regulatory audits or legal reviews, blocking changes to locked articles.

---

## 8. API EDGE PROTECTION & GATEWAY SECURITY

The API Gateway acts as the primary firewall, securing public REST and GraphQL endpoints against malicious requests and DDoS attacks:

*   **Cryptographic Webhook Signatures**: Outbound webhook alerts include a cryptographic signature header calculated using a shared tenant secret, allowing receivers to verify payload integrity:
```
Header: X-Juanet-Signature: sha256=a82bc194dfa1892bf3010b47...
```
*   **Replay Attack Protection**: Webhook headers include a timestamp parameter. Receivers reject requests older than 5 minutes to prevent replay attacks.
*   **GraphQL Query Hardening**: Restricts incoming queries to a maximum depth of 5 nested object levels and rejects queries exceeding a complexity score of 250 points, preventing Denial-of-Service (DoS) attacks from malicious queries.

---

## 9. DIGITAL ASSET MANAGER (DAM) FILE FIREWALL

The digital asset upload pipeline applies strict security controls to prevent malware uploads and protect CDN distribution networks:

```
                         UPLOAD SECURITY WORKSPACE
  [Asset Upload Request] ──► [Validate MIME & Magic Bytes] ──► [Run ClamAV Malware Scan]
                                                                          │
         ┌────────────────────────────────────────────────────────────────┴─────────────────────────┐
         ▼ (Clean)                                                                                  ▼ (Malicious)
  [Generate Signed CDN URLs] ──► [Deploy Asset]                                             [Quarantine & Alert SIEM]
```

*   **MIME and Magic-Byte Verification**: Uploaded files undergo binary signature checks (magic-byte validation) to confirm file types, blocking attempts to upload executable scripts disguised as images (e.g., uploading PHP scripts disguised as `.png` files).
*   **Automated Malware Scanning**: Integrates with security scanners (such as ClamAV) to scan uploads for viruses and malware before deploying assets.
*   **Temporary Signed URLs**: Private media assets and documents (such as invoices or internal designs) are delivered using time-limited signed URLs, preventing unauthorized downloads.

---

## 10. AI ENGINE & SEMANTIC SEARCH HARDENING

When using generative AI or semantic search tools, the platform enforces strict security boundaries to protect tenant data:

*   **Prompt Injection Defenses**: Cleans and validates user queries before passing inputs to AI models, preventing prompt injection attacks from overriding system instructions.
*   **Tenant-Isolated Semantic Retrieval**: Semantic search queries include the active organization ID in vector filter configurations, preventing cross-tenant vector leakage:
```sql
-- Tenant-isolated semantic vector retrieval query
SELECT document_id, cosine_similarity(embedding_vector, :query_vector) AS score
FROM public.search_embeddings
WHERE organization_id = :current_organization_id
ORDER BY score DESC
LIMIT 5;
```
*   **PII Masking**: Hashes or replaces personal identifiable information (PII) before routing prompts to external AI models to protect user privacy.

---

## 11. OPERATIONAL PERFORMANCE & SIEM MONITORING

The platform monitors security events in real time, routing threat logs to corporate Security Information and Event Management (SIEM) systems for analysis:

*   **SIEM Log Routing**: Access failures, RLS violations, and authentication errors are formatted as structured JSON logs and forwarded to SIEM platforms.
*   **Vulnerability Management**: Runs daily automated container and dependency scanners to detect outdated libraries and security vulnerabilities.
*   **Backup Encryption**: Database physical backups and WAL logs are encrypted using AES-256-GCM before being uploaded to cold storage, protecting backup files against unauthorized access.

---

## 12. SECURITY VALIDATION MATRIX

The validation matrix below serves as an engineering checklist to verify system correctness, data integrity, and compliance across modules:

| Target System Area | Quality Verification Method | Expected Operational Output | Target Validation Suite |
| :--- | :--- | :--- | :--- |
| **RLS Isolation** | Query content tables without setting tenant parameters. | Database returns an empty result set, blocking access. | Tenant Isolation Audits |
| **Maker-Checker Block** | Attempt to self-approve a self-drafted content item. | Security policies block the action, logging an audit exception. | Self-Publishing Audits |
| **Audit Hash-Chain** | Modify an audit trail record directly in the table. | Hash verification scans detect the modification and dispatch alarms. | Log Integrity Suite |
| **Malware Prevention** | Attempt to upload malicious binaries to the DAM. | Scanner blocks the upload, moves file to quarantine, and logs alerts. | Malware Prevention Suite|
| **Signature Checks** | Intercept webhook and attempt to edit payload values. | Signature verification fails on the receiver, blocking the request. | Signature Integrity Audits |
| **Vector Security** | Query vector similarity indexes across tenants. | Search engine restricts queries to the active tenant's vector space. | Vector Isolation Audits |
| **Data Anonymization** | Execute GDPR right-to-erasure workflows. | Personal data is permanently deleted or hashed, preserving history. | GDPR Erasure Workflows |

---

## 13. CROSS REFERENCES & GOVERNANCE DOCUMENT MAP

This manual builds upon previous database design specifications. Refer to the manuals below for additional information:
*   **JUANET CMS Physical Tables (`Phase_2_3_2G_CMS_Physical_Tables.md`)**: Defines physical table schemas, transactional UUIDv7 columns, database constraints, and RLS rules.
*   **CMS Modeling & Publishing Engine (`Phase_2_3_2G_1_Content_Modeling_and_Publishing_Engine.md`)**: Governs core content lifecycle state machines, content structures, and database publishing workflows.
*   **Media & DAM Specification (`Phase_2_3_2G_2_Media_and_Digital_Asset_Management.md`)**: Manages S3-compatible object storage pointers, asset transformations, and media usage tracking.
*   **Localization & Multi-Language (`Phase_2_3_2G_3_Localization_and_Multilanguage_Content.md`)**: Coordinates localized content paths, language translation states, and fallback routing tables.
*   **Search & Content Discovery (`Phase_2_3_2G_4_Search_and_Content_Discovery_Engine.md`)**: Governs read-model search documents, trigram fuzzy indexing, and vector similarity search.
*   **Content Delivery & API (`Phase_2_3_2G_5_Content_Delivery_and_Headless_API.md`)**: Manages CDN delivery networks, edge caches, and headless GraphQL query interfaces.
*   **Workflow & Collaboration (`Phase_2_3_2G_6_Workflow_Editorial_Collaboration_and_Content_Governance.md`)**: Coordinates collaborative pipelines, role assignments, parallel approvals, and compliance logs.
*   **SEO & Site Management (`Phase_2_3_2G_7_SEO_Site_Management_and_Web_Experience.md`)**: Governs site directories, custom domain verifications, redirects, sitemaps, and robots configurations.
*   **CMS Integration & Events (`Phase_2_3_2G_8_CMS_Integration_and_Event_Contracts.md`)**: Governs event-driven decoupling, transactional outbox schemas, and canonical event payloads.
*   **Dashboards & Telemetry (`Phase_2_3_2G_9_CMS_Dashboards_Analytics_and_Operational_Telemetry.md`)**: Governs materialized OLAP aggregations, system telemetry, and operational dashboards.
*   **Performance & Scalability (`Phase_2_3_2G_10_CMS_Performance_and_Scalability.md`)**: Governs database partitioning, indexing configurations, and multi-tier caching structures.

---

*Authorized by the JUANET Security Governance Board & Global Compliance Council.*
