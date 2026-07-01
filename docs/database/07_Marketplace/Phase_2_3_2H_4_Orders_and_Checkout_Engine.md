# JUANET Marketplace Orders & Checkout Engine Manual
## Phase 2.3.2H.4 — Cart Engines, Checkout Orchestration Pipelines, Order Aggregates, Multi-Vendor Splitting, and Life-Cycle States

**Document Version:** 2.1  
**Author:** Principal Enterprise Solutions Architect, VP of Transactional Platform Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. ORDERS ARCHITECTURE PHILOSOPHY

The **JUANET Orders & Checkout Engine** serves as the authoritative **System of Record (SoR)** for all customer purchase events, checkout sessions, order modifications, financial billing allocations, and fulfillment statuses. Positioned as the foundational transactional system within the JUANET Enterprise SaaS Platform, it manages billions in Gross Merchandise Value (GMV) with absolute consistency, high concurrency, and complete audit compliance.

```
                      [ORDERS & CHECKOUT BOUNDED CONTEXT SOLID BOUNDARIES]

     ┌─────────────────────────────────────────────────────────────────────────────────┐
     │                      CUSTOMER / CLIENT CONTEXT (Web, Mobile, POS)               │
     └────────────────────────────────────────┬────────────────────────────────────────┘
                                              │
                      REST API / gRPC (Idempotency Handshake)
                                              ▼
     ┌─────────────────────────────────────────────────────────────────────────────────┐
     │                     ORDERS & CHECKOUT BOUNDED CONTEXT                           │
     │                                                                                 │
     │   ┌───────────────────────────┐           ┌─────────────────────────────────┐   │
     │   │      Cart Aggregate       │ ◄───────► │    Checkout Session Aggregate   │   │
     │   │   - Anonymous / Auth      │           │   - Validations, Tax, Rates     │   │
     │   └───────────────────────────┘           └────────────────┬────────────────┘   │
     │                                                            │ (Commit Order)     │
     │                                                            ▼                    │
     │                                           ┌─────────────────────────────────┐   │
     │                                           │        Order Aggregate          │   │
     │                                           │   - Items, Snapshots, RLS       │   │
     │                                           └────────────────┬────────────────┘   │
     └────────────────────────────────────────────────────────────┼────────────────────┘
                                                                  │
                                                     (Outbox Event Notifications)
                                                                  ▼
     ┌─────────────────────────────────────────────────────────────────────────────────┐
     │                             DOWNSTREAM SYSTEMS BOUNDARIES                       │
     │                                                                                 │
     │  ┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐ │
     │  │   Pricing Engine     │   │   Inventory Engine   │   │   Finance Ledger     │ │
     │  │ (Coupons, Contracts) │   │ (SKIP LOCKED Holds)  │   │  (Tax/Vendor Splits) │ │
     │  └──────────────────────┘   └──────────────────────┘   └──────────────────────┘ │
     └─────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Separation of Bounded Contexts & Concerns
To maintain strict Domain-Driven Design (DDD) isolation and high write throughput, the Orders context enforces absolute boundaries against adjacent domains:
*   **Separation from Shopping Carts**: Carts represent high-churn, low-commitment client actions. The Cart Engine resides in an ephemeral caching layer (Redis) or isolated, low-overhead database tables, entirely separate from the heavy, audited `public.orders` tables. This guarantees that cart abandonments never lock or fragment transactional order records.
*   **Separation from Checkout Sessions**: Checkout Sessions act as a transient orchestration pipeline where address verification, tax calculation, and payment authorization occur. Order database records are only instantiated after the checkout pipeline succeeds.
*   **Separation from Inventory**: The Orders context does not write directly to physical stock balances. Instead, it issues a reservation hold request to the Inventory Bounded Context using transaction-safe, non-blocking locking patterns.
*   **Separation from Pricing & Promotions**: The Orders domain does not compute active pricing tiers, discount stacking logic, or loyalty rewards. It delegates these calculations to the Pricing Bounded Context, freezing the resolved rates inside an immutable pricing snapshot row at checkout submission.
*   **Separation from Payments**: The Order context does not communicate directly with payment processors (Stripe, Adyen, PayPal). It orchestrates payment intents, receiving and registering asynchronous tokenized gateway confirmations.
*   **Separation from Shipping & Warehousing**: Shipping labels, warehouse picking lists, and carrier integrations are managed within the Inventory & Warehouse context. The Order context references these via tracking numbers and shipment states.
*   **Separation from CRM, Support, and AI**: Customer satisfaction metrics, support tickets, and AI recommendation engines query read-only CQRS projections of the order history, preventing heavy analytical queries from degrading transactional OLTP performance.

### 1.2 Bounded Context Rules & Core Assertions
*   **Transactional Outbox Integration**: Every transaction within the Orders domain writes event payloads to a unified outbox table (`public.marketplace_event_outbox`) in the same database transaction block. This ensures absolute consistency between database states and outward-bound messages.
*   **Immutable Financial History**: Once an order moves past the `Pending Checkout` state, its pricing records are completely frozen. Retroactive modifications must be processed using corrective financial ledger entries rather than direct cell updates.
*   **No Cross-Domain Writes**: External contexts are strictly forbidden from writing to Order tables. All integrations are handled through gRPC interfaces or by consuming outbox events.
*   **Eventual Consistency**: Downstream integrations (e.g., inventory deduction, accounting ledger posting, warehouse shipping scheduling) must be processed asynchronously using idempotent event handlers, ensuring the system can survive network partitions and high-load events.

### 1.3 Why Orders Engine Never Performs Direct Writes
To ensure system durability, transaction safety, and domain encapsulation, the Orders domain prohibits direct database writes to adjacent systems, utilizing asynchronous, canonical events instead:
*   **Inventory Context**: Modifying inventory levels directly within checkout transactions causes database-level lock contention, cascading bottlenecks across unrelated carts. The Orders domain calls reservation endpoints to place temporary holds, and downstream workers deduct actual counts asynchronously upon order confirmation.
*   **Finance & Accounting**: Financial records must adhere to rigorous audit regulations (e.g., SOX compliance). Direct cell modifications by the Orders engine violate the dual-entry accounting model. Instead, finalized transactions write to an immutable outbox, enabling the finance ledger to ingest events and build audited journal entries cleanly.
*   **CRM & Support**: Customer satisfaction logs, loyalty points, and helpdesk profiles reside on secondary write engines optimized for text processing and customer interactions. Writing directly to these from the primary orders database introduces external dependencies and degrades transactional checkout throughput.
*   **CMS (Content Management System)**: Customer-facing marketing materials, blog entries, and storefront catalog layouts belong to content engines. Directly tying checkout transactions to content databases violates isolation bounds and introduces unnecessary failure modes.
*   **Vendors**: Merchant portals and vendor-specific ledgers operate as independent SaaS units. Direct database updates from a consolidated shopping cart checkout violate security tenants and bypass RLS isolation policies.
*   **Shipping**: Logistics carriers (e.g., FedEx, UPS, DHL) require API Handshakes which are prone to network timeouts. Directly writing to shipping queues within the primary transaction exposes the checkout pipeline to immediate network failures.

---

## 2. SHOPPING CART ARCHITECTURE

The Shopping Cart Engine handles millions of transient page visits and cart additions without impacting primary transactional tables, utilizing a high-performance hybrid model.

```
                         [HYBRID SHOPPING CART SYSTEM ARCHITECTURE]

       Shopper Segment                     Storage Engine                      Fulfillment Check
  ┌───────────────────────┐          ┌───────────────────────┐             ┌───────────────────────┐
  │   Anonymous Shopper   │ ───────► │    Redis Cache Node   │ ──────────► │  Lightweight Catalog  │
  │                       │          │   (Cookie Key, TTL)   │             │   Price/Promo Check   │
  └───────────────────────┘          └───────────┬───────────┘             └───────────────────────┘
                                                 │
                                           (User Log-In)
                                                 ▼
  ┌───────────────────────┐          ┌───────────────────────┐             ┌───────────────────────┐
  │ Authenticated Shopper │ ───────► │   PostgreSQL Tables   │ ──────────► │  Inventory Reservation │
  │                       │          │   (public.carts, RLS) │             │    Pre-check (gRPC)   │
  └───────────────────────┘          └───────────────────────┘             └───────────────────────┘
```

### 2.1 Anonymous (Guest) Shopping Carts
*   Anonymous shoppers write shopping cart additions to an in-memory Redis cluster, keyed by a client-provided, crytographically unique cookie identifier (`cart:anon:<uuid>`).
*   Anonymous carts have a strict Time-To-Live (TTL) of 14 days, which resets on every item addition. This approach keeps transactional tables clean from abandoned search traffic.

### 2.2 Authenticated Shopping Carts
*   Authenticated users store their carts in PostgreSQL (`public.carts` and `public.cart_items`), which are protected by Row-Level Security (RLS) policies matching their `user_id` and tenant `organization_id`.
*   Authenticated carts are preserved for a default period of 90 days, enabling seamless shopping experiences across web, mobile, and in-store channels.

### 2.3 Guest Checkout Sessions
*   Shoppers can complete checkout as a Guest without creating a platform account.
*   The guest shopping cart is validated and converted into a transient checkout session, creating a temporary customer record (`guest_flag = true`) for shipping, tax, and order tracking.

### 2.4 Persistent, Saved, and Shared Carts
*   **Saved Carts (Save for Later)**: Users can move items from their active cart to a secondary saved status, excluding them from immediate checkout calculations.
*   **Shared Carts**: Multi-user B2B procurement accounts can link shopping carts to a shared organization ID. This allows several buyers to add items to a single, unified purchase order awaiting manager approval.
*   **Wishlist Interaction**: Moving an item from a wishlist to a cart performs a catalog check to verify stock availability, and vice-versa.

### 2.5 Cart Expirations and Automated Sweeps
*   Inactive authenticated carts are cleaned up after 90 days of inactivity by a scheduled cron sweep.
*   Items abandoned in active carts do not hold inventory reservations. Inventory is only reserved when a user enters the active checkout pipeline.

### 2.6 Cart Merge Engine (After Login)
When a shopper signs in, the platform merges their anonymous guest cart with their persistent authenticated cart:

```
                          [CART MERGING AND RESOLUTION ENGINE]

  [Guest Cart Items (Redis)] ──┐
                               │
                               ├─► [Merge Engine] ─► [Validate & Deduplicate] ─► [Save to PostgreSQL]
                               │
  [Auth Cart Items (Postgres)] ┘
```

The merge workflow executes the following transactional rules:
1.  **Deduplication**: If the same product variant ID exists in both carts, the quantities are merged up to the maximum purchase limit allowed per SKU.
2.  **Catalog Rate Re-evaluation**: Merged item pricing is re-evaluated using current catalog rates, tenant-specific contracts, and active promotions.
3.  **Real-Time Stock Verification**: Checks active stock levels for all merged items. If an item is out of stock, it is flagged and moved to the user's "Saved for Later" list, preventing checkout failures.

### 2.7 Cross-Device and Cross-Browser Recovery
*   **Cross-Device Synchronization**: When an authenticated user updates their cart on a mobile device, the application writes to PostgreSQL, immediately pushing WebSockets broadcasts to synchronize other active customer sessions.
*   **Cross-Browser Recovery**: If a logged-out customer switches browsers, they can recover their shopping session by verifying their identity (e.g., via magic link or authentication callback), pulling their active PostgreSQL cart immediately.

### 2.8 Cart Locking, Concurrent Editing, and Conflict Resolution
*   **Cart Locking**: During checkout execution, the shopping cart is locked to prevent customers from modifying items while payments are being authorized.
*   **Concurrent Editing**: For shared B2B carts where multiple procurement agents edit a single cart simultaneously, the platform uses Optimistic Concurrency Control (OCC) via a `version` column.
*   **Conflict Resolution**: If two agents attempt to update cart quantities at the exact same instant:
    1.  The fast update commits, incrementing the cart's `version`.
    2.  The slow update fails, prompting the client interface to re-fetch the latest quantities and retry the action smoothly.

### 2.9 Cache and Data Refresh Triggers
To prevent stale catalog rates, the platform enforces strict data refresh rules:
*   **Price Refresh**: Triggered when a cart is idle for more than 10 minutes, when a coupon is added, or when the user navigates to the checkout portal.
*   **Inventory Refresh**: Inventory is verified at checkout entry, during checkout validation, and on final order submission.
*   **Coupon & Promotion Refresh**: Coupon codes are validated against current campaigns on entry and checkout submission.
*   **Currency Refresh**: Triggered when the customer toggles the site currency, recalculating rates based on current financial exchange indices.
*   **Tax Refresh**: Recalculated immediately when a customer updates their shipping address during checkout.

---

## 3. GUEST VS. AUTHENTICATED CHECKOUT

The platform supports both Guest and Authenticated checkout flows, balancing checkout speeds with customer data security and onboarding.

```
                           [CHECKOUT FLOW PATH SEPARATION]

                         Shopper Initiates Checkout
                                      │
                   ┌──────────────────┴──────────────────┐
                   ▼                                     ▼
             [Guest Shopper]                    [Authenticated Shopper]
                   │                                     │
         Validate Billing Address               Load Secure Address Wallet
         & Shipping Credentials                 & Saved Payment Cards (Tokens)
                   │                                     │
         Check Fraud Risk Scores                Check Fraud Risk Scores
                   │                                     │
                   ▼                                     ▼
           Verify & Submit                        Verify & Submit
                   │                                     │
                   └──────────────────┬──────────────────┘
                                      ▼
                        Order Created in PostgreSQL
                        (Optional: User Onboarding Prompt)
```

### 3.1 Data Flow and Customer Profiling
*   **Guest Checkout**: Requires shoppers to input email, billing address, shipping address, and payment credentials manually. The system creates a transient customer profile record with a `guest_flag = true` tag, allowing the customer to track their order without creating a persistent login profile.
*   **Authenticated Checkout**: Automatically loads stored shipping addresses and tokenized payment methods from the customer’s secure profile wallet, reducing friction and accelerating checkouts.

### 3.2 Transient Guest Data Security
*   All guest profiles and order details are secured using Row-Level Security (RLS) keys linked to a cryptographically secure, tokenized checkout session key.
*   Guest session tokens are passed back to client browsers via HTTP-Only, secure, same-site cookies, preventing unauthorized access to order tracking pages.

### 3.3 Onboarding Promotion Gates
*   Upon order completion, the checkout success interface offers Guest users the option to register a persistent account.
*   If the guest accepts, the transient profile is converted to a permanent profile, associating their guest order history with their new account.

---

## 4. THE CHECKOUT VALIDATION PIPELINE

The Checkout Validation Pipeline executes as a single, transactional orchestration block. It ensures that every prerequisite is validated before order confirmation. If any check fails, the pipeline halts, rolls back active database locks, and returns clear validation feedback to the shopper.

```
                     [10-STEP CHECKOUT VALIDATION PIPELINE]

   START SUBMISSION (Client Handshake with Idempotency Key)
          │
          ▼
   [Step  1: Account Verification] ──► Verify tenant status, RLS permissions, credit blocks.
          │
          ▼
   [Step  2: Address Cleansing]    ──► Validate geo-addresses using Geolocation APIs.
          │
          ▼
   [Step  3: Stock Verification]   ──► Query inventory balances, matching available stock pools.
          │
          ▼
   [Step  4: Price Snapshotting]   ──► Resolve contract rates, pricing tiers, and freeze prices.
          │
          ▼
   [Step  5: Tax Computation]      ──► Fetch tax rules and calculate regional tax liabilities.
          │
          ▼
   [Step  6: Promotion Evaluation] ──► Verify discount stacking rules and coupon validity.
          │
          ▼
   [Step  7: Fraud Screening]      ──► Screen transaction attributes for fraud risk indicators.
          │
          ▼
   [Step  8: Payment Authorization]──► Capture authorized payment tokens from gateway partners.
          │
          ▼
   [Step  9: Inventory Reservation]──► Convert transient reservations into locked allocations.
          │
          ▼
   [Step 10: Order Generation]     ──► Commit records to DB, write to outbox, clear cart caches.
          │
          ▼
   SUCCESS EMISSION (Client Order Tracking Live Confirmation)
```

### 4.1 Step-by-Step Validation Rules
1.  **Account Verification**: Verifies that the tenant's `organization_id` is active, that the customer account is in good standing, and checks B2B credit lines to prevent unauthorized purchases.
2.  **Address Cleansing**: Validates customer shipping and billing addresses against regional postal standards, identifying invalid addresses and routing restrictions.
3.  **Stock Verification**: Verifies stock availability for all order items across the nearest fulfillment centers, blocking the checkout if items are out of stock.
4.  **Price Snapshotting**: Queries the Pricing Engine to resolve contract rates and pricing tiers, locking these values into an immutable pricing snapshot row.
5.  **Tax Computation**: Computes local tax rates based on customer locations and item categories.
6.  **Promotion Evaluation**: Evaluates applied coupons and promotional discounts, ensuring stack rules are respected and usage limits are not exceeded.
7.  **Fraud Screening**: Evaluates order attributes (e.g., velocity, IP geolocations, card patterns) to assign a fraud risk score, flagging suspicious transactions for manual review.
8.  **Payment Authorization**: Submits the payment intent to the gateway partner, securing an authorization token and locking checkout funds.
9.  **Inventory Reservation**: Converted transient cart reservations into permanent stock allocations, locking rows against concurrent picking.
10. **Order Generation**: Commits the finalized order records, line items, and taxes to the PostgreSQL database, writes to outbox tables, and clears ephemeral cart caches.

### 4.2 Idempotent Submissions & Fault Isolation
*   **Idempotency Handshake**: The API layer requires clients to pass an `Idempotency-Key` header with checkout requests. If a duplicate request is received, the system returns the cached response rather than recreating the order.
*   **Fault Isolation & Rollbacks**: If any validation step fails (e.g., payment gateway rejection or inventory stockouts during final locks), the pipeline triggers a complete database rollback, cancels payment authorization holds, and returns descriptive errors.

---

## 5. ORDER AGGREGATE DESIGN

The Order Aggregate represents a transactional consistency boundary, managed by the Order aggregate root table `public.orders`. It coordinates multiple child tables, ensuring all updates are processed as an atomic unit.

```
                  [ORDER AGGREGATE ARCHITECTURE & BOUNDARIES]

 ┌─────────────────────────────────────────────────────────────────────────────┐
 │  public.orders (Aggregate Root)                                             │
 │  - id (UUID Primary Key)                                                    │
 │  - organization_id (Tenant Isolation Key)                                   │
 │  - order_number (Unique Human Readable Index)                               │
 │  - customer_id (Profile Map)                                                │
 │  - net_total, tax_total, shipping_total, gross_total                        │
 │  - lifecycle_status (FSM State)                                             │
 │                                                                             │
 │   ┌─────────────────────────────────────────────────────────────────────┐   │
 │   │  public.order_items (1:N Relationship)                              │   │
 │   │  - id, product_variant_id, sku, quantity, net_unit_price, status    │   │
 │   └─────────────────────────────────────────────────────────────────────┘   │
 │   ┌─────────────────────────────────────────────────────────────────────┐   │
 │   │  public.order_taxes (1:N Relationship)                              │   │
 │   │  - id, tax_jurisdiction, tax_rate, tax_amount_applied               │   │
 │   └─────────────────────────────────────────────────────────────────────┘   │
 │   ┌─────────────────────────────────────────────────────────────────────┐   │
 │   │  public.order_discounts (1:N Relationship)                          │   │
 │   │  - id, discount_code, calculated_reduction_value, coupon_id         │   │
 │   └─────────────────────────────────────────────────────────────────────┘   │
 │   ┌─────────────────────────────────────────────────────────────────────┐   │
 │   │  public.order_shipments (1:N Relationship)                          │   │
 │   │  - id, warehouse_id, carrier_code, tracking_number, ship_status     │   │
 │   └─────────────────────────────────────────────────────────────────────┘   │
 │   ┌─────────────────────────────────────────────────────────────────────┐   │
 │   │  public.order_payments (1:N Relationship)                           │   │
 │   │  - id, payment_gateway, transaction_token, authorized_amount, state │   │
 │   └─────────────────────────────────────────────────────────────────────┘   │
 └─────────────────────────────────────────────────────────────────────────────┘
```

### 5.1 Aggregate Ownership Rules
*   **Unified Mutator Actions**: Downstream clients and worker tasks are strictly forbidden from modifying child tables (e.g., `public.order_items` or `public.order_payments`) directly. All mutations must route through the Order aggregate root, ensuring state validations and financial calculations are evaluated together.
*   **Multi-Tenant Isolation**: The `organization_id` column is verified across all entities in the aggregate, ensuring complete data isolation.
*   **Immutable Referencing**: Foreign key relationships use `RESTRICT` or `NO ACTION` deletion rules to preserve historical data integrity. Deleting customer profiles or SKU codes will not orphan or remove transactional order records.

### 5.2 Entity Ownership Dictionary
The Order Bounded Context maintains explicit transactional authority over the following sub-entities within the Aggregate Root consistency boundary:

| Sub-Entity | Physical Table Name | Domain Purpose | Ownership & Mutation Policy |
| :--- | :--- | :--- | :--- |
| **Orders** | `public.orders` | Root aggregate record tracking human-readable order numbers, tenant organizations, customer references, state machine codes, and consolidated totals. | **Absolute Root.** Only mutable via formal state-machine transition commands. |
| **Order Items** | `public.order_items` | Individual purchase lines tracking variants, SKU identifiers, frozen unit pricing details, quantities, and line-item fulfillment state. | Owned by root. Individual items are frozen after transition to `Confirmed`. |
| **Taxes** | `public.order_taxes` | Record of regional tax jurisdictions, applied tax classifications, percentage rates, and calculated tax amounts. | Immutable after checkout. Re-calculated only on formal address changes before picking. |
| **Discounts & Coupons** | `public.order_discounts` | Applied promotional codes and coupon identifiers, mapping proportional reduction values allocated across lines. | Immutable after checkout completion. Proportional reduction cannot be altered. |
| **Gift Cards** | `public.order_gift_card_redemptions` | Records specific gift card codes used, authorization locks, and final deducted amounts applied during transaction blocks. | Owned by Root. Handled as secure credit deduction. Modifiable only during rollback. |
| **Addresses** | `public.order_addresses` | Complete, frozen copy of the billing and shipping address records captured at the second of checkout submission. | Immutable to prevent historical audit corruption. Corrected only via support overrides. |
| **Order Notes** | `public.order_notes` | Customer checkout special instructions and internal administrative processing annotations. | Append-only. Modifying existing entries is strictly prohibited. |
| **Order Timeline** | `public.order_history` | Complete historical audit trail logging every FSM state mutation, actor ID, execution timestamp, and transaction context. | **Immutable Append-Only.** Updates or deletes are blocked at the database level. |
| **Order Metadata** | `public.order_metadata` | Structured JSONB column on the root table enabling custom key-value pairs for regional processing options or integrations. | Mutable by approved platform system accounts only. |
| **Order Attachments** | `public.order_attachments` | Safe, audited references to static system storage assets, including invoice PDFs, customs documents, or packaging slips. | Append-only. Deletions are forbidden. |
| **Vendor References** | Column: `vendor_id` | Foreign-key association mapping items to independent Marketplace vendors, triggering multi-vendor order splitting. | Immutable after order creation. |
| **Warehouse References** | Column: `warehouse_id` | Mapping identifying fulfillment facilities selected during dynamic checkout routing. | Mutable only during pre-fulfillment shipping adjustments. |
| **Shipment References** | `public.order_shipments` | Association linking line items to outbound packages, tracking codes, carrier records, and transit milestones. | Managed via logistics integration handlers asynchronously. |
| **Payment References** | `public.order_payments` | Gateway tracking tokens, authorization codes, fee details, and transaction processing statuses. | Immutable. Modified only on gateway callback events. |
| **Support References** | `public.order_support_tickets` | Map table linking transactional orders to active CRM or helpdesk ticket IDs for tracking. | Append-only. Managed via CRM webhook sync. |
| **AI References** | `public.order_ai_scoring` | Risk metrics, fraud rating records, and predictive analytics regarding potential delivery delay windows. | Append-only. Written by secondary scoring workers. |

---

## 6. ORDER PRICING SNAPSHOT

To meet international financial auditing and tax standards, checkout pricing is frozen as an immutable snapshot within the order tables.

```
                           [IMMUTABLE PRICING LEDGER]

  Resolved Checkout Prices ──► Write to public.order_items (Locked)
                               ├── net_unit_price: $89.99 (Locked)
                               ├── tax_rate & value: 8.25% ($7.42) (Locked)
                               └── discount_applied: -$10.00 (Locked)
```

The system preserves complete pricing snapshots inside `public.order_items` tables, locking values to prevent subsequent edits or updates:
*   **net_unit_price**: The exact unit price paid by the customer, net of tax and promotions.
*   **original_catalog_price**: The standard catalog list price at checkout, preserved for promotional performance tracking.
*   **discounts_applied**: The total discount applied to each line item, tracking the breakdown of promo deductions.
*   **exchange_rate**: The active currency exchange rate at checkout, preserved for foreign currency accounting audits.
*   **shipping_total**: The calculated shipping cost charged to the customer.
*   **fees_total**: Processing fees, platform surcharges, or payment gateway fees applied to the order.

### 6.1 Audit Protection
Once the order moves past `Pending Checkout`, these pricing records are immutable. Corrective actions (e.g., refunds or credit adjustments) write new ledger entries instead of altering historical cell values, protecting audit trails.

### 6.2 Architectural Rationale for Immutability
*   **Audit Protection**: Recalculating historical transactions is illegal under GAAP/IFRS standards. Price histories must match the cash captured on the day of the sale.
*   **Isolation from Catalog Changes**: If a merchant updates a product's price from $100 to $120 next week, historical orders must still display $100. Storing pricing statically rather than joining on the live product catalog ensures complete isolation.
*   **Corrections Policy**: Any pricing discrepancy post-purchase is handled via structured adjustments:
    *   **Credit Memos**: To refund a client, a negative transactional receipt is issued.
    *   **Supplemental Invoicing**: To charge extra fees, a separate transactional order is spawned.

---

## 7. TAX SNAPSHOT

Tax calculations must be preserved as frozen snapshots to protect historical audits against regional tax rate changes.

```
                         [IMMUTABLE TAX RECORD MAPPING]

  Calculated Tax Details ──► Write to public.order_taxes (Locked)
                             ├── Tax Jurisdiction: "State of California"
                             ├── Tax Category: "Electronics Tax Class"
                             ├── Tax Rate: 8.25%
                             └── Calculated Tax Amount: $14.85
```

### 7.1 Address-Based Tax Calculations
*   The tax engine calculates regional taxes based on customer shipping addresses and item tax categories, identifying state, county, and local tax liabilities.

### 7.2 Tax Snapshot Fields
*   **tax_jurisdiction**: The legal authority collecting the tax (e.g., "State of California").
*   **tax_category**: The product class used for tax calculation (e.g., "Standard", "Apparel", "Digital Goods").
*   **tax_rate**: The precise tax percentage applied to each line item.
*   **calculated_tax_amount**: The tax value calculated for each line item, frozen inside `public.order_taxes`.

### 7.3 Tax Exemption Handling
*   B2B accounts with active tax exemptions submit exemption certificate numbers during checkout.
*   The tax engine validates these certificate numbers and zeroes tax calculations, logging certificate references inside tax snapshot rows for audit verification.

---

## 8. COUPON & PROMOTION RESOLUTION

Promotion and coupon codes are evaluated and locked during checkout, ensuring promotional accuracy and tracking.

```
                            [COUPON REDEMPTION FLOW]

  Coupon Applied ──► Evaluate Stacking Rules ──► Apply Discounts ──► Lock Record
                     - Check active dates        - Apportion value   - Prevent reuse
                     - Verify eligibility        - Update balances   - Write snapshot
```

### 8.1 Promotion Stacking and Priorities
*   **Stacking Rules**: Dictates whether coupons can be combined with other active sales or promotions (e.g., "A discount coupon cannot stack with storewide clearance prices").
*   **Evaluation Priorities**: The engine resolves discounts in a strict sequence:
    1.  Product-level discounts (e.g., sale prices, quantity breaks).
    2.  Order-level discount coupons (e.g., promo codes, percentage discounts).
    3.  Shipping discounts (e.g., free shipping promotions).

### 8.2 Multi-Item Discount Distribution
*   When order-level coupons are applied, discount values are distributed proportionally across all eligible line items based on their net-unit prices.
*   Proportional distribution ensures that partial cancellations and returns calculate correct refund values:
    $$\text{Item Proportional Discount} = \text{Total Discount} \times \left( \frac{\text{Line Item Value}}{\text{Total Order Value}} \right)$$

### 8.3 Cancellation Recoveries
*   If a partial cancellation drops order values below coupon threshold limits, the promotion is revoked and remaining item totals are recalculated.

---

## 9. INVENTORY COORDINATION & RESERVATION

The Orders Engine coordinates inventory allocations during checkout using robust reservation checks, preventing overselling during high-volume sales:

```
                          [INVENTORY COORDINATION PIPELINE]

    Checkout Session Initiated ─────────────► Write Reservation hold to
                                             public.stock_reservations (15-min TTL)
                                                              │
                    ┌─────────────────────────────────────────┘
                    ▼
    Payment Gateway Authorization Success ──► Convert Reservation to Allocation
                                             (Set inventory_item version += 1)
                                                              │
                    ┌─────────────────────────────────────────┘
                    ▼
    Picking Waves Generated ────────────────► Dispatch Outbox Event Notifications
```

### 9.1 Reservation Creation
*   Upon entering checkout, the system attempts to reserve items in `public.stock_reservations` using `SELECT FOR UPDATE SKIP LOCKED` parameters to avoid database deadlocks.
*   Active reservations temporarily reduce available stocks while leaving physical on-hand inventories unchanged.

### 9.2 Expiration and Automated Sweeps
*   Reservations enforce a default Time-To-Live (TTL) limit of 15 minutes.
*   If checkouts timeout or fail, background sweep tasks purge the expired reservations and return the held stock to available pools:
    ```sql
    -- Sweep expired reservations and restore available quantities
    DELETE FROM public.stock_reservations
    WHERE expires_at < CURRENT_TIMESTAMP
    RETURNING product_variant_id, quantity;
    ```

### 9.3 Allocation Locks and Splits
*   When payments are authorized, reservations are converted to permanent allocations, flagging items for picking.
*   If an order contains items located in different warehouses, the system splits allocations across target facilities based on regional routing rules.

### 9.4 Backorders, Preorders, and Oversell Prevention
*   **Backorders**: If a tenant enables backorders for high-demand items, the system bypasses stock blockades, creating orders flagged with `backorder_flag = true`. Stock allocations are linked to scheduled inbound purchase orders.
*   **Preorders**: For pre-release inventory, the catalog freezes prices and issues a future delivery flag. Stock is reserved directly from dedicated preorder pools.
*   **Oversell Prevention**: Enforced at the database layer using Postgres check constraints and pessimistic isolation boundaries:
    ```sql
    ALTER TABLE public.inventory_items 
    ADD CONSTRAINT chk_available_not_negative CHECK (qty_available >= 0);
    ```

---

## 10. MULTI-VENDOR ORDER SPLITTING

For marketplaces, orders containing items from multiple vendors are split into distinct sub-orders, enabling independent vendor fulfillment.

```
                         [MULTI-VENDOR SPLIT WORKFLOW]

                   Consolidated Customer Order (ORD-9845)
                         (Gross total paid: $450)
                                    │
               ┌────────────────────┴────────────────────┐
               ▼                                         ▼
     Sub-Order: ORD-9845-A                     Sub-Order: ORD-9845-B
     - Vendor A (Electronics)                  - Vendor B (Apparel)
     - Value: $300                             - Value: $150
     - Commission: 15% ($45)                   - Commission: 10% ($15)
     - Payout: $255                            - Payout: $135
               │                                         │
     Dispatch Vendor A Pick task               Dispatch Vendor B Pick task
```

### 10.1 Sub-Order Instantiation
*   The checkout pipeline identifies vendors for each order line item, generating distinct sub-orders linked to the master customer order.
*   Consolidated customer invoices display single total charges, while vendor portals display individual sub-orders for fulfillment.

### 10.2 Vendor Commissions and Payouts
*   The system calculates vendor commissions based on active merchant contract rates, writing balances to payout ledgers inside the Finance context:
    $$\text{Vendor Payout} = \text{Sub-order Gross Total} - \text{Calculated Commission} - \text{Processing Fees}$$

### 10.3 Independent Fulfillment & SLAs
*   **Independent Shipment**: Vendors manage packaging, select shipping carriers, print labels, and upload transit details inside their isolated portal dashboard.
*   **Vendor SLAs**: Contracts mandate strict fulfillment windows (e.g., ship items within 48 hours). The system monitors transitions and reports SLA violations.
*   **Marketplace Settlement Mapping**: The consolidated payment token is distributed across merchant balance accounts in a unified ledger, linking vendor payouts to master order transactions.

---

## 11. PARTIAL FULFILLMENT

The Orders Engine manages inventory shortages and split-warehouse shipments using clean, partial fulfillment workflows.

```
                          [PARTIAL FULFILLMENT SEQUENCE]

   Warehouse short-stocks SKU ──► Split Order Items into separate shipments
                                              │
                      ┌───────────────────────┴───────────────────────┐
                      ▼                                               ▼
            Shipment A (In-Stock)                           Shipment B (Backorder)
         - Dispatch tracking link                        - Track restock timelines
         - Charge proportional values                    - Process balance capture
```

### 11.1 Split Shipments
*   If order items are located across different warehouses, the system generates separate shipments within `public.order_shipments`, allowing warehouses to pack and ship items independently.
*   Customers receive separate tracking links and notifications for each package, providing complete delivery transparency.

### 11.2 Backorder and Short-Stock Handling
*   If inventory shortages are detected during picking (e.g., due to stock damage), affected line items are flagged as Backordered.
*   The system ships the available items, and places backordered items in a secondary queue to be fulfilled from alternative warehouses or incoming supplier deliveries.

### 11.3 Customer Communications
*   The system dispatches split-fulfillment notifications to customers, detailing shipment breakdowns, tracking numbers, and expected backorder timelines.

---

## 12. ORDER LIFECYCLE FINITE STATE MACHINE (FSM)

All transactions, modifications, and fulfillment updates route through a strict, deterministic state machine to ensure correct tracking and prevent operational errors:

```
                      [ORDER LIFECYCLE STATE MACHINE]

                         ┌───────────────────────┐
                         │         Draft         │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
                         │   Pending Checkout    │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
                         │   Awaiting Payment    │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
         ┌──────────────►│  Payment Authorized   ├────────────────┐
         │               └───────────┬───────────┘                │
         │                           │                            │
    (Re-auth)                        ▼                            │
         │               ┌───────────────────────┐                │
         └───────────────┤       Confirmed       │                │
                         └───────────┬───────────┘                │
                                     │                            ▼
                                     ▼                      ┌───────────┐
                                 Processing ───────────────►│ Cancelled │
                                     │                      └───────────┘
                                     ├────────────────────────────┐
                                     ▼                            ▼
                            Partially Fulfilled               Allocated
                                     │                            │
                                     ▼                            ▼
                                 Fulfilled ◄──────────────────────┘
                                     │
                                     ▼
                                 Completed
                                     │
                                     ▼
                        ┌────────────┴────────────┐
                        ▼                         ▼
                     Refunded                  Archived
```

### 12.1 Detailed FSM States, Criteria, and Recovery

#### 12.1.1 State: `Draft`
*   **Purpose**: Represents active order creations being drafted by support specialists or B2B customer sales portals.
*   **Entry Criteria**: Created manually in the back-office or spawned via draft-order APIs.
*   **Exit Criteria**: Customer accepts quotation or administrator submits draft to checkout.
*   **Automatic Transitions**: None.
*   **Manual Transitions**: Draft ➔ Pending Checkout, Draft ➔ Cancelled.
*   **Forbidden Transitions**: Draft ➔ Confirmed, Draft ➔ Fulfilled.
*   **Rollback Rules**: Draft deletions completely release references. No inventory holds were ever placed.
*   **Timeout & Recovery**: Expired draft documents are marked Archived after 30 days of inactivity.

#### 12.1.2 State: `Pending Checkout`
*   **Purpose**: Active customer checkout session, holding temporary inventory and promotional locks.
*   **Entry Criteria**: Customer clicks checkout button, sending active cart ID.
*   **Exit Criteria**: User submits payment or exits checkout session.
*   **Automatic Transitions**: Pending Checkout ➔ Awaiting Payment (upon checkout button click).
*   **Manual Transitions**: Pending Checkout ➔ Cancelled.
*   **Forbidden Transitions**: Pending Checkout ➔ Confirmed, Pending Checkout ➔ Fulfilled.
*   **Rollback Rules**: Release active stock reservations and promotional locks.
*   **Timeout & Recovery**: Times out after 15 minutes of inactivity. Reservations are automatically re-allocated.

#### 12.1.3 State: `Awaiting Payment`
*   **Purpose**: Transaction processing window, waiting for asynchronous callbacks from payment providers.
*   **Entry Criteria**: Order created and submitted to gateway processor.
*   **Exit Criteria**: Gateway returns successful capture or failure callback.
*   **Automatic Transitions**: Awaiting Payment ➔ Payment Authorized (success token), Awaiting Payment ➔ Failed (decline).
*   **Manual Transitions**: Awaiting Payment ➔ Cancelled.
*   **Forbidden Transitions**: Awaiting Payment ➔ Processing.
*   **Rollback Rules**: Release inventory holds immediately.
*   **Timeout & Recovery**: Times out after 30 minutes if no webhook callback is received. Status moves to Failed, triggering a recovery sweep.

#### 12.1.4 State: `Payment Authorized`
*   **Purpose**: Customer funds are verified and held by the gateway provider, awaiting fraud checks.
*   **Entry Criteria**: Gateway token verification complete.
*   **Exit Criteria**: Fraud scoring tool releases order.
*   **Automatic Transitions**: Payment Authorized ➔ Confirmed (fraud score clean), Payment Authorized ➔ Failed (fraud alert high).
*   **Manual Transitions**: None.
*   **Forbidden Transitions**: Payment Authorized ➔ Processing.
*   **Rollback Rules**: Release gateway holds, restore stock reservations to available pools.
*   **Timeout & Recovery**: If stuck in screening for >24 hours, support agent is alerted.

#### 12.1.5 State: `Confirmed`
*   **Purpose**: Order confirmed, credit cards processed. Items are queued in warehouse lines.
*   **Entry Criteria**: Clean pass from fraud and financial screening.
*   **Exit Criteria**: Pick-pack worker claims queue.
*   **Automatic Transitions**: Confirmed ➔ Processing.
*   **Manual Transitions**: Confirmed ➔ Cancelled.
*   **Forbidden Transitions**: Confirmed ➔ Fulfilled.
*   **Rollback Rules**: Release allocations, refund cards via reverse API.
*   **Timeout & Recovery**: If unassigned for >12 hours, alarms trigger for depot scheduling.

#### 12.1.6 State: `Processing`
*   **Purpose**: Active warehouse preparation. Order is assigned to picker waves.
*   **Entry Criteria**: Fulfillment center ingestion.
*   **Exit Criteria**: Picker prints packing sheets.
*   **Automatic Transitions**: None.
*   **Manual Transitions**: Processing ➔ Inventory Allocated, Processing ➔ Cancelled (only before picking starts).
*   **Forbidden Transitions**: Processing ➔ Completed.
*   **Rollback Rules**: Re-queue order lines, reset picking indices.
*   **Timeout & Recovery**: Standard picker recovery algorithms.

#### 12.1.7 State: `Inventory Allocated`
*   **Purpose**: SKU items mapped to precise warehouse physical bins and inventory blocks.
*   **Entry Criteria**: Successful SKU reservation match in bin mapping matrices.
*   **Exit Criteria**: Wave picker scans barcode index.
*   **Automatic Transitions**: Inventory Allocated ➔ Picking.
*   **Manual Transitions**: Inventory Allocated ➔ Processing (upon reallocation block).
*   **Forbidden Transitions**: Inventory Allocated ➔ Cancelled (must release locks first).
*   **Rollback Rules**: Revert bin counts to active storage.

#### 12.1.8 State: `Picking`
*   **Purpose**: Physical extraction of stock items from warehouse shelves.
*   **Entry Criteria**: Scanned confirmation from picker device.
*   **Exit Criteria**: Basket placed at sorting conveyor.
*   **Automatic Transitions**: Picking ➔ Packing.
*   **Manual Transitions**: Picking ➔ Confirmed (upon physical short-stock incident).
*   **Rollback Rules**: Log discrepancies, move order to triage queue for alternative warehouse selection.

#### 12.1.9 State: `Packing`
*   **Purpose**: Consolidation, box selection, weight scanning, and shipping label printing.
*   **Entry Criteria**: Sorting terminal receipt.
*   **Exit Criteria**: Box sealed with shipping label.
*   **Automatic Transitions**: Packing ➔ Ready for Shipment.
*   **Manual Transitions**: None.

#### 12.1.10 State: `Ready for Shipment`
*   **Purpose**: Packages resting on loading docks awaiting carrier truck arrival.
*   **Entry Criteria**: Sealed box registration.
*   **Exit Criteria**: Driver scanning carrier manifest.
*   **Automatic Transitions**: Ready for Shipment ➔ Partially Fulfilled / Fulfilled.
*   **Manual Transitions**: None.

#### 12.1.11 State: `Partially Fulfilled`
*   **Purpose**: Some order items are in transit, while other items remain in backorder or split shipments.
*   **Entry Criteria**: Manifest registration of box A, while box B is delayed.
*   **Exit Criteria**: All split boxes are shipped.
*   **Automatic Transitions**: Partially Fulfilled ➔ Fulfilled.
*   **Manual Transitions**: None.

#### 12.1.12 State: `Fulfilled`
*   **Purpose**: All order packages are shipped, with carrier tracking codes generated.
*   **Entry Criteria**: Last box scanned onto carrier transport.
*   **Exit Criteria**: Carrier reports successful residential delivery scan.
*   **Automatic Transitions**: Fulfilled ➔ Completed.
*   **Manual Transitions**: None.

#### 12.1.13 State: `Completed`
*   **Purpose**: Items delivered safely, completing the active transaction life cycle.
*   **Entry Criteria**: Valid carrier delivery scans.
*   **Exit Criteria**: None.
*   **Automatic Transitions**: Completed ➔ Archived (after 180 days).
*   **Manual Transitions**: Completed ➔ Refunded (upon customer returns).

#### 12.1.14 State: `Cancelled`
*   **Purpose**: Order aborted before shipping. Reservations are released.
*   **Entry Criteria**: Administrative intervention or automated timeout.
*   **Exit Criteria**: None.
*   **Automatic Transitions**: None.
*   **Manual Transitions**: None.

#### 12.1.15 State: `Partially Refunded`
*   **Purpose**: Portion of payment returned to customer due to partial returns or cancellations.
*   **Entry Criteria**: Partial credit ledger balance settlement.
*   **Exit Criteria**: Balance is fully refunded.
*   **Automatic Transitions**: Partially Refunded ➔ Refunded.
*   **Manual Transitions**: None.

#### 12.1.16 State: `Refunded`
*   **Purpose**: Complete payment return, ledger records balance closed.
*   **Entry Criteria**: Full credit settlement.
*   **Exit Criteria**: Archived.

#### 12.1.17 State: `Failed`
*   **Purpose**: Validation or transaction processing failure.
*   **Entry Criteria**: Gateway decline or timeout checks.
*   **Exit Criteria**: Cancelled.

#### 12.1.18 State: `Archived`
*   **Purpose**: Closed files, offline storage storage, legal and financial reporting.
*   **Entry Criteria**: Expiry schedules.

### 12.2 Transition Matrix and Validation Rules

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Pending Checkout** | User Checkout | Locks active items and prices; validates SKU availability. |
| **Pending Checkout**| **Awaiting Payment** | Payment Submit | Generates transaction references; blocks changes to order details. |
| **Awaiting Payment**| **Payment Authorized**| Gateway Success | Secures payment authorizations; updates transaction records. |
| **Payment Authorized**| **Confirmed** | Fraud Check Pass | Confirms orders; releases orders to warehouse picking queues. |
| **Confirmed** | **Processing** | Warehouse Release | Generates picking tickets; groups orders into picking waves. |
| **Processing** | **Allocated** | Location Locking | Reserves specific bins and items, locking rows against concurrent picking. |
| **Processing** | **Partially Fulfilled**| Multi-Warehouse Split| Dispatches available items; places remaining items in backorder. |
| **Processing** | **Fulfilled** | Carrier Scan | Generates shipping notifications; updates order statuses. |
| **Fulfilled** | **Completed** | Delivery Scan | Updates delivery dates; triggers customer feedback loops. |
| **Confirmed** | **Cancelled** | Support Action | Cancels orders; releases inventory reservations. |
| **Completed** | **Refunded** | RMA Complete | Processes refunds; returns items to inventory pools. |
| **Awaiting Payment**| **Failed** | Gateway Decline | Cancels checkouts; flags orders as failed. |

---

## 13. CHECKOUT VALIDATION PIPELINE

Checkout submissions undergo strict validations executed sequentially inside database transaction boundaries:

```
  [Start Checkout Transaction]
         │
         ├──► 1. Customer Check: Account active? No credit blocks?
         ├──► 2. Organization Check: Tenant active? Plan status okay?
         ├──► 3. Currency Check: Matches base currency config?
         ├──► 4. Tax Region Check: Address mapped to valid tax authority?
         ├──► 5. Warehouse Check: Optimal depot identified?
         ├──► 6. Inventory Check: Available stock counts >= requested?
         ├──► 7. Promotion & Coupon Check: Code active? Stacking rules okay?
         ├──► 8. Gift Card Check: Card code has active credit balance?
         ├──► 9. Pricing Snapshot Check: Locks pricing states on line items.
         ├──► 10. Fraud Check: Risk analysis scoring matrix acceptable?
         ├──► 11. Payment Check: Stripe/gateway card capture verified?
         ├──► 12. Shipping Check: Matches approved rates?
         ├──► 13. Vendor Availability Check: Vendor stores active?
         ├──► 14. Business Rules Check: Purchase limits respected?
         ├──► 15. Duplicate Orders Check: No identical submission in last 10 min?
         └──► 16. Concurrency Check: Row versions verified?
         │
  [Commit Checkout Transaction]
```

---

## 14. IMMUTABLE PRICING SNAPSHOT

To meet international financial auditing and tax standards, checkout pricing is frozen as an immutable snapshot within the order tables.

*   **Frozen Fields**: Unit Price, List Price, Sale Price, Discounts, Applied Coupons, Gift Cards, Applied Tax Rates, Currency Exchange Rates, Shipping Fees, Platform Processing Fees, Calculated Vendor Commissions, and Marketplace Commissions.
*   **Price Adjustment Boundary**: Direct cell updates are strictly prohibited. Modifications are written to separate ledger tables:
    *   `public.order_pricing_adjustments`
    *   `public.order_credit_memos`

---

## 15. ORDER MODIFICATION RULES

To maintain financial accuracy and compliance, orders can only be edited under strict structural conditions:

### 15.1 State-Based Modification Constraints

| State | Addresses | Quantities | Item Additions | Shipping Method |
| :--- | :--- | :--- | :--- | :--- |
| **Draft** | Allowed | Allowed | Allowed | Allowed |
| **Pending Checkout** | Allowed | Allowed | Allowed | Allowed |
| **Confirmed** | Allowed (with Tax recalculation) | Reduction Only | Blocked | Blocked |
| **Processing** | Support Intervention Only | Blocked | Blocked | Blocked |
| **Allocated** | Blocked | Blocked | Blocked | Blocked |
| **Picking** | Blocked | Blocked | Blocked | Blocked |
| **Shipped** | Blocked | Blocked | Blocked | Blocked |

### 15.2 Rule Declarations
*   **No Item Additions**: Customers cannot add new products to completed orders, as this changes transaction captures. Adding items requires spawning a separate order.
*   **Order Merging**: Combining orders is strictly prohibited, protecting transactional event histories and mapping paths.
*   **Order Splitting**: Orders can be split into distinct shipments based on vendor origins or warehouse locations. Split orders generate individual tracking IDs under the master order reference.

---

## 16. RETURNS AND RMA PREPARATION

Returns are managed using structured workflows to ensure quality checks and accurate financial tracking:

### 16.1 Return Window Guidelines
*   **Retail Customers**: 30-day return eligibility window post-delivery.
*   **Enterprise B2B**: 90-day return window validation checks.
*   **Non-Returnable Items**: Clearance sales, digital software licenses, and perishable goods are blocked at the RMA engine layer.

### 16.2 Processing Steps
1.  **RMA Token Generation**: Generates a secure RMA token and pre-paid shipping label.
2.  **Partial Returns**: Customers can return portions of orders. Net totals, taxes, and proportionally distributed discounts are recalculated automatically.
3.  **Vendor Approval Gate**: For multi-vendor marketplaces, RMA requests routes to the respective vendor portal for authorization.
4.  **Warehouse Inspection & Restocking**: Returned items are graded at receiving docks:
    *   *Grade A (Like New)*: Restocked to picking shelves, incrementing inventory.
    *   *Grade B (Open Box)*: Moved to clearance or liquidations pools.
    *   *Grade C (Damaged)*: Written off as scrap, logging losses to general ledgers.
5.  **Refund Reference Association**: Finalized refunds trigger API commands to payment gateways, saving successful refund transaction IDs alongside RMAs for reconciliation.

---

## 17. BUSINESS EVENT CONTRACTS

The Orders Context communicates transactional milestones to downstream systems using standardized event payloads written to transactional outbox tables:

### 17.1 Schema Contract: `cart.created.v1`
*   **Publisher**: Cart Engine Service
*   **Primary Consumers**: Marketing engines, abandoned-cart tracking systems.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033531",
  "event_type": "cart.created.v1",
  "timestamp": "2026-07-01T00:30:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "cart_id": "018f63bb-9ab6-7000-8d59-fc5095033532",
  "customer_id": "018f63bb-9ab6-7000-8d59-fc5095033524",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.2 Schema Contract: `cart.updated.v1`
*   **Publisher**: Cart Engine Service
*   **Primary Consumers**: Real-time pricing models, retargeting engines.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033533",
  "event_type": "cart.updated.v1",
  "timestamp": "2026-07-01T00:31:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "cart_id": "018f63bb-9ab6-7000-8d59-fc5095033532",
  "items": [
    {
      "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
      "quantity": 3
    }
  ],
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.3 Schema Contract: `checkout.started.v1`
*   **Publisher**: Checkout Engine Service
*   **Primary Consumers**: Shopping analytics, customer onboarding.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033534",
  "event_type": "checkout.started.v1",
  "timestamp": "2026-07-01T00:32:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "checkout_session_id": "018f63bb-9ab6-7000-8d59-fc5095033535",
  "cart_id": "018f63bb-9ab6-7000-8d59-fc5095033532",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.4 Schema Contract: `checkout.completed.v1`
*   **Publisher**: Checkout Engine Service
*   **Primary Consumers**: Order processors, marketing funnels.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033536",
  "event_type": "checkout.completed.v1",
  "timestamp": "2026-07-01T00:33:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "checkout_session_id": "018f63bb-9ab6-7000-8d59-fc5095033535",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.5 Schema Contract: `order.created.v1`
*   **Publisher**: Checkout Engine Service
*   **Primary Consumers**: Shipping coordinators, inventory managers, analytics engines.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095233521",
  "event_type": "order.created.v1",
  "timestamp": "2026-07-01T00:30:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "order_number": "ORD-2026-98451",
  "customer_id": "018f63bb-9ab6-7000-8d59-fc5095033524",
  "total_amount": 189.98,
  "currency": "USD",
  "items": [
    {
      "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
      "quantity": 2,
      "unit_price": 94.99
    }
  ],
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.6 Schema Contract: `order.confirmed.v1`
*   **Publisher**: Order Management Service
*   **Primary Consumers**: Warehouse wave schedulers, partner notification interfaces.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033526",
  "event_type": "order.confirmed.v1",
  "timestamp": "2026-07-01T00:32:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "confirmed_at": "2026-07-01T00:31:58Z",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.7 Schema Contract: `order.processing.v1`
*   **Publisher**: Order Fulfillment Service
*   **Primary Consumers**: Warehouse Management System (WMS), logistics dashboards, shipping coordinators.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033544",
  "event_type": "order.processing.v1",
  "timestamp": "2026-07-01T00:33:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "warehouse_id": "018f63bb-9ab6-7000-8d59-fc5095033991",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.8 Schema Contract: `order.cancelled.v1`
*   **Publisher**: Order Management Service
*   **Primary Consumers**: Inventory controllers, financial refund systems, customer notifications.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033527",
  "event_type": "order.cancelled.v1",
  "timestamp": "2026-07-01T00:35:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "reason": "CUSTOMER_REQUEST",
  "cancelled_at": "2026-07-01T00:34:55Z",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.9 Schema Contract: `order.completed.v1`
*   **Publisher**: Order Management Service
*   **Primary Consumers**: Financial ledgers, customer satisfaction surveys.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033538",
  "event_type": "order.completed.v1",
  "timestamp": "2026-07-01T00:40:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
  "completed_at": "2026-07-01T00:39:58Z",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.10 Schema Contract: `order.failed.v1`
*   **Publisher**: Checkout Engine Service
*   **Primary Consumers**: Customer recovery workflows, support center alerting.
*   **Payload Schema**:
```json
{
  "event_id": "018f63bb-9ab6-7000-8d59-fc5095033547",
  "event_type": "order.failed.v1",
  "timestamp": "2026-07-01T00:31:00Z",
  "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
  "checkout_session_id": "018f63bb-9ab6-7000-8d59-fc5095033535",
  "reason": "PAYMENT_DECLINED",
  "error_message": "Card verification failed (3D Secure validation timeout)",
  "correlation_id": "018f63bb-9ab6-7000-8d59-fc5095033001",
  "trace_id": "018f63bb-9ab6-7000-8d59-fc5095033999"
}
```

### 17.11 Global Resiliency & Message Handling Policies
*   **Correlation & Trace IDs**: To ensure traceability across distributed systems, all payloads must carry `correlation_id` and `trace_id` fields, propagated across HTTP/gRPC headers.
*   **Ordering Guarantees**: Sequential processing is achieved by partitioning messaging queues (e.g., Kafka Topics, RabbitMQ exchanges) using the `order_id` or `cart_id` as partition keys. This ensures that an `order.confirmed.v1` event is never processed before its corresponding `order.created.v1` event by a consumer.
*   **Retry Mechanisms**: Failed event dispatches use exponential backoff schedules, retrying 5 times before routing messages to dead-letter queues.
*   **Dead-Letter Queues (DLQ)**: Events failing all retry attempts are quarantined in `dlq.marketplace.orders.v1`. Support webhooks alert system administrators for manual repair.
*   **Idempotency Checks**: Downstream systems track unique message keys (`event_id`) to block duplicate message processing.
*   **Versioning**: All events adhere to semantic versioning patterns. Breaking schema changes require publishing new major routes (e.g., `v2`).

---

## 18. ROLE-BASED ACCESS CONTROL (RBAC) & DATA SECURITY

The Orders subsystem implements strict, multi-tenant security layers to protect sensitive customer data and order details:

```
                          [SECURITY CONTROL ACCESS GATE]

     Client Request ──► Verify Session JWT ──► Verify Tenant Isolation (RLS)
                                                      │
                   ┌──────────────────────────────────┘
                   ▼
     Verify RBAC Role Action ──► Execute Database Write ──► Write Compliance Log
```

### 18.1 Row-Level Security (RLS)
All tables within the orders context must declare PostgreSQL RLS policies to prevent cross-tenant data leaks.

```sql
-- Enable Row Level Security
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.order_items ENABLE ROW LEVEL SECURITY;

-- Construct Tenant Isolation Policies
CREATE POLICY tenant_isolation_orders_policy ON public.orders
    FOR ALL
    USING (organization_id = (current_setting('request.jwt.claim.organization_id', true))::uuid);

CREATE POLICY tenant_isolation_items_policy ON public.order_items
    FOR ALL
    USING (organization_id = (current_setting('request.jwt.claim.organization_id', true))::uuid);
```

### 18.2 RBAC Role Map & JWT Scopes

| Role | JWT Scopes | Permitted Entities | Permitted FSM Mutations | Visibility Boundaries |
| :--- | :--- | :--- | :--- | :--- |
| **Customer Account** | `order:read:own`, `cart:write` | `public.carts`, `public.orders` (Own) | Draft ➔ Pending Checkout, Confirmed ➔ Cancelled | Visible only to own user profile. |
| **Merchant Manager** | `order:read:sub`, `order:write:sub` | `public.orders` (Sub-Orders) | Processing ➔ Allocated, Fulfilled | Limited to sub-orders containing own products. |
| **Support Specialist**| `order:read:tenant`, `order:write:tenant` | `public.orders` (Tenant Wide) | Confirmed ➔ Cancelled, Completed ➔ Refunded | Tenant-wide visibility; edits locked after shipment. |
| **Administrator** | `order:admin:all` | All Tables | All Transitions | Full platform visibility. |

### 18.3 Maker-Checker Approvals
*   High-value actions (e.g., refunds over $1,000) are flagged for supervisor approval before being committed.
*   Audits track user IDs, supervisor approvals, and timestamps to ensure operational compliance.

### 18.4 Privacy, Compliance, and Data Retention
*   **GDPR & CCPA**: Upon receiving a deletion request, customer-specific PII fields inside transactional databases are scrubbed or masked with high-entropy placeholder values. To prevent audit trails from breaking, invoice records are preserved under legal compliance exemptions, keeping cash totals intact while stripping customer attributes.
*   **PII Masking**: Names, addresses, credit cards, and telephone lines are masked within read replicas and server logging trails:
    *   *Input*: `John Doe, 123 Main St, 555-0199`
    *   *Masked*: `J*** D***, 123 M*** St, 555-****`
*   **Data Retention**: Transactional histories are retained for a minimum of 7 years to comply with international fiscal guidelines. Ephemeral Redis carts are purged automatically after 14 days.

---

## 19. HIGH-PERFORMANCE SCALABILITY ENGINE

To scale orders processing across high checkout volumes, several performance optimizations are implemented at the database and application levels:

### 19.1 CQRS Segregation & Read Replicas
*   **CQRS Segregation**: Transactional OLTP writes target the main PostgreSQL database. Real-time customer dashboards, analytical reports, and searching indexing tasks query read replicas or Elasticsearch indexes.
*   **Materialized Views**: Aggregated vendor sales metrics, transaction velocities, and order performance analytics are generated inside materialized views updated on an hourly cron schedule, shielding write pools from resource exhaustion.

### 19.2 Table Partitioning Strategy
Primary order registries (`public.orders` and `public.order_items`) utilize list partitioning keyed by year and month values on `created_at` columns.
*   **Active Partition**: Direct checkout write paths only hit the current month's partition slice.
*   **Archived Partitions**: Historical months are locked, mounted on slower archival block storage disks, and queried solely for historical searches.

### 19.3 Performance Indexing Map
```sql
-- Covering composite index on active lookup paths
CREATE INDEX idx_orders_org_customer_status ON public.orders (organization_id, customer_id, lifecycle_status);

-- Partial index on active reservations to speed up automated sweeps
CREATE INDEX idx_active_reservations ON public.stock_reservations (expires_at) 
WHERE expires_at > CURRENT_TIMESTAMP;

-- Composite indexing for vendor invoice lookups
CREATE INDEX idx_order_items_vendor_sku ON public.order_items (vendor_id, sku);
```

### 19.4 High-Throughput SKIP LOCKED Queue Patterns
For high-volume transactional queue processing (e.g., processing transaction outboxes, matching picking lines), workers query tables using lock-free patterns:
```sql
-- Transactional sweep of the outbox table
BEGIN;
SELECT id, event_type, payload 
FROM public.marketplace_event_outbox
WHERE processed = false
ORDER BY created_at ASC
LIMIT 100
FOR UPDATE SKIP LOCKED;

-- [Process payloads and dispatch to broker]

UPDATE public.marketplace_event_outbox
SET processed = true, processed_at = CURRENT_TIMESTAMP
WHERE id = ANY(array_of_ids);
COMMIT;
```

---

## 20. ARCHITECTURE REVIEW BOARD VALIDATION MATRIX

The Technical Review Board verifies all system builds against the following compliance verification checklist:

| Review Category | Validation Criteria & Test Assertions | Status | Verification Protocol |
| :--- | :--- | :---: | :--- |
| **FSM Correctness** | Verify that state transitions strictly follow the deterministic transition matrix, blocking illegal state jumps. | [ ] | Run state transition unit suites, asserting exception codes on forbidden paths. |
| **Pricing Integrity** | Confirm that pricing snapshot records inside `public.order_items` remain frozen across subsequent catalog rate changes. | [ ] | Edit catalog price variant record, asserting that completed order records match checkout values. |
| **Inventory Consistency**| Test stock levels under extreme concurrent load, verifying check constraint triggers. | [ ] | Execute Jmeter stress test with 5,000 threads buying matching SKU blocks. Assert 0 stock overshoot. |
| **Reservation Correctness**| Validate that expired reservations are cleaned, restoring physical counts instantly. | [ ] | Inject test reservation, fast-forward mock date past TTL threshold, and execute sweep. |
| **Vendor Splitting** | Check that orders with multiple vendors split into separate sub-orders with correct commission ledger writes. | [ ] | Submit mock cart with SKU A (Vendor A) and SKU B (Vendor B). Verify creation of ORD-X-A and ORD-X-B. |
| **Checkout Validation**| Ensure that the 16 validation steps execute sequentially, rolling back on failures. | [ ] | Inject credit lock on buyer profile, trigger checkout, assert total database rollback. |
| **Payment Coordination** | Confirm that card capture failures roll back reserved stock allocations cleanly. | [ ] | Process payment with invalid test card token, verifying that stock reservations are freed. |
| **Concurrency Safeguards**| Verify that simultaneous cart additions from two client browser tabs resolve via OCC. | [ ] | Execute overlapping updates with stale version keys, asserting OCC abort codes. |
| **Deadlock Prevention** | Confirm that picking queue allocation tasks utilize `FOR UPDATE SKIP LOCKED` parameters. | [ ] | Launch multiple worker threads reading overlapping rows, asserting zero lock deadlocks. |
| **Event Ordering** | Validate that partition key algorithms guarantee linear event delivery. | [ ] | Trace outbox events on Kafka logs, verifying chronological sequence of `created` before `confirmed`. |
| **Outbox Transactionality**| Verify outbox rows commit in identical transaction blocks as their parent order updates. | [ ] | Trigger mock database failures midway during order writes, verifying 0 orphan outbox lines. |
| **Row-Level Security (RLS)**| Assert that cross-tenant queries return empty result sets. | [ ] | Query orders using Org A JWT token, asserting Org B records are invisible. |
| **RBAC Boundaries** | Confirm that Merchant accounts cannot view sister vendor's sub-orders. | [ ] | Attempt sub-order read with Vendor B JWT on Vendor A sub-order, asserting 403 Forbidden. |
| **Audit Trails** | Verify database-level auditing tracks all modifications. | [ ] | Execute administrative support correction, asserting history row matches actor ID. |
| **Performance Targets** | Checkout execution completion must remain sub 200ms. | [ ] | Measure transaction trace logs on APM dashboards during load test spikes. |
| **Disaster Recovery** | Validate that active checkout states recover smoothly from server node drops. | [ ] | Drop Kubernetes database pods mid-checkout, verifying transaction log rollbacks on recovery. |

---

## 21. CLOSE-COUPLED ARCHITECTURAL CROSS-REFERENCES

For complete physical design definitions, integration guides, and database schema mappings, refer to the following JUANET core documents:

1.  **PostgreSQL Physical Standards & Blueprints**: Mapping of primary database connection clusters, partition rules, and indexing rules (`/docs/database/01_Core_DB_Standards.md`).
2.  **Marketplace Physical Tables**: Definitive PostgreSQL schema layouts for carts, orders, sub-orders, and outbox tables (`/docs/database/07_Marketplace/Phase_2_3_2H_Marketplace_Physical_Tables.md`).
3.  **Product Catalog Specification**: Definitions of global product variants, master SKU tables, and vendor organization mapping parameters (`/docs/database/07_Marketplace/Phase_2_3_2H_1_Marketplace_Product_Catalog_and_Catalog_Management.md`).
4.  **Pricing, Discounts & Promotion Engine Manual**: Rule books governing active contract lines, coupon definitions, and proportional rate distribution mathematics (`/docs/database/07_Marketplace/Phase_2_3_2H_2_Pricing_Discounts_and_Promotion_Engine.md`).
5.  **Inventory & Warehouse Management Manual**: Warehouse locator definitions, bin coordinate maps, picking waves, and reservation ledger procedures (`/docs/database/07_Marketplace/Phase_2_3_2H_3_Inventory_and_Warehouse_Management.md`).
6.  **Enterprise Entity Dictionary**: Definitive master document indexing all data types, column attributes, and tenant references across Bounded Contexts (`/docs/database/08_Entity_Dictionary.md`).

---

*Authorized and committed by the JUANET Technical Review Board & Global Transactional Council.*
