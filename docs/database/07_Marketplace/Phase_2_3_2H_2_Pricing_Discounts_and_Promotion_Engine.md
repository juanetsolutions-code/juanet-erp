# JUANET Marketplace Pricing, Discounts, and Promotion Engine Manual
## Phase 2.3.2H.2 — Pricing Architecture, Deterministic Resolution Hierarchy, Lifecycle State Machines, Event Contracts, and Integration Standards
**Document Version:** 1.0  
**Author:** Principal Enterprise Solutions Architect, VP of Commerce Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. PRICING PHILOSOPHY

The **JUANET Marketplace Pricing Engine** operates as an entirely independent, isolated, multi-tenant bounded context. In enterprise commerce architectures (comparable to SAP Commerce Cloud, Salesforce Commerce Cloud, and Adobe Commerce), treating pricing as a decoupled domain is critical to prevent database locking, ensure low-latency storefront product page loads, and isolate complex commercial calculation logic from core catalog records.

```
                           [DECOUPLED PRICING BOUNDED CONTEXT]

     ┌────────────────────────────────────────────────────────────────┐
     │                      PRODUCT CATALOG CONTEXT                   │
     │  - Master SKU Registry                                         │
     │  - Custom Attributes & Variants                                │
     └──────────────────────────────┬─────────────────────────────────┘
                                    │ (Emits product.created.v1)
                                    ▼
     ┌────────────────────────────────────────────────────────────────┐
     │                     PRICING ENGINE CONTEXT                     │
     │  - Price Lists (public.price_lists)                            │
     │  - Variant Base & Tier Price Models (public.product_prices)    │
     │  - Volume Escalations (public.volume_pricing)                  │
     │  - Active Customer Contract Overrides (public.customer_rules)   │
     │  - Coupon & Discount Engines (public.coupon_codes)             │
     └──────────────────────────────┬─────────────────────────────────┘
                                    │
                       (Outbox Event Notifications)
                                    ▼
     ┌────────────────────────────────────────────────────────────────┐
     │                   TRANSACTIONAL CHECKOUT / ORDER               │
     │  - Frozen Pricing Snapshots (public.marketplace_order_links)   │
     └────────────────────────────────────────────────────────────────┘
```

### 1.1 Architectural Foundations & Decoupling Boundaries
*   **Pricing Bounded Context**: Pricing is a dedicated microservice context. The Product Catalog is completely blind to marketing promotions, seasonal campaigns, customer volume pricing, and regional exchange rates. The Product Catalog owns *what the product is*; the Pricing Context owns *how much it costs under a specific transaction context*.
*   **Separation from Catalog Models**: A product variant's price is not a hardcoded attribute on the main product table. While `public.product_variants.price_base` acts as the master default MSRP fallback, all active marketplace commerce transactions route through scoped price registers (`public.price_lists` and `public.product_prices`). This decoupling prevents lock contention on catalog records when dynamic pricing engines adjust rates multiple times per second.
*   **Event-Driven Syncing**: When a product variant is created or changed, the pricing engine consumes a `product.created.v1` or `product.updated.v1` event, registering it within active pricing lists asynchronously.
*   **Effective Dating**: All pricing lists, discount rules, promotions, and coupons implement explicit temporal controls (`starts_at` and `ends_at` timestamptz bounds) to support scheduled promotions, flash sales, and seasonal contract pricing.
*   **Price Versioning**: Every modification to a price list or promotion record generates an immutable audit record, enabling temporal audits and rollback recovery in compliance with SOC 2 requirements.
*   **CQRS Lite Separation**: Read-intensive pricing evaluations (such as product search listings) run against localized caches (Redis) and materialized read-views, while transactional checkout verifications run against strict ACID transaction blocks.
*   **Event Sourcing Principles**: Price adjustment logs and balance runs (e.g., gift card ledger debits) are stored as an append-only transaction ledger, ensuring that balances can be reconstructed from scratch.
*   **Multi-Tenant Separation**: All pricing tables use a non-nullable `organization_id` tenant identifier as their partitioning and RLS routing key.
*   **Separation from Finance Ledger**: The Pricing Engine computes active commercial values during checkout, whereas the Finance Bounded Context processes post-checkout double-entry ledger bookings once an order has been successfully placed.

---

## 2. PRICE MODELS

The engine natively supports several dynamic, context-specific commercial price structures to accommodate complex B2B, B2C, and multi-merchant scenarios:

```
                            [SUPPORTED PRICING MODELS]
    ┌───────────────────────────────────┬───────────────────────────────────┐
    │ Basic / Cost Models               │ Contractual / Channel Models       │
    ├───────────────────────────────────┼───────────────────────────────────┤
    │ - Base Default MSRP               │ - B2B Contractual Agreements      │
    │ - Unit Acquisition Cost           │ - Channel / Storefront Specific   │
    │ - comparative Retail Price        │ - Geographic / Regional Tiers     │
    ├───────────────────────────────────┼───────────────────────────────────┤
    │ Subscription & Temporal Models    │ Dynamic / Behavioral Models       │
    ├───────────────────────────────────┼───────────────────────────────────┤
    │ - Recurring Subscription Interval │ - Tiered Quantity / Volume Scales │
    │ - Time-Based Rental Slot          │ - Real-Time Dynamic Demand        │
    │ - Flash Sale / Scheduled Overrides│ - AI Recommended Optimized Rates   │
    └───────────────────────────────────┴───────────────────────────────────┘
```

*   **Base Price**: The standard baseline selling price for a SKU, defined inside `public.product_variants.price_base` as a fallback.
*   **Cost Price**: The merchant’s unit acquisition cost (`public.product_variants.cost_per_item`), used solely for calculating margins and verifying that pricing rules do not sell products below cost.
*   **Retail Price (MSRP)**: Comparative retail reference price (`public.product_variants.compare_at_price`), rendered as a crossed-out baseline value to highlight savings.
*   **Wholesale / Distributor Price**: Lower pricing models assigned to merchant distribution networks and bulk trade accounts, resolved via target customer groups.
*   **Regional Price**: Localized price sheets bound to specific geographical markets (e.g., charging different rates in North America vs. EMEA), managed within `public.price_lists` and filtered by regional customer IP or delivery location.
*   **Channel Price**: Dynamic prices optimized for specific digital entry points (e.g., offering lower prices on mobile apps to drive user engagement vs. web or retail POS terminals).
*   **Contract Price**: Fixed, long-term pricing agreements negotiated with specific B2B clients, stored in `public.customer_price_rules` and bound to the client's organization ID.
*   **Subscription Price**: Multi-interval billing parameters (e.g., weekly, monthly, annually) managed within `public.subscription_pricing`.
*   **Rental Price**: Duration-based pricing parameters (e.g., hourly, daily) integrated with booking calendars.
*   **Promotional / Flash Sale Price**: Temporary pricing overrides tied to marketing campaigns. These rules override normal pricing catalogs for short, pre-configured windows.
*   **Auction Reserve Price**: The minimum price threshold required to validate and accept auction bids.
*   **Marketplace Commission Price**: Rules governing platform fees deducted from merchant payouts, configured in `public.vendor_settings.commission_override_rate`.
*   **Partner Pricing**: Discounted price sheets made available to authorized partner networks or referrers.
*   **Volume / Tier Pricing**: Escalating quantity discounts (e.g., $10/unit for 1-9 units, $8/unit for 10+ units) tracked inside `public.volume_pricing`.
*   **Dynamic Pricing**: High-frequency price adjustments based on real-time market signals, catalog inventory counts, and competitive indices.
*   **AI Recommended Price**: Price recommendations generated by machine learning models and held in a `DRAFT` state for admin review.

---

## 3. PRICING HIERARCHY AND DETERMINISTIC RESOLUTION

When a customer views a product page or proceeds to checkout, the Pricing Engine must resolve a single, definitive transaction price for every SKU. To ensure consistent results across all storefront channels, the engine applies a strict, deterministic priority cascade:

```
                      [DETERMINISTIC PRICE RESOLUTION CASCADED PATH]

                                 START SKU RESOLUTION
                                          │
                        Is there an ACTIVE MANUAL OVERRIDE?
                                ├─── YES ───► Use Override Price [Term 1]
                                └─── NO ────► [Cascade 2]
                                                │
                                  Is an ACTIVE PROMOTION present?
                                ├─── YES ───► Use Promotion Price [Term 2]
                                └─── NO ────► [Cascade 3]
                                                │
                              Is a CONTRACT PRICE rule active?
                                ├─── YES ───► Use Contract Price [Term 3]
                                └─── NO ────► [Cascade 4]
                                                │
                            Does a VOLUME / TIER pricing rule apply?
                                ├─── YES ───► Use Volume Price [Term 4]
                                └─── NO ────► [Cascade 5]
                                                │
                          Is there a REGIONAL / CHANNEL price list?
                                ├─── YES ───► Use Regional Price [Term 5]
                                └─── NO ────► [Cascade 6]
                                                │
                                     Use DEFAULT BASE MSRP
                                    (Fallback to product_variants)
```

### 3.1 Precedence Levels
1.  **Level 1: Manual / Cart-Level Override (Highest Precedence)**: Direct overrides applied manually by authorized support agents or sales staff. Validated using cryptographically signed session tokens.
2.  **Level 2: Campaign Promotion / Coupon Overrides**: Overrides calculated from active promo codes (`public.coupon_codes`) or site-wide sales (`public.promotions`).
3.  **Level 3: B2B Contract Pricing**: Customer-specific pricing rules defined in `public.customer_price_rules`.
4.  **Level 4: Customer Segment Group Pricing**: Price sheets assigned to specific customer cohorts (e.g., VIP loyalty tiers).
5.  **Level 5: Volume Pricing Tiers**: Quantity-dependent unit rates verified against current line-item counts in `public.volume_pricing`.
6.  **Level 6: Regional & Channel Price Lists**: Prices scoped by local currency, geographic region, and sales channel.
7.  **Level 7: Default Catalog Base Price**: The default MSRP defined in `public.product_variants.price_base`.
8.  **Level 8: System Fallback Price (Lowest Precedence)**: A hardcoded safety rate used to block checkouts if missing configuration values would otherwise result in a $0 sale.

### 3.2 Resolution Algorithm
The Pricing Engine executes this resolution logic using the following structured process:

```typescript
interface ResolutionContext {
  organizationId: string;
  variantId: string;
  customerId: string | null;
  customerGroupId: string | null;
  regionCode: string;
  channelId: string;
  quantity: number;
  couponCodes: string[];
}

async function resolveSkuPrice(ctx: ResolutionContext): Promise<number> {
  const now = new Date();

  // 1. Check for Active Promotional Overrides via Coupon Codes
  if (ctx.couponCodes.length > 0) {
    const promoPrice = await queryActiveCouponPromotions(ctx, now);
    if (promoPrice !== null) return promoPrice;
  }

  // 2. Check for General Active Promotions (Automatic Discounts)
  const automaticPromoPrice = await queryActiveAutomaticPromotions(ctx, now);
  if (automaticPromoPrice !== null) return automaticPromoPrice;

  // 3. Check for B2B Contract and Customer Group Price Lists
  if (ctx.customerGroupId) {
    const contractPrice = await queryCustomerGroupPrice(ctx, now);
    if (contractPrice !== null) return contractPrice;
  }

  // 4. Check for Volume/Tier Pricing Rules
  const volumePrice = await queryVolumePricing(ctx);
  if (volumePrice !== null) return volumePrice;

  // 5. Check for Scoped Regional & Channel Price Lists
  const regionalPrice = await queryRegionalPriceList(ctx, now);
  if (regionalPrice !== null) return regionalPrice;

  // 6. Fallback to Master Product Variant Base Price
  const basePrice = await queryVariantBasePrice(ctx.variantId);
  if (basePrice !== null) return basePrice;

  // 7. System Emergency Fallback to prevent $0 checkout errors
  throw new Error(`CRITICAL: Pricing engine failed to resolve a price for Variant: ${ctx.variantId}`);
}
```

---

## 4. CURRENCY MANAGEMENT AND ROUNDING

Multi-tenant platforms must compute, convert, and display product prices across global markets with absolute numerical precision, preventing rounding discrepancies during financial audits.

```
                         [BANKER'S ROUNDING (HALF-EVEN) MODEL]

               Calculated Raw Value: 12.345  ──► Round to Even ──► 12.34
               Calculated Raw Value: 12.355  ──► Round to Even ──► 12.36
```

### 4.1 Banker's Rounding (Half-Even) Standards
To prevent cumulative rounding errors from skewing order totals upwards (as standard rounding does), the JUANET engine enforces **Banker's Rounding (IEEE 754 Round Half-Even)** for all currency calculations. 
*   If the fractional part of a value is exactly halfway between two integers, it rounds to the nearest even number:
    *   `10.125` rounds to `10.12`
    *   `10.135` rounds to `10.14`
*   Rounding operations are isolated to the application layer to maintain consistency across different database platforms.

### 4.2 Multi-Currency Handling and Exchange Rate Snapshots
*   **Base Catalog Currencies**: All transactions are calculated using the catalog's base currency, converting to local currencies at checkout using current exchange rates.
*   **Exchange Rate Snapshots**: To prevent price differences if exchange rates fluctuate during a checkout session, the engine locks the exchange rate when the user begins checkout:
    ```json
    {
      "checkout_id": "018f63bb-9ab6-7000-8d59-fc50950334a1",
      "base_currency": "USD",
      "target_currency": "EUR",
      "locked_exchange_rate": 0.923451,
      "rate_snapshot_timestamp": "2026-06-30T16:15:00Z"
    }
    ```
*   **Tax-Inclusive vs. Tax-Exclusive Pricing**: 
    *   *US Markets*: Displays tax-exclusive prices, calculating sales tax dynamically at checkout based on the delivery address.
    *   *EMEA/APAC Markets*: Displays tax-inclusive prices, calculating embedded VAT/GST rates using back-allocation equations:
        $$\text{Net Price} = \frac{\text{Gross Display Price}}{1 + \text{Tax Rate}}$$

---

## 5. DISCOUNTS AND PRIORITY RULES

The engine processes multiple stacked discount rules using a deterministic priority resolver to prevent customers from combining unintended discounts.

```
                         [DISCOUNT RESOLUTION FLOW]

              Raw Resolved Base Price ──► apply Customer Group Discounts (e.g., -10%)
                                                   │
                             ┌─────────────────────┘
                             ▼
              Apply Eligible Coupons (e.g., -$20) ──► Apply Flat Shipping Discounts
```

### 5.1 Supported Discount Formats
*   **Percentage Discounts**: Reduces product or cart totals by a set percentage (e.g., 15% off).
*   **Fixed Amount Discounts**: Deducts a flat currency amount (e.g., $10 off), capped at the product’s unit cost to prevent negative pricing.
*   **Buy X Get Y (BXGY)**: Dynamically awards discounts (e.g., Buy 2 Get 1 Free) by grouping qualifying items by price and discounting the lowest-priced item.
*   **Bundle Discounts**: Applies custom discount rates when specific item groupings are purchased together.
*   **Loyalty & Customer Segment Discounts**: Customer-specific discounts resolved using user segment profiles.
*   **Clearance & Markdowns**: Standard markdown rules used to clear aging inventory.

### 5.2 Stacking and Exclusivity Logic
To prevent customers from combining multiple high-value discounts on a single order, the engine evaluates stacking rules using specific parameters:
*   **`is_stackable` Boolean Flag**: If set to `FALSE`, the discount cannot be combined with any other active discounts. The engine will evaluate all eligible discounts and apply only the single highest-value discount.
*   **`priority` Integer Scale**: Defines the order in which multiple stackable discounts are applied (e.g., applying percentage discounts before fixed-amount deductions).
*   **Category Constraints**: Restricts discounts to specific categories or catalog segments, protecting high-margin items from margin erosion.

---

## 6. THE PROMOTION ENGINE

The **JUANET Promotion Engine** evaluates cart contents, customer profiles, and active campaigns to calculate promotional discounts at checkout.

```
                         [PROMOTION EVALUATION BLOCK]

   Order Checkout Started ──► Fetch Promotions ──► Evaluate Rules & Target Criteria
                                                         │
                                  ┌──────────────────────┘
                                  ▼
   Resolve Conflicts ──► Apply Priorities ──► Generate Frozen Checkout Snapshots
```

### 6.1 Promotion Lifecycle and Configuration
Promotions are configured in `public.promotions` and managed via administrative workflows:
*   **Audience Targeting**: Restricts promotions to specific customer cohorts (e.g., first-time buyers).
*   **Geographic Targeting**: Scopes campaign eligibility to specific delivery zones or shipping regions.
*   **Time Windows**: Supports time-constrained campaigns, automatically activating and deactivating promotions based on system timestamps.
*   **Usage Limits**: Sets global and per-customer usage caps to protect marketing budgets:
    *   *Global Limits*: The maximum number of times a promotion can be redeemed platform-wide.
    *   *Per-Customer Limits*: Restricts redemptions per user ID to prevent coupon abuse.

### 6.2 Conflict Resolution
When multiple promotions match a customer's cart, the engine resolves conflicts using the following rules:
*   **Exclusive Promotions**: If an exclusive promotion matches the cart, all other active promotions are discarded.
*   **Best-Deal Algorithm**: If multiple non-stackable promotions are eligible, the engine calculates the savings for each and automatically applies the option that saves the customer the most money.

---

## 7. PRICING LIFECYCLE STATE MACHINE (FSM)

All pricing listings, promotional rules, and discount configurations route through a deterministic state machine to ensure correct staging, reviewing, and publishing.

```
                        [PRICING LIFECYCLE STATE MACHINE]

                         ┌───────────────────────┐
                         │         Draft         │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
                         │    Pending Review     │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
         ┌──────────────►│       Approved        │
         │               └───────────┬───────────┘
         │                           │
   (Reactivation)                    ▼
         │               ┌───────────────────────┐
         │               │       Scheduled       │
         │               └───────────┬───────────┘
         │                           │
         │                           ▼
         │               ┌───────────────────────┐
         ├───────────────┤        Active         │
         │               └───────────┬───────────┘
         │                           │
         │                           ▼
         │               ┌───────────────────────┐
         └───────────────┤        Paused         │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
                         │       Expired         │
                         └───────────┬───────────┘
                                     │
                                     ▼
                         ┌───────────────────────┐
                         │       Archived        │
                         └───────────────────────┘
```

### 7.1 FSM States
1.  **Draft**: Work-in-progress price rules. Skipping system validations is permitted during editing.
2.  **Pending Review**: Pricing rules submitted for verification. Triggers automated checks for negative margins and overlapping dates.
3.  **Approved**: Verified rules signed off by administrators and ready for publishing.
4.  **Scheduled**: Staged prices set to activate automatically at a future date (`starts_at` timestamp).
5.  **Active**: Live pricing rules currently applied to storefront searches and checkouts.
6.  **Paused**: Temporarily deactivated rules, bypassed by the pricing resolver.
7.  **Expired**: Rules whose active end dates (`ends_at` timestamp) are in the past.
8.  **Archived**: Discarded price rules preserved solely for historical audit trails.
9.  **Deleted**: Soft-deleted records hidden from all active systems.

### 7.2 Transition Matrix and Validation Rules

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Pending Review** | API Submission | Verifies that prices are greater than 0; checks for active target products and currencies. |
| **Pending Review** | **Approved** | Editor Approval | Triggers Maker-Checker rules; verifies that promotional rates do not drop below product cost. |
| **Approved** | **Scheduled** | Scheduler Engine | Verifies that future start dates are greater than the current system time. |
| **Approved** | **Active** | Immediate Activation | Instantly registers pricing rules on active indices and clears CDN caches. |
| **Scheduled** | **Active** | Cron Sweep Job | Automatically activates rules once current system times surpass scheduled start dates. |
| **Active** | **Paused** | Manual Intervention | Temporarily suspends pricing rules; clears active Redis caches. |
| **Paused** | **Active** | Manual Activation | Restores paused rules to active states; verifies that end dates are still in the future. |
| **Active** | **Expired** | Automated Sweep | Automatically expires rules once current system times surpass scheduled end dates. |
| **Expired** | **Archived** | Admin Command | Permanently flags pricing rules as archived; restricts reactivation. |
| **Archived** | **Deleted** | Compliance Run | Sets `deleted_at` soft-delete timestamps, removing records from all active systems. |

*   **FSM Rollback Rule**: If pricing updates fail to sync across CDN caches or search indices, the transaction rolls back, restoring the pricing rule to its previous state (e.g., reverting from `Active` to `Approved`) and alerting operations teams.

---

## 8. TAX INTEGRATION BOUNDARIES

The Pricing Engine calculates taxes dynamically during checkout, delegating tax filing and ledger mappings to the Finance Bounded Context.

```
                          [TAX INTEGRATION BOUNDARY]

   Pricing Context (Checkout) ──► Calculate Cart Taxes ──► Query Tax Classes & Zones
                                                                 │
               ┌─────────────────────────────────────────────────┘
               ▼
   Apply Geographic Exemptions ──► Validate B2B Reverse Charges ──► Emit Checkout Event
```

*   **Tax Classes**: Products map to tax classes (e.g., Standard, Reduced, Zero-Rated) based on item type and category.
*   **Tax Engine Isolation**: The checkout pipeline queries regional tax rates dynamically based on customer shipping addresses:
    *   *US Sales Tax*: Calculated using state and county jurisdiction rules.
    *   *VAT/GST*: Calculates values using regional tax brackets.
*   **B2B Reverse Charge**: Cross-border B2B sales bypass standard tax calculations once the buyer's VAT registration number is verified, logging the transaction for tax reporting.
*   **Exemption Verification**: Customers upload tax exemption certificates, which are verified by compliance reviewers before tax-free checkouts are permitted.

---

## 9. AI DYNAMIC PRICING (ADVISORY OVERVIEW)

The Pricing Engine supports automated, AI-driven pricing updates to optimize sales and margins:

```
                         [AI DYNAMIC PRICING ENGINE]

    Market Signals ──► Demand Forecaster ──► Competitor Scraper ──► Generate Price Shift
                                                                          │
             ┌────────────────────────────────────────────────────────────┘
             ▼
    Check Margin Guardrails ──► Write Draft Prices ──► Trigger Human Approval
```

*   **Demand Forecasting**: Adjusts prices dynamically based on site traffic, category conversion rates, and seasonal spikes.
*   **Competitor Scraper Integration**: Scrapes competitor pricing to adjust marketplace rates, maintaining competitive positioning.
*   **Margin Guardrails**: Prevents automated pricing updates from dropping below minimum margins (`public.product_variants.cost_per_item` + margin floor).
*   **Human-in-the-Loop Approval**: All AI-suggested pricing updates are written as `DRAFT` price rules, requiring administrative review before going live.

---

## 10. ROLE-BASED ACCESS CONTROL (RBAC) & DATA SECURITY

The Pricing Engine implements strict security layers to protect sensitive financial parameters and prevent unauthorized price manipulations:

```
                         [PRICING SECURITY PIPELINE]

   Client Request ──► Verify Session JWT ──► Verify Tenant Isolation (RLS)
                                                    │
                 ┌──────────────────────────────────┘
                 ▼
   Verify RBAC Role Action ──► Execute Database Write ──► Write Compliance Log
```

*   **Tenant Isolation**: Row-Level Security (RLS) is enabled across all pricing tables, isolating queries using the tenant's `organization_id`.
*   **Maker-Checker Controls**: Prevents pricing administrators from self-approving price lists or promotional codes, requiring independent validation before publishing.
*   **RBAC Permissions**: Restricts pricing actions to specific roles:
    *   *Pricing Analyst*: Can create, edit, and schedule price sheets and promotions.
    *   *Pricing Reviewer*: Can review and approve scheduled price updates.
    *   *Support Staff*: Can apply manual cart overrides within pre-configured limits.
*   **Audit Logging**: The engine logs all pricing changes, coupon generations, and manual overrides to immutable audit tables, tracking user IDs, timestamps, and previous values for compliance auditing.

---

## 11. BUSINESS EVENT CONTRACTS

The Pricing Engine communicates updates to downstream systems using standardized event payloads written to transactional outbox tables:

```
                        [TRANSACTIONAL OUTBOX PIPELINE]

   Parent DB Commit ──► Write Outbox Event Payload ──► Sweep Queue ──► Dispatch Message
```

### 11.1 `price.created.v1`
*   **Publisher**: Pricing Engine Service
*   **Primary Consumers**: Search indexers, storefront caches, merchant dashboards.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc50950334c1",
      "event_type": "price.created.v1",
      "timestamp": "2026-06-30T16:20:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc50950334c2",
      "price_id": "018f63bb-9ab6-7000-8d59-fc50950334c3",
      "price_list_id": "018f63bb-9ab6-7000-8d59-fc50950334c4",
      "variant_id": "018f63bb-9ab6-7000-8d59-fc50950334c5",
      "price_value": 99.99,
      "currency": "USD"
    }
    ```

### 11.2 `price.updated.v1`
*   **Publisher**: Pricing Engine Service
*   **Primary Consumers**: Search platforms, cache invalidators, analytics dashboards.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc50950334c6",
      "event_type": "price.updated.v1",
      "timestamp": "2026-06-30T16:22:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc50950334c2",
      "price_id": "018f63bb-9ab6-7000-8d59-fc50950334c3",
      "variant_id": "018f63bb-9ab6-7000-8d59-fc50950334c5",
      "old_price": 99.99,
      "new_price": 89.99,
      "currency": "USD"
    }
    ```

### 11.3 `coupon.redeemed.v1`
*   **Publisher**: Checkout Engine
*   **Primary Consumers**: Marketing trackers, usage limit monitors, CRM systems.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc50950334c7",
      "event_type": "coupon.redeemed.v1",
      "timestamp": "2026-06-30T16:25:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc50950334c2",
      "coupon_id": "018f63bb-9ab6-7000-8d59-fc50950334c8",
      "coupon_code": "SUMMER10",
      "order_id": "018f63bb-9ab6-7000-8d59-fc50950334c9",
      "customer_id": "018f63bb-9ab6-7000-8d59-fc50950334d0",
      "discount_value": 10.00
    }
    ```

### 11.4 Resiliency Policies
*   **Transactional Outbox Delivery**: Event payloads are written to outbox tables (`public.marketplace_event_outbox`) inside the same database transaction as the price changes to guarantee consistency.
*   **Retry Schedules**: Sweep jobs process failed event dispatches using exponential backoff schedules, retrying 5 times before moving messages to dead-letter queues.
*   **Idempotency Checks**: Downstream systems track unique message keys (`event_id`) to block duplicate message processing.

---

## 12. INTEGRATION MATRIX

Pricing Engine integrations use strictly decoupled APIs and event-driven architectures to prevent direct database locks:

| Target Platform Domain | System of Record | Primary Channel | Data Sync Strategy | Failure Recovery Policy |
| :--- | :--- | :--- | :--- | :--- |
| **Product Catalog** | Product Catalog | Event Bus | Asynchronous SKU registrations | Cache SKU records; re-verify on catalog reconnection |
| **Inventory** | Inventory Subsystem | REST API / gRPC | Real-time stock status validation | Display fallback stock alerts; prevent out-of-stock orders |
| **Orders Context** | Orders Context | Event Bus | Asynchronous checkout event dispatches | Queue order messages; retry with progressive backoffs |
| **Checkout Engine** | Cart / Sessions | REST API gateway | Real-time price resolution | Render base catalog prices; flag checkout exceptions |
| **Finance / Ledger** | Finance Context | Asynchronous Events | Transactional outbox bookings | Transfer failed bookings to DLQ; raise ops alarms |
| **CRM / Profiles** | Identity / Profiles | JWT Token context | Real-time segment validations | Disable loyalty tier pricing; use standard retail prices |

---

## 13. PERFORMANCE OPTIMIZATIONS

To scale pricing operations across high-traffic volumes, several performance optimizations are implemented at the database and application levels:

*   **Caching with Redis**: Active storefront routes fetch pre-computed product prices from Redis caches, bypassing database queries for catalog browsing.
*   **Pre-Computed Price Lists**: Generates flat, pre-computed customer group price lists, avoiding on-the-fly calculation overhead.
*   **Materialized Path Views**: Category trees and regional tax classes are compiled into materialized views, refreshing records asynchronously.
*   **Row-Level Query Locking**: Time-sensitive pricing updates use `FOR UPDATE SKIP LOCKED` database queries to prevent write locks during concurrent checkouts.
*   **Batch Operations**: Large pricing updates or catalog imports are executed inside transactional batches, splitting massive datasets into chunks of 1,000 rows.

---

## 14. ENGINEERING VALIDATION CHECKLIST

The Architecture Review Board evaluates Pricing Engine deployments against this comprehensive checklist to ensure system integrity:

*   [ ] **FSM Correctness**: Verify that all state machine transitions validate fields and block unauthorized state changes.
*   [ ] **Precedence Validation**: Verify that the pricing cascade correctly resolves prices across all levels.
*   [ ] **Multi-Tenant RLS**: Validate that Row-Level Security policies isolate pricing queries by tenant `organization_id` at the database layer.
*   [ ] **Maker-Checker Security**: Confirm that pricing updates require independent administrator approval.
*   [ ] **Rounding Accuracy**: Verify that Banker's Rounding calculations prevent cumulative rounding errors across aggregated totals.
*   [ ] **Outbox Transactionality**: Verify that outbox event writes are committed within the parent database transaction blocks.
*   [ ] **Tax Calculation Isolation**: Confirm that checkout pipelines handle tax calculations correctly across diverse regional tax classes.
*   [ ] **Search Index Synchronization**: Confirm that pricing updates trigger asynchronous search index and cache purges.
*   [ ] **High-Performance Scaling**: Benchmark price resolution response speeds under high simulated concurrent checkout volumes.
*   [ ] **Disaster Rollback Scenarios**: Test database transaction rollbacks during simulated network and database disruptions.

---

*Authorized by the JUANET Technical Review Board & Global Commerce Council.*
