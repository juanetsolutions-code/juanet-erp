# JUANET PostgreSQL Security & Authentication Table Specifications
## Phase 2.3.2B — Authentication, Identity, Session Management, and RBAC Physical Tables
**Document Version:** 1.0  
**Author:** Chief PostgreSQL Security Architect, JUANET Platform  
**Classification:** Technical / Security Architecture Reference / Database Schema  

---

## 1. DOCUMENT ARCHITECTURE & COMPLIANCE

This document establishes the canonical physical table definitions for the Authentication, Identity, Session Management, and Role-Based Access Control (RBAC) schemas of the JUANET Enterprise SaaS Platform. All designs specified herein must be implemented exactly by future migrations and ORM entities to ensure compliance with **SOC 2 Type II**, **ISO 27001**, **GDPR**, **PCI-DSS**, and **OWASP ASVS Level 2** standards.

These tables conform strictly to:
*   `JUANET_Master_Specification.md` (v1.3)
*   `Phase_2_Enterprise_Database_Blueprint.md`
*   `Phase_2_2_Enterprise_Entity_Dictionary.md`
*   `Phase_2_3_1_PostgreSQL_Physical_Standards.md`
*   `Phase_2_3_2A_Core_Physical_Tables.md`

---

## 2. COMPREHENSIVE SCHEMAS DEFINED

To support highest-grade security and prevent relational pollution, objects are organized into these database namespaces:
1.  **`security`**: Stores user credentials, MFA configurations, sessions, and lookup maps. Bypassed only by authorized security boundary services.
2.  **`public`**: Contains user-accessible profiles, RBAC mappings, organizational memberships, and notification configurations. Row-Level Security (RLS) is active across this schema.
3.  **`audit`**: Stores immutable security event ledgers and brute-force tracking metrics.

---

## 3. DOMAIN 1 — IDENTITY & USER MANAGEMENT

### 3.1 Table Name: `security.users`

#### 3.1.1 Overview
*   **Purpose**: Stores the root authentication credentials, account lock states, and verified indicators for every identity. Houses password hashes encrypted with Argon2id parameters.
*   **Ownership Domain**: Identity Core
*   **Dependencies**: `system.organization_statuses`
*   **Expected Lifetime**: Persistent (Lifetime of the identity)
*   **Expected Growth Rate**: Linear (Linked to user onboarding)
*   **Expected Read / Write Frequency**: 95% Reads / 5% Writes

#### 3.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Uniquely identifies the user across the entire SaaS cluster. |
| `email` | `citext` | NO | None | Unique Index | None | Restricted-Identity | Primary identifier and communication target. Case-insensitive via `citext`. |
| `password_hash` | `varchar(255)` | YES | `NULL` | None | None | Restricted-Secret | Encoded password hash. Nullable to support passwordless/OAuth-only authentication flows. |
| `status_id` | `uuid` | NO | None | FK Reference | None | Public | References `system.organization_statuses` to manage global user states. |
| `is_verified` | `boolean` | NO | `false` | None | None | Public | Flag confirming that the user has verified ownership of their email address. |
| `lockout_until` | `timestamptz` | YES | `NULL` | None | None | Restricted-Identity | Temporal threshold after which an account lockout expires. |
| `failed_login_attempts` | `integer` | NO | `0` | None | None | Restricted-Identity | Consecutive authentication failures counter. Triggers lockout at threshold. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Standard UTC creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Dynamic modification audit timestamp. Managed by trigger. |
| `deleted_at` | `timestamptz` | YES | `NULL` | Partial Index | None | Restricted-Identity | Logical soft delete threshold. |
| `version` | `integer` | NO | `1` | None | None | Public | Optimistic concurrency locking sequence counter. |

#### 3.1.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `status_id REFERENCES system.organization_statuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT users_email_key UNIQUE (email)`
*   **Check Constraints**:
    *   `CONSTRAINT users_email_format CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')`
    *   `CONSTRAINT users_failed_attempts_non_negative CHECK (failed_login_attempts >= 0)`

#### 3.1.4 Index Strategy
*   **`users_pkey`**: Primary Key Index (Implicit B-Tree) on `id`.
*   **`users_email_uidx`**: Unique Index (B-Tree) on `email`. Crucial for high-velocity user identity verification queries during login. Selectivity is high (100%).
*   **`users_active_partial_idx`**: Partial B-Tree Index on `(id)` WHERE `deleted_at IS NULL`. Restricts lookup engines to non-deleted users.

#### 3.1.5 Row-Level Security (RLS) Policy
*   **Read**: Users can select only their own records.
*   **Insert**: Publicly writable for signup processes, but constrained via system validations.
*   **Update**: Restricted to the user themselves (only for email or settings updates; status shifts must be authorized by admins).
*   **Delete**: Soft delete only, executed by system administrators or the user themselves during account deletion.
*   **Service Account Bypass**: Enabled for the security subsystem.

#### 3.1.6 Audit Requirements
*   Any changes to `email`, `password_hash`, or `status_id` must immediately write a high-severity audit record to `audit.security_events`.

#### 3.1.7 Authentication Security Implementation Details
*   **Argon2id Requirements**: Password hashing must utilize Argon2id with parameters: $m=65536$ (64MB RAM), $t=3$ iterations, and $p=4$ parallelism lanes.
*   **Account Lockout**: Triggers automatically on the 5th consecutive login failure. Locks the account for exactly 15 minutes.
*   **Email Verification**: Mandated prior to completing first-time login to prevent account squatting.

#### 3.1.8 Optimistic Locking
*   Any write operation checks the current `version` integer, failing if it does not match the active row, and increments it by 1 on success.

#### 3.1.9 Event Matrix
*   **Events Produced**: `user.created`, `user.verified`, `user.locked_out`, `user.soft_deleted`
*   **Events Consumed**: None

#### 3.1.10 Validation Rules
*   **Email**: Length <= 255. Standard syntax regex.
*   **Password**: Must contain at least 12 characters, including 1 uppercase, 1 lowercase, 1 digit, and 1 special symbol.

#### 3.1.11 Performance Considerations
*   **Expected Scale**: 10,000,000+ records.
*   **Caching**: Password verification lookups must be cached with an active TTL of 5 minutes or invalidated immediately upon password change events.

#### 3.1.12 Relationships
*   **One-to-One**: `public.profiles`
*   **One-to-Many**: `public.organization_members`, `security.sessions`, `security.password_history`

---

### 3.2 Table Name: `public.profiles`

#### 3.2.1 Overview
*   **Purpose**: Stores public-facing metadata, display names, telephone numbers, and avatars corresponding to a user account. Kept logically distinct from the core security identity database to protect privacy.
*   **Ownership Domain**: User Directory Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Persistent (Matches user account lifecycle)
*   **Expected Growth Rate**: 1:1 with `security.users`
*   **Expected Read / Write Frequency**: 90% Reads / 10% Writes

#### 3.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `user_id` | `uuid` | NO | None | PK & FK | None | Restricted-Identity | One-to-one identifier matching the core user identity. |
| `first_name` | `varchar(50)` | NO | None | None | None | Restricted-Identity | Standard display name field. |
| `last_name` | `varchar(50)` | NO | None | None | None | Restricted-Identity | Standard display name field. |
| `phone_number` | `varchar(20)` | YES | `NULL` | B-Tree Index | None | Restricted-Identity | Optional international format telephone number. |
| `avatar_url` | `text` | YES | `NULL` | None | None | Public | Link to user profile photo stored in secure storage. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Standard update tracking timestamp. |
| `version` | `integer` | NO | `1` | None | None | Public | Optimistic concurrency controller. |

#### 3.2.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (user_id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT profiles_first_name_length CHECK (length(trim(first_name)) >= 1)`
    *   `CONSTRAINT profiles_last_name_length CHECK (length(trim(last_name)) >= 1)`
    *   `CONSTRAINT profiles_phone_format CHECK (phone_number IS NULL OR phone_number ~ '^\+[1-9]\d{1,14}$')`

#### 3.2.4 Index Strategy
*   **`profiles_pkey`**: Primary Key index.
*   **`profiles_phone_idx`**: B-Tree Index on `phone_number` WHERE `phone_number IS NOT NULL` to support contact-based directory searches.

#### 3.2.5 Row-Level Security (RLS) Policy
*   **Read**: Users belonging to the same organization as this user can view the profile.
*   **Insert / Update**: Restricted strictly to the owner user matching `user_id`.
*   **Delete**: Managed via CASCADE upon parent `users` record removal.

#### 3.2.6 Operational Performance
*   **Expected Row Count**: 1:1 match with `security.users`.
*   **Caching**: Highly requested profiles (such as organization admins) are cached locally within the application layer.

---

### 3.3 Table Name: `public.organization_members`

#### 3.3.1 Overview
*   **Purpose**: Coordinates the physical association mapping between users and SaaS tenant organizations. Enables multi-tenant isolation and user context switching.
*   **Ownership Domain**: Membership and Access Core
*   **Dependencies**: `system.organizations`, `security.users`
*   **Expected Lifetime**: Matches active employment/engagement window.
*   **Expected Growth Rate**: $O(N)$ based on organizations and members.
*   **Expected Read / Write Frequency**: 98% Reads / 2% Writes

#### 3.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique membership ID. |
| `organization_id` | `uuid` | NO | None | FK & Composite PK | None | Public | Associated tenant organization context. |
| `user_id` | `uuid` | NO | None | FK & Composite PK | None | Restricted-Identity | Associated user identity. |
| `is_active` | `boolean` | NO | `true` | B-Tree Index | None | Public | Status flag to temporarily suspend memberships without deleting records. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Membership establishment date. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Modification audit tracker. |
| `version` | `integer` | NO | `1` | None | None | Public | Optimistic locking manager. |

#### 3.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT org_members_composite_key UNIQUE (organization_id, user_id)`

#### 3.3.4 Index Strategy
*   **`organization_members_pkey`**: Primary Key index.
*   **`org_members_composite_uidx`**: Unique Index (B-Tree) on `(organization_id, user_id)`. Used constantly during request lifecycle routing to verify tenant access.
*   **`org_members_user_idx`**: B-Tree Index on `(user_id)`. Essential for resolving all organizations a user belongs to during authentication or context switching.

#### 3.3.5 Row-Level Security (RLS) Policy
*   **Read**: Users can select records where they are the member, or if they are an administrator within the same organization.
*   **Insert**: Organization administrators can create new memberships.
*   **Update / Delete**: Restricted strictly to organization administrators of the target tenant.

#### 3.3.6 Audit Requirements
*   Any insertion or deletion of organization memberships must emit a security audit log to track user provisioning and access changes.

#### 3.3.7 Event Matrix
*   **Events Produced**: `membership.created`, `membership.suspended`, `membership.revoked`
*   **Events Consumed**: None

---

## 4. DOMAIN 2 — ROLE-BASED ACCESS CONTROL (RBAC)

To avoid hardcoded privileges and provide clean compliance mapping (SOC 2 Access Control), RBAC utilizes normalized table layers mapped directly to active organization namespaces.

```
                  ┌───────────────────────┐
                  │  security.permissions │
                  └───────────┬───────────┘
                              │ 1:N
                  ┌───────────▼───────────┐
                  │ public.role_permissions│
                  └───────────▲───────────┘
                              │ 1:N
┌──────────────┐  ┌───────────┴───────────┐  ┌──────────────────┐
│system.orgs   ├──►     public.roles      ├─►│public.user_roles │
└──────────────┘  └───────────────────────┘  └──────────────────┘
```

### 4.1 Table Name: `public.roles`

#### 4.1.1 Overview
*   **Purpose**: Defines authorized security roles (e.g., `SuperAdmin`, `BillingManager`, `ProjectLead`) configured within specific organizations to aggregate granular permissions.
*   **Ownership Domain**: Authorization Core
*   **Dependencies**: `system.organizations`
*   **Expected Lifetime**: Persistent (Static presets and custom configurations)
*   **Expected Growth Rate**: Low (Static scale per organization)
*   **Expected Read / Write Frequency**: 99.9% Reads / 0.1% Writes

#### 4.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique role identifier. |
| `organization_id` | `uuid` | NO | None | FK & Composite Key | None | Public | Restricts the role definitions to a specific tenant organization. |
| `name` | `varchar(50)` | NO | None | Composite Key | None | Public | Display name of the custom role (e.g., `Accountant`). |
| `code` | `varchar(50)` | NO | None | Composite Key | None | Public | Lowercase, unique machine identifier for the role (e.g., `billing_auditor`). |
| `is_system` | `boolean` | NO | `false` | None | None | Public | Flag protecting core system roles from modification or deletion. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Role creation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Role modification timestamp. |

#### 4.1.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `organization_id REFERENCES system.organizations(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT roles_code_org_composite UNIQUE (organization_id, code)`
*   **Check Constraints**:
    *   `CONSTRAINT roles_code_format CHECK (code ~ '^[a-z0-9\_]+$')`

#### 4.1.4 Index Strategy
*   **`roles_pkey`**: Primary Key index.
*   **`roles_org_code_uidx`**: Unique Index on `(organization_id, code)`. Crucial for RBAC authorization checks during runtime middleware evaluation.

#### 4.1.5 Row-Level Security (RLS) Policy
*   **Read**: Users belonging to the target organization can view roles.
*   **Insert / Update / Delete**: Restricted strictly to organization admins of the specific tenant. System-created roles are protected from editing.

#### 4.1.6 Event Matrix
*   **Events Produced**: `role.created`, `role.updated`, `role.deleted`
*   **Events Consumed**: None

---

### 4.2 Table Name: `security.permissions`

#### 4.2.1 Overview
*   **Purpose**: Global system catalog defining every granular capability token supported across the platform (e.g., `invoice:create`, `project:view`, `settings:write`).
*   **Ownership Domain**: Authorization Core
*   **Dependencies**: None
*   **Expected Lifetime**: Persistent (Static catalog)
*   **Expected Growth Rate**: Static (Controlled by system upgrades)
*   **Expected Read / Write Frequency**: 100% Reads (Read-only after seeding)

#### 4.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique capability ID. |
| `token` | `varchar(100)`| NO | None | Unique Index | None | Public | String representation of permission (e.g., `user:write`). |
| `description` | `text` | NO | None | None | None | Public | Explains scope permissions to administrators. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Seed creation date. |

#### 4.2.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Unique Constraints**:
    *   `CONSTRAINT permissions_token_key UNIQUE (token)`
*   **Check Constraints**:
    *   `CONSTRAINT permissions_token_format CHECK (token ~ '^[a-z0-9\_]+\:[a-z0-9\_]+$')`

#### 4.2.4 Index Strategy
*   **`permissions_pkey`**: Primary Key index.
*   **`permissions_token_uidx`**: Unique Index on `token`. High-frequency lookup candidate. Selectivity: 100%.

#### 4.2.5 Row-Level Security (RLS) Policy
*   **Read**: Globally readable by authenticated clients.
*   **Insert / Update / Delete**: System administrators only.

---

### 4.3 Table Name: `public.role_permissions`

#### 4.3.1 Overview
*   **Purpose**: Junction table linking permissions to roles, establishing the foundational many-to-many relationship mapping of RBAC.
*   **Ownership Domain**: Authorization Core
*   **Dependencies**: `public.roles`, `security.permissions`
*   **Expected Lifetime**: Matches role configuration lifecycle.
*   **Expected Growth Rate**: $O(N)$ based on custom roles and assigned permissions.
*   **Expected Read / Write Frequency**: 99.9% Reads / 0.1% Writes

#### 4.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `role_id` | `uuid` | NO | None | PK & FK | None | Public | Target role reference. |
| `permission_id`| `uuid` | NO | None | PK & FK | None | Public | Assigned system permission reference. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Assignment timestamp. |

#### 4.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (role_id, permission_id)`
*   **Foreign Keys**:
    *   `role_id REFERENCES public.roles(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `permission_id REFERENCES security.permissions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

#### 4.3.4 Index Strategy
*   **`role_permissions_pkey`**: Composite Primary Key index.
*   **`role_permissions_reverse_idx`**: B-Tree Index on `(permission_id)`. Essential for reverse capability evaluations (e.g., checking which roles possess a specific permission).

#### 4.3.5 Row-Level Security (RLS) Policy
*   **Read**: Users belonging to the role's organization can query permissions.
*   **Insert / Delete**: Restricted strictly to organization admins of the parent role.
*   **Update**: Prohibited (Junction assignments are immutable; modifications require deletion and re-insertion).

#### 4.3.6 Event Matrix
*   **Events Produced**: `permission.assigned`, `permission.revoked`
*   **Events Consumed**: None

---

### 4.4 Table Name: `public.user_roles`

#### 4.4.1 Overview
*   **Purpose**: Binds individual user memberships to specific organization roles, granting active execution capabilities.
*   **Ownership Domain**: Authorization Core
*   **Dependencies**: `public.organization_members`, `public.roles`
*   **Expected Lifetime**: Matches user lifecycle.
*   **Expected Growth Rate**: Linear.
*   **Expected Read / Write Frequency**: 99.9% Reads / 0.1% Writes

#### 4.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `member_id` | `uuid` | NO | None | PK & FK | None | Public | Associated organization member ID. |
| `role_id` | `uuid` | NO | None | PK & FK | None | Public | Target assigned organization role ID. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Allocation timestamp. |

#### 4.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (member_id, role_id)`
*   **Foreign Keys**:
    *   `member_id REFERENCES public.organization_members(id) ON DELETE CASCADE ON UPDATE RESTRICT`
    *   `role_id REFERENCES public.roles(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

#### 4.4.4 Index Strategy
*   **`user_roles_pkey`**: Composite Primary Key index on `(member_id, role_id)`.
*   **`user_roles_reverse_idx`**: B-Tree Index on `(role_id)`. Supports fast queries to identify all members assigned to a specific role.

#### 4.4.5 Row-Level Security (RLS) Policy
*   **Read**: Users can select their own roles, or admins can query all roles within their organization.
*   **Insert / Delete**: Restricted strictly to organization administrators of the associated tenant.

#### 4.4.6 Event Matrix
*   **Events Produced**: `user_role.assigned`, `user_role.revoked`
*   **Events Consumed**: None

---

## 5. DOMAIN 3 — SESSION MANAGEMENT & TOKENS

Session architectures require fast lookup, cryptographically secure random identifier storage, automatic expiration monitoring, and instant revocation capability to protect against hijacking.

### 5.1 Table Name: `security.sessions`

#### 5.1.1 Overview
*   **Purpose**: Logs and validates active browser or mobile client session states, providing single-sign-out capability and tracking concurrent access.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Ephemeral (Valid for session duration, max 30 days)
*   **Expected Growth Rate**: Highly volatile.
*   **Expected Read / Write Frequency**: 90% Reads / 10% Writes

#### 5.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique session reference identifier. |
| `user_id` | `uuid` | NO | None | FK & B-Tree Index | None | Restricted-Identity | Associated authenticated user. |
| `session_token`| `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **Cryptographically secure, randomly generated session token**. |
| `ip_address` | `inet` | NO | None | None | None | Restricted-Identity | Logs the client IP address for security auditing and suspicious activity detection. |
| `user_agent` | `text` | YES | `NULL` | None | None | Public | Client browser or operating system string. |
| `is_active` | `boolean` | NO | `true` | B-Tree Index | None | Public | Status flag to immediately invalidate sessions without physically deleting records. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | UTC timestamp when session expires (Absolute Timeout). |
| `idle_expires_at`|`timestamptz`| NO | None | B-Tree Index | None | Public | UTC timestamp representing the dynamic idle session timeout limit. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Session initiation timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Timestamp of last session activity. |

#### 5.1.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT sessions_token_key UNIQUE (session_token)`
*   **Check Constraints**:
    *   `CONSTRAINT sessions_dates_ordered CHECK (created_at <= expires_at)`

#### 5.1.4 Index Strategy
*   **`sessions_pkey`**: Primary Key index.
*   **`sessions_token_uidx`**: Unique Index on `session_token`. Crucial for sub-millisecond validation checks on every API request.
*   **`sessions_user_active_idx`**: B-Tree Index on `(user_id, is_active)`. Used to find active sessions for a user or enforce concurrent session limits.
*   **`sessions_expiry_cleanup_idx`**: Partial Index on `(expires_at, idle_expires_at)` WHERE `is_active = true`. Optimized for background cleanup workers to invalidate stale sessions.

#### 5.1.5 Row-Level Security (RLS) Policy
*   **Read**: Restricted strictly to the owner user matching `user_id`.
*   **Insert / Update**: Managed by the authentication subsystem.
*   **Delete**: Soft delete or hard delete only during formal logout processes.

#### 5.1.6 Session Security & Timeout Rules
*   **Idle Timeout**: Active session window is extended on each authenticated request by 30 minutes, up to the absolute session limit.
*   **Absolute Timeout**: Sessions are strictly capped at 30 days, requiring full re-authentication upon expiration.
*   **Session Fixation Protection**: Session identifiers are generated from scratch on each successful login, preventing reuse of previous session states.

#### 5.1.7 Event Matrix
*   **Events Produced**: `session.created`, `session.idle_timeout`, `session.absolute_timeout`, `session.terminated`
*   **Events Consumed**: None

---

### 5.2 Table Name: `security.refresh_tokens`

#### 5.2.1 Purpose, Ownership & Dependencies
*   **Purpose**: Manages OAuth2 or SPA client refresh token families, supporting Refresh Token Rotation (RTR) to prevent replay attacks and secure silent token renewals.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `security.sessions`
*   **Expected Lifetime**: Dynamic (Standard retention up to 60 days)
*   **Expected Growth Rate**: Linear.
*   **Expected Read / Write Frequency**: 50% Reads / 50% Writes (High rotation rate)

#### 5.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique token identifier. |
| `session_id` | `uuid` | NO | None | FK & B-Tree Index | None | Restricted-Identity | Associated parent session. |
| `token_hash` | `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **SHA-256 hash of the generated refresh token**. |
| `is_used` | `boolean` | NO | `false` | None | None | Public | Status flag to track if the token has been consumed. Used for replay detection. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | Absolute expiration threshold of the token. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Creation date. |

#### 5.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `session_id REFERENCES security.sessions(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT refresh_tokens_hash_key UNIQUE (token_hash)`

#### 5.2.4 Index Strategy
*   **`refresh_tokens_pkey`**: Primary Key index.
*   **`refresh_tokens_hash_uidx`**: Unique Index on `token_hash`. Crucial for rapid lookup during silent token renewals.
*   **`refresh_tokens_session_idx`**: B-Tree Index on `(session_id)`. Tracks the token family tree.

#### 5.2.5 Security Replay Prevention Block
*   **Token Rotation Standard**: When a client requests a new access token using a refresh token, the current refresh token is marked as `is_used = true` and a new refresh token is issued.
*   **Replay Attack Detection**: If a client attempts to reuse a refresh token that has already been consumed (`is_used = true`), the security subsystem flags this as a potential replay attack, immediately invalidating the entire parent session and all associated child tokens.

---

### 5.3 Table Name: `security.password_history`

#### 5.3.1 Overview
*   **Purpose**: Prevents users from reusing previous passwords during password reset flows, complying with access control compliance frameworks.
*   **Ownership Domain**: Identity Security Core (Append-Only)
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Persistent (Retained up to 2 years)
*   **Expected Growth Rate**: Low.
*   **Expected Read / Write Frequency**: 95% Reads (Reset checks) / 5% Writes

#### 5.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique log identifier. |
| `user_id` | `uuid` | NO | None | FK & Composite Index | None | Restricted-Identity | Target user. |
| `password_hash` | `varchar(255)`| NO | None | None | None | Restricted-Secret | Historical Argon2id password hash. |
| `created_at` | `timestamptz` | NO | `now()` | Composite Index | None | Public | Timestamp of when the password was replaced. |

#### 5.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`

#### 5.3.4 Index Strategy
*   **`password_history_composite_idx`**: B-Tree Index on `(user_id, created_at DESC)`. Used to quickly retrieve and verify the user's recent password history.

#### 5.3.5 Operational Policy
*   **Reuse Prevention**: Users are prohibited from reusing any of their last **6** passwords during a password reset or change flow.

---

### 5.4 Table Name: `security.password_reset_tokens`

#### 5.4.1 Overview
*   **Purpose**: Manages single-use, short-lived tokens generated for secure password recovery flows.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Ephemeral (Max 1 hour)

#### 5.4.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique identifier. |
| `user_id` | `uuid` | NO | None | FK & B-Tree Index | None | Restricted-Identity | Target user requesting recovery. |
| `token_hash` | `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **SHA-256 hash of the generated recovery token**. |
| `is_used` | `boolean` | NO | `false` | None | None | Public | Verification status flag. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | Token expiration timestamp (strictly capped at 1 hour). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Token generation timestamp. |

#### 5.4.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT pwd_reset_token_hash UNIQUE (token_hash)`

#### 5.4.4 Index Strategy
*   **`pwd_reset_token_hash_uidx`**: Unique Index on `token_hash`. Crucial for validation lookups when users click password reset links.

---

### 5.5 Table Name: `security.email_verification_tokens`

#### 5.5.1 Overview
*   **Purpose**: Manages registration and email update verification tokens, ensuring email ownership is confirmed before unlocking account access.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Ephemeral (Valid for 24 hours)

#### 5.5.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique identifier. |
| `user_id` | `uuid` | NO | None | FK | None | Restricted-Identity | Associated user identity. |
| `token_hash` | `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **SHA-256 hash of the verification token**. |
| `target_email` | `citext` | NO | None | None | None | Restricted-Identity | Destined email target being verified. |
| `is_used` | `boolean` | NO | `false` | None | None | Public | Verification status flag. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | Token expiration timestamp (strictly capped at 24 hours). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Token generation timestamp. |

#### 5.5.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT email_ver_token_hash UNIQUE (token_hash)`

#### 5.5.4 Index Strategy
*   **`email_verification_token_hash_uidx`**: Unique Index on `token_hash` for fast validation lookups.

---

## 6. DOMAIN 4 — MULTI-FACTOR AUTHENTICATION (MFA)

To comply with OWASP ASVS Level 2, multi-factor authentication (TOTP or WebAuthn) must be supported natively and protected behind cryptographically secure storage bounds.

### 6.1 Table Name: `security.mfa_methods`

#### 6.1.1 Overview
*   **Purpose**: Registers the active MFA configurations (such as TOTP authenticator secrets) established by users to secure their accounts.
*   **Ownership Domain**: Multi-Factor Security Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Persistent (Active while MFA is enabled)
*   **Expected Growth Rate**: Low.
*   **Expected Read / Write Frequency**: 98% Reads / 2% Writes

#### 5.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique configuration ID. |
| `user_id` | `uuid` | NO | None | FK & Composite Index | None | Restricted-Identity | Target user context. |
| `method_type` | `varchar(30)` | NO | `'totp'` | Composite Index | None | Public | MFA method classification (`totp`, `webauthn`). |
| `secret_key` | `text` | NO | None | None | AES-256-GCM | Restricted-Secret | **Encrypted multi-factor seed configuration or shared TOTP secret**. |
| `is_active` | `boolean` | NO | `false` | None | None | Public | Confirms the MFA method is verified and active (disabled during setup until validated). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Registration timestamp. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Modification timestamp. |

#### 6.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT mfa_user_method_composite UNIQUE (user_id, method_type)`
*   **Check Constraints**:
    *   `CONSTRAINT mfa_method_type_valid CHECK (method_type IN ('totp', 'webauthn'))`

#### 6.1.4 Index Strategy
*   **`mfa_methods_pkey`**: Primary Key index.
*   **`mfa_user_lookup_idx`**: Unique composite index on `(user_id, method_type)`. Main route for retrieving MFA configuration during authentication.

#### 6.1.5 MFA Configuration Security Rules
*   **Encryption**: `secret_key` is encrypted using application-managed AES-256-GCM.
*   **Setup Verification**: MFA configurations are initialized as `is_active = false` and only set to `true` after the user successfully verifies a test MFA code, preventing accidental lockouts during setup.

#### 6.1.6 Event Matrix
*   **Events Produced**: `mfa.registered`, `mfa.enabled`, `mfa.disabled`
*   **Events Consumed**: None

---

### 6.2 Table Name: `security.mfa_recovery_codes`

#### 6.2.1 Overview
*   **Purpose**: Stores single-use emergency backup recovery codes generated during MFA setup, allowing users to regain access to their account if they lose their MFA device.
*   **Ownership Domain**: Multi-Factor Security Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Persistent (Until consumed or regenerated)
*   **Expected Growth Rate**: Low.

#### 6.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique code identifier. |
| `user_id` | `uuid` | NO | None | FK & B-Tree Index | None | Restricted-Identity | Associated user identity. |
| `code_hash` | `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **SHA-256 hash of the generated recovery code**. |
| `is_used` | `boolean` | NO | `false` | None | None | Public | Status flag to track if code has been consumed. |
| `used_at` | `timestamptz` | YES | `NULL` | None | None | Public | Timestamp of consumption. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Generation timestamp. |

#### 6.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT mfa_recovery_code_hash_key UNIQUE (code_hash)`

#### 6.2.4 Index Strategy
*   **`mfa_recovery_code_hash_uidx`**: Unique Index on `code_hash`. Crucial for rapid lookup and validation during emergency account recovery.

---

### 6.3 Table Name: `security.trusted_devices`

#### 6.3.1 Overview
*   **Purpose**: Logs and validates client devices trusted by the user, allowing subsequent login flows to bypass MFA challenges within a specified trust window.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `security.users`
*   **Expected Lifetime**: Ephemeral (Trust expires after 30 days)

#### 6.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Restricted-Identity | Unique device identifier. |
| `user_id` | `uuid` | NO | None | FK & Composite Index | None | Restricted-Identity | Target user. |
| `device_token` | `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **Secure, randomly generated device identification token**. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | Expiration date of the trust window (strictly capped at 30 days). |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Trust establishment timestamp. |

#### 6.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT trusted_devices_token_key UNIQUE (device_token)`

#### 6.3.4 Index Strategy
*   **`trusted_devices_token_uidx`**: Unique Index on `device_token`. Crucial for rapid device verification checks during the login flow.

---

## 7. DOMAIN 5 — SECURITY METRICS & AUDITING

To detect security threats (such as brute-force or credential-stuffing attacks) and comply with SOC 2 audit requirements, security events are logged in high-performance registries.

### 7.1 Table Name: `audit.login_attempts`

#### 7.1.1 Overview
*   **Purpose**: Tracks all authentication attempts (both successful and failed) in real-time, providing the data necessary to detect brute-force attacks and trigger account lockouts.
*   **Ownership Domain**: Security Intelligence (Append-Only)
*   **Dependencies**: None (Unlinked to prevent cascading delete vulnerabilities)
*   **Expected Lifetime**: Persistent (Retained up to 180 days)
*   **Expected Growth Rate**: High (Directly proportional to login volume)
*   **Expected Read / Write Frequency**: 10% Reads / 90% Writes

#### 7.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique attempt ID. |
| `email` | `citext` | NO | None | B-Tree Index | None | Restricted-Identity | Input email address. |
| `ip_address` | `inet` | NO | None | B-Tree Index | None | Restricted-Identity | Client IP address. |
| `user_agent` | `text` | YES | `NULL` | None | None | Public | Client browser or operating system string. |
| `is_successful`| `boolean` | NO | None | B-Tree Index | None | Public | Indicates if the login attempt succeeded. |
| `failure_reason`| `varchar(100)`| YES | `NULL` | None | None | Public | Reason for failure (e.g., `invalid_password`, `user_suspended`). |
| `attempted_at` | `timestamptz` | NO | `now()` | B-Tree Index | None | Public | UTC timestamp of attempt. |

#### 7.1.3 Constraints & Integrity Rules
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check Constraints**:
    *   `CONSTRAINT login_attempts_email_check CHECK (length(email) >= 3)`

#### 7.1.4 Index Strategy
*   **`login_attempts_pkey`**: Primary Key index.
*   **`login_attempts_brute_force_idx`**: B-Tree Index on `(ip_address, is_successful, attempted_at DESC)`. Crucial for real-time rate limiting and IP-based brute-force detection.
*   **`login_attempts_user_brute_idx`**: B-Tree Index on `(email, is_successful, attempted_at DESC)`. Used to check for account-based brute-force attacks.

#### 7.1.5 Operational Performance & Lifecycles
*   **Expected Row Count**: 10,000,000+ rows over time.
*   **Partitioning Recommended**: Partitioned by Range on `attempted_at` monthly.
*   **Archival Policy**: Attempt logs older than 90 days are aggregated and archived to cold storage; raw records older than 180 days are purged.

---

### 7.2 Table Name: `audit.security_events`

#### 7.2.1 Overview
*   **Purpose**: Immutable, SOC 2-compliant ledger tracking all high-severity security events (such as password changes, MFA activations, and privilege elevation).
*   **Ownership Domain**: Security Auditing Core (Append-Only)
*   **Dependencies**: None (Unlinked to prevent tampering)
*   **Expected Lifetime**: Persistent (Retained up to 7 years for compliance)

#### 7.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique event ID. |
| `user_id` | `uuid` | YES | `NULL` | B-Tree Index | None | Restricted-Identity | Target user if applicable. |
| `organization_id`| `uuid` | YES | `NULL` | B-Tree Index | None | Public | Target tenant context if applicable. |
| `event_type` | `varchar(100)`| NO | None | B-Tree Index | None | Public | Event code classification (e.g., `password_changed`, `mfa_enabled`). |
| `ip_address` | `inet` | NO | None | None | None | Restricted-Identity | Actor's IP address. |
| `user_agent` | `text` | YES | `NULL` | None | None | Public | Actor's browser string. |
| `details` | `jsonb` | NO | `'{}'` | None | None | Public | Structured contextual details (e.g., `{"changed_by": "admin_uuid"}`). |
| `occurred_at` | `timestamptz` | NO | `now()` | B-Tree Index | None | Public | UTC timestamp of event. |

#### 7.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Check Constraints**:
    *   `CONSTRAINT sec_events_type_format CHECK (event_type ~ '^[a-z0-9\_]+\.[a-z0-9\_]+$')`

#### 7.2.4 Index Strategy
*   **`security_events_pkey`**: Primary Key index.
*   **`security_events_audit_idx`**: B-Tree Index on `(organization_id, event_type, occurred_at DESC)`. Optimized for tenant-specific security audits.

#### 7.2.5 Operational Policy
*   **Immutability**: This table is strictly append-only. Any system attempt to `UPDATE` or `DELETE` rows must be blocked at the database engine level.

---

### 7.3 Table Name: `security.api_sessions`

#### 7.3.1 Overview
*   **Purpose**: Logs and validates active external application sessions authorized via API keys, tracking external access.
*   **Ownership Domain**: Session Management Core
*   **Dependencies**: `public.api_keys`
*   **Expected Lifetime**: Ephemeral (Valid for up to 24 hours)

#### 7.3.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique session ID. |
| `api_key_id` | `uuid` | NO | None | FK & B-Tree Index | None | Public | Parent API Key reference. |
| `session_token`| `varchar(255)`| NO | None | Unique Index | None | Restricted-Secret | **Cryptographically secure, randomly generated session token**. |
| `ip_address` | `inet` | NO | None | None | None | Restricted-Identity | IP address of the calling server. |
| `is_active` | `boolean` | NO | `true` | None | None | Public | Status flag to immediately revoke the session. |
| `expires_at` | `timestamptz` | NO | None | B-Tree Index | None | Public | UTC timestamp when session expires. |
| `created_at` | `timestamptz` | NO | `now()` | None | None | Public | Session initiation timestamp. |

#### 7.3.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `api_key_id REFERENCES public.api_keys(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT api_sessions_token_key UNIQUE (session_token)`

#### 7.3.4 Index Strategy
*   **`api_sessions_pkey`**: Primary Key index.
*   **`api_sessions_token_uidx`**: Unique Index on `session_token`. Used to validate incoming external API requests.

---

## 8. DOMAIN 6 — USER PREFERENCES & NOTIFICATIONS

Provides user-level customization parameters isolated per tenant domain.

### 8.1 Table Name: `public.user_preferences`

#### 8.1.1 Overview
*   **Purpose**: Stores user preference parameters (such as theme choice, UI localization options, and sidebar state) to persist settings across sessions.
*   **Ownership Domain**: User Customization Core
*   **Dependencies**: `security.users`

#### 8.1.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `user_id` | `uuid` | NO | None | PK & FK | None | Public | Associated user. |
| `theme` | `varchar(30)` | NO | `'light'` | None | None | Public | UI layout theme preference (`light`, `dark`). |
| `locale` | `varchar(10)` | NO | `'en-US'` | None | None | Public | Selected display locale. |
| `config` | `jsonb` | NO | `'{}'` | None | None | Public | **JSONB block supporting flexible, minor preferences settings** without schema updates. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Audit tracking. |

#### 8.1.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (user_id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Check Constraints**:
    *   `CONSTRAINT user_pref_theme CHECK (theme IN ('light', 'dark'))`

---

### 8.2 Table Name: `public.notification_preferences`

#### 8.2.1 Overview
*   **Purpose**: Manages communication preferences and opt-out configurations (such as email, SMS, and in-app alerts) for various event categories.
*   **Ownership Domain**: Communication Gating Core
*   **Dependencies**: `security.users`

#### 8.2.2 Physical Column Catalog
| Column Name | PostgreSQL Type | Nullable | Default | Constraints / Indexes | Encryption | Sensitivity | Architectural Reason |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | PK | None | Public | Unique preference ID. |
| `user_id` | `uuid` | NO | None | FK & Composite Index | None | Restricted-Identity | Target user. |
| `channel` | `varchar(30)` | NO | `'email'` | Composite Index | None | Public | Delivery channel (`email`, `sms`, `in_app`). |
| `event_category`|`varchar(50)`| NO | None | Composite Index | None | Public | Target event category (e.g., `invoicing`, `security_alerts`). |
| `is_enabled` | `boolean` | NO | `true` | None | None | Public | Preferences toggle status. |
| `updated_at` | `timestamptz` | NO | `now()` | None | None | Public | Audit tracking. |

#### 8.2.3 Relational & Integrity Constraints
*   **Primary Key**: `PRIMARY KEY (id)`
*   **Foreign Keys**:
    *   `user_id REFERENCES security.users(id) ON DELETE CASCADE ON UPDATE RESTRICT`
*   **Unique Constraints**:
    *   `CONSTRAINT user_notification_composite UNIQUE (user_id, channel, event_category)`
*   **Check Constraints**:
    *   `CONSTRAINT notif_channel_valid CHECK (channel IN ('email', 'sms', 'in_app'))`

#### 8.2.4 Index Strategy
*   **`notif_preferences_lookup_idx`**: Unique composite index on `(user_id, channel, event_category)`. Used by dispatchers to determine if a user should receive a notification.

---

## 9. PHYSICAL ENTITY RELATIONSHIP DICTIONARY

To prevent data drift and enforce transaction consistency, relationships across core security tables follow strict parameters:

| Source Entity | Destination Entity | Cardinality | Primary Relational Key | Delete Propagation Behavior | Update Propagation Behavior |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `security.users` | `public.profiles` | 1 : 1 | `user_id` | **CASCADE** | RESTRICT |
| `security.users` | `public.organization_members`| 1 : Many | `user_id` | **CASCADE** | RESTRICT |
| `system.organizations`| `public.organization_members`| 1 : Many | `organization_id` | **CASCADE** | RESTRICT |
| `public.organization_members`| `public.user_roles` | 1 : Many | `member_id` | **CASCADE** | RESTRICT |
| `public.roles` | `public.user_roles` | 1 : Many | `role_id` | **RESTRICT** | RESTRICT |
| `public.roles` | `public.role_permissions`| 1 : Many | `role_id` | **CASCADE** | RESTRICT |
| `security.permissions`| `public.role_permissions`| 1 : Many | `permission_id` | **RESTRICT** | RESTRICT |
| `security.users` | `security.sessions` | 1 : Many | `user_id` | **CASCADE** | RESTRICT |
| `security.sessions` | `security.refresh_tokens`| 1 : Many | `session_id` | **CASCADE** | RESTRICT |

---

## 10. SYSTEM VALIDATION MATRIX

Every value entered into authentication, membership, or authorization registries must pass these validation rules prior to write processing:

*   **Email Formats**: Must adhere to RFC 5322 specifications. Standard evaluation regex: `^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$`.
*   **Phone Formats**: Must adhere to ITU-T E.164 standards. Mandatory evaluation regex: `^\+[1-9]\d{1,14}$`.
*   **Role Code Formats**: Role names must use lowercase snake_case format. Required regex: `^[a-z0-9\_]+$`.
*   **Permission Token Formats**: Permissions must be defined as `context:action`. Required regex: `^[a-z0-9\_]+\:[a-z0-9\_]+$`.

---

## 11. ARCHITECTURAL CONSISTENCY REVIEW

This security specification has been validated against all authoritative blueprints:
*   **JUANET Master Specification (v1.3)**: Aligns with JWT parameters, active MFA gates, and double-entry authentication audits.
*   **Phase 2.1 Database Blueprint**: Physical standards map directly to conceptual axioms and session rotation constraints.
*   **Phase 2.3.1 PostgreSQL Standards**: Conforms to all naming conventions, UUID standards, TIMESTAMPTZ formatting, and RLS guidelines.

All security schema standards are now finalized and ready for implementation.
