# JUANET Support Security, Privacy & Compliance Specification
## Phase 2.3.2F.10 — Support Security and Compliance
**Document Version:** 1.0  
**Author:** Chief Security & Compliance Officer, JUANET Platform  
**Classification:** Technical / Security & Compliance Constitution  

---

## 1. SECURITY PHILOSOPHY

The JUANET Support Security & Compliance Constitution defines the foundational architectural rules, encryption standards, isolation engines, and access policies that govern the Support domain.

Due to the nature of enterprise workflows, support tickets, conversation streams, and customer interactions contain highly sensitive data—including PII, corporate IP, billing secrets, and internal system notes. The platform enforces a robust **Secure-by-Design and Privacy-by-Design** engineering paradigm to protect data across multi-tenant environments.

```
       UNTRUSTED EDGE / API CLIENT
                   │
                   ▼
+---------------------------------------+
|        DEFENSE-IN-DEPTH GATES         |
|  - TLS 1.3 Termination                |
|  - JWT Signature & Expiration Checks  |
|  - Rate-Limiting & WAF Inspection     |
+---------------------------------------+
                   │
                   ▼
+---------------------------------------+
|     APPLICATION LAYER (RBAC)          |
|  - Enforce Permission Scopes          |
|  - Run Masking / PII Sanitizers       |
|  - Advisory-Only AI Validation Gates  |
+---------------------------------------+
                   │
                   ▼
+---------------------------------------+
|      DATABASE LAYER (RLS & KMS)       |
|  - Session Context Injection          |
|  - Strict Tenant RLS Checks           |
|  - AES-256 Storage Encryption (TDE)   |
+---------------------------------------+
```

### 1.1 Foundation Core Pillars
*   **Zero Trust Architecture**: The platform treats every interface, network segment, and process as untrusted. No connection—including internal calls between microservices—is authorized without explicit cryptographic verification, trace logs, and active token evaluations.
*   **Least Privilege Access Control**: Users and system service accounts are granted only the minimum permissions required to perform their tasks. Privileges are strictly scoped, role-based, and continuously audited.
*   **Defense-in-Depth**: Security controls are applied at multiple layers (network, gateway, application, database, and storage), ensuring that the failure of any single control does not compromise the security of the entire platform.
*   **Secure-by-Default**: Every feature is provisioned with its most restrictive security settings active. Users must explicitly enable looser security controls or permissions through structured, authorized approval flows.
*   **Advisory AI Guardrails**: AI engines operate strictly as advisor-only systems. They are prevented from mutating database records, executing financial actions, or dispatching customer messages without explicit human verification and authorization.

---

## 2. DATA CLASSIFICATION SCHEMA

To apply appropriate encryption, retention, and access policies, all data within the Support domain is classified into distinct categories:

| Data Category | Sensitivity Level | Encryption Type | Retention Period | RBAC Access Level | Export Controls | Deletion / GDPR Action |
| :--- | :---: | :--- | :--- | :--- | :--- | :--- |
| **Tickets** | Medium | Storage TDE (AES-256) | 7 Years | Agent / Supervisor / Manager | Admin Approval Only | Obfuscate PII fields |
| **Messages** | High | Field-level Envelope | 7 Years | Agent / Supervisor / Manager | Admin Approval Only | Redact body text |
| **Attachments** | High | S3 KMS Customer Managed| 7 Years | Assigned Agent / Supervisor | Admin Approval Only | Hard delete physical file |
| **Internal Notes** | High | Storage TDE (AES-256) | 7 Years | Internal Employees Only | Strictly Prohibited | Keep intact (No customer PII) |
| **AI Suggestions** | Low | Storage TDE (AES-256) | 90 Days | Agent / Supervisor / Manager | Allowed | Auto-purge expired logs |
| **CSAT / Surveys** | Medium | Storage TDE (AES-256) | 3 Years | Supervisor / Manager | Allowed | Anonymize respondent metadata |
| **Audit Logs** | Extreme | AES-256-GCM Hash Chained| 7 Years (Immutable) | Compliance Officer / Auditor | Strictly Prohibited | Retain under legal exception |
| **Telemetry** | Low | Storage TDE (AES-256) | 30 Days | DevOps / SRE | Allowed | Auto-purge expired logs |
| **PII Data** | High | Field-level Envelope | 7 Years | Masked for standard roles | Strictly Prohibited | Obfuscate / Anonymize |

---

## 3. ROW-LEVEL SECURITY (RLS) POLICIES

To prevent cross-tenant data leakage, the database enforces **Row-Level Security (RLS)** at the PostgreSQL engine layer. This ensures that queries executed by an agent or automated worker can never access or modify data belonging to another tenant organization.

```
       INCOMING DATABASE QUERY
                  │
                  ▼
+---------------------------------------+
|     EXTRACT JWT ORGANIZATION CLAIM    |
|   `current_setting('app.tenant_id')`  |
+---------------------------------------+
                  │
                  ▼
+---------------------------------------+
|      ENFORCE RLS DATABASE POLICY      |
|  - Compare row tenant ID with claim   |
|  - Filter out unauthorized records     |
+---------------------------------------+
                  │
                  ▼
       AUTHORIZED QUERY RESPONSE
```

### 3.1 Session Parameter Context Injection
Before executing any transaction, the application gateway must inject the active JWT organization claim into the PostgreSQL transaction-local session state:

```sql
-- Executed inside the connection pool context immediately before running queries
SET LOCAL app.current_tenant_id = 'org_9831a238-bfbc-4122-a9b3-1f19f2a00d41';
SET LOCAL app.current_user_role = 'support_agent';
```

### 3.2 High-Performance RLS Policies

```sql
-- Enable Row-Level Security on the core tickets table
ALTER TABLE public.tickets ENABLE ROW LEVEL SECURITY;

-- Create policy isolating access to matching organization IDs
CREATE POLICY tickets_tenant_isolation_policy ON public.tickets
    FOR ALL
    USING (
        organization_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid
    );

-- Create policy for internal employee visibility on private logs
ALTER TABLE public.ticket_activity_logs ENABLE ROW LEVEL SECURITY;

CREATE POLICY activity_logs_security_policy ON public.ticket_activity_logs
    FOR ALL
    USING (
        organization_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid
        AND (
            current_setting('app.current_user_role', true) IN ('support_supervisor', 'support_manager', 'compliance_auditor')
        )
    );
```

### 3.3 Background Worker & Replication RLS Bypasses
Certain automated workers (such as database backup processes or analytical metric aggregation pipelines) must be explicitly allowed to bypass RLS policies to perform their administrative tasks:

```sql
-- Grant RLS bypass capability strictly to verified system replication roles
ALTER ROLE support_replication_worker BYPASSRLS;
ALTER ROLE support_backup_worker BYPASSRLS;
```

---

## 4. ROLE-BASED ACCESS CONTROL (RBAC) SCHEMA

The system maps user capabilities to structured, hierarchical roles. This ensures that sensitive operations—such as approving SLA overrides, viewing QA reviews, or deleting files—are restricted to authorized personnel.

```
                  +-----------------------------+
                  |    compliance_auditor       |
                  +-----------------------------+
                                 │
                                 ▼
                  +-----------------------------+
                  |       support_manager       |
                  +-----------------------------+
                                 │
                                 ▼
                  +-----------------------------+
                  |     support_supervisor      |
                  +-----------------------------+
                                 │
         ┌───────────────────────┴───────────────────────┐
         ▼                                               ▼
+--------------------+                         +--------------------+
|   support_agent    |                         |  knowledge_editor  |
+--------------------+                         +--------------------+
```

### 4.1 Enterprise Role Hierarchies and Permissions

#### 4.1.1 `support_agent`
*   **Permitted Actions**: View assigned tickets, respond to customer messages, create internal ticket notes, and view public knowledge articles.
*   **Restrictions**: Strictly forbidden from editing SLA targets, viewing peer QA scorecards, deleting files, or exporting reports.

#### 4.1.2 `knowledge_editor`
*   **Permitted Actions**: Create, update, translate, and archive knowledge base articles.
*   **Restrictions**: Cannot publish draft articles to the public workspace without approval from a supervisor or manager.

#### 4.1.3 `support_supervisor`
*   **Permitted Actions**: Assign tickets, view all active tenant queues, evaluate ticket quality, write QA scorecards, and assign coaching sessions to agents.
*   **Restrictions**: Cannot alter tenant-wide configurations, modify security policies, or export financial data.

#### 4.1.4 `support_manager`
*   **Permitted Actions**: Override SLA compliance flags, approve draft knowledge articles for public release, export team performance reports, and manage queue allocations.
*   **Restrictions**: Cannot modify global tenant billing details or override RLS access rules.

#### 4.1.5 `compliance_auditor`
*   **Permitted Actions**: Read-only access across all transaction logs, security audit tables, and system interaction histories.
*   **Restrictions**: Strictly blocked from making modifications to active tickets, changing passwords, or altering operational logs.

---

## 5. SENSITIVE DATA ENCRYPTION & SANITIZATION

To protect sensitive data (such as emails, payment card references, and passwords), the Support platform implements standard cryptographic controls.

```
                        INBOUND PAYLOAD
                               │
                               ▼
+-------------------------------------------------------------+
|                     PII SANITIZER ENGINE                    |
|  - Identifies credit card numbers, passwords, and SSNs      |
|  - Replaces sensitive content with obfuscated placeholders  |
+-------------------------------------------------------------+
                               │
                               ▼
+-------------------------------------------------------------+
|                 ENVELOPE ENCRYPTION PROCESS                 |
|  - Generates unique Data Encryption Keys (DEKs) per tenant  |
|  - Encrypts keys using Cloud Key Management Services (KMS)  |
+-------------------------------------------------------------+
                               │
                               ▼
                   ENCRYPTED DATABASE COMMITS
```

### 5.1 Storage & Field-Level Encryption Standards
*   **Transparent Data Encryption (TDE)**: All database disks and database backups are encrypted at-rest using **AES-256** encryption standards.
*   **Field-Level Envelope Encryption**: High-sensitivity columns (such as contact phone numbers and email bodies) are encrypted at the application layer before being written to disk:
    *   *Mechanism*: A unique Data Encryption Key (DEK) is generated for each tenant.
    *   *KMS Integration*: DEKs are encrypted using an external Key Management Service (KMS) master key, protecting encryption keys from database-level access.
*   **Transport Encryption**: All transit routes enforce **TLS 1.3** connections, blocking weak cipher suites.

### 5.2 Cryptographic Standard Specifications
*   **Symmetric Encryption Cipher**: `AES-256-GCM` (authenticated encryption ensuring confidentiality and integrity).
*   **Key Derivation Function**: `PBKDF2` with SHA-256, utilizing unique salts of at least 128 bits.

---

## 6. IMMUTABLE SYSTEM AUDITING

To maintain SOC2 and ISO 27001 readiness, the database records system modifications inside an append-only audit trail table.

### 6.1 Audit Ledger Table Schema

```sql
CREATE TABLE audit.system_audit_logs (
    id uuid NOT NULL DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL,
    actor_id uuid NOT NULL,
    trace_id uuid NOT NULL,
    action_type varchar(50) NOT NULL,
    target_table varchar(100) NOT NULL,
    record_id uuid NOT NULL,
    original_state jsonb DEFAULT '{}'::jsonb NOT NULL,
    mutated_state jsonb DEFAULT '{}'::jsonb NOT NULL,
    client_ip_address inet NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    payload_sha256 char(64) NOT NULL,
    PRIMARY KEY (organization_id, id, created_at)
) PARTITION BY RANGE (created_at);
```

### 6.2 Forensic Tamper Detection Mechanics
To prevent internal administrators from altering log histories, audit logs generate a cumulative cryptographic hash chain:

$$\text{Log Hash}_n = \text{SHA256}(\text{Log Payload}_n \,\|\, \text{Log Hash}_{n-1})$$

A background job runs hourly to re-verify the hash chain across all log entries, flagging any discrepancies or missing sequences immediately.

---

## 7. GDPR "RIGHT TO BE FORGOTTEN" WORKFLOWS

Under GDPR, CCPA, and regional privacy rules, tenants must be able to delete customer personal data without compromising transactional database integrity.

```
       GDPR PURGE REQUEST TRIGGERED
                     │
                     ▼
+-----------------------------------------+
|          IDENTIFY TARGET USER           |
|  Query user identifiers across tables   |
+-----------------------------------------+
                     │
                     ▼
+-----------------------------------------+
|          OBFUSCATE PROFILE DATA         |
|  Replace names, emails, and IPs with   |
|  randomly generated obfuscated string   |
+-----------------------------------------+
                     │
                     ▼
+-----------------------------------------+
|          REDACT MESSAGE BODIES          |
|  Replace text comments with generic     |
|  "[REDACTED_BY_GDPR]" placeholder       |
+-----------------------------------------+
                     │
                     ▼
+-----------------------------------------+
|          DELETE ATTACHED FILES          |
|  Purge physical binary files from S3    |
+-----------------------------------------+
```

### 7.1 Secure Redaction Standards
*   **Metadata Preservations**: To maintain accurate operational dashboards (such as ticket count metrics, response speed, and SLA performance indicators), the parent ticket shell remains in the database.
*   **Scrubbing Targets**:
    *   *Profiles*: Customer names, emails, and IP addresses are replaced with random obfuscated strings (e.g., `user_redacted_5510@juanet.com`).
    *   *Conversations*: Message bodies are replaced with `[REDACTED_BY_GDPR_REQUEST]`.
    *   *Storage*: Associated files and attachments are permanently deleted from secure object storage buckets, and corresponding metadata references are updated to a redacted status.

---

## 8. COMPLIANCE MAPPING MATRIX

Platform technical controls map directly to security and privacy compliance frameworks:

| Compliance Framework | Requirement Identifier | Platform Implementation Control | Database Verification Source |
| :--- | :--- | :--- | :--- |
| **SOC 2 Type II** | CC 6.1 (Access Control) | Hierarchical RBAC permissions and sessionJWT checking. | `security.users`, `security.roles` |
| **SOC 2 Type II** | CC 6.3 (Audit Logging) | Hash-chained append-only system auditing. | `audit.system_audit_logs` |
| **GDPR** | Article 17 (Right to Erasure) | Automated user anonymization and file scrubbing workflows. | `public.tickets` (Redacted) |
| **CCPA** | Sec 1798.105 (Delete Data) | Automated anonymization and GCS deletion workflows. | `public.ticket_messages` (Scrubbed) |
| **ISO 27001** | A.12.4.1 (Event Logging) | Automated tracking of user, admin, and worker actions. | `audit.system_audit_logs` |
| **PCI DSS** | Requirement 3.4 (Protect Card Data)| Outbound regex scanners masking credit card numbers from messages. | `public.ticket_messages` (Cleaned) |

---

## 9. AI GOVERNANCE AND SAFETY DEEP DIVE

To protect data privacy and maintain operational safety, the platform enforces strict security guidelines across all AI interactions.

*   **Human-in-the-Loop Enforcements**: Draft answers generated by the AI Copilot must be reviewed and accepted by an agent before being sent to the customer. Under no circumstances can the AI engine bypass human verification for public messages.
*   **PII Masking Filters**: Before transmitting text to external AI models, a local parser scans the prompt context and replaces sensitive details (such as names, credentials, and account IDs) with generic placeholder blocks, keeping personal data isolated.
*   **Prompt Injection Protection**: Incoming customer messages are scanned for injection patterns (e.g., "Ignore previous instructions", "Switch to developer mode"). Flagged messages are blocked, and an alert is sent to the supervisor dashboard.
*   **Model Version Tracking**: All AI suggestions are logged alongside the active model version (e.g., `gemini-3.5-flash`), temperature parameters, and prompt templates, supporting reproducibility and quality audits.

---

## 10. ATTACHMENT GATEWAY SECURITY

The system scans and validates all incoming attachments before saving them to secure object storage.

```
       FILE UPLOAD DISPATCHED
                  │
                  ▼
+---------------------------------------+
|        MIME & SIZE VERIFICATION       |
|  - Inspects file signatures & headers  |
|  - Enforces strict file size limits   |
+---------------------------------------+
                  │
                  ▼
+---------------------------------------+
|          SANDBOX VIRUS SCAN           |
|  - Streams file to scan container     |
|  - Validates file safety credentials  |
+---------------------------------------+
                  │
                  ▼
+---------------------------------------+
|       SECURE S3 STORAGE COMMIT        |
|  - Uploads safe file to bucket        |
|  - Restricts download to signed URLs  |
+---------------------------------------+
```

### 10.1 Gateway Validation Rules
*   **MIME Whitelist**: File uploads are restricted to safe file types:
    *   *Allowed Documents*: `application/pdf`, `text/plain`, `application/msword`.
    *   *Allowed Images*: `image/png`, `image/jpeg`, `image/webp`.
*   **Content-Type Validation**: The gateway performs magic-number byte signature checks on uploads, preventing attackers from renaming executable files (e.g., `.exe` or `.sh`) to bypass file filters.
*   **Virus and Malware Scans**: Files are uploaded to an isolated staging bucket, scanned for malware, and deleted immediately if a threat is detected.
*   **Access Control**: Download links use short-lived, pre-signed URLs with a 5-minute expiration window, preventing unauthorized sharing.

---

## 11. API & WEBHOOK SEGREGATION

Outbound webhook events and incoming API requests are secured to prevent unauthorized access and protect system resources:

*   **HMAC-SHA256 Webhook Verification**: Outbound webhooks include signature headers computed with tenant-specific secret keys, allowing receivers to verify payload integrity and authenticity.
*   **Replay Attack Defenses**: Outbound signatures include delivery timestamps. Receiver systems must reject webhooks arriving outside a 5-minute delivery window to prevent replay attempts.
*   **Token-Bucket Rate Limiting**: Token-bucket limiters are enforced on API endpoints, preventing system abuse and ensuring stable performance.
*   **JWT Scope Verification**: JWT authentication tokens contain specific permission scopes (e.g., `tickets:write`, `knowledge:read`). Requests lacking the required scope are rejected at the API gateway layer.

---

## 12. OPERATIONAL COMPLIANCE

Standard workflows require secondary approvals for high-risk operations to protect tenant configurations and system stability.

*   **SLA Configuration Changes**: Modifying tenant-wide SLA policies requires two-person verification (Maker-Checker approval). A supervisor must propose the change, and a designated support manager must authorize it before it is activated.
*   **Break-Glass Administrative Access**: Emergency access protocols allow administrators to bypass standard restrictions during system outages.
    *   *Activation*: Triggers an immediate critical alert on the compliance dashboard.
    *   *Duration*: Emergency privileges expire automatically after 2 hours, and all actions taken during the session are compiled into a high-priority audit report.

---

## 13. SECURITY MONITORING AND INCIDENT ALERTS

An automated Monitoring Engine continuously monitors system logs to detect potential threats and flag abnormal behaviors:

*   **RLS Access Violations**: Flags instances where a user attempts to execute queries containing mismatched tenant IDs, immediately blocking the connection.
*   **Abnormal Data Exports**: Monitors report exports. If an agent attempts to download more than 50 tickets or files within a 1-minute window, the system suspends export privileges and alerts the supervisor.
*   **AI Abuse Detection**: Logs instances where prompts trigger injection blocks or fail content safety checks, suspending access for accounts with repeated violations.

---

## 14. DATA RETENTION AND ARCHIVAL

To comply with global data regulations while managing storage footprints, the platform applies a structured data retention schedule:

*   **Operational Tickets**: Retained for 7 years post-resolution to support customer audits and performance reporting.
*   **Customer Messages**: Retained for 7 years in active storage, then archived to secure cold storage.
*   **AI Interaction Logs**: Automated pruning tasks purge AI suggestions and interaction logs after 90 days, reducing storage bloat.
*   **Telemetry Logs**: SRE logs and connection diagnostics are purged after 30 days.

---

## 15. INCIDENT RESPONSE WORKFLOWS

When a potential security incident or data leak is detected, the platform triggers an automated response plan:

```
[Incident Flagged] ──> [Isolate Tenant Session] ──> [Preserve System Evidence] ──> [Notify Teams]
```

1.  **Immediate Containment**:
    *   Suspends affected user sessions and blocks compromised tokens.
    *   Isolates affected tenant instances from active API traffic, preventing further data exposure.
2.  **Evidence Preservation**:
    *   Disables autovacuum tasks and table purging on affected partition tables, preserving active data state.
    *   Compiles transaction histories, audit logs, and IP access records into a secure evidence package.
3.  **Analysis and Investigation**: Compliance teams review the evidence package to evaluate incident impact, determine the root cause, and compile a security report.
4.  **Notification Dispatches**: If a data breach is confirmed, the platform dispatches notifications to affected tenant organizations within the regulatory 72-hour window.

---

## 16. VALIDATION CHECKLIST

Before deploying security updates or granting API access, compliance teams must verify the following checklist:

| Area | Verification Scenario / Validation Objective | Expected Result | Checked |
| :---: | :--- | :--- | :---: |
| **RLS** | Querying ticket records without active tenant context. | Database-level blocks trigger, and query returns 0 rows. | [ ] |
| **RBAC** | Agent attempting to delete a customer file. | Security engine blocks the request and logs a permission violation. | [ ] |
| **GDPR** | Customer submits valid Right to be Forgotten request. | Profiles are anonymized, and associated files are permanently purged. | [ ] |
| **Audit** | Administrator attempts to update system audit records. | SQL write fails on the append-only table, and an alert is sent. | [ ] |
| **Uploads** | User uploads executable file disguised as a PDF. | Byte signature scanner detects mismatch, rejecting the file. | [ ] |
| **Webhooks**| Webhook dispatch arrives without HMAC signature header. | Receiver rejects payload due to missing verification credentials. | [ ] |

---

## 17. CROSS REFERENCES

This security manual defines the compliance rules, access controls, and encryption standards applied across the physical and logical architectures structured in the following support specifications:

*   **Support Physical Tables**: References core schemas (`public.tickets`, `public.ticket_messages`) documented in `Phase_2_3_2F_Support_Physical_Tables.md`.
*   **Ticket Lifecycle**: Governs transition operations and state parameters defined in `Phase_2_3_2F_1_Ticket_Lifecycle_Engine.md`.
*   **SLA Engine**: Applies compliance checks to SLA configurations detailed in `Phase_2_3_2F_2_SLA_and_Escalation_Engine.md`.
*   **Knowledge Base**: Secures authoring and approval flows mapped out in `Phase_2_3_2F_3_Knowledge_Base_Architecture.md`.
*   **Omnichannel Communication**: Integrates WebSocket security standards specified in `Phase_2_3_2F_4_Omnichannel_Communication_Engine.md`.
*   **Customer Satisfaction & QA**: Limits visibility on QA scorecards and calibration files governed by `Phase_2_3_2F_5_Customer_Satisfaction_and_Quality_Assurance.md`.
*   **AI Copilot**: Enforces safety and RLS boundaries on prompt retrievals detailed in `Phase_2_3_2F_6_AI_Copilot_and_Intelligent_Support_Assistance.md`.
*   **Event Contracts**: Outlines payload security and HMAC signature contracts documented in `Phase_2_3_2F_7_Support_Integration_and_Event_Contracts.md`.
*   **Dashboards & Telemetry**: Secures metric aggregations and report exports defined in `Phase_2_3_2F_8_Support_Dashboards_and_Operational_Telemetry.md`.
*   **Performance & Scalability**: Coordinates partition optimizations and RLS planning rules detailed in `Phase_2_3_2F_9_Support_Performance_and_Scalability.md`.

---

This document serves as the architectural reference for implementing security controls, database access policies, and data privacy compliance workflows within the JUANET Support Platform. All configurations must adhere strictly to these specifications.
