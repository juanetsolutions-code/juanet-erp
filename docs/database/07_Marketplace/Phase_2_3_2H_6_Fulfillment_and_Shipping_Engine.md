# JUANET Marketplace Fulfillment & Shipping Engine Manual
## Phase 2.3.2H.6 — Warehouse Execution, Picking & Packing Engines, Carrier Abstraction, Delivery Tracking, Reverse Logistics, and Shipping Lifecycles

**Document Version:** 2.0  
**Author:** Principal Enterprise Solutions Architect, VP of Logistics and Warehouse Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. FULFILLMENT & SHIPPING ARCHITECTURE PHILOSOPHY

The **JUANET Fulfillment & Shipping Engine** acts as the authoritative boundary governing the physical movement, packaging, labeling, carrier allocation, real-time tracking, and reverse logistics of physical inventory post-order confirmation. Positioned as a mission-critical, highly resilient subsystem, it bridges transient digital commitments (orders) with real-world physical fulfillment networks.

```
                    [FULFILLMENT & SHIPPING BOUNDED CONTEXT ISOLATION GRAPH]

   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                            ORDERS & CHECKOUT CONTEXT                            │
   └────────────────────────────────────────┬────────────────────────────────────────┘
                                            │
                             (Emit: order.confirmed.v1)
                                            ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                       FULFILLMENT & SHIPPING BOUNDED CONTEXT                    │
   │                                                                                 │
   │  ┌───────────────────────┐         ┌───────────────────┐    ┌────────────────┐  │
   │  │   Shipment Aggregate  │ ◄─────► │ Warehouse Exec    │ ◄─►│ Picking/Packing│  │
   │  │   (Root: Shipment ID) │         │ Wave/Zone Routing │    │ Engines        │  │
   │  └───────────────────────┘         └─────────┬─────────┘    └────────────────┘  │
   │                                              │                                  │
   │                                  Carrier Abstraction Layer                      │
   │                                              ▼                                  │
   │                                    ┌───────────────────┐                        │
   │                                    │  Carrier Adapter  │                        │
   │                                    └─────────┬─────────┘                        │
   └──────────────────────────────────────────────┼──────────────────────────────────┘
                                                  │
                                   gRPC / REST (HTTPS TLS 1.3)
                                                  ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                          EXTERNAL LOGISTICS CARRIERS (API)                      │
   │                                                                                 │
   │     ┌──────────────┐          ┌──────────────┐          ┌─────────────────┐     │
   │     │     DHL      │          │    FedEx     │          │ Local Couriers  │     │
   │     └──────────────┘          └──────────────┘          └─────────────────┘     │
   └──────────────────────────────────────────────┬──────────────────────────────────┘
                                                  │
                                     (Outbox Event Notification)
                                                  ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                            DOWNSTREAM BOUNDED CONTEXTS                          │
   │                                                                                 │
   │  ┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐ │
   │  │  Inventory (Stock)   │   │  Finance (Ledger)    │   │  CRM & Support       │ │
   │  │ - Physical Deduct    │   │ - Shipping Accruals  │   │ - Delivery Alerts    │ │
   │  └──────────────────────┘   └──────────────────────┘   └──────────────────────┘ │
   └─────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Separation of Bounded Contexts & Concerns
To maintain strict Domain-Driven Design (DDD) isolation, the Fulfillment & Shipping bounded context enforces absolute boundaries:
*   **The Shipment acts as the Aggregate Root** for all physical delivery operations.
*   **Warehouse Execution** translates virtual lines into picking routes, wave plans, and packing workflows, entirely isolated from pricing or credit validation.
*   **Carrier Abstraction** decouples platform business logic from the proprietary APIs of shipping networks (DHL, FedEx, UPS, etc.).
*   **Asynchronous Orchestration**: Physical picking, packing, label creation, and carrier dispatches execute within isolated background workers, protecting customer-facing storefronts from carrier API latencies or warehouse hardware failures.
*   **CQRS Read/Write Segregation**: Analytical queries regarding transit speeds, warehouse SLAs, and carrier performance are run against read replicas, preventing analytical load from blocking physical dispatch lines.
*   **Immutable Shipment History**: Once a package has been loaded onto a carrier vehicle and scanned (`In Transit`), its routing, billing, and packaging histories are frozen. Any subsequent operational exceptions (e.g., redirection, damage, return) write new historical timelines rather than mutating physical data cells.

### 1.2 Absolute Operational Isolation: Why Shipping Never Writes Directly
To ensure system durability and strict transaction boundaries, the Fulfillment & Shipping Engine is forbidden from executing direct database updates to the following systems:
*   **Orders**: Shipping status updates are communicated back to the Orders domain purely through outbox events (e.g., `shipment.dispatched.v1`). The Shipping engine does not modify order statuses directly.
*   **Inventory**: Stock adjustments are owned exclusively by the Inventory context. The Shipping engine emits allocation and deduction events, allowing Inventory systems to reduce stock balances asynchronously.
*   **Payments**: Shipping triggers do not execute captures or credit voids. It writes status changes to the outbox, allowing the Payment context to process captures for delayed-capture scenarios.
*   **Finance**: Financial shipping accruals, cost write-offs, and vendor payouts are recorded in the double-entry accounting ledger via downstream message consumers listening for shipment delivery confirmations.
*   **CRM & Support**: Live delivery tracking, delivery alerts, and transit delays publish outbox events that CRM webhooks digest, preventing external API latencies from impacting warehouse picking lines.

---

## 2. SHIPMENT AGGREGATE DESIGN

The **Shipment Aggregate** represents the consistency and transactional boundary for all logistics operations. The root table `public.shipments` coordinates and protects all sub-entities, enforcing physical and operational rules as a unified atomic unit.

```
                    [SHIPMENT AGGREGATE ENTITY RELATIONSHIPS]

┌──────────────────────────────────────────────────────────────────────────────┐
│  public.shipments (Aggregate Root)                                           │
│  - id (UUIDv7 Primary Key)                                                   │
│  - organization_id (Tenant Isolation Key)                                    │
│  - order_id (Target Order Reference)                                         │
│  - warehouse_id (Fulfillment Node Reference)                                 │
│  - carrier_code (Active Carrier Identifier)                                  │
│  - tracking_number (Carrier Reference String)                                │
│  - status (FSM Lifecycle Status)                                             │
│  - version (Optimistic Lock Version)                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.shipment_items (1:N Relationship)                            │   │
│   │  - id, product_variant_id, quantity, serial_numbers (JSONB), lot_id  │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.shipment_packages (1:N Relationship)                         │   │
│   │  - id, package_type, weight_grams, height_mm, width_mm, length_mm    │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.shipment_addresses (1:2 Relationship - Ship/Return)          │   │
│   │  - id, address_type, recipient_name, street_1, geo_coordinates       │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.shipment_delivery_proofs (1:N Relationship)                  │   │
│   │  - id, proof_type, image_url, signature_payload, geolocation_point   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.shipment_history (1:N Relationship - Timeline)               │   │
│   │  - id, from_state, to_state, transition_by, notes, occurred_at       │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Complete Entity Ownership Dictionary
The Fulfillment Bounded Context maintains exclusive transactional authority over the following sub-entities:

| Sub-Entity | Physical Table Name | Domain Purpose | Ownership & Mutation Policy |
| :--- | :--- | :--- | :--- |
| **Shipments** | `public.shipments` | Root aggregate record managing active carriers, master tracking, origin warehouses, target orders, and lifecycles. | **Absolute Root.** Only mutable via formal FSM transitions. |
| **Shipment Items** | `public.shipment_items` | Tracked physical products packed in the shipment, mapping SKUs, serial numbers, and batch lot IDs. | Owned by Root. Frozen once transitioned to `Picked`. |
| **Packages** | `public.shipment_packages` | Container profiles mapping outer boxing codes, certified weights, and dimensions. | Owned by Root. Appended during the packing phase. |
| **Package Dimensions** | Columns on `shipment_packages` | Standardized millimeter and gram records used for carrier rating calculations. | Owned by Root. Locked on label generation. |
| **Shipment Addresses** | `public.shipment_addresses` | Geocoded destination and return address structures captured at the second of warehouse release. | Owned by Root. Immutable to protect historical audit tracks. |
| **Carrier References** | Column: `carrier_code` | Categorical system identifiers mapping active shipping networks (e.g., 'DHL', 'FEDEX'). | Mutable only prior to label printing. |
| **Tracking Numbers** | Column: `tracking_number` | Carrier-supplied reference strings enabling cross-network parcel tracking. | Immutable once registered from carrier integrations. |
| **Shipping Labels** | `public.shipment_labels` | Storehouses for Base64 label image blobs, ZPL commands, and carrier routing codes. | Owned by Root. Read-only once generated. |
| **Delivery Proof** | `public.shipment_delivery_proofs` | Encapsulated delivery scans, customer signature maps, and carrier drop-off photographs. | Owned by Root. Written exclusively during completion sweeps. |
| **Delivery Attempts** | `public.shipment_delivery_attempts` | Historical logs detailing failed carrier drop-offs, transit exceptions, and rescheduled dates. | Owned by Root. Append-only logs for tracking exceptions. |
| **Pickup Requests** | `public.shipment_pickup_requests` | Scheduling records coordinating carrier collections from origin warehouses. | Owned by Root. Updated during dispatcher sweeps. |
| **Return Shipments** | `public.shipment_returns` | Traced reverse-logistics pathways mapping RMAs, drop-off spots, and arrival scans. | Owned by Root. Managed via reverse logistic pipelines. |
| **Shipment Notes** | `public.shipment_notes` | Context annotations for packers, special delivery markers, or internal logistic logs. | Append-only. Updates are blocked. |
| **Shipment Metadata** | `public.shipment_metadata` | Flexible JSONB container storing custom integration details or regional customs compliance keys. | Owned by Root. |
| **Shipment Attachments** | `public.shipment_attachments` | Safe links to digital assets (e.g., commercial invoices, customs manifests, packing slips). | Append-only. Deletions are forbidden. |
| **Warehouse References** | Column: `warehouse_id` | Foreign-key binding mapping origin fulfillment nodes. | Immutable after wave allocation starts. |
| **Vendor References** | Column: `vendor_id` | Identifiers mapping independent vendors for multi-vendor split checkouts. | Immutable after aggregate initialization. |
| **Order References** | Column: `order_id` | Mapping tying shipments back to authoritative customer order entries. | Immutable. |

### 2.2 Aggregate Consistency Rules
*   **Item Reconciliation Constraints**: The total quantity of items allocated across all shipments of an order must never exceed the total quantity purchased on the order:
    $$\sum \text{Shipment Item Quantity} \le \text{Order Line Item Quantity}$$
*   **Package Weight Balances**: Every shipment package must register a weight greater than zero grams prior to carrier booking.
*   **Optimistic Lock Guarding**: The parent table `public.shipments` utilizes a `version` column to prevent concurrent warehouse updates from corrupting shipping states.

---

## 3. FULFILLMENT WORKFLOW

The end-to-end warehouse execution flow coordinates actions from initial order intake to final customer receipt:

```
                            [15-STEP LOGISTICS WORKFLOW]

   [Step  1: Order Confirmation]   ──► Downstream consumer intercepts order.confirmed.v1.
          │
          ▼
   [Step  2: Warehouse Allocation] ──► Selects optimal origin warehouses based on inventory.
          │
          ▼
   [Step  3: Wave Planning]        ──► Group shipments into logical picking waves.
          │
          ▼
   [Step  4: Batch Generation]     ──► Create picking batches based on zones and routes.
          │
          ▼
   [Step  5: Task Assignment]      ──► Route picking sheets to dedicated picker devices.
          │
          ▼
   [Step  6: Picking Execution]    ──► Picker navigates routes, scanning barcodes.
          │
          ▼
   [Step  7: Scanning & Verification]──► Verifies serial and lot numbers at the packing station.
          │
          ▼
   [Step  8: Packing Execution]    ──► Selects optimal containers and wraps items.
          │
          ▼
   [Step  9: Quality Inspection]   ──► Performs random or high-value QC assessments.
          │
          ▼
   [Step 10: Weight Validation]    ──► Scales package to verify physical vs. theoretical weight.
          │
          ▼
   [Step 11: Label Printing]       ──► Pulls carrier APIs to print shipping labels.
          │
          ▼
   [Step 12: Carrier Booking]      ──► Schedules pickups and dispatches shipping manifests.
          │
          ▼
   [Step 13: Dispatch Execution]   ──► Hand off packages to carriers, scanning dispatches.
          │
          ▼
   [Step 14: Transit & Tracking]   ──► Monitors carrier updates and updates tracking pipelines.
          │
          ▼
   [Step 15: Delivery & Completion]──► Captures proof-of-delivery scans and completes shipments.
```

### 3.1 Exception Handling and Rollback Protocols
*   **Picking Shortages**: If a picker discovers an item is damaged or missing, they mark a "Short Pick" on their device. The system immediately rolls back the item's allocation state, halts the current packing workflow, and routes the allocation request to alternative warehouses or triggers a support alert.
*   **Weight Anomalies**: If scale measurements diverge from theoretical weights by more than $\pm 5\%$, the packing station blocks label printing, routing the package to a manual inspection lane.

---

## 4. WAREHOUSE EXECUTION ENGINE

The **Warehouse Execution Engine** determines how orders are routed and fulfilled across various network nodes:

```
                          [WAREHOUSE ROUTING PIPELINE]

   Order Confirmed ──► Fetch Warehouse Inventory Balances
                                │
                  ┌─────────────┴─────────────┐
                  ▼                           ▼
        Single Node Match?            Multi-Warehouse Split?
                  │                           │
          Allocate & Dispatch          Evaluate Routing Rules
                                       ├── Priority 1: Minimize split count
                                       ├── Priority 2: Minimize transit distance
                                       └── Priority 3: Maximize warehouse capacity
                                              │
                                              ▼
                                   Route separate Shipments
```

### 4.1 Fulfillment Models
*   **Single Warehouse Fulfillment**: The entire order is allocated and fulfilled from a single warehouse node, minimizing shipping costs and transit times.
*   **Multi-Warehouse Split**: If no single warehouse holds the full order inventory, the system splits the order into multiple shipments routed to different regional facilities.
*   **Regional & Overflow Warehouses**: Primary regional warehouses serve local zones, while overflow warehouses handle peak-season volumes or backup allocations.
*   **Store Pickup (BOPIS)**: Orders are allocated directly to local retail stores, reserving inventory from store shelves and alerting staff to prepare orders for pickup.
*   **Dropshipping**: Shipments are routed directly to third-party suppliers, bypassing platform warehouses entirely.
*   **Cross-Docking**: Incoming supplier shipments are moved directly to outbound shipping lanes, bypassing storage bins to accelerate fulfillment.

### 4.2 Dynamic Routing Rules
The warehouse routing engine optimizes node selection based on multi-criteria algorithms:
*   **Distance Pricing**: Estimates transit costs based on target addresses and warehouse geocodes.
*   **Warehouse Priority**: Prioritizes warehouses with excess stock capacity or lower operational backlogs.
*   **Warehouse Balancing Algorithm**:
    $$\text{Target Warehouse} = \text{argmin} \left( w_1 \cdot \text{Distance} + w_2 \cdot \text{Operational Load} - w_3 \cdot \text{Stock Level} \right)$$

---

## 5. PICKING ENGINE

The **Picking Engine** optimizes item collection routes, minimizing picker travel times and maximizing warehouse throughput:

```
                        [PICK PATH ROUTE OPTIMIZATION]

            Bin A-01 (Start) ──► Bin A-03 ──► Bin B-12 ──► Packing Station
```

### 5.1 Picking Methodologies
*   **Wave Picking**: Groups shipments into logical fulfillment waves based on carrier pickup schedules or shipping priorities.
*   **Zone Picking**: Divides warehouses into zones, assigning pickers to specific sections to pick items for multiple orders concurrently.
*   **Batch Picking**: Consolidates multiple orders into a single picking pass, allowing pickers to collect identical items in one trip.
*   **Cluster Picking**: Pickers place items directly into separate order containers on their carts, sorting items during collection.

### 5.2 Path Optimization & Dynamic Allocation
*   **Routing Algorithms**: Generates picking paths using optimized Traveling Salesperson algorithms (e.g., S-Shape or heuristic routing) to prevent pickers from backtracking.
*   **Inventory Rotation**: Employs FIFO (First-In, First-Out), LIFO (Last-In, First-Out), or FEFO (First-Expired, First-Out) inventory rotation rules.
*   **Serial and Lot Tracking**: Mandates barcode scans of manufacturer serials or batch lot codes during picking, enforcing strict quality control.

---

## 6. PACKING ENGINE

The **Packing Engine** manages packaging processes, optimizing box selection and validating package attributes to minimize shipping costs and damage:

```
                            [PACKING WORKFLOW]

   Picked Items ──► Package Optimizer ──► Selected Container (Carton B)
                                                │
                                                ▼
                                         Wrap & Seal Carton
                                                │
                                                ▼
                                         Scale Verification
                                                │
                               ┌────────────────┴────────────────┐
                               ▼                                 ▼
                     Weight Match (Label Print)        Weight Mismatch (Audit)
```

### 6.1 Package Optimization & Cartonization
*   **Volumetric Fitting**: Computes physical dimensions of items to recommend optimal box sizes, reducing packaging material waste and carrier dimensional surcharges.
*   **Fragile & Hazmat Handling**: Flags sensitive items, prompting packers to use protective wraps or apply hazardous material labels.
*   **Multi-Package Shipments**: Splits large, heavy, or mixed items across multiple containers, tracking weight and tracking metrics for each package.

### 6.2 Validation & Audit Audits
*   **Weight Verification**: Compares physical weights against theoretical item totals, flagging discrepancies for manual inspection.
*   **Image Capture**: Captures photos of open boxes prior to sealing, preserving visual evidence for dispute resolution and quality control.

---

## 7. CARRIER INTEGRATION LAYER (CIL)

The **Carrier Integration Layer (CIL)** abstracts external carrier APIs, standardizing rate calculations, label printing, and tracking updates.

```
                       [CIL INTEGRATION INTERFACE]

                       Fulfillment Packing Station
                                    │
                ┌───────────────────┴───────────────────┐
                ▼                                       ▼
      CIL Interface: getRates()               CIL Interface: book()
                │                                       │
       ┌────────┴────────┐                     ┌────────┴────────┐
       ▼                 ▼                     ▼                 ▼
  DHLAdapter        FedExAdapter          DHLAdapter        FedExAdapter
       │                 │                     │                 │
  DHL REST API      FedEx REST API        DHL REST API      FedEx REST API
```

### 7.1 CIL Adapter Interface Definition (TypeScript)

The CIL defines standard TypeScript interfaces for carrier integrations:

```typescript
export interface BaseCarrierAdapter {
  readonly carrierCode: string;

  /**
   * Fetches real-time shipping rates from the carrier.
   */
  fetchRates(request: CarrierRateRequest): Promise<CarrierRateResponse>;

  /**
   * Books a shipment with the carrier, generating tracking numbers and labels.
   */
  bookShipment(request: CarrierBookingRequest): Promise<CarrierBookingResponse>;

  /**
   * Schedules a carrier pickup at the warehouse.
   */
  schedulePickup(request: CarrierPickupRequest): Promise<CarrierPickupResponse>;

  /**
   * Cancels a previously booked shipment.
   */
  cancelShipment(trackingNumber: string): Promise<boolean>;
}

export interface CarrierRateRequest {
  originAddress: AddressPayload;
  destinationAddress: AddressPayload;
  packages: PackageDimensions[];
  serviceType?: string;
}

export interface CarrierRateResponse {
  rates: Array<{
    serviceName: string;
    rateAmount: number;
    currency: string;
    estimatedDelivery: string;
  }>;
}

export interface CarrierBookingRequest {
  shipmentId: string;
  originAddress: AddressPayload;
  destinationAddress: AddressPayload;
  packages: PackageDimensions[];
  serviceType: string;
}

export interface CarrierBookingResponse {
  success: boolean;
  trackingNumber: string;
  labelFormat: 'PNG' | 'PDF' | 'ZPL';
  labelBlob: string;
  rawPayload: Record<string, any>;
}

export interface PackageDimensions {
  weightGrams: number;
  heightMm: number;
  widthMm: number;
  lengthMm: number;
}

export interface AddressPayload {
  name: string;
  street1: string;
  street2?: string;
  city: string;
  state: string;
  postalCode: string;
  countryCode: string;
}
```

### 7.2 Standard Carrier Adapters
*   **DHL Adapter**: Connects to DHL Express XML/REST services, supporting international customs data integration.
*   **FedEx Adapter**: Direct Web Services integration, processing tracking sweeps and label creations.
*   **UPS Adapter**: Connects to the UPS Developer Portal, managing volumetric rates and address validations.
*   **USPS Adapter**: Processes domestic flat-rate parcels and API label requests.
*   **Royal Mail Adapter**: Integrates with UK domestic and international delivery systems.
*   **Aramex Adapter**: Handles cross-border Middle Eastern deliveries and local pickups.
*   **Local Couriers Adapter**: Direct Webhook routing to local, last-mile delivery partners.

### 7.3 Webhook Tracking Processors
*   **Signature Verifications**: Validates incoming carrier webhook headers to secure incoming delivery tracking updates.
*   **Unified State Mapping**: Standardizes carrier-specific status codes to canonical FSM shipment states, updating active tracking records.

---

## 8. SHIPMENT LIFECYCLE FINITE STATE MACHINE (FSM)

Every shipment is managed by a strict, deterministic Finite State Machine (FSM) to enforce valid transitions, prevent operational conflicts, and provide clear tracking.

```
                        [SHIPMENT LIFECYCLE STATE TRANSITIONS]

                            ┌──────────────────────┐
                            │       Pending        │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │      Allocated       │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Picking        │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │        Picked        │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Packing        │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │        Packed        │       │  Cancelled   │
                            └──────────┬───────────┘       └──────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │  Ready for Dispatch  │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │   Carrier Assigned   │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │   Awaiting Pickup    │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │      In Transit      │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │  Customs Clearance   │       │   Damaged    │
                            └──────────┬───────────┘       └──────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │   Out For Delivery   │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │      Delivered       │       │    Failed    │
                            └──────────┬───────────┘       └──────┬───────┘
                                       │                          │
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │       Archived       │◄──────┤   Returned   │
                            └──────────────────────┘       └──────────────┘
```

### 8.1 State Definitions and Transition Rules

#### 8.1.1 State Dictionary
1.  **Pending**: Shipment initialized and awaiting warehouse queue assignment.
2.  **Allocated**: Items reserved in bin storage; assigned to pickers.
3.  **Picking**: Picker actively collecting items in the warehouse.
4.  **Picked**: Items collected, scanned, and delivered to packing stations.
5.  **Packing**: Packing staff boxing items, verifying weights, and sealing packages.
6.  **Packed**: Packaging sealed, dimensions registered, and theoretical weight verified.
7.  **Ready for Dispatch**: Consolidated packages queued at loading dock lines.
8.  **Carrier Assigned**: Carrier booked, tracking number registered, and shipping label printed.
9.  **Awaiting Pickup**: Packages staged at the carrier pickup lane; carrier notified.
10. **In Transit**: Handed off to carrier; first sorting facility scan received.
11. **Customs Clearance**: Packages processing through cross-border customs checks.
12. **Out For Delivery**: Carrier parcel scanned onto last-mile delivery vehicles.
13. **Delivered**: Signature captured and parcel delivered successfully.
14. **Delivery Failed**: Carrier reported failed delivery attempts, holding package at hub.
15. **Returned**: Parcel returned to origin warehouse due to delivery failures or returns.
16. **Lost**: Carrier declared package lost in transit.
17. **Damaged**: Parcel declared damaged prior to final delivery.
18. **Cancelled**: Shipment aborted prior to carrier pickup; releases inventory reservations.
19. **Archived**: Closed historical logs preserved for audit and compliance.

#### 8.1.2 Transition Matrix

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Pending** | **Allocated** | Wave Scheduler | Verifies inventory allocations; assigns picking paths. |
| **Allocated** | **Picking** | Picker Scanner App | Assigns shipment to active picker cart. |
| **Picking** | **Picked** | Picking Scan Complete | Verifies barcode totals match picking sheet requirements. |
| **Picked** | **Packing** | Packing Scan Init | Pulls item lists to active packing screens. |
| **Packing** | **Packed** | Weight Verification | Weights must match within configured thresholds. |
| **Packed** | **Ready for Dispatch**| Dock Stage Hand-off | Transfers package to loading dock staging areas. |
| **Ready for Dispatch**| **Carrier Assigned** | Carrier Booking API | Registers tracking number and prints shipping labels. |
| **Carrier Assigned** | **Awaiting Pickup** | Pickup Call Success | Consolidates booking manifests; schedules carrier pickups. |
| **Awaiting Pickup**| **In Transit** | Carrier Pickup Scan | First carrier transit hub scan registered. |
| **In Transit** | **Customs Clearance**| Customs Scan | Triggers cross-border clearance processing alerts. |
| **In Transit** | **Out For Delivery** | Last Mile Scan | Standardizes carrier-specific sorting station scans. |
| **Out For Delivery**| **Delivered** | Proof of Delivery | Validates signature captures, photos, and geolocations. |
| **Out For Delivery**| **Delivery Failed** | Carrier Exception | Registers delivery exception reasons; schedules retries. |
| **Delivery Failed**| **Returned** | Return to Sender | Returns package to origin warehouse; updates inventory. |

---

## 9. SHIPMENT TRACKING ENGINE

The **Shipment Tracking Engine** processes transit updates from carriers, consolidating tracking metrics and updating customer pipelines:

```
                          [SHIPMENT TRACKING PIPELINE]

   Carrier Scan ──► Webhook Ingestion ──► Parse & Standardize Code
                                                  │
                                                  ▼
                                         Update shipment_history
                                                  │
                                                  ▼
                                       Publish outbox update event
                                                  │
                        ┌─────────────────────────┴─────────────────────────┐
                        ▼                                                   ▼
             Update Storefront Status                             Notify Customer Email
```

### 9.1 Sync Strategies
*   **Webhook Ingestion**: Primary ingestion route; parses real-time transit updates from carrier webhook posts.
*   **Polling Fallbacks**: Scheduled cron tasks query carrier APIs for packages in transit that have not received webhook updates for over 6 hours.
*   **Carrier Reconciliation**: Cross-references tracking logs with carrier settlement reports, identifying billing or status discrepancies.

### 9.2 Proof of Delivery Verification (POD)
*   **Signature Captures**: Decodes and archives customer signatures.
*   **Photo Confirmation**: Stores photographs of delivered packages at customer doors.
*   **Geolocation Verification**: Compares GPS coordinates of carrier delivery scans with customer shipping addresses, validating delivery accuracy:
    $$\text{Delivery Deviation} = \text{GeoDistance}\left( \text{Carrier Scan Point}, \text{Shipping Address Point} \right) \le 100\text{ meters}$$

---

## 10. MULTI-VENDOR FULFILLMENT

The system coordinates fulfillment paths for marketplaces where orders contain items from multiple independent vendors:

```
                         [MULTI-VENDOR SHIPMENT SPLIT]

                   Consolidated Customer Order (ORD-9845)
                                    │
               ┌────────────────────┴────────────────────┐
               ▼                                         ▼
     Sub-Order: ORD-9845-A                     Sub-Order: ORD-9845-B
     (Vendor A - Electronics)                  (Vendor B - Apparel)
               │                                         │
     Fulfillment: Marketplace Node             Fulfillment: Vendor Warehouse
     (Fulfillment Node: WH-01)                 (Vendor dropships directly)
               │                                         │
     Packs & Ships Carton A                    Packs & Ships Carton B
     (Tracking: carrier_tracking_1)            (Tracking: carrier_tracking_2)
```

### 10.1 Fulfillment Pathways
*   **Vendor-Owned Fulfillment**: Vendors manage packing and shipping independently from their own warehouses, printing shipping labels and scheduling pickups through their vendor portals.
*   **Marketplace Fulfillment (Fulfillment by Platform)**: Vendors store bulk stock at platform warehouses; platform nodes pack and ship orders on behalf of vendors.
*   **Hybrid Fulfillment**: Orders are split, with some items fulfilled by platform warehouses and others dropshipped by independent vendors.

### 10.2 SLA Monitoring & Dashboards
*   **SLA Tracking**: Monitors vendor packaging speeds, flagging delays that exceed contract limits (e.g., ship items within 48 hours).
*   **Vendor Dashboards**: Provides vendors with tracking interfaces to manage orders, schedule pickups, and print shipping labels.

---

## 11. REVERSE LOGISTICS & RETURNS

The **Reverse Logistics Engine** coordinates product returns, managing reverse shipping labels, warehouse inspections, and inventory reintegration:

```
                             [REVERSE LOGISTICS FLOW]

   Return Authorized ──► Print Reverse Label ──► Drop-off / Carrier Scan
                                                           │
                                                           ▼
                                                Warehouse Receive Dock
                                                           │
                                                           ▼
                                                   Quality Inspection
                                                           │
                                ┌──────────────────────────┴──────────────────────────┐
                                ▼                                                     ▼
                       QC Pass (Restock)                                     QC Fail (Disposed/Rework)
                                │                                                     │
                     Drizzle: increment stock                              Emit outbox return event
```

### 11.1 Returns Pathways
*   **Return Authorization (RMA)**: Validates return requests against eligibility windows, generating Return Merchandise Authorization (RMA) codes and pre-paid shipping labels once approved.
*   **Carrier Drop-offs**: Customers drop off parcels at carrier locations or regional retail outlets.
*   **Warehouse Inspection**: Warehouse teams scan arriving RMAs, inspecting returned products for damage or wear.

### 11.2 Reintegration Controls
*   **QC Pass (Restock)**: Restores items to available inventory pools, incrementing stock balances.
*   **QC Fail (Disposed/Rework)**: Routes damaged items to disposal locations or refurbishment centers.

---

## 12. SHIPPING COST ENGINE

The **Shipping Cost Engine** computes shipment costs based on package dimensions, transit distances, and carrier rates:

```
                         [SHIPPING COST ENGINE PIPELINE]

   Get Order Package Dimensions ──► Query Distance Zones (Warehouse geocode -> Target geocode)
                                                │
                                                ▼
                                     Calculate Base Zone Rate
                                                │
                                                ▼
                                     Evaluate Cost Multipliers
                                     ├── Fragile, Hazmat, Volume surcharge
                                     └── Fuel surcharge index update
                                                │
                                                ▼
                                    Verify Customer Discounts
                                    (E.g., Free Shipping promotions)
```

### 12.1 Cost Calculations
*   **Volumetric Pricing**: Computes dimensional weight based on package dimensions, charging the greater of actual or dimensional weight:
    $$\text{Dimensional Weight (kg)} = \frac{\text{Length (cm)} \times \text{Width (cm)} \times \text{Height (cm)}}{5000}$$
*   **Zone Pricing**: Evaluates shipping zones based on origin warehouse and target delivery coordinates, applying zone-specific cost multipliers.
*   **Promotional Shipping**: Applies discount rules (e.g., free shipping for orders exceeding \$50) to offset shipping fees.

---

## 13. CANONICAL EVENT CONTRACTS

The Fulfillment & Shipping Engine writes standard event payloads to the `public.marketplace_event_outbox` table, ensuring consistent downstream tracking.

```
                      [TRANSACTIONAL OUTBOX PIPELINE]

   Parent Transaction Commit ──► Write Outbox Payload ──► Message Queue Dispatch
```

### 13.1 `shipment.created.v1`
*   **Publisher**: Fulfillment & Shipping Service
*   **Consumers**: Orders Bounded Context, CRM & Notification Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033700",
      "event_type": "shipment.created.v1",
      "timestamp": "2026-07-01T01:04:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "shipment_id": "018f63bb-9ab6-7000-8d59-fc5095033701",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "warehouse_id": "018f63bb-9ab6-7000-8d59-fc5095033510",
      "items": [
        {
          "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
          "quantity": 2
        }
      ],
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 13.2 `shipment.dispatched.v1`
*   **Publisher**: Fulfillment & Shipping Service
*   **Consumers**: Orders Bounded Context, Finance Service, CRM & Notification Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033702",
      "event_type": "shipment.dispatched.v1",
      "timestamp": "2026-07-01T01:05:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "shipment_id": "018f63bb-9ab6-7000-8d59-fc5095033701",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "carrier_code": "FEDEX",
      "tracking_number": "781234567890",
      "shipping_cost": 15.75,
      "currency": "USD",
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 13.3 `shipment.delivered.v1`
*   **Publisher**: Fulfillment & Shipping Service
*   **Consumers**: Orders Bounded Context, Finance Service, CRM & Notification Service, Customer Portal
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033703",
      "event_type": "shipment.delivered.v1",
      "timestamp": "2026-07-01T01:08:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "shipment_id": "018f63bb-9ab6-7000-8d59-fc5095033701",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "carrier_code": "FEDEX",
      "tracking_number": "781234567890",
      "delivered_at": "2026-07-01T01:07:45Z",
      "delivered_to": "Jane Doe",
      "delivery_proof": {
        "proof_type": "PHOTO_AND_SIGNATURE",
        "image_url": "https://secure-storage.juanet.net/pod/781234567890.jpg",
        "signature_text": "Signed by J. Doe"
      },
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

---

## 14. SECURITY & COMPLIANCE

The system applies strict security controls to protect logistics data and maintain regulatory compliance:

### 14.1 Row-Level Security (RLS)
The database enforces strict RLS policies on shipping tables to ensure tenant and user data isolation:
```sql
-- Enforce tenant-isolation on shipping records
ALTER TABLE public.shipments ENABLE ROW LEVEL SECURITY;

CREATE POLICY shipment_tenant_isolation_policy ON public.shipments
FOR ALL
USING (organization_id = (SELECT current_setting('app.current_organization_id', true)::uuid));
```

### 14.2 Role-Based Access Control (RBAC)
User permissions are managed using specific JWT scopes and RBAC roles:
*   `warehouse_staff`: Permissions to view wave plans, execute picking routes, and manage packaging flows within assigned facilities.
*   `logistics_manager`: Full administrative access to reassign waves, update carrier adapters, edit shipping zones, and process manual delivery exceptions.
*   `vendor_operator`: Read-and-write permissions restricted to self-owned shipments, printed labels, and carrier bookings within their assigned sub-portals.

### 14.3 GDPR and PII Compliance
*   **PII Masking**: Customer shipping names and delivery addresses are masked or truncated in analytical logs.
*   **Data Retention**: Customer delivery records are anonymized or archived after 2 years of inactivity, unless retained longer for regulatory compliance audits.

---

## 15. PERFORMANCE & SCALABILITY

To handle high-volume warehouse execution queues, the Fulfillment Engine implements optimized performance patterns:

### 15.1 Indexing Strategy
*   **Partial Indexes**: Indexes active shipments to accelerate wave processing:
    ```sql
    CREATE INDEX idx_shipments_unresolved_active
    ON public.shipments (organization_id, status)
    WHERE status IN ('Pending', 'Allocated', 'Picking', 'Picked', 'Packing');
    ```
*   **Covering Indexes**: Prevents heavy analytical table joins by storing frequently queried columns inside the index:
    ```sql
    CREATE INDEX idx_shipments_covering_tracking
    ON public.shipments (tracking_number)
    INCLUDE (status, carrier_code, order_id);
    ```

### 15.2 Lock Prevention with FOR UPDATE SKIP LOCKED
Background picking workers utilize `FOR UPDATE SKIP LOCKED` parameters to process wave allocations concurrently without causing database bottlenecks:
```sql
-- Secure non-blocking lock to assign picking tasks
SELECT id, wave_id
FROM public.picking_tasks
WHERE status = 'Pending'
LIMIT 50
FOR UPDATE SKIP LOCKED;
```

---

## 16. ARCHITECTURE REVIEW BOARD (ARB) VALIDATION MATRIX

The Architecture Review Board evaluates the Fulfillment & Shipping Engine against a comprehensive checklist to confirm system reliability:

| Review Category | Validation Target | Success Metric | Verification Pattern |
| :--- | :--- | :--- | :--- |
| **Warehouse Execution**| Multi-Warehouse Split Allocation | Orders with mixed regional items must split into separate shipments without corrupting parent order lines. | End-to-end automated split checks. |
| **Picking Integrity** | Bin Locking Controls | Background workers must avoid deadlocks when concurrent pickers reserve identical storage bins. | Simulation checks under high-concurrency loops. |
| **Packing Accuracy** | Theoretical Weight Deviation | Station scales must block package progression when physical weight deviates from theoretical bounds. | Weight check validation rules verification. |
| **Carrier Decoupling** | Carrier Failovers | Carrier booking timeouts must trigger automated fallback adapters within 5 seconds. | Mock network outage simulations. |
| **Tracking Ingestion** | Standardized Webhooks | Webhook payloads from DHL, FedEx, and UPS must parse successfully into unified FSM states. | Signature validation and event parsing checks. |
| **Outbox Guarantees** | Exactly-Once Event Posting | State transitions must write event payloads to outbox tables within the same database transaction. | Database constraint verification audits. |
| **Access Protections** | RLS / RBAC isolation | Vendor operators must be restricted from viewing third-party shipments or modifying platform settings. | Security penetration and policy validation audits. |

---

## 17. RELEVANT SPECIFICATIONS REFERENCE

For comprehensive system alignment, this manual coordinates with the following platform specifications:
*   **Phase 2.3.2H.1**: Product Catalog and Catalog Management.
*   **Phase 2.3.2H.3**: Inventory and Warehouse Management (Authoritative System of Record for bulk balances and bin mappings).
*   **Phase 2.3.2H.4**: Orders and Checkout Engine (Authoritative System of Record for customer purchase history).
*   **Phase 2.3.2H.5**: Payment Orchestration Engine.

---

## 18. CONCLUDING ARB CERTIFICATION

The Technical Review Board certifies that the **Fulfillment & Shipping Engine** meets all enterprise architectural standards for transaction isolation, carrier decoupling, and performance scaling. Implementing teams must adhere strictly to these specifications, ensuring zero direct writes to external contexts and maintaining complete data consistency through the Transactional Outbox pattern.
