# JUANET Marketplace Inventory & Warehouse Management Engine Manual
## Phase 2.3.2H.3 — Inventory Topology, Reservation Engines, Lifecycle State Machines, Event Contracts, and Fulfillment Logic
**Document Version:** 1.0  
**Author:** Principal Enterprise Solutions Architect, VP of Supply Chain Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. INVENTORY ARCHITECTURE PHILOSOPHY

In an enterprise-grade multi-tenant marketplace platform, the **Inventory & Warehouse Management Engine** acts as the absolute **System of Record (SoR)** for physical and virtual stock balances. Aligning with standards established by SAP EWM, Oracle WMS, and Microsoft Dynamics 365 SCM, this engine guarantees high-concurrency stock tracking, zero-overselling safety locks, and geographical fulfillment optimizations.

```
                         [INVENTORY DECOUPLED SYSTEMS OF RECORD]

     ┌──────────────────────────────────────────────────────────────────┐
     │                     PRODUCT CATALOG BOUNDED CONTEXT              │
     │  - Master SKU Registry                                           │
     │  - Product Dimensions & Material Attributes                      │
     └──────────────────────────────┬───────────────────────────────────┘
                                    │ (References Catalog SKU)
                                    ▼
     ┌──────────────────────────────────────────────────────────────────┐
     │                    INVENTORY ENGINE CONTEXT (SoR)                │
     │  - Warehouses (public.warehouses)                                │
     │  - Storage Zones & Bins (public.inventory_locations)             │
     │  - Quantity Balances & Safety Stocks (public.inventory_items)    │
     │  - Concurrent Reservations (public.stock_reservations)           │
     └──────────────────────────────┬───────────────────────────────────┘
                                    │
                        (Outbox Event Notifications)
                                    ▼
     ┌──────────────────────────────────────────────────────────────────┐
     │                       ORDERS BOUNDED CONTEXT                     │
     │  - Transactional Sales Orders                                    │
     │  - Customer Ship-To Parameters                                    │
     └──────────────────────────────────────────────────────────────────┘
```

### 1.1 Decoupling and Bounded Context Boundaries
To maintain high throughput on catalog views and prevent database locking contention during sales spikes, the Inventory subsystem is strictly decoupled from adjacent domains:
*   **Separation from Product Catalog**: The Product Catalog does not own stock quantities. It defines what a product is (dimensions, weights, descriptions). The Inventory engine owns where a product is located, how many units physically exist, and how many are committed to active checkouts.
*   **Separation from Orders**: The Orders subsystem processes transactional invoices and customer details. The Inventory engine translates those orders into physical picking batches and material allocations, processing holds inside transactional boundaries before orders are finalized.
*   **Separation from Finance**: Inventory asset valuations (FIFO/FEFO cost-bases) and write-off journals are computed within the Inventory Engine, while the double-entry accounting bookings are dispatched asynchronously to the Finance ledger.
*   **Decoupling from Front-End Channels**: Storefronts query cached search read models to display product availability. Direct database inventory sweeps are strictly blocked during search operations, running only during direct cart-checkout validation gates.

### 1.2 Core Domain Design Guidelines
*   **Reservation-First Architecture**: Physical stock cannot be allocated directly by checkouts. Orders must obtain a transient reservation lock (e.g., a 15-minute cart hold) before checkout is allowed. The reservation is converted to an allocation upon payment verification, or automatically rolled back upon timeout.
*   **Aggregate Roots**: 
    *   **Warehouse Aggregate**: Governs regional warehouse metadata, layout topology (zones, bins), and operational shifts (`public.warehouses`).
    *   **InventoryItem Aggregate**: Coordinates physical SKU balances, batches, lot numbers, and active reservations within specific warehouse boundaries (`public.inventory_items`).
*   **Event-First Integration**: Changes in inventory states (e.g., stock receipts, low-stock breaches, or warehouse transfers) are emitted immediately as transactional outbox events to alert downstream services.
*   **No External Direct Writes**: External services are strictly forbidden from executing direct updates on inventory tables. All changes must route through public gRPC/REST APIs or consume event contracts.

---

## 2. WAREHOUSE DOMAIN MODEL

The platform handles global fulfillment networks by supporting native warehouse models within a unified multi-tenant database hierarchy:

```
                            [WAREHOUSE ARCHITECTURE HIERARCHY]

                           ┌──────────────────────────────┐
                           │    organization_id (Tenant)  │
                           └──────────────┬───────────────┘
                                          │
                        ┌─────────────────┴─────────────────┐
                        ▼                                   ▼
          ┌───────────────────────────┐       ┌───────────────────────────┐
          │   Geographic Region (US)  │       │  Geographic Region (EU)   │
          └─────────────┬─────────────┘       └─────────────┬─────────────┘
                        │                                   │
              ┌─────────┴─────────┐               ┌─────────┴─────────┐
              ▼                   ▼               ▼                   ▼
        [Fulfillment Center]   [3PL Node]   [Regional Hub]    [Virtual Drop-ship]
```

*   **Fulfillment Centers (FC)**: High-density, automated storage warehouses optimized for rapid, direct-to-consumer picking and shipping.
*   **Distribution Centers (DC)**: Bulk storage facilities used for wholesale storage and replenishment transfers to smaller fulfillment nodes.
*   **Store Inventory Nodes**: Retail locations capable of shipping online orders or handling buy-online-pickup-in-store (BOPIS) requests.
*   **Regional Hubs**: Localized sorting hubs used to optimize last-mile delivery routes.
*   **Virtual Warehouses**: Logical warehouses used to aggregate inventory across multiple physical nodes for specific online channels.
*   **Drop-Shipping Inventory**: Logical warehouses representing supplier-owned stock. Orders placed against these nodes trigger automated shipping requests directly to suppliers.
*   **Third-Party Logistics (3PL)**: External partner warehouses (e.g., Amazon FBA). These sync stock balances using real-time API integrations.
*   **Geographic Scoping**: Warehouses are assigned to geographical regions to enforce regional shipping restrictions and optimize transit costs.
*   **Multi-Tenant Ownership**: Every warehouse row is isolated using the tenant's `organization_id` key, preventing cross-tenant inventory leaks.

---

## 3. WAREHOUSE TOPOLOGY (STORAGE ARCHITECTURE)

To optimize picking paths and stock allocations, physical warehouses are modeled down to the bin level:

```
                          [PHYSICAL STORAGE TOPOLOGY PATH]

    Warehouse ──► Zone (e.g., Cold) ──► Aisle ──► Shelf ──► Bin (Unique picking address)
```

### 3.1 Layout Topology
*   **Zones**: Storage sections optimized for specific material requirements (e.g., Cold Storage, Hazmat/Dangerous Goods, Ambient Temperature, High-Security Valuables).
*   **Aisles**: Numbered picking walkways within storage zones.
*   **Shelves**: Vertical storage shelves within aisles.
*   **Bins**: The smallest physical storage locations, mapped to unique picking addresses (e.g., `ZN-A-12-S3-B04`).
*   **Pallet Storage**: Heavy bulk storage sections containing full pallet loads.
*   **Container Sections**: Storage areas for un-palletized containers or loose stock.

### 3.2 Functional Storage Allocations
*   **Picking Locations**: Bins optimized for rapid human or robotic picking access, limited to active safety-stock levels.
*   **Bulk Storage**: High-density overflow storage used to replenish picking bins.
*   **Overflow Storage**: Temporary storage sections used during seasonal stock arrivals.
*   **Returns Locations**: Quarantined sections where returned items are held for quality checks before being restocked or written off.

---

## 4. INVENTORY QUANTITY MODELS

To maintain absolute data integrity, the system replaces single-integer stock counters with multi-state quantity pools, avoiding locking issues and preventing overselling.

```
                      [LOGICAL INVENTORY QUANTITY POOLS]

 ┌──────────────────────────────────────────────────────────────────────────────┐
 │                        ON-HAND STOCK (Physical count in facility)            │
 ├──────────────────────────────────────┬───────────────────────────────────────┤
 │     ALLOCATED (Paid, in picking)     │      QUARANTINED (Damaged/Awaiting QC)│
 ├──────────────────────────────────────┼───────────────────────────────────────┤
 │     RESERVED (Cart holds / unpaid)   │      AVAILABLE (On-hand - committed)  │
 └──────────────────────────────────────┴───────────────────────────────────────┘
```

The system calculates active stock availability using the following deterministic equation:
$$\text{Available Stock} = \text{On-Hand Stock} - \text{Reserved Stock} - \text{Allocated Stock} - \text{Quarantined Stock} - \text{Damaged Stock}$$

### 4.1 Pool Definitions
*   **On-Hand**: The exact physical count of items in the warehouse.
*   **Available**: Physical items currently free for purchase.
*   **Reserved**: Temporary holds secured during cart sessions.
*   **Allocated**: Items paid for and actively being picked, packed, or shipped.
*   **In-Transit**: Inventory shipped from suppliers or transferred between warehouses that has not yet been received.
*   **Damaged**: Broken or unsellable stock, excluded from purchase calculations.
*   **Quarantined**: Items held for quality checks or regulatory validation.
*   **Returned**: Returned stock undergoing QC inspection in quarantined sections.
*   **Consignment**: Supplier-owned stock stored in tenant facilities, billed only when sold.
*   **Safety Stock**: Minimum stock buffers held to absorb demand spikes or supplier delays.
*   **Buffer Stock**: Temporary stock buffers used during promotional campaigns to absorb high checkout volumes.

---

## 5. INVENTORY LIFECYCLE FINITE STATE MACHINE (FSM)

The status of physical units and stock batches is governed by a strict, deterministic finite state machine (FSM) to ensure accurate picking and delivery pipelines:

```
                     [INVENTORY LIFECYCLE STATE MACHINE]

                       ┌───────────────────────┐
                       │    Pending Receipt    │
                       └───────────┬───────────┘
                                   │ (Supplier Delivery)
                                   ▼
                       ┌───────────────────────┐
                       │       Receiving       │
                       └───────────┬───────────┘
                                   │ (QC Pass / Put-away)
                                   ▼
                       ┌───────────────────────┐
                       │       Available       │
                       └───────────┬───────────┘
                                   │
                   ┌───────────────┴───────────────┐
                   ▼ (Cart Hold)                   ▼ (Defect detected)
       ┌───────────────────────┐       ┌───────────────────────┐
       │       Reserved        │       │      Quarantined      │
       └───────────┬───────────┘       └───────────┬───────────┘
                   │ (Payment Pass)                ├──────────────────────────┐
                   ▼                               ▼ (QC Fail)                ▼ (QC Pass)
       ┌───────────────────────┐       ┌───────────────────────┐              │
       │       Allocated       │       │        Damaged        │              │
       └───────────┬───────────┘       └───────────┬───────────┘              │
                   │ (Pick Wave)                   │                          │
                   ▼                               ▼                          │
       ┌───────────────────────┐       ┌───────────────────────┐              │
       │        Picking        │       │       Destroyed       │              │
       └───────────┬───────────┘       └───────────────────────┘              │
                   │ (Pack Complete)                                          │
                   ▼                                                          │
       ┌───────────────────────┐                                              │
       │        Packed         │                                              │
       └───────────┬───────────┘                                              │
                   │ (Carrier Scan)                                           │
                   ▼                                                          │
       ┌───────────────────────┐                                              │
       │        Shipped        │◄─────────────────────────────────────────────┘
       └───────────┬───────────┘
                   │ (Customer Return)
                   ▼
       ┌───────────────────────┐
       │       Returned        │
       └───────────────────────┘
```

### 5.1 FSM States
1.  **Pending Receipt**: Inventory shipments ordered from suppliers that have not yet arrived.
2.  **Receiving**: Physical cargo at the warehouse dock, undergoing cargo counts and verification against purchase orders.
3.  **Available**: Items stored in picking bins, active for storefront purchases.
4.  **Reserved**: Items placed in user shopping carts, securing transient checkout locks.
5.  **Allocated**: Items with verified customer payments, awaiting fulfillment routing.
6.  **Picking**: Active pick tasks, locked to a picker's handheld terminal.
7.  **Packed**: Items verified, packed into shipping cartons, and tagged with carrier labels.
8.  **Shipped**: Packages handed off to shipping carriers, clearing physical inventory counts.
9.  **Returned**: Customer-returned items received at returns docks, held for inspection.
10. **Quarantined**: Items held in isolated bins during product recalls or quality inspections.
11. **Damaged**: Broken or unsellable stock awaiting write-off.
12. **Destroyed**: Written-off items permanently purged from active systems.

### 5.2 Transition Matrix and Validation Rules

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Pending Receipt** | **Receiving** | Cargo Dock Scan | Verifies that shipment SKU codes match the registered Purchase Order. |
| **Receiving** | **Available** | Put-Away Complete | Verifies that the storage bin exists and is assigned to active picking zones. |
| **Available** | **Reserved** | Customer Cart Add | Reserves stock transiently; decrements `quantity_available` but leaves `quantity_on_hand` unchanged. |
| **Reserved** | **Available** | Cart Timeout / Clear | Occurs automatically if checkout timers expire or users manually clear their shopping carts. |
| **Reserved** | **Allocated** | Payment Success | Flags items for pick-pack operations; increments `quantity_allocated` and decrements `quantity_reserved`. |
| **Allocated** | **Picking** | Picker Terminal Claim | Assigns lines to wave picker IDs, locking rows to prevent duplicate picking. |
| **Picking** | **Packed** | Station Scan | Validates picked items against original shipping orders using barcode scanning. |
| **Packed** | **Shipped** | Carrier Cargo Scan | Decrements `quantity_on_hand` and `quantity_allocated` values upon cargo handoff. |
| **Shipped** | **Returned** | Customer Return Scan | Places returned items in isolated quarantine bins, logging reasons. |
| **Returned** | **Available** | QC Inspection Pass | Returns inspected items to active picking bins, updating available stocks. |
| **Returned** | **Damaged** | QC Inspection Fail | Shifts failed items to quarantined damaged pools, awaiting write-off. |
| **Damaged** | **Destroyed** | Disposal Complete | Destroys items under supervisor oversight, logging write-off values. |

*   **FSM Rollback Rule**: If picking verification scans fail (e.g., due to stock damage found during picking), picking tasks are aborted, affected items are flagged as damaged, and replacement picking runs are scheduled from alternative storage locations.

---

## 6. THE CONCURRENT RESERVATION ENGINE

The system coordinates concurrent checkout holds using robust reservation locks, avoiding overselling during high-traffic sales events.

```
                         [CONCURRENT RESERVATION LOCK PATH]

    Client Checkout Request ──► Run PostgreSQL SELECT FOR UPDATE SKIP LOCKED Query
                                                      │
                                   ┌──────────────────┘
                                   ▼
    Verify Stock Availability ──► Write Reservation Record ──► Release Row lock
```

### 6.1 Transactional Locking with `SKIP LOCKED`
To avoid database deadlocks when multiple users attempt to purchase the same high-demand item simultaneously, the Reservation Engine isolates rows using PostgreSQL's `SELECT FOR UPDATE SKIP LOCKED` query. This allows background workers to identify and allocate available stock rows without blocking concurrent sessions:

```sql
-- Query to identify and reserve stock across active warehouse zones without blocking
SELECT id, warehouse_id, quantity_on_hand, quantity_allocated 
FROM public.inventory_items
WHERE product_variant_id = :targetVariantId
  AND (quantity_on_hand - quantity_allocated) >= :purchaseQuantity
FOR UPDATE SKIP LOCKED;
```

### 6.2 Reservation Expirations and TTL Releases
*   **Transient Expiration Timers**: Reservations are committed to `public.stock_reservations` with a default Time-To-Live (TTL) limit (e.g., 15 minutes for standard carts, 5 minutes for flash sales).
*   **Payment Timeout Cleanup**: A scheduled cron sweep job processes expired reservations, returning the reserved stock to the available pool and clearing Redis caches:
    ```json
    {
      "sweep_job_id": "018f63bb-9ab6-7000-8d59-fc5095033501",
      "records_swept": 142,
      "stock_restored_count": 284,
      "execution_duration_ms": 42
    }
    ```

### 6.3 Split and Partial Reservations
*   **Partial Reservations**: If a warehouse lacks sufficient stock to fulfill an order, the system can split reservations across multiple facilities (e.g., reserving 2 units in warehouse A and 3 units in warehouse B), notifying customers of split shipments during checkout.
*   **Reservation Priorities**: Emergency orders or wholesale B2B accounts can bypass standard reservations, reclaiming stock from low-priority retail cart holds.

---

## 7. IMMUTABLE INVENTORY TRANSACTIONS

All physical stock changes generate immutable transaction records to provide complete auditing trail compliance (SOC 2 and ISO 27001):

```
                       [IMMUTABLE DOUBLE-ENTRY LEDGER PROCESS]

    Trigger Event ──► Log Physical Inventory Transaction ──► Apply Cryptographic Hash
                      - Record source and target bins      - Chain hash keys
                      - Log user and transaction type      - Prevent historical changes
```

To prevent fraudulent stock modifications and historical corrections, stock updates are recorded as double-entry ledger listings inside `public.inventory_transactions`:
*   Every stock receipt, adjustment, or transfer requires both a source and target location reference.
*   Physical adjustments require a reason code (e.g., `THEFT`, `DAMAGE`, `QC_REJECTION`).
*   Transaction records are cryptographically signed, chaining row hashes to detect historical changes.

---

## 8. STOCK MOVEMENT ENGINE

The Stock Movement Engine manages the internal logistics of inventory within and between warehouses:

```
                            [INTERNAL MOVEMENT ROUTING]

   Replenishment Alert ──► Locate Bulk Storage Pallet ──► Generate Internal Pick Task
                                                                   │
               ┌───────────────────────────────────────────────────┘
               ▼
   Scan Pallet to Pick Bin ──► Update Location Balances ──► Verify Destination Load
```

*   **Warehouse Transfers**: Manages transit tracking and customs validations for shipments moving between company facilities.
*   **Replenishments**: Coordinates internal stock replenishments, automatically generating picking tasks to move bulk storage items to picking bins when stocks run low.
*   **Picking Paths**: Calculates optimized picking paths using warehouse layout maps, reducing walking distances for warehouse personnel.
*   **Quarantine Transfers**: Automatically routes items that fail incoming quality checks to isolated quarantine sections, blocking them from active catalogs.

---

## 9. INVENTORY COUNTING & AUDIT RECONCILIATION

To align physical stock with database records, the platform supports advanced inventory counting workflows:

```
                         [BLIND CYCLE COUNT PROCESS]

    Schedule Cycle Count ──► Generate Blind Pick sheets ──► Counters Input Physical Counts
                             (Expected numbers hidden)              │
                                                                    ▼
    Reconcile Variances ◄── Authorize adjustments (Maker-Checker) ◄─┘
```

*   **Cycle Counting**: Small, scheduled daily counts targeting specific shelves or categories without interrupting warehouse operations.
*   **Blind Counts**: Picksheets hide expected quantities from warehouse counters, preventing confirmation bias and ensuring accurate physical counts.
*   **Variance Management**: Variance thresholds (e.g., discrepancies over $500 or 5%) flag counts for secondary recount reviews.
*   **Maker-Checker Adjustment Approvals**: Minor inventory adjustments are applied automatically, while adjustments that exceed threshold limits require manager approval before updating databases.

---

## 10. MULTI-WAREHOUSE ROUTING AND ORDER ALLOCATION

For multi-warehouse setups, order routing is optimized to minimize delivery times and costs:

```
                        [ORDER ALLOCATION ROUTING LOGIC]

    Customer Order Received ──► Locate stock near coordinates (Lat/Long)
                                             │
                          ┌──────────────────┴──────────────────┐
                          ▼                                     ▼
               Single Warehouse Found                Split Fulfillment Required
             - Pick near facility                   - Split orders across warehouses
             - Dispatch single package              - Dispatch multiple packages
```

*   **Nearest Warehouse Routing**: Calculates distance matrices using customer delivery coordinates and warehouse geolocations, routing orders to the closest facility with available stock.
*   **Least-Cost Routing**: Factors in shipping carrier rates, packaging materials, and labor costs to select the most cost-effective fulfillment location.
*   **Split Fulfillment Rules**: If an order contains multiple items that are not co-located, the system can split the order into multiple shipments or consolidate items at a central hub before delivery.
*   **Regional Restrictions**: Prevents orders from being allocated to warehouses that cannot ship to the destination country due to trade or customs regulations.

---

## 11. WAREHOUSE OPERATIONS PIPELINES

Warehouse fulfillment processes are managed via structured execution pipelines:

```
                          [FULFILLMENT PIPELINE STEP PATH]

    Step 1: Wave Picking ──► Step 2: Packing Station ──► Step 3: Weighing & Labeling
    (Group orders by zone)    (Barcode verification)      (Apply carrier barcodes)
```

1.  **Incoming Cargo Receiving**: Verification scans check incoming deliveries against purchase orders, logging variances before put-away.
2.  **Put-Away**: Calculates optimized storage locations for newly received stock based on weight, safety, and inventory turnovers.
3.  **Wave Picking**: Groups orders into picking waves based on warehouse zones, optimizing picking paths.
4.  **Packing Station Scanning**: Verifies picked items using barcode scans before packing them into cartons, reducing shipment errors.
5.  **Weighing and Carrier Labeling**: Automatically calculates packaging weights, selects carriers, and prints shipping labels.
6.  **Returns Processing**: Customer returns undergo rigorous quality checks at returned stations, routing items to restocking bins, refurbishing, or disposal.

---

## 12. BATCH, LOT, AND SERIAL NUMBER TRACKING

The system enforces strict traceability rules for food, medical, high-value, and sensitive items:

```
                            [LOT & EXPIRATION WORKFLOW]

   Receive Batch (Lot #, Expiry) ──► Enforce FEFO picking ──► Recall Issued
                                             │                     │
                                             ▼                     ▼
                                  Scan items to pick     Quarantine all units
                                  (Block expired items)   matching lot ID
```

*   **Lot & Batch Tracking**: Groups inventory by production lots to support product recalls and batch track reviews.
*   **FEFO (First Expired, First Out)**: Automatically routes picking tasks to items with the earliest expiration dates, reducing waste.
*   **FIFO (First In, First Out)**: Routes picking tasks to oldest inventory lots, maintaining inventory rotations.
*   **Serial Number Tracking**: Assigns unique serial numbers to specific items (such as high-end electronics), tracking units from receipt to final shipment.
*   **Product Recalls**: If a manufacturer issues a product recall, operators can quarantine all stock matching affected lot numbers instantly across all facilities.

---

## 13. INVENTORY FORECASTING & REORDER LOGIC

The system monitors stock levels and uses historical consumption patterns to automate replenishment:

```
                         [AUTOMATED REORDER FORECAST LOOP]

    Analyze sales speeds ──► Compute Reorder Point (ROP) ──► Quantity drops below ROP
                                                                     │
                 ┌───────────────────────────────────────────────────┘
                 ▼
    Calculate Economic Order Quantity (EOQ) ──► Generate purchase draft
```

*   **Demand Forecasting**: Analyzes past sales trends and seasonal shifts to forecast future inventory requirements.
*   **Economic Order Quantity (EOQ)**: Calculates optimal order quantities to minimize ordering and holding costs:
    $$EOQ = \sqrt{\frac{2 \times \text{Annual Demand} \times \text{Order Cost}}{\text{Holding Cost}}}$$
*   **Reorder Points (ROP)**: Calculates reorder points based on lead times and safety stock levels, generating replenishment alerts when stocks drop:
    $$ROP = (\text{Average Daily Sales} \times \text{Lead Time Days}) + \text{Safety Stock}$$
*   **Supplier Reliability Tracking**: Monitors supplier delivery timelines and defect rates, adjusting safety stock calculations dynamically.

---

## 14. BUSINESS EVENT CONTRACTS

The Inventory subsystem communicates updates to downstream systems using standardized event payloads written to transactional outbox tables:

```
                        [TRANSACTIONAL OUTBOX PIPELINE]

   Parent DB Commit ──► Write Outbox Event Payload ──► Sweep Queue ──► Dispatch Message
```

### 14.1 `inventory.received.v1`
*   **Publisher**: Inventory Warehouse Service
*   **Primary Consumers**: Purchase order managers, merchandising portals, financial accounting.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033511",
      "event_type": "inventory.received.v1",
      "timestamp": "2026-07-01T00:00:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033512",
      "warehouse_id": "018f63bb-9ab6-7000-8d59-fc5095033513",
      "purchase_order_id": "018f63bb-9ab6-7000-8d59-fc5095033514",
      "received_items": [
        {
          "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033515",
          "quantity_received": 100,
          "batch_number": "LOT-2026-A1",
          "expiry_date": "2027-12-31"
        }
      ]
    }
    ```

### 14.2 `inventory.reserved.v1`
*   **Publisher**: Concurrent Reservation Engine
*   **Primary Consumers**: Shopping cart services, fulfillment coordinators, analytics tracking.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033516",
      "event_type": "inventory.reserved.v1",
      "timestamp": "2026-07-01T00:05:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033512",
      "reservation_id": "018f63bb-9ab6-7000-8d59-fc5095033517",
      "expires_at": "2026-07-01T00:20:00Z",
      "items": [
        {
          "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033515",
          "quantity_reserved": 2,
          "warehouse_id": "018f63bb-9ab6-7000-8d59-fc5095033513"
        }
      ]
    }
    ```

### 14.3 `inventory.low_stock.v1`
*   **Publisher**: Inventory Monitoring Service
*   **Primary Consumers**: Merchandising planners, purchasing agents, marketing engines.
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033518",
      "event_type": "inventory.low_stock.v1",
      "timestamp": "2026-07-01T00:10:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033512",
      "warehouse_id": "018f63bb-9ab6-7000-8d59-fc5095033513",
      "product_variant_id": "018f63bb-9ab6-7000-8d59-fc5095033515",
      "quantity_on_hand": 5,
      "reorder_point": 10
    }
    ```

### 14.4 Resiliency Policies
*   **Outbox Transactionality**: Event payloads are written to outbox tables (`public.marketplace_event_outbox`) inside database transaction blocks, guaranteeing atomic consistency.
*   **Retry Mechanisms**: Failed event dispatches use exponential backoff schedules, retrying 5 times before routing messages to dead-letter queues.
*   **Idempotency Checks**: Downstream systems track unique message keys (`event_id`) to block duplicate message processing.

---

## 15. ROLE-BASED ACCESS CONTROL (RBAC) & DATA SECURITY

The Inventory subsystem implements strict, multi-tenant security layers to protect sensitive inventory data and warehouse parameters:

```
                         [SECURITY CONTROL ACCESS GATE]

    Client Request ──► Verify Session JWT ──► Verify Tenant Isolation (RLS)
                                                     │
                  ┌──────────────────────────────────┘
                  ▼
    Verify RBAC Role Action ──► Execute Database Write ──► Write Compliance Log
```

*   **Tenant Isolation**: Row-Level Security (RLS) is enabled across all inventory tables, isolating queries using the tenant's `organization_id`.
*   **Maker-Checker Controls**: Prevents warehouse staff from self-approving large inventory adjustments, requiring supervisor approval.
*   **RBAC Permissions**: Restricts inventory actions to specific roles:
    *   *Warehouse Operator*: Can claim picking wave tasks, verify packing cartons, and execute stock counts.
    *   *Warehouse Supervisor*: Can authorize inventory adjustments, trigger warehouse replenishments, and configure topologies.
    *   *Procurement Specialist*: Can generate purchase orders and manage pending cargo receiving timelines.
*   **Audit Logging**: The engine logs all inventory changes, adjustments, and warehouse transfers to immutable audit tables, tracking user IDs, timestamps, previous locations, and reasons.

---

## 16. HIGH-PERFORMANCE PERFORMANCE TRACEABILITY

To scale inventory operations across high checkout volumes, several performance optimizations are implemented at the database and application levels:

*   **Table Partitioning**: Partitions inventory transactions (`public.inventory_transactions`) by date range (e.g., monthly partitions) to keep query times consistent as datasets grow.
*   **Database Indexing**: Implements composite B-Tree indexes on frequently queried fields (such as `warehouse_id, product_variant_id`) to speed up stock lookups.
*   **Optimistic Concurrency Control**: Tracks active row versions using integer columns (`version`), blocking concurrent overwrites.
*   **In-Memory Caching (Redis)**: Caches product availability states on Redis nodes to deliver fast storefront page loads, bypassing direct database lookups.
*   **SKIP LOCKED Processing**: Sweep jobs use `SKIP LOCKED` queries to locate and process unlocked reservation rows without causing database deadlocks.

---

## 17. ENGINEERING VALIDATION CHECKLIST

The Architecture Review Board evaluates Inventory deployments against this comprehensive checklist to ensure system integrity:

*   [ ] **FSM Correctness**: Verify that all state machine transitions validate fields and block unauthorized state changes.
*   [ ] **Locking Mechanisms**: Confirm that concurrent reservations use `SKIP LOCKED` parameters to prevent database locks.
*   [ ] **Multi-Tenant RLS**: Validate that Row-Level Security policies isolate inventory queries by tenant `organization_id` at the database layer.
*   [ ] **Maker-Checker Security**: Confirm that inventory adjustments over threshold limits require supervisor approval.
*   [ ] **Audit Trail Integrity**: Verify that physical stock modifications write detailed records to immutable transaction ledgers.
*   [ ] **Outbox Transactionality**: Verify that outbox event writes are committed within parent database transaction blocks.
*   [ ] **Geographic Routing Accuracy**: Test multi-warehouse routing calculations to confirm nearest-warehouse and least-cost allocations.
*   [ ] **FEFO Rotation Enforcements**: Confirm that batch picking runs route pickers to items with the earliest expiration dates.
*   [ ] **High-Performance Scaling**: Benchmark reservation speeds under high simulated concurrent checkout volumes.
*   [ ] **Disaster Rollback Scenarios**: Test database transaction rollbacks during simulated network and database disruptions.

---

*Authorized by the JUANET Technical Review Board & Global Logistics Council.*
