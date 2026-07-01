# JUANET Marketplace Vendor Operations & Merchant Management Engine Manual
## Phase 2.3.2H.7 — Onboarding Pipelines, Merchant Workspaces, Compliance Engines, Settlement Configurations, and Performance SLAs

**Document Version:** 2.0  
**Author:** Principal Enterprise Solutions Architect, VP of Merchant Operations Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. VENDOR ARCHITECTURE PHILOSOPHY

The **JUANET Vendor Operations & Merchant Management Engine** governs the onboarding, verification, compliance, organizational RBAC, performance SLA evaluation, storefront management, commission structuring, and operational lifecycle of independent sellers (vendors) within the Marketplace bounded context. This engine provides the critical infrastructure allowing thousands of global merchants to sell, fulfill, and settle transaction volumes alongside native JUANET first-party inventories.

```
                    [VENDOR BOUNDED CONTEXT ISOLATION & DATA ROUTING]

   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                       VENDOR OPERATIONS BOUNDED CONTEXT                         │
   │                                                                                 │
   │  ┌───────────────────────┐         ┌───────────────────┐    ┌────────────────┐  │
   │  │   Vendor Aggregate    │ ◄─────► │ Compliance Engine │ ◄─►│ Performance    │  │
   │  │    (Root: Vendor ID)  │         │ KYC / KYB / OFAC  │    │ SLAs Engine    │  │
   │  └───────────────────────┘         └─────────┬─────────┘    └────────────────┘  │
   │                                              │                                  │
   │                                      Transactional Outbox                       │
   │                                              ▼                                  │
   │                                    ┌───────────────────┐                        │
   │                                    │  Outbox Publisher │                        │
   │                                    └─────────┬─────────┘                        │
   └──────────────────────────────────────────────┼──────────────────────────────────┘
                                                  │
                                       (Canonical Event Stream)
                                                  ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                             DOWNSTREAM BOUNDED CONTEXTS                         │
   │                                                                                 │
   │  ┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐ │
   │  │       Catalog        │   │      Inventory       │   │       Finance        │ │
   │  │ - Variant Assign     │   │ - Virtual Allocation │   │ - Settlement Ledgers │ │
   │  │ - Listing Permission  │   │ - SLA Alerts         │   │ - Commision Audits   │ │
   │  └──────────────────────┘   └──────────────────────┘   └──────────────────────┘ │
   │  ┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐ │
   │  │         CRM          │   │       Support        │   │        Orders        │ │
   │  │ - Merchant Comm      │   │ - Seller Disputes    │   │ - Split Dispatches   │ │
   │  │ - Compliance Alerts  │   │ - Ticket Routing     │   │ - SLA Infractions    │ │
   │  └──────────────────────┘   └──────────────────────┘   └──────────────────────┘ │
   └─────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Separation of Bounded Contexts & Concerns
To maintain Domain-Driven Design (DDD) isolation and protect primary transactional databases, the Vendor Bounded Context enforces strict limits:
*   **The Vendor acts as the Aggregate Root** for all administrative, legal, and operational metrics.
*   **Merchant Independence**: Each merchant operates inside an isolated logical workspace. Merchant data remains separated from other vendors using hard multi-tenant Row-Level Security (RLS) filters.
*   **Marketplace Governance**: The platform operator exercises programmatic oversight (e.g., automated account suspensions, commission updates, category gatekeeping) without accessing merchant database cells directly.
*   **Operational Isolation**: Heavy business logic (e.g., processing KYB document checks, compiling daily sales charts, evaluating compliance risks) is routed to background workers or read replicas, keeping active checkout databases fast and responsive.
*   **Asynchronous Communications**: All cross-boundary operations execute via standardized outbox events. The Vendor Operations domain never issues synchronous database queries to downstream systems.

### 1.2 Absolute Bounded Boundaries: Why Vendor Operations Never Directly Writes
To protect database integrity and maintain clean system boundaries, the Vendor Operations domain is strictly forbidden from executing direct writes to the following bounded contexts:
*   **Orders**: Order updates, partial fulfillment updates, and SLA tracking metrics are consumed asynchronously. The Vendor engine cannot modify orders or write shipment items directly.
*   **Inventory**: Physical stock quantities and warehouse balances are owned exclusively by the Inventory context. The Vendor engine registers virtual warehouse references but does not write to inventory ledgers.
*   **Payments**: Captures, refunds, and payment voids are handled by the Payment context. The Vendor engine publishes settlement profiles but does not access gateway sessions directly.
*   **Finance**: Vendor management is not a financial ledger. All ledger postings, payouts, reserve accruals, and balance balances are recorded asynchronously inside the Finance Bounded Context using Outbox event logs.
*   **CRM**: Vendor interactions, communications, and customer feedback are recorded on secondary CRM write engines via outbox event subscribers.
*   **Support**: Helpdesk tickets, dispute reviews, and seller arbitration tasks are managed within the Support context. The Vendor engine links to these cases using read-only keys.

---

## 2. VENDOR AGGREGATE MODEL

The **Vendor Aggregate** represents the logical consistency and transaction boundary for all vendor-related data. The root table `public.vendors` manages and coordinates all sub-entities to ensure atomic writes and prevent inconsistent states.

```
                    [VENDOR AGGREGATE ENTITY RELATIONSHIPS]

┌──────────────────────────────────────────────────────────────────────────────┐
│  public.vendors (Aggregate Root)                                             │
│  - id (UUIDv7 Primary Key)                                                   │
│  - organization_id (Tenant Isolation Key)                                    │
│  - business_name (Legal Name String)                                         │
│  - country_code (ISO 3166-1 alpha-2)                                         │
│  - lifecycle_state (FSM State Variable)                                      │
│  - current_risk_score (Computed Numeric)                                     │
│  - version (Optimistic Lock Version)                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_stores (1:N Relationship)                             │   │
│   │  - id, display_name, slug, branding_config (JSONB), active_themes    │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_business_profiles (1:1 Relationship)                  │   │
│   │  - registration_number, tax_id, registered_address_id, ky_status     │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_staff (1:N Relationship)                              │   │
│   │  - id, user_id, email, rbac_role, active_flag, temporal_expiration   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_settlement_profiles (1:1 Relationship)                │   │
│   │  - id, bank_routing_number, bank_account_hash, currency_code, state  │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_commissions (1:N Relationship)                        │   │
│   │  - id, category_id, rate_percentage, flat_fee_amount, active_until   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.vendor_documents (1:N Relationship)                          │   │
│   │  - id, document_type, s3_key_hash, verified_at, expires_at, status   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Complete Entity Ownership Dictionary
The Vendor Operations Bounded Context maintains exclusive transactional authority over the following sub-entities:

| Sub-Entity | Physical Table Name | Domain Purpose | Ownership & Mutation Policy |
| :--- | :--- | :--- | :--- |
| **Vendors** | `public.vendors` | Root aggregate record coordinating overall seller state, multi-tenant mappings, legal structures, compliance flags, risk scores, and lifecycles. | **Absolute Root.** Only mutable via formal command handlers and FSM transition rules. |
| **Merchant Store** | `public.vendor_stores` | Public storefront configurations, display slugs, localized branding presets, social linkages, templates, and active catalog filters. | Owned by Root. Editable by authorized vendor staff. |
| **Vendor Profiles** | `public.vendor_business_profiles` | Verifiable corporate information including incorporation dates, legal structure indices, contact points, and validation flags. | Owned by Root. Locked once verification begins. |
| **Business Information**| Columns on `vendor_business_profiles` | Physical registry registration numbers, VAT identifiers, and certified corporate addresses. | Owned by Root. Modification triggers re-onboarding. |
| **Legal Information** | Columns on `vendor_business_profiles` | Parent company identifiers, corporate ownership structures, and beneficial owner registration histories. | Owned by Root. Modified during deep compliance reviews. |
| **Verification Status** | `public.vendor_verifications` | Audit histories detailing individual verification tasks (KYC, KYB, OFAC Sanctions, and AML screening checks). | Owned by Root. Read-only records appended by automated compliance tasks. |
| **Merchant Staff** | `public.vendor_staff` | User profiles associated with the vendor organization, tracking system accounts, active flags, and permission maps. | Owned by Root. Managed by vendor administrators. |
| **Merchant Roles** | Column: `rbac_role` | Standardized internal security profiles (e.g., 'ADMIN', 'FINANCE', 'CATALOG') governing feature access. | Managed by Root. Staff roles must conform to system limits. |
| **Store Settings** | `public.vendor_store_settings` | Operational configurations including holiday modes, automated warning thresholds, pickup scheduling rules, and warehouse mappings. | Owned by Root. Updated by vendor staff. |
| **Store Branding** | Column: `branding_config` on `vendor_stores` | Structured JSONB fields containing custom color selections, design assets, cover layouts, and font preferences. | Owned by Root. Subject to content moderation filters. |
| **Store Policies** | `public.vendor_store_policies` | Custom shipping rates, return instructions, warranty terms, and privacy disclosures displayed to buyers. | Owned by Root. Content is validated against system rules. |
| **Business Hours** | `public.vendor_business_hours` | Weekly operational schedules and custom warehouse closure dates used for SLA planning. | Owned by Root. Validated to prevent overlapping schedules. |
| **Warehouse References**| `public.vendor_warehouses` | Virtual mappings associating vendor-owned facilities with downstream fulfillment nodes. | Owned by Root. Registers node keys without catalog stock data. |
| **Settlement Profiles** | `public.vendor_settlement_profiles` | Bank routing data, encrypted IBAN codes, swift codes, payout currency settings, and bank ledger records. | Owned by Root. Changes trigger a 72-hour payout hold. |
| **Commission Profiles** | `public.vendor_commissions` | Active commission structures mapping category fees, flat transaction charges, and dynamic pricing tiers. | Owned by Root. Editable only by platform operators. |
| **Vendor Documents** | `public.vendor_documents` | Verification file records containing encrypted document links, types, metadata, and expiration dates. | Owned by Root. Append-only data; deletions are blocked. |
| **Vendor Metadata** | `public.vendor_metadata` | Flexible JSONB container holding localized parameters, custom platform tags, and integration settings. | Owned by Root. |
| **Vendor Notes** | `public.vendor_notes` | Administrative logs, operational notes, and support remarks written during audits. | Append-only. Modifying or deleting logs is blocked. |
| **Vendor Attachments** | `public.vendor_attachments` | Safe, audited file lists tracking vendor-uploaded contracts, PDF agreements, and certificates. | Append-only. |
| **Vendor Preferences** | `public.vendor_preferences` | System preferences including language targets, communication formats, and alert thresholds. | Owned by Root. |
| **Vendor AI Settings** | `public.vendor_ai_settings` | Feature flags and opt-in settings for advisory AI systems, including auto-reorder and smart-pricing tools. | Owned by Root. Humans must confirm critical actions. |
| **Vendor Audit Records**| `public.vendor_audits` | Detailed transactional logs capturing change histories, IP addresses, actor keys, and session contexts. | **Immutable Append-Only.** Modifications are blocked. |

### 2.2 Aggregate Consistency and Transaction Rules
*   **Uniqueness of Active Stores**: A vendor aggregate is limited to one primary public storefront (`public.vendor_stores`) per target organization tenant to prevent sellers from flooding directories with duplicate listings.
*   **Verification Safeguards**: Settlement profiles (`public.vendor_settlement_profiles`) cannot be transitioned to `ACTIVE` unless KYB and bank verification tasks have successfully resolved.
*   **Commission Guardrails**: Default commission rates defined inside `public.vendor_commissions` must range between $0.00\%$ and $95.00\%$. Commission values cannot be configured below zero.
*   **Optimistic Lock Guarding**: The parent table `public.vendors` employs a `version` column to enforce optimistic concurrency control, preventing simultaneous administrative modifications from corrupting seller profiles.

---

## 3. MERCHANT ONBOARDING PIPELINE

The Merchant Onboarding Pipeline is a highly audited, multi-stage compliance pipeline designed to verify seller identities and register legal businesses before enabling active sales features.

```
                    [MERCHANT ONBOARDING PIPELINE STEPS]

   START REGISTRATION ──► Verify Email ──► Collect KYB Records (Tax, ID, Bank)
                                                │
                                                ▼
                                         OFAC / AML Check ──► Failed? (Block Account)
                                                │
                                                ▼
                                         Penny-Drop Bank Verification
                                                │
                                                ▼
                                         Maker-Checker Review (Ops Team)
                                                │
                                                ▼
                                         Provision Storefront & Catalog permissions
```

### 3.1 Step-by-Step Onboarding Workflow
1.  **Account Registration**: The merchant inputs their primary contact details, country of operation, and corporate business name, initializing a vendor record in the `Draft` state.
2.  **Email Verification**: The platform sends a verification link with an ephemeral token to the merchant's registered email address.
3.  **Identity Verification (KYC)**: Collects identity document scans and liveness checks from the company’s primary beneficial owners and legal representatives.
4.  **Business Verification (KYB)**: Captures official business registry numbers, incorporation documents, and tax registration certificates (e.g., EIN, VAT).
5.  **Tax & Bank Verification**: Verifies bank details using small deposit checks ("penny drops") or digital verification tools (e.g., Plaid). Checks tax registration identifiers against active government databases (e.g., IRS, VIES).
6.  **Compliance Screening (OFAC/AML)**: Runs business profiles, beneficial owners, and entities against international sanctions lists, PEP databases, and AML registers.
7.  **Maker-Checker Review**: Platforms reviews are governed by a strict two-person control system:
    *   *Maker*: An automated compliance task or first-level operations agent reviews the uploaded files and recommends the profile for approval.
    *   *Checker*: A senior compliance officer or automated approval worker reviews the recommendation and authorizes final activation.
8.  **Storefront & Catalog Provisioning**: Once authorized, the system provisions default storefront configurations, enables listing creations within assigned categories, and sets the seller state to `Active`.

---

## 4. VENDOR LIFECYCLE FINITE STATE MACHINE (FSM)

All merchant accounts route through a strict, deterministic state machine to govern active privileges and enforce platform compliance rules.

```
                        [VENDOR LIFECYCLE STATE TRANSITIONS]

                            ┌──────────────────────┐
                            │        Draft         │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │      Registered      │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │ Pending Verification │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │Verification Progress │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │  Compliance Review   │       │   Rejected   │
                            └──────────┬───────────┘       └──────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Approved       │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │     Provisioning     │
                            └──────────┬───────────┘
                                       │
                                       ▼
         ┌────────────────────────►┌───┴──────────┐◄────────────────────────┐
         │                         │    Active    │                         │
         │                         └───┬──────────┘                         │
   (Reactivation)                      │                                    │
         │                             ▼                              (Reactivation)
         │                  ┌──────────────────────┐                        │
         ├──────────────────┤      Restricted      ├────────────────────────┤
         │                  └──────────┬───────────┘                        │
         │                             │                                    │
         │                             ▼                                    │
         │                  ┌──────────────────────┐                        │
         ├──────────────────┤      Suspended       ├────────────────────────┤
         │                  └──────────┬───────────┘                        │
         │                             │                                    │
         │                             ▼                                    │
         │                  ┌──────────────────────┐                        │
         └──────────────────┤ Under Investigation  ├────────────────────────┘
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │        Closed        ├────────────────────────┐
                            └──────────┬───────────┘                        │
                                       │                                    ▼
                                       ▼                             ┌──────────────┐
                            ┌──────────────────────┐                 │   Deleted    │
                            │       Archived       │                 └──────────────┘
                            └──────────────────────┘
```

### 4.1 State Definitions and Transition Rules

#### 4.1.1 State Dictionary
1.  **Draft**: Account created but missing vital contact details or profile configurations.
2.  **Registered**: Account initialized, and primary contact email verified.
3.  **Pending Verification**: Merchant submitted their KYB documents; awaiting verification tasks.
4.  **Verification In Progress**: Document details are actively routing through external verification services.
5.  **Compliance Review**: Verifications complete; account queued for final operator approvals.
6.  **Approved**: Compliance reviews passed; ready for workspace activation.
7.  **Provisioning**: Initializing virtual warehouses, default stores, and catalog settings.
8.  **Active**: Account has full permissions to manage listings, accept orders, and receive payouts.
9.  **Restricted**: The merchant can fulfill active orders but cannot create new listings or modify pricing, usually due to minor compliance issues.
10. **Suspended**: All active listings are disabled and current payouts are held, usually due to critical SLA infractions or compliance failures.
11. **Under Investigation**: Administrative hold state; platforms operators are actively reviewing account violations or fraud alerts.
12. **Rejected**: Onboarding checks failed. The business cannot operate on the platform.
13. **Closed**: Account voluntarily deactivated by the merchant.
14. **Archived**: Account preserved in a read-only state for compliance and audit retention.
15. **Deleted**: Masked profile data preserved strictly to meet tax audit requirements.

#### 4.1.2 Transition Matrix

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Registered** | Email Verification | Email token validated; basic contact profiles filled. |
| **Registered** | **Pending Verification**| Document Submit | Verifies that all mandatory KYB and bank files are uploaded. |
| **Pending Verification**| **Verification Progress**| Verification Job | Dispatches verification requests to external API partners. |
| **Verification Progress**| **Compliance Review**| Verification Success| Confirms KYC, KYB, and OFAC matches have successfully resolved. |
| **Verification Progress**| **Rejected** | Verification Failure| High-risk indicators or failed verification sweeps block activation. |
| **Compliance Review**| **Approved** | Maker-Checker Approval| Approvals must be authorized by designated checkers. |
| **Approved** | **Provisioning** | System Provisioner | Initializes default storefront tables and system parameters. |
| **Provisioning** | **Active** | Provisioning Success | Verifies warehouses, categories, and channels are initialized. |
| **Active** | **Restricted** | SLA Warning | Automated triggers restrict features due to minor SLA drops. |
| **Active** | **Suspended** | Critical Infraction | Hard suspensions are triggered by critical SLA drops or compliance issues. |
| **Active** | **Under Investigation**| Fraud Alert | Disables active payouts and locks profiles during active audits. |
| **Restricted** | **Active** | SLA Recovery | Recovers active status once SLA metrics meet targets. |
| **Suspended** | **Active** | Manual Release | Reactivation requires manual review and sign-off. |

---

## 5. MERCHANT WORKSPACE

The **Merchant Workspace** provides a secured portal and unified dashboard enabling vendors to manage day-to-day operations independently. Access is filtered using multi-tenant security layers.

```
                         [MERCHANT WORKSPACE INTERFACE]

   ┌─────────────────────────────────────────────────────────────────────────┐
   │                       Unified Merchant Workspace Portal                 │
   ├───────────────────┬───────────────────┬─────────────────────────────────┤
   │  Products         │  Orders           │  Inventory                      │
   │  - Listings, SEO  │  - Fulfillments   │  - Stock thresholds, alerts     │
   ├───────────────────┼───────────────────┼─────────────────────────────────┤
   │  Finance & Payout │  Analytics        │  Support & SLA                  │
   │  - Bank profiles  │  - Sales forecast │  - Disputes, response metrics   │
   └───────────────────┴───────────────────┴─────────────────────────────────┘
```

### 5.1 Workspace Capabilities
*   **Products & Catalog**: Allows merchants to manage product listings, configure pricing, upload media assets, and write SEO metadata.
*   **Orders & Fulfillment**: Provides interfaces to track customer orders, organize picking and packing tasks, print labels, and schedule carrier pickups.
*   **Inventory Coordination**: Tracks physical stock levels, configures low-stock alert thresholds, and manages virtual warehouse allocations.
*   **Promotions & Coupons**: Enables merchants to run targeted sales, configure discount parameters, and monitor promotional performance.
*   **Analytics Dashboard**: Displays real-time operational metrics, conversion trends, and regional sales summaries.
*   **Support & Dispute Resolution**: Routes customer support tickets and manages dispute representments within a single interface.
*   **Settlements & Payouts**: Displays settlement summaries, processing fees, reserve balances, and upcoming payout timelines.

---

## 6. MERCHANT STAFF & ROLE-BASED ACCESS CONTROL (RBAC)

Each merchant organization manages its staff and system permissions using a robust Role-Based Access Control (RBAC) model, restricting access to authorized profiles.

```
                         [MERCHANT STAFF RBAC MODEL]

                                Vendor Owner (Root)
                                        │
                    ┌───────────────────┴───────────────────┐
                    ▼                                       ▼
             Operations Manager                       Finance Manager
                    │                                       │
        ┌───────────┴───────────┐                           ▼
        ▼                       ▼                    Review Settlements
   Warehouse Staff        Support Staff
```

### 6.1 Standard Security Roles
*   **Owner**: Has full administrative and financial permissions, including managing bank profiles, updating tax details, and assigning roles.
*   **Administrator**: Manages day-to-day operations, edits store settings, invites new staff members, and manages catalog listings.
*   **Finance Manager**: Manages bank settlement profiles, views payout reports, and processes returns.
*   **Operations Manager**: Coordinates order fulfillments, manages warehouse locations, and monitors shipper SLAs.
*   **Warehouse Manager**: Manages picking waves, designs bin paths, and verifies packing accuracy.
*   **Inventory Manager**: Updates stock thresholds, manages listings, and tracks supplier shipments.
*   **Customer Support**: Manages support tickets, processes refund requests, and resolves buyer disputes.
*   **Marketing Manager**: Creates discount coupons, manages storefront branding, and configures promotional campaigns.
*   **Catalog Manager**: Uploads products, writes copy, and optimizes taxonomy listings.
*   **Read-Only Auditor**: Has view-only permissions for compliance and operational tracking.

### 6.2 Temporary Delegations & Maker-Checker Workflows
*   **Temporal Permissions**: Permissions can be configured with automatic expiration limits to grant temporary access for seasonal staff or external auditors.
*   **Checker Policies for Bank Updates**: Changes to bank accounts or settlement profiles require two-party authorization (Maker-Checker) within the merchant organization before saving.

---

## 7. STOREFRONT CONFIGURATION

The Storefront Configuration engine allows merchants to customize public-facing storefronts without risking database integrity or impacting performance.

```
                           [STOREFRONT DATA CACHING]

   Drizzle Read ──► Materialized View Storefront ──► Redis Cache Layer ──► Buyer Screen
```

### 7.1 Custom Domains & Routing Setup
*   **Subdomains**: Storefronts are provisioned under default system paths (e.g., `https://<merchant-slug>.juanet.market`).
*   **Custom Domains**: Merchants can map personal domains using DNS CNAME configurations. The platform’s reverse-proxy routes traffic to isolated storefront rendering engines.
*   **Content Moderation**: Dynamic uploads (e.g., brand logos, descriptions, policies) are run through automated content scanners before publication to block inappropriate material.

---

## 8. MERCHANT COMPLIANCE ENGINE

The **Compliance Engine** screens and monitors merchant profiles, maintaining regulatory compliance and protecting the platform from fraud.

```
                         [ONGOING COMPLIANCE SCREENING]

   Compliance Trigger ──► Real-Time OFAC Sweep ──► Passed?
                                                      │
                                        ┌─────────────┴─────────────┐
                                        ▼                           ▼
                                       YES                          NO (Alert SecOps / Suspend)
                                        │
                                Validate Document Expirations
```

### 8.1 Active Monitoring Routines
*   **Scheduled Screener Checks**: Background tasks run daily checks to screen merchant business records, beneficial owners, and entities against OFAC, PEP, and global sanctions registers.
*   **Document Tracking**: Automated jobs track document expiration dates (e.g., business licenses or tax certificates), sending warning alerts to merchants 30 days prior to expiry.
*   **Suspensions and Appeals**: Document failures automatically transition merchant accounts to the `Restricted` state. If documents are not updated within 14 days of expiration, the account is suspended.

---

## 9. COMMISSION & SETTLEMENT CONFIGURATION

The system calculates commissions and handles payouts based on merchant contracts and category rules, coordinating balance updates with the Finance Bounded Context.

```
                         [COMMISSION CALCULATION SEQUENCE]

   Capture Success Event ──► Load Active Category Commission Tiers
                                             │
                                             ▼
                                  Apply Merchant Discounts
                                             │
                                             ▼
                                    Calculate Payout Fees
                                             │
                                             ▼
                               Write balance entries to Outbox
```

### 9.1 Fee Configurations
*   **Flat Commission**: A set transaction fee applied to all orders (e.g., \$0.50 per order).
*   **Percentage Commission**: A flat percentage fee applied to order sub-totals (e.g., 10% per transaction).
*   **Category Commissions**: Commissions configured by product category (e.g., 15% for Electronics, 8% for Groceries).
*   **Dynamic Tiering**: Commission percentages decrease as merchant transaction volumes grow:
    $$\text{Commission Rate} = f(\text{Monthly Sales Volume})$$

### 9.2 Rolling Reserves and Balance Safety
*   **Rolling Reserves**: The engine holds a percentage of settlement totals (e.g., 10% rolling reserve for 30 days) to cover customer chargebacks or disputes.
*   **Negative Balance Adjustments**: If returns or dispute chargebacks exceed captured sales, the engine records negative balances. Payouts are suspended until sales volumes restore balances.

---

## 10. VENDOR PERFORMANCE ENGINE

The **Performance Engine** evaluates merchant performance against platform quality standards, updating seller rankings and badges.

```
                        [PERFORMANCE ENGINE SCORECARD]

   Fetch Order & Shipment Metrics ──► Calculate KPI Percentages ──► Update Tier Rankings
```

### 10.1 Key Performance Indicators (KPIs)
*   **Order Acceptance Rate**: Percentage of assigned orders confirmed by the merchant within SLA limits.
*   **Fulfillment SLA**: Percentage of packages shipped within committed packaging windows.
*   **Shipment Delay Rate**: Percentage of packages with carrier dispatches exceeding scheduled dates.
*   **Cancellation Rate**: Percentage of orders canceled by the seller (e.g., due to stockouts).
*   **Return & Refund Rate**: Percentage of orders returned by customers due to product quality issues or damage.
*   **Customer Review Scores**: Aggregate score of customer feedback and reviews.

### 10.2 Ranking and Tiering Formulas
$$\text{Performance Score} = w_1 \cdot \text{Acceptance} + w_2 \cdot \text{Fulfillment} - w_3 \cdot \text{Cancellations} - w_4 \cdot \text{Returns} + w_5 \cdot \text{Reviews}$$

#### Tiers
*   **Bronze**: Standard tier assigned to new sellers.
*   **Silver**: Score > 80; unlocks standard fee discounts.
*   **Gold**: Score > 90; unlocks fast settlement timelines.
*   **Platinum**: Score > 95; grants priority support, premium badge awards, and featured search rankings.

---

## 11. VENDOR SLA MANAGEMENT

SLA contracts protect customer experiences by setting clear timelines for order processing, packaging, and shipping.

```
                            [SLA WARNING PIPELINE]

   Order Dispatch Window Missed ──► Register SLA Infraction ──► Infractions > Threshold?
                                                                          │
                                                      ┌───────────────────┴───────────────────┐
                                                      ▼                                       ▼
                                                     YES (Apply warning / Suspend)            NO
```

### 11.1 Escalation Protocols
*   **SLA Infractions**: Missed fulfillment windows trigger automated alerts and record infractions.
*   **Warnings and Suspensions**: Accumulating multiple infractions within a 30-day window automatically places the account in a `Restricted` state. Persistent SLA failures trigger manual compliance reviews.

---

## 12. VENDOR ANALYTICS ENGINE

The **Analytics Engine** processes transaction metrics to provide merchants with operational insights and sales performance tracking:

```
                          [ANALYTICS ENGINE PIPELINE]

   Transaction Events ──► Parse and Standardize Metrics ──► Update Materialized Views
                                                                      │
                                                                      ▼
                                                           Refresh Workspace Graphs
```

### 12.1 Analytical View Highlights
*   **Sales Trends**: Real-time sales trackers, average order values, and conversion metrics.
*   **Operational Health**: Active trackers monitoring fulfillment speeds, error rates, and carrier performance.
*   **Inventory Forecasts**: Live stock projections identifying low-stock items and estimating restock dates.

---

## 13. AI ADVISORY INTEGRATION

The platform integrates AI capabilities to assist merchants with business optimization, operating strictly as an advisory system.

```
                            [AI ADVISORY WORKFLOW]

   Load Analytics Logs ──► Analyze Performance Trends ──► Generate Suggestions
                                                                 │
                                                                 ▼
                                                       Prompt Merchant Review
                                                       (Explicit approval required)
```

### 13.1 Advisory Scenarios
*   **Sales Forecasting**: Estimates future sales volumes based on historical transaction trends.
*   **Inventory Recommendations**: Suggests restocking quantities to prevent stockouts during upcoming peak periods.
*   **Dynamic Pricing Suggestions**: Recommends pricing adjustments based on competitor rates and market demand.
*   **SEO Optimization**: Suggests optimization improvements for product listing descriptions, tags, and titles.
*   **Reviews & Sentiment Summarization**: Compiles customer reviews to identify common issues or product quality themes.

---

## 14. CANONICAL EVENT CONTRACTS

The Vendor Bounded Context writes standard event payloads to the `public.marketplace_event_outbox` table, ensuring consistent downstream tracking.

```
                      [TRANSACTIONAL OUTBOX PIPELINE]

   Parent Transaction Commit ──► Write Outbox Payload ──► Message Queue Dispatch
```

### 14.1 `vendor.created.v1`
*   **Publisher**: Vendor Operations Service
*   **Consumers**: CRM Service, Administrative Logs Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095034000",
      "event_type": "vendor.created.v1",
      "timestamp": "2026-07-01T01:12:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095034001",
      "business_name": "Acme Electronics Corp",
      "country_code": "US",
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 14.2 `vendor.approved.v1`
*   **Publisher**: Vendor Operations Service
*   **Consumers**: Catalog Context, Warehouse Provisioner Service, Finance Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095034002",
      "event_type": "vendor.approved.v1",
      "timestamp": "2026-07-01T01:15:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095034001",
      "approved_by": "018f63bb-9ab6-7000-8d59-fc5095033501",
      "default_commission_rate": 0.10,
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 14.3 `vendor.suspended.v1`
*   **Publisher**: Vendor Operations Service
*   **Consumers**: Catalog Context, CRM & Notification Service, Finance Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095034003",
      "event_type": "vendor.suspended.v1",
      "timestamp": "2026-07-01T01:20:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095034001",
      "reason_code": "SLA_VIOLATION",
      "details": "Fulfillment rate dropped below standard platform threshold of 80%",
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

---

## 15. SECURITY & COMPLIANCE

The system enforces multi-tenant security structures and strictly protects data access using PostgreSQL Row-Level Security (RLS) policies.

### 15.1 Row-Level Security (RLS) Configuration
Sellers can view and edit data only within their assigned organizations. Row-Level Security filters restrict database operations to matching corporate accounts:

```sql
-- Enable Row-Level Security
ALTER TABLE public.vendors ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.vendor_stores ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.vendor_settlement_profiles ENABLE ROW LEVEL SECURITY;

-- Apply Select & Update RLS Security Policies
CREATE POLICY vendor_isolation_policy ON public.vendors
  FOR ALL
  USING (organization_id = CURRENT_SETTING('app.current_organization_id', true));

CREATE POLICY store_isolation_policy ON public.vendor_stores
  FOR ALL
  USING (organization_id = CURRENT_SETTING('app.current_organization_id', true));
```

### 15.2 Data Privacy & Encryption
*   **PII Masking**: Customer and staff details are masked in diagnostic databases.
*   **Document Encryption**: Documents uploaded for onboarding (e.g., identity verification files or tax forms) are encrypted using AES-256-GCM prior to storage.
*   **Audit Logging**: The system records an immutable audit trail of profile and configuration modifications, tracking actor IDs, change values, and session contexts.

---

## 16. PERFORMANCE & SCALABILITY

To support high transaction volumes, the engine utilizes scalable architecture patterns, database partitioning, and intelligent caching:

*   **Database Partitioning**: Partitions the `public.vendor_audits` and `public.vendor_verifications` tables by month, preventing indexing bottlenecks on growing operational histories.
*   **Materialized Views**: Aggregates merchant sales and SLA calculations within materialized views to offload reporting queries from active transaction tables.
*   **Redis Caching**: Caches storefront configurations and branding profiles, serving static merchant details directly from memory to improve page loading speeds.
*   **Background Outbox Workers**: Handles outbox notifications asynchronously, processing event messages outside the primary database write loop.

---

## 17. ARB VALIDATION MATRIX

This validation matrix serves as the official scorecard for the Architecture Review Board (ARB) to verify implementation and design correctness.

| Assessment Category | Verification Goal & System Test Requirement | Pass / Fail Criteria |
| :--- | :--- | :--- |
| **Merchant Onboarding**| Submit complete onboarding profiles containing KYB files. | Verification tasks must register and write files successfully. |
| **Compliance Screening**| Run mock KYC, KYB, and OFAC screener checks. | High-risk indicators must flag profiles and block activation. |
| **RBAC Security** | Attempt to modify bank details using a `Warehouse Manager` session. | Database must reject the operation with an authorization failure. |
| **FSM Transitions** | Attempt to transition a `Draft` account directly to `Active`. | State machine must block invalid transitions. |
| **Row-Level Security** | Attempt to select vendor store records using an alternative organization ID. | PostgreSQL must return empty query results. |
| **Commission Rules** | Apply Category Commission rates to mixed shopping carts. | Payout totals must match calculated rates. |
| **SLA Infractions** | Force missed shipping deadlines to trigger SLA rules. | SLA metrics must record infractions and flag warnings. |
| **AI Suggestions** | Suggest restock counts based on analytical trend logs. | Suggestions must require manual approval prior to execution. |
| **Outbox Integrations** | Process profile updates and verify outbox writes. | Outbox records must commit within the same database transaction. |

---

## 18. ENTERPRISE VALIDATION CONCLUSION

This specification establishes the architectural foundation and database design standards for the **Vendor Operations & Merchant Management Engine** within the JUANET Enterprise SaaS Platform.

The specifications outlined in this document are fully aligned with the **Marketplace Physical Tables**, **Orders & Checkout Engine**, **Pricing Engine**, and **Fulfillment & Shipping Engine** specifications. 

The implementation details provided are sufficient to enable developers, QA teams, database architects, and system security auditors to implement the complete subsystem without requiring additional design clarifications. All subsequent marketplace implementation plans must conform to the security boundaries, transaction rules, and FSM transition rules documented herein.
