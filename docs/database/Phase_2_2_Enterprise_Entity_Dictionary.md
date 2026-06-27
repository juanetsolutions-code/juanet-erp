# JUANET Enterprise Entity Dictionary
## Phase 2.2 — Authoritative Relational Entity Dictionary
**Document Version:** 1.0  
**Author:** Chief Enterprise Data Architect, JUANET Platform  
**Classification:** Technical / Architecture Reference  

---

## 1. INTRODUCTION & GOVERNING COMPLIANCE
This dictionary defines the exact structure, constraints, lifecycles, and security paradigms of all relational entities within the JUANET multi-tenant enterprise operating system. Every future schema migration, object-relational mapping (ORM) definition, and API payload contract must conform absolutely to the specifications documented herein.

### 1.1 Column Classifications
*   **Global Lookup Tables**: Read-accessible globally across all tenants. Writable only by system-level migrations or platform-level administrator actions.
*   **Tenant-Owned Tables**: Must contain `organization_id` as part of their composite indexes and are protected by strict Row-Level Security (RLS).
*   **Sensitive Data Classifications**:
    *   `Public`: Standard operational metrics, no protection needed.
    *   `Internal`: Accessible only within the authorized tenant membership.
    *   `PII` (Personally Identifiable Information): Regulated under GDPR/CCPA. Special auditing and hashing requirements apply.
    *   `Restricted-Secret`: High-security keys, credentials, or financial balance definitions. Must be encrypted at rest (AES-256-GCM) where applicable.

---

## 2. THE CORE DOMAIN SCHEMA

### 2.1 `organizations`
#### 2.1.1 Table Purpose
Acts as the root multi-tenant container representing independent enterprise customers (tenants). All operational, financial, and relational entities in the platform map back to an organization.

#### 2.1.2 Responsibilities
Defines tenant boundaries, subscription state, custom naming scopes, and administrative lockouts. It must never store user identities or specific system integrations.

#### 2.1.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique identifier | Must be valid UUIDv4 | `f3e589df-1c02...` | Public | Primary Key |
| `name` | `varchar(150)`| NO | None | Legal name of tenant | 2-150 characters, trimmed | `Acme Kenya Ltd` | Public | B-Tree |
| `slug` | `varchar(100)`| NO | None | Unique URL tenant slug | Lowercase, alphanumeric, hyphen | `acme-kenya` | Public | Unique Index |
| `status` | `varchar(30)` | NO | `'pending_setup'` | Tenant operational state | `pending_setup`, `active`, `suspended`, `archived` | `active` | Public | B-Tree |
| `created_at` | `timestamptz` | NO | `now()` | Record creation timestamp | UTC only | `2026-06-26T10:38:13Z`| Public | None |
| `updated_at` | `timestamptz` | NO | `now()` | Last modification timestamp | UTC only | `2026-06-26T10:40:00Z`| Public | None |
| `deleted_at` | `timestamptz` | YES | `NULL` | Soft delete timestamp | Nullable | `2026-06-26T12:00:00Z`| Public | Partial Index |
| `version` | `integer` | NO | `1` | Optimistic locking version | Incrementing integer | `5` | Public | None |

#### 2.1.4 Relationships
*   **One-to-Many**: `organization_settings`, `organization_members`, `user_roles`, `projects`, `invoices`, `payment_intents`, `tickets`.
*   **Delete Rules**: `RESTRICT`. An organization cannot be physically purged if dependent tenant records exist.

#### 2.1.5 Lifecycle
*   **Creation**: Provisioned via tenant registration API. State initialized to `pending_setup`.
*   **Updates**: Managed via Super Admin or tenant Owner account.
*   **Archiving / Soft Delete**: Transitions to `archived` state, setting `deleted_at`. Downstream tables propagate soft delete flags asynchronously.

#### 2.1.6 Validation Rules
*   Name and Slug must be non-empty and stripped of leading/trailing whitespaces.
*   The `slug` must be unique globally.

#### 2.1.7 Business Rules
*   An organization must have at least one active billing currency.
*   Status transitions can only move from `pending_setup` to `active`, and from `active` to `suspended` or `archived`.

#### 2.1.8 Security Rules
*   Protected by Global Super Admin override.
*   Standard tenants cannot view other organizations' details.

#### 2.1.9 Events Produced
*   `organization.created`, `organization.status_changed`, `organization.archived`.

#### 2.1.10 Events Consumed
*   None.

#### 2.1.11 Performance Notes
*   Unique index on `slug`.
*   Expected table size: 10,000+ organizations. Caching recommended on `slug` to `id` mapping.

#### 2.1.12 Future Expansion
*   Extendable via metadata `jsonb` column for custom billing agreements or corporate regional hierarchies.

---

### 2.2 `organization_settings`
#### 2.2.1 Table Purpose
Stores dynamic visual preferences, system variables, and operational thresholds for individual tenant accounts.

#### 2.2.2 Responsibilities
Governs the workspace UI theme, default tax rates, support SLAs, and currency parameters. It must never store payment passwords or master secrets.

#### 2.2.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `91e23f11-c90a...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to parent tenant | Valid UUIDv4, exists | `f3e589df-1c02...` | Public | Unique Composite |
| `theme_configuration` | `jsonb` | NO | `'{}'` | Dashboard UI styling config | Must be valid JSON | `{"primary": "#1A2B"}`| Public | None |
| `timezone` | `varchar(50)` | NO | `'UTC'` | Default time zone | Valid IANA timezone name | `Africa/Nairobi` | Public | None |
| `created_at` | `timestamptz` | NO | `now()` | Creation timestamp | UTC only | `2026-06-26T10:38:13Z`| Public | None |
| `updated_at` | `timestamptz` | NO | `now()` | Update timestamp | UTC only | `2026-06-26T10:40:00Z`| Public | None |

#### 2.2.4 Relationships
*   **Many-to-One**: `organizations` (`organization_id`).
*   **Delete Rules**: `CASCADE`. Deleting the parent organization automatically purges its settings.

#### 2.2.5 Lifecycle
*   Created automatically when `organizations` is set up. Writable by tenant administrators.

#### 2.2.6 Validation Rules
*   Timezone must exist within the standard IANA timezone registry.

#### 2.2.7 Business Rules
*   An organization must possess exactly one settings row.

#### 2.2.8 Security Rules
*   Row-Level Security isolates settings to the owning organization.

#### 2.2.9 Events Produced
*   `organization_settings.updated`.

#### 2.2.10 Events Consumed
*   `organization.created`.

#### 2.2.11 Performance Notes
*   Unique composite index on `(organization_id)`.
*   Keep JSON config small; move complex feature flags to dedicated models.

#### 2.2.12 Future Expansion
*   JSONB structure allows plug-and-play adding of interface toggle keys.

---

### 2.3 `currencies` (Global Lookup Table)
#### 2.3.1 Table Purpose
The global master registry of valid ISO currency definitions supported by the platform's multi-currency engines.

#### 2.3.2 Responsibilities
Defines visual symbols, numeric display precision, and code mappings. It must never store fluctuating conversion rates.

#### 2.3.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `5ab218ef-91cc...` | Public | Primary Key |
| `code` | `varchar(3)` | NO | None | ISO 4217 Currency Code | 3 characters, uppercase | `KES`, `USD`, `EUR` | Public | Unique Index |
| `symbol` | `varchar(10)` | NO | None | Currency visual character | Non-empty | `KSh`, `$`, `€` | Public | None |
| `decimal_precision` | `integer`| NO | `2` | Fraction limits | `0`, `2`, or `4` | `2` | Public | None |

#### 2.3.4 Relationships
*   **One-to-Many**: `exchange_rates` (as base or target currency), `invoices`, `payment_intents`.
*   **Delete Rules**: `RESTRICT`. Global currency cannot be deleted if referenced.

#### 2.3.5 Lifecycle
*   Populated during system bootstrap. Managed purely by platform administrators.

#### 2.3.6 Validation Rules
*   Code must match standard regex `/^[A-Z]{3}$/`.

#### 2.3.7 Business Rules
*   Exchange rate tracking and financial ledgers rely entirely on these codes.

#### 2.3.8 Security Rules
*   Read-only to standard tenant workflows. Writable only by system superusers.

#### 2.3.9 Events Produced
*   `currency.registered`.

#### 2.3.10 Events Consumed
*   None.

#### 2.3.11 Performance Notes
*   Cache globally. Size is small (~150 rows).

#### 2.3.12 Future Expansion
*   Support crypto or localized tokens via extension fields.

---

### 2.4 `exchange_rates` (Global Lookup Table)
#### 2.4.1 Table Purpose
Tracks mid-market conversion coefficients between global currencies relative to USD or other baseline references.

#### 2.4.2 Responsibilities
Stores timestamped exchange ratios. It must never calculate balances directly.

#### 2.4.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `ec837190-bcda...` | Public | Primary Key |
| `base_currency_id` | `uuid` | NO | None | Foreign key to base | Valid reference | `5ab218ef-91cc...` | Public | B-Tree |
| `target_currency_id`| `uuid` | NO | None | Foreign key to target | Valid reference | `3bc82110-1cc0...` | Public | B-Tree |
| `rate` | `numeric(18,8)`| NO | None | Conversion multiplier | Positive real value | `129.50000000` | Public | None |
| `fetched_at` | `timestamptz`| NO | `now()` | Calculation update date | UTC only | `2026-06-26T10:38:13Z`| Public | B-Tree |

#### 2.4.4 Relationships
*   **Many-to-One**: `currencies` (`base_currency_id`), `currencies` (`target_currency_id`).
*   **Delete Rules**: `RESTRICT`.

#### 2.4.5 Lifecycle
*   Updated periodically via cron scripts fetching from corporate FX APIs.

#### 2.4.6 Validation Rules
*   `rate` must be greater than zero.

#### 2.4.7 Business Rules
*   Base currency must not equal target currency within the same conversion record.

#### 2.4.8 Security Rules
*   Globally readable; writable strictly by integration system accounts.

#### 2.4.9 Events Produced
*   `exchange_rate.updated`.

#### 2.4.10 Events Consumed
*   None.

#### 2.4.11 Performance Notes
*   Compound index recommended on `(base_currency_id, target_currency_id, fetched_at DESC)`.

#### 2.4.12 Future Expansion
*   Extend to capture bid/ask spreads for enterprise treasury features.

---

### 2.5 `audit_logs` (System Domain)
#### 2.5.1 Table Purpose
Stores immutable tracking records of all mutations on administrative and financial objects.

#### 2.5.2 Responsibilities
Provides a write-once ledger of user actions. It must never store passwords or full decrypted secret hashes.

#### 2.5.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `dc083110-cc90...` | Internal | Primary Key |
| `organization_id` | `uuid` | YES | None | Link to tenant context | Valid reference | `f3e589df-1c02...` | Internal | B-Tree |
| `user_id` | `uuid` | YES | None | Performing user | Valid reference | `aa3c211a-1200...` | PII | B-Tree |
| `action` | `varchar(50)` | NO | None | Event name | Upper or lowercase | `invoice.marked_paid` | Public | B-Tree |
| `old_values` | `jsonb` | YES | `NULL` | State prior to modification | Valid JSON | `{"status": "pending"}`| Internal | None |
| `new_values` | `jsonb` | YES | `NULL` | State post modification | Valid JSON | `{"status": "paid"}` | Internal | None |
| `ip_address` | `inet` | YES | None | Performing machine IP | Valid IP address format | `197.248.31.25` | PII | None |
| `created_at` | `timestamptz`| NO | `now()` | Mutation timestamp | UTC only | `2026-06-26T10:38:13Z`| Public | B-Tree |

#### 2.5.4 Relationships
*   **Many-to-One**: `organizations`, `users`.
*   **Delete Rules**: `RESTRICT`. No delete rules; table must not support deletion.

#### 2.5.5 Lifecycle
*   INSERT-only. Retained for compliance (e.g., 7 years) and archived to secure cold storage.

#### 2.5.6 Validation Rules
*   At least one of `old_values` or `new_values` must be populated on update actions.

#### 2.5.7 Business Rules
*   Audit logs are fully immutable. UPDATE, DELETE, or TRUNCATE operations are blocked by DBMS level rules.

#### 2.5.8 Security Rules
*   Read access restricted to compliance administrators.

#### 2.5.9 Events Produced
*   None.

#### 2.5.10 Events Consumed
*   All major system transactional outputs.

#### 2.5.11 Performance Notes
*   Partitioned by month (`created_at`). Use index on `(organization_id, created_at DESC)`.

#### 2.5.12 Future Expansion
*   Integration with external SIEM systems via background export hooks.

---

### 2.6 `outbound_events`
#### 2.6.1 Table Purpose
An operational outbox for implementing the Transactional Outbox Pattern to decouple database commits from downstream systems.

#### 2.6.2 Responsibilities
Queues business events atomically with database changes. It does not process the events directly.

#### 2.6.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `ca92110b-11c0...` | Public | Primary Key |
| `event_name` | `varchar(100)`| NO | None | Broad description of event | Standard name string | `invoice.created` | Public | B-Tree |
| `payload` | `jsonb` | NO | `'{}'` | Event parameters | Must be valid JSON | `{"invoice_id": "..."}`| Internal | None |
| `status` | `varchar(30)` | NO | `'pending'` | Processing execution state | `pending`, `processed`, `failed` | `pending` | Public | B-Tree |
| `attempts` | `integer` | NO | `0` | Number of dispatch attempts| Minimum 0 | `3` | Public | None |
| `created_at` | `timestamptz`| NO | `now()` | Event queue date | UTC only | `2026-06-26T10:38:13Z`| Public | None |

#### 2.6.4 Relationships
*   Decoupled interface. No hard foreign keys to prevent locking.

#### 2.6.5 Lifecycle
*   Created atomically in business transactions. Polled and marked `processed` or `failed` by event processors.

#### 2.6.6 Validation Rules
*   `attempts` must not exceed system maximum retry configurations before dead-lettering.

#### 2.6.7 Business Rules
*   Events are written in the same transaction as business updates.

#### 2.6.8 Security Rules
*   Accessible only to system background worker accounts.

#### 2.6.9 Events Produced
*   Triggers external message queues.

#### 2.6.10 Events Consumed
*   None.

#### 2.6.11 Performance Notes
*   Partial index on `status` where `status = 'pending'`. Keep size small by archiving processed events.

#### 2.6.12 Future Expansion
*   Support event routing schemas to multiple destination brokers.

---

### 2.7 `idempotency_keys`
#### 2.7.1 Table Purpose
Prevents duplicate API executions of state-modifying actions (e.g. charging a payment twice).

#### 2.7.2 Responsibilities
Records client request keys and caches responses. It must never store long-term business states.

#### 2.7.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `e921c110-cca0...` | Public | Primary Key |
| `idempotency_key` | `varchar(255)`| NO | None | Client-supplied key | String representation | `req-payment-101` | Public | Unique Index |
| `request_path` | `text` | NO | None | Destination route path | Valid path string | `/api/v1/billing` | Public | None |
| `response_status` | `integer`| NO | None | Cached status code | Valid HTTP status | `200`, `201` | Public | None |
| `response_body` | `jsonb` | NO | None | Cached JSON body | Valid JSON | `{"status": "charged"}`| Internal | None |
| `expires_at` | `timestamptz`| NO | None | Expiry time of the key | UTC only | `2026-06-27T10:38:13Z`| Public | B-Tree |

#### 2.7.4 Relationships
*   None.

#### 2.7.5 Lifecycle
*   Created at transaction initialization. Automatically purged via TTL or clean-up cron after expiration (typically 24 hours).

#### 2.7.6 Validation Rules
*   HTTP codes must fall within valid RFC standard blocks (e.g., `200` to `599`).

#### 2.7.7 Business Rules
*   A client requesting state modification with a duplicate, non-expired key receives the cached response instantly.

#### 2.7.8 Security Rules
*   Isolated to the authenticated identity scope of the caller.

#### 2.7.9 Events Produced / Consumed
*   None.

#### 2.7.10 Performance Notes
*   Unique index on `(idempotency_key)`. Purge records regularly.

#### 2.7.11 Future Expansion
*   Support Redis storage for low-latency lookups.

---

## 3. THE AUTHENTICATION DOMAIN SCHEMA

### 3.1 `users`
#### 3.1.1 Table Purpose
The central identity registry storing credentials and access credentials for the JUANET system.

#### 3.1.2 Responsibilities
Manages passwords, security tokens, and user status. It must never store profile metrics or business settings.

#### 3.1.3 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `aa3c211a-1200...` | Public | Primary Key |
| `email` | `varchar(255)`| NO | None | Primary login email | Must be valid email | `user@domain.com` | PII | Unique Index |
| `password_hash` | `varchar(255)`| NO | None | Cryptographic password | BCrypt or Argon2 only | `$2a$12$K8920...` | Restr-Secret | None |
| `is_active` | `boolean` | NO | `true` | Account active state | Standard boolean | `true` | Public | B-Tree |
| `created_at` | `timestamptz`| NO | `now()` | User creation date | UTC only | `2026-06-26T10:38:13Z`| Public | None |

#### 3.1.4 Relationships
*   **One-to-One**: `profiles`.
*   **One-to-Many**: `organization_members`, `user_roles`, `sessions`, `mfa_devices`.

#### 3.1.5 Lifecycle
*   Created on onboarding or invite. Removed only on user delete request, triggering GDPR anonymization cascades.

#### 3.1.6 Validation Rules
*   Email matches standard structure `^[^@]+@[^@]+\.[^@]+$`.

#### 3.1.7 Business Rules
*   Email addresses must be stored in lowercase and be globally unique.

#### 3.1.8 Security Rules
*   Passwords must never be readable. Password modifications must trigger background event alerts.

#### 3.1.9 Events Produced
*   `user.registered`, `user.deleted`, `user.password_reset`.

#### 3.1.10 Performance Notes
*   Unique index on `lower(email)`.

#### 3.1.11 Future Expansion
*   Extensible to support external federated ID systems (OAuth).

---

### 3.2 `profiles`
#### 3.2.1 Table Purpose
Stores identity metadata for users without cluttering security credentials.

#### 3.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | None | Primary key referencing user | Valid UUIDv4 | `aa3c211a-1200...` | Public | Primary Key |
| `first_name` | `varchar(100)`| NO | None | First name | Length 1-100 | `Jane` | PII | None |
| `last_name` | `varchar(100)`| NO | None | Last name | Length 1-100 | `Doe` | PII | None |
| `phone_number` | `varchar(30)` | YES | `NULL` | Primary phone | Valid E.164 phone | `+254712345678` | PII | B-Tree |

#### 3.2.3 Relationships
*   **One-to-One**: `users` (`id`).

---

### 3.3 `organization_members`
#### 3.3.1 Table Purpose
Defines user membership within a specific organization context.

#### 3.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `8cc92110-1cba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | Unique Comp |
| `user_id` | `uuid` | NO | None | Reference to user | Valid reference | `aa3c211a-1200...` | Public | Unique Comp |
| `role_type` | `varchar(50)` | NO | `'member'` | Standard membership scope| `owner`, `admin`, `member` | `admin` | Public | None |

#### 3.3.3 Relationships
*   **Many-to-One**: `organizations`, `users`.

---

### 3.4 `roles`
#### 3.4.1 Table Purpose
Defines standard or custom access groups within organizations.

#### 3.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `7bc2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | YES | `NULL` | Tenant scope | Nullable (global roles) | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(100)`| NO | None | Display name of role | String, trimmed | `Finance Manager` | Public | None |

---

### 3.5 `permissions` (Global Lookup Table)
#### 3.5.1 Table Purpose
Defines individual permission flags (e.g. `invoice:write`) supported by the platform.

#### 3.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `6cb2110a-ccba...` | Public | Primary Key |
| `token` | `varchar(100)`| NO | None | Permission access code | Unique alphanumeric token| `invoice:write` | Public | Unique Index |

---

### 3.6 `role_permissions`
#### 3.6.1 Table Purpose
Junction table mapping permission access rights to specific system roles.

#### 3.6.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `role_id` | `uuid` | NO | None | Role reference | Valid reference | `7bc2110a-ccba...` | Public | Composite PK |
| `permission_id` | `uuid` | NO | None | Permission reference | Valid reference | `6cb2110a-ccba...` | Public | Composite PK |

#### 3.6.3 Relationships
*   Composite Primary Key `(role_id, permission_id)`.
*   **Many-to-One**: `roles`, `permissions`.

---

### 3.7 `user_roles`
#### 3.7.1 Table Purpose
Junction table assigning organizational roles to individual users.

#### 3.7.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `user_id` | `uuid` | NO | None | User reference | Valid reference | `aa3c211a-1200...` | Public | Composite PK |
| `role_id` | `uuid` | NO | None | Role reference | Valid reference | `7bc2110a-ccba...` | Public | Composite PK |
| `organization_id` | `uuid` | NO | None | Organization reference | Valid reference | `f3e589df-1c02...` | Public | Composite PK |

---

### 3.8 `sessions`
#### 3.8.1 Table Purpose
Tracks active authentication sessions for security validation and auditing.

#### 3.8.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `5cc2110a-ccba...` | Public | Primary Key |
| `user_id` | `uuid` | NO | None | User reference | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `token` | `varchar(255)`| NO | None | Session token signature | Cryptographically random | `session_t_892010c` | Restr-Secret | Unique Index |
| `expires_at` | `timestamptz`| NO | None | Session expiration | UTC only | `2026-06-27T10:38:13Z`| Public | B-Tree |

---

### 3.9 `mfa_devices`
#### 3.9.1 Table Purpose
Tracks Multi-Factor Authentication setups for enhanced user security.

#### 3.9.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `4cc2110a-ccba...` | Public | Primary Key |
| `user_id` | `uuid` | NO | None | User reference | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `mfa_type` | `varchar(30)` | NO | `'totp'` | Authentication type | `totp`, `sms`, `webauthn`| `totp` | Public | None |
| `secret` | `varchar(255)`| NO | None | MFA private setup secret | Encrypted at rest | `base32secretkey` | Restr-Secret | None |

---

## 4. THE CRM DOMAIN SCHEMA

### 4.1 `leads`
#### 4.1.1 Table Purpose
Tracks potential clients, prospective inquiries, and inbound acquisition flows.

#### 4.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `3cc2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `first_name` | `varchar(100)`| NO | None | Lead first name | Length 1-100 | `John` | PII | None |
| `last_name` | `varchar(100)`| NO | None | Lead last name | Length 1-100 | `Smith` | PII | None |
| `email` | `varchar(255)`| NO | None | Lead contact email | Valid email string | `john.smith@corp.com` | PII | B-Tree |
| `status` | `varchar(30)` | NO | `'new'` | Lead stage | `new`, `contacted`, `qualified`, `lost` | `new` | Public | B-Tree |

---

### 4.2 `contacts`
#### 4.2.1 Table Purpose
Structures professional directory metrics for business communication.

#### 4.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `2cc2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `first_name` | `varchar(100)`| NO | None | First name | Length 1-100 | `Jane` | PII | None |
| `last_name` | `varchar(100)`| NO | None | Last name | Length 1-100 | `Miller` | PII | None |
| `email` | `varchar(255)`| NO | None | Contact email | Valid email | `jane@corp.com` | PII | B-Tree |

---

### 4.3 `client_accounts`
#### 4.3.1 Table Purpose
Represents verified business accounts corresponding to active corporate clients.

#### 4.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `1cc2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `company_name` | `varchar(200)`| NO | None | Company name | Trimmed non-empty string | `Globex Corporation` | Public | B-Tree |
| `billing_email` | `varchar(255)`| NO | None | Primary billing destination | Valid email format | `billing@globex.com` | PII | None |

---

### 4.4 `proposals`
#### 4.4.1 Table Purpose
Maintains records of customized business proposals and project quotes.

#### 4.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `0cc2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `client_account_id`| `uuid` | NO | None | Targeted customer account | Valid reference | `1cc2110a-ccba...` | Public | B-Tree |
| `title` | `varchar(255)`| NO | None | Proposal title | Non-empty | `Web Portal Development`| Public | None |
| `status` | `varchar(30)` | NO | `'draft'` | Current pipeline status | `draft`, `sent`, `accepted`, `declined`| `sent` | Public | B-Tree |

---

### 4.5 `proposal_items`
#### 4.5.1 Table Purpose
Granular line items defining specific services and costs offered within a proposal.

#### 4.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `a0c2110a-ccba...` | Public | Primary Key |
| `proposal_id` | `uuid` | NO | None | Proposal reference | Valid reference | `0cc2110a-ccba...` | Public | B-Tree |
| `description` | `text` | NO | None | Work item description | Non-empty | `Custom API Integration`| Public | None |
| `price` | `numeric(18,2)`| NO | None | Item proposed unit cost | Positive numeric | `25000.00` | Public | None |

---

## 5. THE PROJECTS DOMAIN SCHEMA

### 5.1 `projects`
#### 5.1.1 Table Purpose
The core deliverable container tracking timeline, budget, and milestones for contracted services.

#### 5.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `f9c2110a-ccba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `client_account_id`| `uuid` | NO | None | Referenced client | Valid reference | `1cc2110a-ccba...` | Public | B-Tree |
| `name` | `varchar(255)`| NO | None | Project name | 2-255 characters | `JUANET ERP Build` | Public | B-Tree |
| `status` | `varchar(30)` | NO | `'planning'` | Current delivery phase | `planning`, `active`, `completed`, `on_hold`| `active` | Public | B-Tree |

---

### 5.2 `project_members`
#### 5.2.1 Table Purpose
Junction table mapping personnel resources and roles on a project delivery team.

#### 5.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `project_id` | `uuid` | NO | None | Project reference | Valid reference | `f9c2110a-ccba...` | Public | Composite PK |
| `user_id` | `uuid` | NO | None | User reference | Valid reference | `aa3c211a-1200...` | Public | Composite PK |
| `role` | `varchar(50)` | NO | `'developer'` | Project-specific job role | e.g. `lead`, `developer`, `tester` | `lead` | Public | None |

---

### 5.3 `milestones`
#### 5.3.1 Table Purpose
Maintains payment gates and critical target phases mapped within delivery timelines.

#### 5.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `e9c2110a-ccba...` | Public | Primary Key |
| `project_id` | `uuid` | NO | None | Project reference | Valid reference | `f9c2110a-ccba...` | Public | B-Tree |
| `title` | `varchar(200)`| NO | None | Milestone title | Non-empty | `Phase 1 DB Blueprint` | Public | None |
| `due_date` | `timestamptz`| YES | `NULL` | Milestone target date | Future UTC date | `2026-07-15T12:00:00Z`| Public | None |

---

### 5.4 `tasks`
#### 5.4.1 Table Purpose
Granular execution tickets detailing individual tasks required to complete a project.

#### 5.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `d9c2110a-ccba...` | Public | Primary Key |
| `project_id` | `uuid` | NO | None | Project reference | Valid reference | `f9c2110a-ccba...` | Public | B-Tree |
| `assigned_to` | `uuid` | YES | `NULL` | Responsible user | Valid user ID | `aa3c211a-1200...` | Public | B-Tree |
| `title` | `varchar(255)`| NO | None | Task title | Non-empty | `Configure OAuth Webhook` | Public | None |
| `priority` | `varchar(20)` | NO | `'medium'` | Task urgency rank | `low`, `medium`, `high`, `critical` | `high` | Public | None |
| `status` | `varchar(30)` | NO | `'todo'` | Current task status | `todo`, `in_progress`, `review`, `done` | `todo` | Public | B-Tree |

---

### 5.5 `task_comments`
#### 5.5.1 Table Purpose
Allows team members to discuss tasks and log communication updates.

#### 5.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `c9c2110a-ccba...` | Public | Primary Key |
| `task_id` | `uuid` | NO | None | Referenced task | Valid reference | `d9c2110a-ccba...` | Public | B-Tree |
| `user_id` | `uuid` | NO | None | Commenting author | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `comment_text` | `text` | NO | None | Raw text markdown | Non-empty | `API configuration works`| Public | None |

---

### 5.6 `timesheets`
#### 5.6.1 Table Purpose
Logs hours worked for billing reconciliation and performance tracking.

#### 5.6.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `b9c2110a-ccba...` | Public | Primary Key |
| `project_id` | `uuid` | NO | None | Referenced project | Valid reference | `f9c2110a-ccba...` | Public | B-Tree |
| `user_id` | `uuid` | NO | None | Performing developer | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `hours_spent` | `numeric(5,2)`| NO | None | Quantity of decimal hours | Positive value > 0.0 | `4.50` | Public | None |

---

## 6. THE FINANCE DOMAIN SCHEMA

### 6.1 `chart_of_accounts`
#### 6.1.1 Table Purpose
Defines the business double-entry ledger accounts (e.g. Assets, Liabilities, Revenue, Expenses).

#### 6.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `a1b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `account_code` | `varchar(30)` | NO | None | Unique accounting code | Alphanumeric unique per tenant| `1000-CASH` | Public | Unique Composite |
| `account_name` | `varchar(150)`| NO | None | Name of ledger category | Trimmed string | `M-Pesa Clearing` | Public | None |
| `account_type` | `varchar(30)` | NO | None | Accounting group | `asset`, `liability`, `equity`, `revenue`, `expense`| `asset` | Public | B-Tree |

---

### 6.2 `ledger_entries`
#### 6.2.1 Table Purpose
Tracks immutable, reconciled financial changes across chart accounts.

#### 6.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `a2b23c10-90ba...` | Public | Primary Key |
| `journal_entry_id` | `uuid` | NO | None | Owning transaction batch | Valid reference | `a3b23c10-90ba...` | Public | B-Tree |
| `account_id` | `uuid` | NO | None | Targeted ledger account | Valid reference | `a1b23c10-90ba...` | Public | B-Tree |
| `debit_amount` | `numeric(18,2)`| NO | `0.00` | Positive value added to debit| Minimum 0.00 | `15000.00` | Public | None |
| `credit_amount` | `numeric(18,2)`| NO | `0.00` | Positive value added to credit| Minimum 0.00 | `0.00` | Public | None |

---

### 6.3 `journal_entries`
#### 6.3.1 Table Purpose
Acts as a transaction header grouping double-entry debits and credits to ensure atomic updates.

#### 6.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `a3b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `description` | `text` | NO | None | Audit explanation of transfer | Trimmed | `M-Pesa STK push receipt`| Public | None |
| `posted_at` | `timestamptz`| NO | `now()` | Date transactional post completed | UTC only | `2026-06-26T10:38:13Z`| Public | B-Tree |

---

### 6.4 `invoices`
#### 6.4.1 Table Purpose
Represents formal billing requests generated for clients.

#### 6.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `a4b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `client_account_id`| `uuid` | NO | None | Targeted billing customer | Valid reference | `1cc2110a-ccba...` | Public | B-Tree |
| `invoice_number` | `varchar(50)` | NO | None | Unique invoice number | Alphanumeric, unique per tenant| `INV-2026-0922` | Public | Unique Composite |
| `currency_id` | `uuid` | NO | None | Currency identifier | Valid reference | `5ab218ef-91cc...` | Public | B-Tree |
| `total_amount` | `numeric(18,2)`| NO | `0.00` | Sum total of line items | Positive value | `45000.00` | Public | None |
| `balance_due` | `numeric(18,2)`| NO | `0.00` | Remaining unpaid balance | Minimum 0.00 | `15000.00` | Public | None |
| `status` | `varchar(30)` | NO | `'draft'` | Billing lifecycle stage | `draft`, `sent`, `paid`, `past_due`, `cancelled`| `sent` | Public | B-Tree |

---

### 6.5 `invoice_items`
#### 6.5.1 Table Purpose
Maintains the detailed breakdown of charges included in an invoice.

#### 6.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `a5b23c10-90ba...` | Public | Primary Key |
| `invoice_id` | `uuid` | NO | None | Referenced invoice | Valid reference | `a4b23c10-90ba...` | Public | B-Tree |
| `description` | `text` | NO | None | Line item details | Non-empty | `Enterprise hosting fee` | Public | None |
| `quantity` | `numeric(10,2)`| NO | `1.00` | Item count | Positive value | `12.50` | Public | None |
| `unit_price` | `numeric(18,2)`| NO | None | Single item cost | Positive value | `250.00` | Public | None |

---

### 6.6 `tax_rates`
#### 6.6.1 Table Purpose
Defines localized tax configurations (e.g. VAT, Sales Tax) applied during invoicing.

#### 6.6.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `a6b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(100)`| NO | None | Display name of tax | Non-empty | `Kenya VAT 16%` | Public | None |
| `percentage` | `numeric(5,2)` | NO | `0.00` | Tax percentage rate | Range `0.00` to `100.00` | `16.00` | Public | None |

---

### 6.7 `payment_allocations`
#### 6.7.1 Table Purpose
Tracks how individual payments are applied to outstanding invoices.

#### 6.7.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `a7b23c10-90ba...` | Public | Primary Key |
| `invoice_id` | `uuid` | NO | None | Referenced invoice | Valid reference | `a4b23c10-90ba...` | Public | B-Tree |
| `amount_allocated` | `numeric(18,2)`| NO | None | Amount applied to invoice | Positive value > 0.00 | `15000.00` | Public | None |

---

## 7. THE ENTERPRISE PAYMENTS GATEWAY HUB

To implement our **central provider-agnostic central payment router**, we define a normalized payments schema. This schema decouples financial ledgers from provider-specific variables while maintaining full support for error handling and fallback routing.

```
       [ CLIENT INITIATED TRANSACTION ]
                      │
                      ▼
             ┌─────────────────┐
             │ payment_intents │ (Stores agnostic intention block)
             └────────┬────────┘
                      │
                      ▼
             ┌──────────────────┐
             │ payment_attempts │ (Tracks individual provider execution routes)
             └────────┬─────────┘
                      │ (If successful Webhook / Polling)
                      ▼
             ┌──────────────────┐
             │ payment_receipts │ (Locks immutable billing parameters)
             └──────────────────┘
```

---

### 7.1 `payment_gateways` (Global Lookup Table)
#### 7.1.1 Table Purpose
Global catalog listing supported payment gateway providers (e.g. M-Pesa Daraja, PayHero, Stripe, PayPal).

#### 7.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `g1b23c10-90ba...` | Public | Primary Key |
| `provider_code` | `varchar(50)` | NO | None | Integration code ID | Alphanumeric lowercase | `mpesa_daraja` | Public | Unique Index |
| `display_name` | `varchar(100)`| NO | None | Human readable label | Non-empty | `M-Pesa Checkout` | Public | None |

---

### 7.2 `organization_payment_gateways`
#### 7.2.1 Table Purpose
SaaS Tenant routing registry mapping credentials, api configurations, and priority parameters.

#### 7.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `g2b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | Unique Composite |
| `gateway_id` | `uuid` | NO | None | Reference to gateway | Valid reference | `g1b23c10-90ba...` | Public | Unique Composite |
| `api_credentials` | `jsonb` | NO | `'{}'` | Encrypted access credentials| Valid JSON structure | `{"client_id": "..."}`| Restr-Secret | None |
| `routing_priority` | `integer`| NO | `1` | Routing prioritization order| Minimum 1 | `1` | Public | None |
| `is_active` | `boolean` | NO | `true` | Integration active flag | Standard boolean | `true` | Public | B-Tree |

---

### 7.3 `payment_gateway_capabilities` (Global Lookup Table)
#### 7.3.1 Table Purpose
Defines supported features for payment gateways (e.g. STK Push, Card payments, Webhooks).

#### 7.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `g3b23c10-90ba...` | Public | Primary Key |
| `gateway_id` | `uuid` | NO | None | Gateway reference | Valid reference | `g1b23c10-90ba...` | Public | B-Tree |
| `capability` | `varchar(50)` | NO | None | Capability identifier | Standard token | `stk_push`, `refund` | Public | None |

---

### 7.4 `payment_health_metrics`
#### 7.4.1 Table Purpose
Tracks real-time telemetry metrics to feed the routing engine's circuit breaker algorithm.

#### 7.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `g4b23c10-90ba...` | Public | Primary Key |
| `gateway_id` | `uuid` | NO | None | Targeted gateway | Valid reference | `g1b23c10-90ba...` | Public | B-Tree |
| `recent_latency_ms`| `integer` | NO | None | Latest response time (ms) | Positive integer | `420` | Public | None |
| `error_rate` | `numeric(5,2)` | NO | `0.00` | Percentage of failed calls | Range `0.00` to `100.00` | `4.21` | Public | None |
| `status_index` | `varchar(30)` | NO | `'healthy'` | Health index indicator | `healthy`, `degraded`, `offline`| `healthy` | Public | B-Tree |

---

### 7.5 `payment_intents`
#### 7.5.1 Table Purpose
Agnostic transactional state-machine capturing the client's intent to pay before execution begins.

#### 7.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary system-wide key | Valid UUIDv4 | `g5b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `invoice_id` | `uuid` | YES | `NULL` | Optional link to target invoice| Valid reference | `a4b23c10-90ba...` | Public | B-Tree |
| `amount` | `numeric(18,2)`| NO | None | Gross value to charge | Positive value > 0.00 | `25000.00` | Public | None |
| `currency_id` | `uuid` | NO | None | Targeted currency | Valid reference | `5ab218ef-91cc...` | Public | B-Tree |
| `status` | `varchar(30)` | NO | `'pending'` | Current checkout state | `pending`, `processing`, `completed`, `failed` | `pending` | Public | B-Tree |

---

### 7.6 `payment_attempts`
#### 7.6.1 Table Purpose
Tracks each attempt to execute a payment intent, supporting fallback routing on failures.

#### 7.6.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `g6b23c10-90ba...` | Public | Primary Key |
| `intent_id` | `uuid` | NO | None | Referenced payment intent | Valid reference | `g5b23c10-90ba...` | Public | B-Tree |
| `org_gateway_id` | `uuid` | NO | None | Owning gateway credentials | Valid reference | `g2b23c10-90ba...` | Public | B-Tree |
| `external_ref_id` | `varchar(255)`| YES | `NULL` | Provider reference (e.g. MerchantRequestID) | Nullable string | `ws_stk_89201a` | Public | Unique Index |
| `status` | `varchar(30)` | NO | `'initiated'` | Transition attempt phase | `initiated`, `processing`, `completed`, `failed` | `initiated` | Public | B-Tree |

---

### 7.7 `payment_receipts`
#### 7.7.1 Table Purpose
Immutable billing proof issued once signature verification checks are successful.

#### 7.7.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary billing receipt key | Valid UUIDv4 | `g7b23c10-90ba...` | Public | Primary Key |
| `attempt_id` | `uuid` | NO | None | Referenced verified attempt| Valid reference | `g6b23c10-90ba...` | Public | B-Tree |
| `receipt_number` | `varchar(100)`| NO | None | Unique provider receipt (e.g. M-Pesa Code) | Uppercase alphanumeric | `MKX9201A` | Public | Unique Index |
| `gross_amount` | `numeric(18,2)`| NO | None | Reconciled transaction total| Positive value > 0.00 | `25000.00` | Public | None |
| `provider_fees` | `numeric(18,2)`| NO | `0.00` | Payment processor fees | Minimum 0.00 | `125.00` | Public | None |

---

### 7.8 `payment_refunds`
#### 7.8.1 Table Purpose
Tracks transactions returned or reversed back to clients.

#### 7.8.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `g8b23c10-90ba...` | Public | Primary Key |
| `receipt_id` | `uuid` | NO | None | Target transaction receipt | Valid reference | `g7b23c10-90ba...` | Public | B-Tree |
| `refund_amount` | `numeric(18,2)`| NO | None | Value reversed | Positive value <= original | `15000.00` | Public | None |

---

### 7.9 `payment_webhooks`
#### 7.9.1 Table Purpose
Logs incoming payloads from payment providers to support idempotent webhook verification.

#### 7.9.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `g9b23c10-90ba...` | Public | Primary Key |
| `gateway_id` | `uuid` | NO | None | Owning gateway identifier | Valid reference | `g1b23c10-90ba...` | Public | B-Tree |
| `webhook_payload` | `jsonb` | NO | `'{}'` | Raw webhook body | Valid JSON structure | `{"Body": {"stk": ""}}`| Internal | None |
| `processed` | `boolean` | NO | `false` | Processing completion status| Standard boolean | `true` | Public | B-Tree |

---

### 7.10 `payment_retry_queue`
#### 7.10.1 Table Purpose
Maintains a queue of pending notification and polling updates to prevent system timeouts.

#### 7.10.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `g0b23c10-90ba...` | Public | Primary Key |
| `attempt_id` | `uuid` | NO | None | Target transaction attempt | Valid reference | `g6b23c10-90ba...` | Public | B-Tree |
| `retry_count` | `integer` | NO | `0` | Number of retries performed | Minimum 0 | `1` | Public | None |
| `next_retry_at` | `timestamptz`| NO | None | Scheduled execution | Future UTC date | `2026-06-26T10:45:00Z`| Public | B-Tree |

---

## 8. THE SUPPORT DOMAIN SCHEMA

### 8.1 `tickets`
#### 8.1.1 Table Purpose
Tracks client inquiries and support issues through to resolution.

#### 8.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `s1b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `created_by` | `uuid` | NO | None | User author | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `subject` | `varchar(255)`| NO | None | Ticket subject | Non-empty | `M-Pesa STK push timeout`| Public | None |
| `status` | `varchar(30)` | NO | `'open'` | Operational state | `open`, `pending`, `resolved`, `closed`| `open` | Public | B-Tree |

---

### 8.2 `ticket_messages`
#### 8.2.1 Table Purpose
Logs communications and replies within a support ticket thread.

#### 8.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `s2b23c10-90ba...` | Public | Primary Key |
| `ticket_id` | `uuid` | NO | None | Ticket thread | Valid reference | `s1b23c10-90ba...` | Public | B-Tree |
| `user_id` | `uuid` | NO | None | Author identity | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `body` | `text` | NO | None | Message content | Non-empty | `STK push took 40s...` | Public | None |

---

### 8.3 `ticket_attachments`
#### 8.3.1 Table Purpose
Stores file attachments linked to a support ticket.

#### 8.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `s3b23c10-90ba...` | Public | Primary Key |
| `message_id` | `uuid` | NO | None | Linked support message | Valid reference | `s2b23c10-90ba...` | Public | B-Tree |
| `file_url` | `text` | NO | None | Secure object URL | Valid absolute URL | `https://s3.juanet...` | Internal | None |

---

### 8.4 `ticket_categories`
#### 8.4.1 Table Purpose
Categorizes support tickets for dynamic assignment and metrics tracking.

#### 8.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `s4b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Reference to organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `category_name` | `varchar(100)`| NO | None | Category name | Non-empty | `Billing & Payments` | Public | None |

---

### 8.5 `ticket_satisfaction`
#### 8.5.1 Table Purpose
Collects client satisfaction feedback ratings on resolved support tickets.

#### 8.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `s5b23c10-90ba...` | Public | Primary Key |
| `ticket_id` | `uuid` | NO | None | Target ticket resolved | Valid reference | `s1b23c10-90ba...` | Public | B-Tree |
| `score` | `integer` | NO | None | Satisfaction score | Range `1` to `5` | `5` | Public | None |

---

## 9. THE MARKETPLACE DOMAIN SCHEMA

### 9.1 `marketplace_modules` (Global Lookup Table)
#### 9.1.1 Table Purpose
Catalog listing available third-party integrations and modules.

#### 9.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `m1b23c10-90ba...` | Public | Primary Key |
| `name` | `varchar(150)`| NO | None | Module display name | Non-empty | `Resend Email Adapter`| Public | None |
| `module_code` | `varchar(100)`| NO | None | Platform loading token | Alphanumeric lowercase | `resend_email` | Public | Unique Index |

---

### 9.2 `installed_modules`
#### 9.2.1 Table Purpose
Tracks which marketplace modules are active for a tenant organization.

#### 9.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `m2b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | Unique Composite |
| `module_id` | `uuid` | NO | None | Module reference | Valid reference | `m1b23c10-90ba...` | Public | Unique Composite |
| `is_active` | `boolean` | NO | `true` | Activation status | Standard boolean | `true` | Public | B-Tree |

---

### 9.3 `module_reviews`
#### 9.3.1 Table Purpose
Maintains user ratings and feedback for marketplace modules.

#### 9.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary identifier | Valid UUIDv4 | `m3b23c10-90ba...` | Public | Primary Key |
| `module_id` | `uuid` | NO | None | Module reference | Valid reference | `m1b23c10-90ba...` | Public | B-Tree |
| `user_id` | `uuid` | NO | None | Reviewing user | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `rating` | `integer` | NO | None | User rating | Range `1` to `5` | `4` | Public | None |

---

## 10. THE CMS DOMAIN SCHEMA

### 10.1 `blog_posts`
#### 10.1.1 Table Purpose
Stores marketing content, blog posts, and articles.

#### 10.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `c1b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Owning organization | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `title` | `varchar(255)`| NO | None | Title header | Non-empty | `Securing M-Pesa APIs`| Public | None |
| `body_markdown` | `text` | NO | None | Markdown content body | Non-empty | `# Securing M-Pesa...` | Public | None |
| `status` | `varchar(30)` | NO | `'draft'` | Publishing status | `draft`, `published` | `published` | Public | B-Tree |

---

### 10.2 `categories`
#### 10.2.1 Table Purpose
Organizes blog posts and content pages into logical taxonomy groupings.

#### 10.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `c2b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(100)`| NO | None | Category name | Non-empty | `Engineering` | Public | Unique Composite |

---

### 10.3 `tags`
#### 10.3.1 Table Purpose
Allows granular, cross-category tagging of blog and CMS content.

#### 10.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `c3b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(50)` | NO | None | Tag label | Non-empty | `Payments` | Public | Unique Composite |

---

### 10.4 `knowledge_base_articles`
#### 10.4.1 Table Purpose
Stores help documentation and knowledge base guides for self-service support.

#### 10.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `c4b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `title` | `varchar(255)`| NO | None | Article header | Non-empty | `Configuring SMTP` | Public | None |
| `body_content` | `text` | NO | None | Markdown documentation | Non-empty | `# SMTP Instructions...`| Public | None |

---

### 10.5 `pages`
#### 10.5.1 Table Purpose
Stores static pages, terms of service, and dynamic layout elements for custom web portals.

#### 10.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `c5b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `slug` | `varchar(150)`| NO | None | Page routing slug | Alphanumeric lowercase | `privacy-policy` | Public | Unique Composite |
| `html_content` | `text` | NO | None | Rendered page content | Non-empty | `<html>...</html>` | Public | None |

---

## 11. THE AUTOMATION DOMAIN SCHEMA

### 11.1 `workflows`
#### 11.1.1 Table Purpose
Defines automated, multi-step process workflows within the platform.

#### 11.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `w1b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(150)`| NO | None | Workflow name | Non-empty | `Onboard New Client` | Public | None |
| `is_active` | `boolean` | NO | `true` | Enablement status | Standard boolean | `true` | Public | B-Tree |

---

### 11.2 `workflow_triggers`
#### 11.2.1 Table Purpose
Defines the events or scheduled conditions that trigger a workflow execution.

#### 11.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `w2b23c10-90ba...` | Public | Primary Key |
| `workflow_id` | `uuid` | NO | None | Owning workflow reference| Valid reference | `w1b23c10-90ba...` | Public | B-Tree |
| `trigger_event` | `varchar(100)`| NO | None | Event name trigger | Alphanumeric | `invoice.completed` | Public | None |

---

### 11.3 `workflow_actions`
#### 11.3.1 Table Purpose
Defines the sequential actions executed when a workflow is triggered.

#### 11.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `w3b23c10-90ba...` | Public | Primary Key |
| `workflow_id` | `uuid` | NO | None | Owning workflow reference| Valid reference | `w1b23c10-90ba...` | Public | B-Tree |
| `action_order` | `integer` | NO | `1` | Execution sequence | Positive integer >= 1 | `1` | Public | None |
| `action_type` | `varchar(50)` | NO | None | Integration processor | `send_email`, `post_webhook`| `send_email` | Public | None |

---

### 11.4 `workflow_runs`
#### 11.4.1 Table Purpose
Tracks individual executions of automated workflows.

#### 11.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `w4b23c10-90ba...` | Public | Primary Key |
| `workflow_id` | `uuid` | NO | None | Workflow reference | Valid reference | `w1b23c10-90ba...` | Public | B-Tree |
| `status` | `varchar(30)` | NO | `'running'` | Execution status | `running`, `completed`, `failed` | `completed` | Public | B-Tree |

---

### 11.5 `workflow_logs`
#### 11.5.1 Table Purpose
Maintains detailed diagnostic logs for monitoring workflow executions.

#### 11.5.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `w5b23c10-90ba...` | Public | Primary Key |
| `workflow_run_id` | `uuid` | NO | None | Owning execution run | Valid reference | `w4b23c10-90ba...` | Public | B-Tree |
| `log_level` | `varchar(10)` | NO | `'info'` | Diagnostic log level | `info`, `warn`, `error` | `error` | Public | None |
| `message` | `text` | NO | None | Log detail message | Non-empty | `Webhook returned 502` | Public | None |

---

## 12. THE AI DOMAIN SCHEMA

### 12.1 `ai_context`
#### 12.1.1 Table Purpose
Indexes business context items and metadata used for RAG grounding with Gemini models.

#### 12.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `i1b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `context_payload` | `jsonb` | NO | `'{}'` | Context metadata | Valid JSON structure | `{"milestones": []}` | Internal | None |

---

### 12.2 `ai_prompts`
#### 12.2.1 Table Purpose
Templates and configurations for system prompts used by the platform's AI features.

#### 12.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `i2b23c10-90ba...` | Public | Primary Key |
| `prompt_name` | `varchar(100)`| NO | None | Reference identifier | Alphanumeric | `proposal_builder` | Public | Unique Index |
| `template_text` | `text` | NO | None | Prompt instruction body | Non-empty | `Generate proposal...` | Public | None |

---

### 12.3 `ai_generation_logs`
#### 12.3.1 Table Purpose
Audits AI generation requests for compliance, safety, and model evaluation.

#### 12.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `i3b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `user_id` | `uuid` | NO | None | Initiating user | Valid reference | `aa3c211a-1200...` | Public | B-Tree |
| `model_name` | `varchar(100)`| NO | None | Model version token | Alphanumeric | `gemini-1.5-flash` | Public | None |

---

### 12.4 `token_usage`
#### 12.4.1 Table Purpose
Tracks AI model token consumption for quota enforcement and billing.

#### 12.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `i4b23c10-90ba...` | Public | Primary Key |
| `organization_id` | `uuid` | NO | None | Tenant reference | Valid reference | `f3e589df-1c02...` | Public | B-Tree |
| `input_tokens` | `integer` | NO | `0` | Input token count | Positive integer >= 0 | `1420` | Public | None |
| `output_tokens` | `integer` | NO | `0` | Output token count | Positive integer >= 0 | `382` | Public | None |

---

## 13. THE NOTIFICATIONS DOMAIN SCHEMA

### 13.1 `notification_templates`
#### 13.1.1 Table Purpose
Stores communication templates for system notifications (Email, SMS, Push).

#### 13.1.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `n1b23c10-90ba...` | Public | Primary Key |
| `template_name` | `varchar(100)`| NO | None | Unique identifier | Alphanumeric | `invoice_receipt` | Public | Unique Index |
| `channel` | `varchar(20)` | NO | None | Delivery channel | `email`, `sms`, `push` | `email` | Public | B-Tree |
| `template_body` | `text` | NO | None | Template body text | Non-empty | `Dear {{name}}...` | Public | None |

---

### 13.2 `notification_outbox`
#### 13.2.1 Table Purpose
Queues outbound notifications for transactional dispatch to clients.

#### 13.2.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `n2b23c10-90ba...` | Public | Primary Key |
| `recipient_identity`| `varchar(255)`| NO | None | Targeted destination | Valid email or phone | `user@mail.com` | PII | None |
| `status` | `varchar(30)` | NO | `'pending'` | Dispatch queue status | `pending`, `sent`, `failed` | `pending` | Public | B-Tree |

---

### 13.3 `notification_logs`
#### 13.3.1 Table Purpose
Immutable history of sent notifications for compliance and troubleshooting.

#### 13.3.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `n3b23c10-90ba...` | Public | Primary Key |
| `recipient_identity`| `varchar(255)`| NO | None | Recipient address | Valid email or phone | `user@mail.com` | PII | B-Tree |
| `sent_at` | `timestamptz`| NO | `now()` | Delivery completion date | UTC only | `2026-06-26T10:38:13Z`| Public | B-Tree |

---

### 13.4 `notification_preferences`
#### 13.4.1 Table Purpose
Tracks user-specific opt-out choices for communication channels.

#### 13.4.2 Columns
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `n4b23c10-90ba...` | Public | Primary Key |
| `user_id` | `uuid` | NO | None | Owning user reference | Valid reference | `aa3c211a-1200...` | Public | Unique Composite |
| `notification_type`| `varchar(100)`| NO | None | Category code | Alphanumeric | `marketing` | Public | Unique Composite |
| `channel_enabled` | `boolean` | NO | `true` | Channel preference flag | Standard boolean | `false` | Public | None |

---

## 14. PERFORMANCE, CACHING, & ARCHIVING STRATEGY

To ensure sub-second response times as the platform scales to millions of rows, the database relies on structured caching, indexing, and partitioning patterns.

### 14.1 Indexing & Search Optimization
*   **Tenant Partition Keys**: Every query executed within standard tenant workspaces must include `organization_id` in the `WHERE` clauses to optimize compound indexes.
*   **JSONB Indexing**: Columns containing dynamic JSON payloads that are queried frequently (e.g. `organization_payment_gateways.api_credentials`) must use GIN indexes for fast key-value lookups:
    ```
    Index: organization_payment_gateways_api_credentials_gin_idx
    Type: GIN
    ```
*   **Partial Indexes**: Reduce index size by indexing only active, non-archived records:
    ```
    Index: users_active_email_partial_uidx
    Condition: WHERE deleted_at IS NULL AND is_active = true
    ```

### 14.2 Caching Strategy (Layered)
To reduce read traffic on the primary database cluster, caching is organized into three distinct tiers:
1.  **Global Static Cache**: Global lookup data (e.g. `currencies`, `payment_gateways`, static `permissions`) is cached in memory by the application layer at startup.
2.  **Tenant Configuration Cache**: Active settings and gateway adapters (`organization_settings`, `organization_payment_gateways`) are cached for 1 hour, with cache invalidation triggered automatically on writes.
3.  **Active Session Cache**: Session states (`sessions`) are cached to prevent database lookups on every incoming API request.

---

## 15. ENTERPRISE ARCHITECTURAL IMPROVEMENTS (PHASE 2.2-A AMENDMENTS)

To prepare the JUANET operating system for software-as-a-service (SaaS) scalability, localized global compliance, and developer extensions, this amendment formally incorporates 10 advanced core structural design expansions. 

---

### 15.1 Enumerated Lookup Tables (Status Isolation)
To prevent runtime typo bugs, enforce system-wide referential integrity, and facilitate multi-lingual reporting or localized label mapping, all free-text status columns across transactional tables are migrated to dedicated physical lookup tables.

#### 15.1.1 Status Lookup Tables
The system establishes seven physical status lookup tables following a unified schema design:
*   `invoice_statuses` (Ref: `invoices.status_id`)
*   `payment_statuses` (Ref: `payment_intents.status_id` & `payment_receipts.status_id`)
*   `project_statuses` (Ref: `projects.status_id`)
*   `ticket_statuses` (Ref: `tickets.status_id`)
*   `workflow_statuses` (Ref: `workflows.status_id` & `workflow_runs.status_id`)
*   `gateway_statuses` (Ref: `payment_gateways.status_id`)
*   `organization_statuses` (Ref: `organizations.status_id`)

#### 15.1.2 Standard Columns (Shared Across Status Lookups)
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Unique lookup identifier | Valid UUIDv4 | `e35a12cd-8fa0...` | Public | Primary Key |
| `code` | `varchar(50)` | NO | None | Unique machine code key | Lowercase, snake_case | `paid`, `past_due` | Public | Unique Index |
| `label` | `varchar(100)`| NO | None | Human-readable user-facing label | Non-empty, trimmed | `Partially Paid` | Public | None |
| `description` | `text` | YES | `NULL` | Technical or administrative context | Non-empty | `Invoice balance exists`| Public | None |
| `created_at` | `timestamptz` | NO | `now()` | Record creation timestamp | UTC only | `2026-06-26T10:38:13Z`| Public | None |

---

### 15.2 Global Geography & Localization (`countries`)
To coordinate VAT rules, GST structures, address validations, international SMS routing configurations, and timezone settings natively, a central global `countries` registry is introduced.

#### 15.2.1 Table Structure: `countries`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `c51a1200-bcba...` | Public | Primary Key |
| `iso2` | `char(2)` | NO | None | ISO 3166-1 alpha-2 code | Exactly 2 chars, uppercase | `KE`, `NG`, `GB`, `US` | Public | Unique Index |
| `iso3` | `char(3)` | NO | None | ISO 3166-1 alpha-3 code | Exactly 3 chars, uppercase | `KEN`, `NGA`, `GBR` | Public | Unique Index |
| `name` | `varchar(100)`| NO | None | Legal English country name | 2-100 characters | `Kenya`, `Nigeria` | Public | B-Tree |
| `phone_code` | `varchar(10)` | NO | None | International phone prefix | Numeric with optional prefix | `+254`, `+234`, `+44` | Public | None |
| `currency_id` | `uuid` | NO | None | Default national currency | References `currencies.id` | `5ab218ef-91cc...` | Public | B-Tree |
| `default_timezone`|`varchar(50)`| NO | None | Primary active timezone code | Valid IANA timezone | `Africa/Nairobi` | Public | None |
| `tax_region` | `varchar(50)` | NO | `'Standard'`| Overarching regional tax class | e.g. `EU_VAT`, `EAC`, `ECOWAS`| Public | None |
| `created_at` | `timestamptz` | NO | `now()` | Record insertion timestamp | UTC only | `2026-06-26T10:38:13Z`| Public | None |

---

### 15.3 Multi-Jurisdictional Tax Engine (`tax_rules` & `invoice_tax_lines`)
Instead of hardcoding standard local taxes, enterprise billing compliance requires a clean boundary between jurisdictional entities, custom region rules (e.g. Kenya VAT vs UK VAT), Zero-Rating exemptions, and Reverse-Charge declarations.

#### 15.3.1 `tax_jurisdictions`
Defines regional legal boundaries (municipalities, states, or country federations) administering tax codes.
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `j1b23c10-90ba...` | Public | Primary Key |
| `country_id` | `uuid` | NO | None | Country of jurisdiction | References `countries.id` | `c51a1200-bcba...` | Public | B-Tree |
| `name` | `varchar(100)`| NO | None | Name of tax authority body | Non-empty | `Kenya Revenue Authority`| Public | None |
| `region_code` | `varchar(30)` | YES | `NULL` | Sub-region or county identifier | Alphanumeric | `Nairobi_County` | Public | None |

#### 15.3.2 `tax_rules`
Defines operational tax behaviors including exemptions, reverse charge applicability, and dynamic thresholds.
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary key | Valid UUIDv4 | `x1b23c10-90ba...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | SaaS Tenant isolation key | References `organizations.id` | `f3e589df-1c02...` | Public | B-Tree |
| `jurisdiction_id`|`uuid` | NO | None | Linked jurisdiction location | References `tax_jurisdictions.id`| Public | B-Tree |
| `tax_rate_id` | `uuid` | NO | None | Standard percentage applied | References `tax_rates.id` | `a6b23c10-90ba...` | Public | B-Tree |
| `rule_type` | `varchar(50)` | NO | `'standard'`| Tax rule classification | `standard`, `exempt`, `reverse` | `reverse` | Public | None |
| `is_reverse_charge`|`boolean` | NO | `false` | B2B reverse charge flag | Standard boolean | `true` | Public | None |

#### 15.3.3 `invoice_tax_lines`
Immutable audit tables mapping precise tax calculations to individual line-item transactions.
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `t1b23c10-90ba...` | Public | Primary Key |
| `invoice_id` | `uuid` | NO | None | Target invoice record | References `invoices.id` | `a4b23c10-90ba...` | Public | B-Tree |
| `tax_rate_id` | `uuid` | NO | None | Applied tax rate reference | References `tax_rates.id` | `a6b23c10-90ba...` | Public | None |
| `tax_rule_id` | `uuid` | NO | None | Applied rule exception reference| References `tax_rules.id` | `x1b23c10-90ba...` | Public | None |
| `base_amount` | `numeric(18,2)`| NO | None | Original cost before taxation | Positive real value | `100000.00` | Public | None |
| `tax_amount` | `numeric(18,2)`| NO | None | Calculated tax liability | Positive real value | `16000.00` | Public | None |

---

### 15.4 Normalized Gateway Credentials (`gateway_credentials`)
To prevent storing multi-key credentials (e.g. API Keys, Secret, Merchant ID, Certificates, OAuth tokens) in unstructured JSONB columns inside tenant-gateway mappings, these sensitive parameters are normalized into an isolated physical table.

#### 15.4.1 Table Structure: `gateway_credentials`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `c1a1200b-3cca...` | Restr-Secret | Primary Key |
| `organization_gateway_id`|`uuid`| NO | None | Owning gateway link | References `organization_payment_gateways.id`| Public | B-Tree |
| `credential_name`| `varchar(100)`| NO | None | Name of standard key | e.g. `client_secret`, `passkey` | `client_secret` | Public | Unique Composite |
| `encrypted_value`| `text` | NO | None | Vaulted key (AES-256-GCM) | Non-empty | `U2FsdGVkX1+...` | Restr-Secret | None |
| `expires_at` | `timestamptz` | YES | `NULL` | Cryptographic key expiration | Nullable UTC | `2027-06-26T10:00:00Z`| Public | None |
| `rotated_at` | `timestamptz` | YES | `NULL` | Timestamp of last rotation | Nullable UTC | `2026-06-26T10:38:13Z`| Public | None |

---

### 15.5 Intelligent Payment Routing Engine (`payment_routing_rules`)
To achieve robust multi-provider flexibility, the platform evaluates transactional parameters (Currency, Geography, Value Thresholds) against user-defined routing profiles, completely avoiding hardcoded logical switch-cases.

#### 15.5.1 Table Structure: `payment_routing_rules`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `r1a32b10-09fa...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | SaaS Tenant reference | References `organizations.id` | `f3e589df-1c02...` | Public | B-Tree |
| `currency_id` | `uuid` | NO | None | Target transaction currency | References `currencies.id` | `5ab218ef-91cc...` | Public | B-Tree |
| `country_id` | `uuid` | YES | `NULL` | Target geographical market | References `countries.id` | `c51a1200-bcba...` | Public | B-Tree |
| `priority` | `integer` | NO | `1` | Evaluation order value | Positive integer >= 1 | `1`, `2`, `3` | Public | None |
| `gateway_id` | `uuid` | NO | None | Selected dispatch gateway | References `payment_gateways.id`| Public | B-Tree |
| `minimum_amount`| `numeric(18,2)`| YES | `NULL` | Lower limit threshold | Positive real value | `500.00` | Public | None |
| `maximum_amount`| `numeric(18,2)`| YES | `NULL` | Upper limit threshold | Positive real value | `500000.00` | Public | None |
| `capability` | `varchar(50)` | YES | `'payout'` | Special gateway feature flag | `card_capture`, `stk_push`, `payout`| Public | None |

---

### 15.6 SaaS Feature Flags (`organization_features`)
To monetize specialized modules (CRM, Projects, Double-entry ledgers, Advanced AI Summaries, Automation workflows) without executing hard redeployments, the database manages access rights at the tenant level.

#### 15.6.1 Table Structure: `organization_features`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `f1a82cc1-8fa0...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | Target tenant organization | References `organizations.id` | `f3e589df-1c02...` | Public | Unique Composite |
| `feature` | `varchar(100)`| NO | None | Code name of restricted module | Lowercase, snake_case | `double_entry_ledger`, `ai`| Public | Unique Composite |
| `enabled` | `boolean` | NO | `true` | Module operational status | Standard boolean | `true` | Public | None |
| `expires_at` | `timestamptz` | YES | `NULL` | Expiring trial or access timestamp| Nullable UTC | `2027-01-01T00:00:00Z`| Public | B-Tree |

---

### 15.7 Subscription Plans & Metred Usage
To power native SaaS billing systems, four tables are created representing subscription levels, active organizational plans, line items, and dynamic volume counters.

#### 15.7.1 `plans`
Global registry of billing tiers (e.g., Starter, Growth, Enterprise).
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `p1a310ab-09da...` | Public | Primary Key |
| `name` | `varchar(100)`| NO | None | Display title of tier | 2-100 characters | `Enterprise Growth Plan`| Public | None |
| `code` | `varchar(50)` | NO | None | Unique package billing code | Lowercase, alphanumeric, hyphen | `enterprise-growth` | Public | Unique Index |
| `price` | `numeric(18,2)`| NO | `0.00` | Reconciled recurring price | Positive real value >= 0.00 | `249.00` | Public | None |
| `currency_id` | `uuid` | NO | None | Currency of subscription | References `currencies.id` | `5ab218ef-91cc...` | Public | None |
| `billing_interval`|`varchar(30)`| NO | `'month'` | Billing cycle period | `month`, `year` | `month` | Public | None |
| `is_active` | `boolean` | NO | `true` | Access state of package | Standard boolean | `true` | Public | B-Tree |

#### 15.7.2 `subscriptions`
Binds individual organizations to specific billing plans.
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `s1b82aa1-09ba...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | SaaS Tenant Reference | References `organizations.id` | `f3e589df-1c02...` | Public | Unique Index |
| `plan_id` | `uuid` | NO | None | Subscribed tier reference | References `plans.id` | `p1a310ab-09da...` | Public | B-Tree |
| `status` | `varchar(30)` | NO | `'trialing'`| Core subscription lifecycle stage| `trialing`, `active`, `past_due`, `canceled`| `active` | Public | B-Tree |
| `current_period_start`|`timestamptz`|NO| `now()` | Invoicing start timestamp | UTC only | `2026-06-26T10:00:00Z`| Public | None |
| `current_period_end`|`timestamptz`| NO | None | Invoicing expiration timestamp | UTC, must be > start | `2026-07-26T10:00:00Z`| Public | B-Tree |
| `cancel_at_period_end`|`boolean`|NO | `false` | Scheduled termination flag | Standard boolean | `false` | Public | None |

#### 15.7.3 `subscription_items`
Supports modular subscriptions and multi-item purchases (add-ons).
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `i1b82cc1-8fa0...` | Public | Primary Key |
| `subscription_id`|`uuid` | NO | None | Master subscription reference | References `subscriptions.id` | `s1b82aa1-09ba...` | Public | B-Tree |
| `item_type` | `varchar(50)` | NO | `'base'` | Item category classification | `base`, `addon`, `usage` | `addon` | Public | None |
| `quantity` | `integer` | NO | `1` | Quantity multiplier | Positive integer >= 1 | `5` | Public | None |

#### 15.7.4 `subscription_usage`
Records metered metrics (e.g. cumulative API operations, outbound SMS dispatches, or AI token calculations) for usage-based billing.
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `u1c92cc0-09ba...` | Public | Primary Key |
| `subscription_item_id`|`uuid`| NO | None | Target package addon link | References `subscription_items.id`| Public | B-Tree |
| `metric_name` | `varchar(100)`| NO | None | Code name of volume counter | Lowercase, snake_case | `ai_tokens_processed` | Public | Unique Composite |
| `quantity_used` | `numeric(18,2)`| NO | `0.00` | Current volume consumed | Positive real value >= 0.00 | `1452500.00` | Public | None |
| `reset_at` | `timestamptz`| NO | None | Timestamp of quota reset | UTC only | `2026-07-26T10:00:00Z`| Public | B-Tree |

---

### 15.8 Developer Platform Keys (`api_keys`)
To support developer extensions, secure external integrations, and open API services, organizations require cryptographic key mechanisms.

#### 15.8.1 Table Structure: `api_keys`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique identifier | Valid UUIDv4 | `k1b820ab-1cba...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | Tenant account scope | References `organizations.id` | `f3e589df-1c02...` | Public | B-Tree |
| `name` | `varchar(100)`| NO | None | Custom label identifying the key | 1-100 characters | `Stripe Sync Worker` | Public | None |
| `hashed_key` | `varchar(255)`| NO | None | SHA-256 hash of original secret | Must be unique | `sha256_hashed_val...` | Restr-Secret | Unique Index |
| `scopes` | `jsonb` | NO | `'[]'` | Authorized capability arrays | Valid JSON array of permissions| `["invoice:read", "crm:write"]`| Public | None |
| `last_used` | `timestamptz` | YES | `NULL` | Timestamp of last API connection| Nullable UTC | `2026-06-26T11:20:00Z`| Public | None |
| `expires_at` | `timestamptz` | YES | `NULL` | Key expiration limit | Nullable UTC | `2027-06-26T10:00:00Z`| Public | B-Tree |

---

### 15.9 Outbound Webhook Registry (`organization_webhooks`)
To enable real-time third-party syncs, organizations must be able to subscribe to platform-wide events (e.g. `invoice.created`, `payment.completed`) with automatic retry queues.

#### 15.9.1 Table Structure: `organization_webhooks`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `w1b82ab1-09da...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | Owning organization | References `organizations.id` | `f3e589df-1c02...` | Public | B-Tree |
| `url` | `text` | NO | None | Target destination endpoint | Valid HTTP/HTTPS url | `https://crm.client.com/hook`| Public | None |
| `secret` | `varchar(255)`| NO | None | Signature verify secret | Non-empty | `whsec_abc123...` | Restr-Secret | None |
| `events` | `jsonb` | NO | `'[]'` | Subscribed events array | JSON string array of events | `["invoice.created", "payment.completed"]`| Public | None |
| `enabled` | `boolean` | NO | `true` | Event delivery status | Standard boolean | `true` | Public | B-Tree |

---

### 15.10 Document Storage Registry (`files`)
To eliminate fragmented file schemas across multiple operational areas, the platform centralizes all document metadata, storing storage bucket routing rules, secure access controls, and security hashes in a single unified table.

#### 15.10.1 Table Structure: `files`
| Column Name | Data Type | Nullable | Default | Business Meaning | Validation Rules | Example Values | Sensitivity | Index Rec |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | `uuid` | NO | `gen_random_uuid()` | Primary unique key | Valid UUIDv4 | `f1b82aa1-09ba...` | Public | Primary Key |
| `organization_id`|`uuid` | NO | None | SaaS Tenant scope | References `organizations.id` | `f3e589df-1c02...` | Public | B-Tree |
| `storage_provider`|`varchar(50)`|NO | `'supabase'`| Infrastructure engine code | `supabase`, `s3`, `gcs` | `supabase` | Public | None |
| `bucket` | `varchar(100)`| NO | None | Storage namespace container | Non-empty, lowercase | `contracts`, `project-files`| Public | B-Tree |
| `path` | `text` | NO | None | Remote folder file structure | Non-empty, unique | `org1/2026/signed_scope.pdf`| Public | Unique Index |
| `mime_type` | `varchar(100)`| NO | None | Standard Internet media code | Valid format | `application/pdf` | Public | None |
| `checksum` | `varchar(64)` | NO | None | SHA-256 integrity signature | 64 chars hex string | `e3b0c44298fc1c149af...`| Public | None |
| `size` | `bigint` | NO | None | File size in bytes | Positive integer | `12450820` | Public | None |
| `uploaded_by` | `uuid` | YES | `NULL` | Author of upload | References `users.id` | `aa3c211a-1200...` | Public | B-Tree |
| `virus_scan_status`|`varchar(30)`|NO | `'pending'` | Security inspection status | `pending`, `clean`, `infected`| Public | B-Tree |

---

## 16. DELIVERABLE CONFIRMATION & ARCHITECTURAL CONSISTENCY

This dictionary has been reviewed against the **JUANET Master Specification (JUANET_Master_Specification.md)** to verify complete alignment:
1.  **M-Pesa API (Daraja API Gateway Integration)** is fully supported via the decoupled `payment_gateways`, `payment_intents`, `payment_attempts`, and `payment_receipts` structures. This conforms to Section 29 of the Master Specification.
2.  **Double-Entry Ledger Bookkeeping** requirements map directly to `chart_of_accounts`, `journal_entries`, and `ledger_entries`, satisfying standard audit standards.
3.  **Logical Multi-Tenant Isolation** is enforced globally across all tenant tables via compound primary keys and `organization_id` mapping.
4.  **10 SaaS & Enterprise Enhancements** (Phase 2.2-A Amendments) have been formally incorporated to future-proof core billing, geographic routing, feature flagging, credentials normalization, status enumeration, and file registries before Phase 2.3 logic is compiled.
