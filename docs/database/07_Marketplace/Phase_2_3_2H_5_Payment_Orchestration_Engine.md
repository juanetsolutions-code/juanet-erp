# JUANET Marketplace Payment Orchestration Engine Manual
## Phase 2.3.2H.5 — Payment Authorizations, Capture Engines, Gateway Abstraction, Refunds, Dispute Lifecycles, and Finite State Machines

**Document Version:** 2.0  
**Author:** Principal Enterprise Solutions Architect, VP of Transactional Platform Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. PAYMENT ARCHITECTURE PHILOSOPHY

The **JUANET Payment Orchestration Engine** is the authoritative system responsible for the secure orchestration, execution, and state management of financial transactions within the Marketplace bounded context. Positioned as a high-throughput, resilient gateway abstractor, it acts as the primary buffer between transient checkout events and the physical networks of global payment processors.

```
                    [PAYMENT ORCHESTRATION BOUNDED CONTEXT RESILIENCY GRAPH]

   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                           ORDERS & CHECKOUT CONTEXT                             │
   └────────────────────────────────────────┬────────────────────────────────────────┘
                                            │
                             (Create Payment Session)
                                            ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                       PAYMENT ORCHESTRATION ENGINE                              │
   │                                                                                 │
   │  ┌───────────────────────┐         ┌───────────────────┐    ┌────────────────┐  │
   │  │    Payment Session    │ ◄─────► │   Payment Attempt │ ◄─►│ Token Vault /  │  │
   │  │   (Amount, Currency)  │         │   (Transaction)   │    │ PCI Gateway    │  │
   │  └───────────────────────┘         └─────────┬─────────┘    └────────────────┘  │
   │                                              │                                  │
   │                                  GAL Router / Fallback                          │
   │                                              ▼                                  │
   │                                    ┌───────────────────┐                        │
   │                                    │  Gateway Adapter  │                        │
   │                                    └─────────┬─────────┘                        │
   └──────────────────────────────────────────────┼──────────────────────────────────┘
                                                  │
                                   gRPC / REST (HTTPS TLS 1.3)
                                                  ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                          EXTERNAL PAYMENT PROCESSORS                            │
   │                                                                                 │
   │     ┌──────────────┐          ┌──────────────┐          ┌─────────────────┐     │
   │     │    Stripe    │          │    PayPal    │          │ M-Pesa / Mobile │     │
   │     └──────────────┘          └──────────────┘          └─────────────────┘     │
   └──────────────────────────────────────────────┬──────────────────────────────────┘
                                                  │
                                     (Outbox Event Notification)
                                                  ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                             FINANCE BOUNDED CONTEXT                             │
   │                                                                                 │
   │    ┌───────────────────────────────────────────────────────────────────────┐    │
   │    │                      System of Record (SoR)                           │    │
   │    │  - Immutable Double-Entry Ledger      - Regional Tax Declarations      │    │
   │    │  - General Ledger & Accounts          - Merchant Payout Invoicing     │    │
   │    └───────────────────────────────────────────────────────────────────────┘    │
   └─────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Separation from the Finance Bounded Context
To maintain strict transactional isolation, the Payment Orchestration Engine operates entirely distinct from accounting structures:
*   **The Finance Context is the Bounded System of Record (SoR)** for the General Ledger, accounts receivable, double-entry journals, merchant invoice settlements, regional tax liabilities, and consolidated balance sheets.
*   **The Payment Context is purely an Operational Engine**. It manages short-lived payment sessions, multi-vendor captures, authorization holds, gateway webhooks, tokens, and chargeback actions. It has zero visibility into balance charts, credit ratings, or accounting entries.
*   **No Direct Accounting Writes**: The Payment Orchestration Engine is strictly prohibited from writing to any table prefixed with `finance.` or directly manipulating accounting accounts. Instead, payment state updates write event payloads to the `public.marketplace_event_outbox` within the same database transaction. Downstream financial worker services ingest these events to construct appropriate debit/credit ledger lines.

### 1.2 Core Architectural Principles
*   **Gateway Abstraction**: The core engine communicates with processors through a unified, vendor-agnostic interface. No business logic outside the Gateway Abstraction Layer (GAL) has direct awareness of Stripe, PayPal, Flutterwave, or M-Pesa API idiosyncrasies.
*   **Adapter-Based Architecture**: Processor integrations are isolated within specific Adapter components. Adding a new payment partner involves deploying a new Adapter class implementing standard interfaces, with zero impact on the core transaction engine.
*   **Stateless Processing**: Gateway API state transformations do not rely on local server memory. All processing contexts are captured in the relational database, permitting arbitrary horizontal scaling of Payment cluster nodes.
*   **Exactly-Once Commercial Execution**: The engine enforces strict idempotency at every stage of the payment pipeline. Network retries, gateway timeouts, and consumer duplications must never result in duplicate financial charges.
*   **At-Least-Once Outbox Delivery**: All internal state shifts (e.g., card authorized, charge captured) publish consistent events to the transactional outbox table, ensuring downstream systems are notified even during hardware or network partitions.
*   **Failure Isolation**: High-latency or failing external gateways must never degrade core checkout flows. If a vendor adapter fails, the engine activates circuit breakers, routing transactions to alternative providers.

---

## 2. PAYMENT AGGREGATE DESIGN

The **Payment Aggregate** represents the logical consistency and transaction boundary for payments, managed by the root table `public.payment_sessions`. It coordinates and secures all related payment entities, ensuring atomic writes and preventing partial state anomalies.

```
                     [PAYMENT AGGREGATE ENTITY RELATIONSHIPS]

┌──────────────────────────────────────────────────────────────────────────────┐
│  public.payment_sessions (Aggregate Root)                                    │
│  - id (UUIDv7 Primary Key)                                                   │
│  - organization_id (Tenant Isolation Key)                                    │
│  - order_id (Target Reference Key)                                           │
│  - currency_code (ISO-4217)                                                  │
│  - amount_authorized, amount_captured, amount_refunded                       │
│  - fsm_status (FSM State)                                                    │
│  - version (Optimistic Lock Version)                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_attempts (1:N Relationship)                          │   │
│   │  - id, gateway_code, attempt_type, status, failure_reason            │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_authorizations (1:1 Relationship)                    │   │
│   │  - id, gateway_auth_token, expires_at, state, voided_at              │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_captures (1:N Relationship)                          │   │
│   │  - id, gateway_capture_id, amount, captured_at, status               │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_refunds (1:N Relationship)                           │   │
│   │  - id, gateway_refund_id, amount, requested_by, status               │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_disputes (1:N Relationship)                          │   │
│   │  - id, dispute_reason, category, amount_disputed, deadline           │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.payment_gateway_responses (1:N Relationship)                 │   │
│   │  - id, payload (JSONB), received_at, tracking_code                   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Complete Entity Ownership Dictionary

The Payment Orchestration Bounded Context maintains exclusive transactional authority over the following sub-entities within the Aggregate Root consistency boundary:

| Sub-Entity | Physical Table Name | Domain Purpose | Ownership & Mutation Policy |
| :--- | :--- | :--- | :--- |
| **Payment Sessions** | `public.payment_sessions` | Root aggregate record coordinating overall transaction state, customer keys, tenant groupings, amounts, and FSM tracking. | **Absolute Root.** All modifications must route through this record. |
| **Payment Attempts** | `public.payment_attempts` | Individual transaction attempts referencing specific gateways, methods, request timestamps, and response codes. | Owned by Root. Append-only record logging execution steps. |
| **Authorizations** | `public.payment_authorizations` | Frozen records of validated credit hold thresholds, tracking provider auth tokens, expiration limits, and void flags. | Owned by Root. Updated when auth holds are processed or released. |
| **Captures** | `public.payment_captures` | Records of captured cash from active authorizations, tracking processing amounts, settlement identifiers, and batches. | Owned by Root. Appended during settlement tasks. |
| **Refunds** | `public.payment_refunds` | Traced entries of funds returned to customers, tracking gateway return IDs, reasons, audit records, and approval state. | Owned by Root. Appended when returns or adjustments are processed. |
| **Chargebacks** | `public.payment_chargebacks` | Tracks dispute events initiated by banks, documenting chargeback reasons, dispute amounts, and financial holds. | Managed by Root. Appended via chargeback webhook alerts. |
| **Disputes** | `public.payment_disputes` | Documented entries mapping active chargeback cases, evidence files, submission status, and card-brand timelines. | Managed by Root. Updated dynamically during representment waves. |
| **Gateway Responses** | `public.payment_gateway_responses` | Complete, unparsed JSON payload history captured from gateway webhooks and synchronous responses, preserved for troubleshooting. | **Immutable Append-Only.** Never modified once written. |
| **Tokens** | `public.payment_tokens` | Non-sensitive, obfuscated customer card references, token sources, brand markers, and billing profiles. | Owned by Root. Managed via strict secure tokenization vaults. |
| **Mandates** | `public.payment_mandates` | Legal authorization agreements enabling recurring, subscription, or future customer-initiated charges. | Owned by Root. Signed agreements cannot be altered once saved. |
| **Payment Metadata** | `public.payment_metadata` | Flexible key-value structures storing localized context details, checkout devices, IP geo-locations, and custom parameters. | Owned by Root. Modifiable during session initial phases. |
| **Risk Assessments** | `public.payment_risk_assessments` | Evaluated fraud scores, card-velocity logs, proxy detections, and automated compliance review markers. | Owned by Root. Appended before external authorization attempts. |
| **Provider References** | Column: `gateway_code` | Categorical indicators identifying active gateway endpoints (e.g., 'STRIPE', 'PAYPAL'). | Immutable once an attempt is initialized. |
| **Order References** | Column: `order_id` | Foreign-key mappings linking payment sessions directly to validated purchase order aggregates. | Immutable to preserve the operational audit trial. |
| **Customer References** | Column: `customer_id` | Identifiers mapping payment records back to authenticated master accounts or guest session keys. | Immutable to maintain audit trails. |
| **Vendor References** | `public.payment_vendor_splits` | Mappings determining how captured checkout funds are split among marketplace vendors, listing commissions and payouts. | Owned by Root. Immutable after checkout completion. |

### 2.2 Aggregate Consistency Rules
*   **Atomic Updates**: Every state change in a sub-entity (e.g., adding a new capture row to `public.payment_captures`) must lock the parent session row using a database transaction to prevent concurrent modifications.
*   **Amount Consistencies**: The sum of values within `public.payment_captures` must never exceed the total authorized amount in `public.payment_authorizations`:
    $$\sum \text{Capture Amounts} \le \text{Authorized Hold Amount}$$
*   **Refund Thresholds**: The total value across `public.payment_refunds` cannot exceed the total settled amount across `public.payment_captures`:
    $$\sum \text{Refund Amounts} \le \sum \text{Captured Amounts}$$
*   **Optimistic Lock Guarding**: The parent table `public.payment_sessions` employs a `version` column to enforce optimistic concurrency control, preventing simultaneous API processing threads from corrupting financial states.

---

## 3. PAYMENT LIFECYCLE FINITE STATE MACHINE (FSM)

Every payment transaction is managed by a strict, deterministic Finite State Machine (FSM) to enforce valid transitions, prevent state corruption, and provide clear tracking.

```
                        [PAYMENT LIFECYCLE STATE TRANSITIONS]

                            ┌──────────────────────┐
                            │        Draft         │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Pending        │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │     Authorizing      │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
         ┌─────────────────►│      Authorized      │       │    Failed    │
         │                  └──────────┬───────────┘       └──────────────┘
    (Re-auth)                          │
         │                             ▼
         │                  ┌──────────────────────┐
         └──────────────────┤       Captured       ├──────────────┐
                            └──────────┬───────────┘              │
                                       │                          ▼
                                       ▼                  ┌──────────────┐
                            ┌──────────────────────┐      │  Cancelled   │
                            │  Partially Captured  │      └──────────────┘
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Settled        ├─────────────────────────────┐
                            └──────────┬───────────┘                             │
                                       │                                         ▼
                                       ▼                                  ┌──────────────┐
                            ┌──────────────────────┐                      │  Disputed    │
         ┌─────────────────►│  Refund Requested    │                      └──────┬───────┘
         │                  └──────────┬───────────┘                             │
    (Retry approval)                   │                                         ▼
         │                             ▼                                  ┌──────────────┐
         │                  ┌──────────────────────┐                      │  Chargeback  │
         └──────────────────┤  Refund Processing   │                      └──────┬───────┘
                            └──────────┬───────────┘                             │
                                       ├──────────────────────────┐              │
                                       ▼                          ▼              ▼
                            ┌──────────────────────┐       ┌──────────────┐     ┌────────┐
                            │       Refunded       │       │  Partially   │◄────┤ Closed │
                            └──────────┬───────────┘       │   Refunded   │     └────────┘
                                       │                   └──────┬───────┘
                                       ▼                          │
                            ┌──────────────────────┐              │
                            │       Archived       │◄─────────────┘
                            └──────────────────────┘
```

### 3.1 State Definitions and Transition Rules

#### 3.1.1 State Dictionary
1.  **Draft**: An unsubmitted payment session where amounts, currencies, and routing metadata are configured.
2.  **Pending**: The session is initialized and locked. The customer is redirected to complete SCA or provider validation challenges.
3.  **Authorizing**: The payment request is submitted to the gateway adapter, waiting for the processor's decision.
4.  **Authorized**: The gateway successfully verified the transaction and locked the funds, but the cash has not been drawn.
5.  **Captured**: The authorized funds are drawn from the customer's account and queued for settlement.
6.  **Partially Captured**: A portion of the authorized hold is drawn, with the remaining balance left open for secondary captures.
7.  **Settled**: The cash is deposited into the merchant account, closing the active checkout phase.
8.  **Failed**: The gateway rejected the payment (e.g., due to insufficient funds, velocity limits, or fraud flags).
9.  **Expired**: The authorization hold was not captured within the gateway's validity window, releasing the funds.
10. **Cancelled**: The session was aborted by the user or an admin before authorization or capture occurred.
11. **Refund Requested**: A refund is initiated and awaits administrative or manager approval.
12. **Refund Processing**: The refund request is submitted to the gateway adapter, waiting for completion.
13. **Refunded**: The gateway processed the return, returning the full transaction value to the customer.
14. **Partially Refunded**: A portion of the transaction value was returned, leaving the remainder settled.
15. **Disputed**: The cardholder challenged the transaction with their bank, placing a temporary hold on the funds.
16. **Chargeback**: The issuer clawed back the funds, debiting the merchant and applying dispute fees.
17. **Closed**: The dispute case is resolved (won or lost), releasing active holds.
18. **Archived**: Closed payment histories preserved for audit compliance.

#### 3.1.2 Transition Matrix

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Pending** | Session Submit | Amounts and currency are locked; validates routing rules. |
| **Pending** | **Authorizing** | Adapter Launch | Risk checks passed; locks payment details. |
| **Authorizing** | **Authorized** | Gateway Success | Verification successful; captures auth tokens and hold details. |
| **Authorizing** | **Failed** | Gateway Decline | Capture decline reasons; releases session holds. |
| **Authorized** | **Captured** | Capture Command | Captured amount must be $\le$ authorized amount. |
| **Authorized** | **Partially Captured**| Split-Capture Request| Capture value is less than hold; updates remaining balance tracking. |
| **Authorized** | **Expired** | Expiry Cron Sweep | Expiry time has passed; releases hold tokens. |
| **Captured** | **Settled** | Gateway Settlement Rec | Settlement receipt received; writes ledger event. |
| **Settled** | **Refund Requested** | RMA / Admin Return | Returns approved; value must be $\le$ settled total. |
| **Refund Requested**| **Refund Processing**| Adapter Refund Launch | Verification complete; locks transaction records. |
| **Refund Processing**| **Refunded** | Gateway Refund Success| Full amount returned; updates transactional outbox. |
| **Refund Processing**| **Partially Refunded**| Partial Return Success| Returns partial amount; updates balance charts. |
| **Settled** | **Disputed** | Bank Dispute Webhook | Logs dispute reason and deadline; freezes funds. |
| **Disputed** | **Chargeback** | Issuer Decision Lost | Deducts dispute amounts and fees from merchant balance. |
| **Disputed** | **Closed** | Issuer Decision Won | Releases dispute holds; updates case status. |

---

## 4. GATEWAY ABSTRACTION LAYER (GAL)

The **Gateway Abstraction Layer (GAL)** decouples the core Payment Engine from specific processor APIs, standardizing all integrations. No external business logic interacts directly with processor SDKs.

```
                      [GATEWAY ABSTRACTION INTERFACE]

                     Payment Orchestration Pipeline
                                   │
               ┌───────────────────┴───────────────────┐
               ▼                                       ▼
     GAL Interface: authorize()              GAL Interface: capture()
               │                                       │
      ┌────────┴────────┐                     ┌────────┴────────┐
      ▼                 ▼                     ▼                 ▼
StripeAdapter     PayPalAdapter         StripeAdapter     PayPalAdapter
      │                 │                     │                 │
Stripe REST API    PayPal REST API      Stripe REST API    PayPal REST API
```

### 4.1 Adapter Interface Definition (TypeScript)

The GAL defines standard TypeScript interfaces that every gateway adapter must implement:

```typescript
export interface BasePaymentAdapter {
  readonly gatewayCode: string;

  /**
   * Authorizes a specific payment amount on a card/token hold.
   */
  authorize(request: PaymentAuthRequest): Promise<PaymentGatewayResponse>;

  /**
   * Captures previously authorized funds from a hold.
   */
  capture(request: PaymentCaptureRequest): Promise<PaymentGatewayResponse>;

  /**
   * Voids an active authorization hold.
   */
  void(request: PaymentVoidRequest): Promise<PaymentGatewayResponse>;

  /**
   * Refunds a captured payment.
   */
  refund(request: PaymentRefundRequest): Promise<PaymentGatewayResponse>;

  /**
   * Queries the current status of a transaction from the gateway.
   */
  queryStatus(transactionId: string): Promise<PaymentGatewayResponse>;
}

export interface PaymentAuthRequest {
  paymentSessionId: string;
  amount: number;
  currency: string;
  token: string;
  metadata: Record<string, string>;
  billingAddress: AddressPayload;
  shippingAddress?: AddressPayload;
}

export interface PaymentGatewayResponse {
  success: boolean;
  gatewayTransactionId?: string;
  authorizationToken?: string;
  rawResponsePayload: Record<string, any>;
  errorCode?: string;
  errorMessage?: string;
  riskScore?: number;
  threeDSecureRequired?: boolean;
  threeDSecureRedirectUrl?: string;
}
```

### 4.2 Standard Core Adapters

#### 4.2.1 Stripe Adapter
*   **Tokenization Mapping**: Maps standard Stripe PaymentIntents to the `authorize` and `capture` interfaces.
*   **Webhook Verification**: Validates incoming Stripe-Signature headers against the configured endpoint secret.

#### 4.2.2 PayPal Adapter
*   **Tokenization Mapping**: Coordinates PayPal Orders and Captures APIs, supporting direct checkout redirections.
*   **SCA Redirections**: Maps authorization approvals to target redirection screens.

#### 4.2.3 Flutterwave Adapter
*   **Tokenization Mapping**: Supports cards, mobile money, and regional bank transfers across African markets.
*   **Dynamic Conversions**: Tracks currency settlements inside localized bank rails.

#### 4.2.4 M-Pesa Adapter
*   **Tokenization Mapping**: Uses the Daraja API to trigger Customer-to-Business (C2B) STK push payments.
*   **Timeout Handlers**: Processes callback responses asynchronously to handle mobile carrier network latencies.

#### 4.2.5 Bank Transfer Adapter
*   **Tokenization Mapping**: Generates unique, virtual reference account numbers for each invoice.
*   **Manual Verification**: Supports administrative settlement verification overlays.

#### 4.2.6 Manual Payments Adapter
*   **Tokenization Mapping**: Tracks cash-on-delivery (COD) or retail point-of-sale (POS) terminal authorizations.
*   **Cash Flow Logs**: Updates offline registers cleanly.

### 4.3 Dynamic Capability Negotiation & Smart Routing
The GAL evaluates payment contexts to optimize transaction routing and minimize transaction fees:
*   **Geo-IP Routing**: Routes local card transactions to regional gateways (e.g., Flutterwave for Nigerian cards, Stripe for North American cards) to reduce international interchange fees.
*   **Provider Capability Mapping**: Verifies that the selected adapter supports requested features (e.g., verifying multi-capture capabilities before processing split-shipment orders).
*   **Smart Routing Algorithm**:
    $$\text{Optimal Gateway} = f(\text{Card Country}, \text{Transaction Size}, \text{Gateway Success Rate}, \text{Processor Fees})$$

### 4.4 Resilient Failovers, Circuit Breakers, & Health Sweeps
To protect checkout pipelines from processor outages, the engine implements automated failovers and circuit breakers:

```
                          [GAL CIRCUIT BREAKER PIPELINE]

   Transaction Attempt ──► Adapter Call ──► Success?
                               │
                 ┌─────────────┴─────────────┐
                 ▼                           ▼
                YES                         NO (Increment gateway failure count)
                 │                           │
         Complete Checkout                   ├─► Failures > Threshold?
                                             │   (E.g., 5 failures in 2 mins)
                                             │         │
                                             │         ▼
                                             │   Trip Circuit (Set to OPEN)
                                             │   Route new checkouts to fallback gateway
                                             │
                                             └─► Health check cron poller
                                                 (Tests OPEN gateways every 60s)
                                                 Restores gateway if tests pass
```

*   **Circuit Breakers**: Trips when a gateway's error rate exceeds configured limits (e.g., 5 failed attempts within 2 minutes), immediately routing new transactions to fallback processors.
*   **Automated Health Sweeps**: Background cron tasks run periodic, lightweight ping requests to test offline gateways, restoring them to service once stable.

---

## 5. PAYMENT AUTHORIZATION PIPELINE

The Payment Authorization Pipeline executes validations, fraud checks, and security protocols to verify cardholder accounts and secure transactional funds.

```
                      [PAYMENT AUTHORIZATION WORKFLOW]

   Payment Init ──► Risk Screening ──► Risk Score > Threshold?
                                              │
                       ┌──────────────────────┴──────────────────────┐
                       ▼                                             ▼
                     YES (Reject Order / Flag for Review)            NO
                                                                     │
                                                             Submit 3DS2 Challenge
                                                                     │
                                                             Gateway Auth Hold Approved?
                                                                     │
                                                       ┌─────────────┴─────────────┐
                                                       ▼                           ▼
                                                      YES                          NO (Log failure /
                                                       │                               Release holds)
                                              Freeze Auth hold token
```

### 5.1 Step-by-Step Authorization Flow
1.  **Risk Screening**: The engine passes billing details to the fraud screening service, calculating a risk score based on transaction attributes (e.g., velocity, IP geolocations, card patterns). High-risk transactions are rejected or flagged for manual review.
2.  **SCA and 3D Secure 2.0 (3DS2)**: If required, the engine presents verification challenges (e.g., SMS codes or biometrics) to authenticate cardholders.
3.  **Gateway Submission**: Validated requests are submitted to the gateway adapter, securing authorization tokens and placing temporary holds on the authorized funds.
4.  **Authorization Freeze**: The engine registers the active authorization token and its expiration window, locking payment session details against concurrent edits.

### 5.2 Card Tokenization & PCI Compliance
To ensure security and maintain regulatory compliance, the platform limits the exposure of cardholder data:
*   **Tokenization**: RAW Primary Account Numbers (PANs) and CVVs never touch or reside on JUANET servers.
*   **Secure Frames**: Web browsers submit card details directly to gateways via secure iFrame fields, receiving transient payment tokens used for subsequent server-side processing.

### 5.3 Authorization Validity Windows
*   **Hold Expirations**: Gateways enforce expiration limits on authorized holds (e.g., standard credit cards expire after 7 days, hotel authorizations after 30 days).
*   **Hold Renewals**: For backordered items with extended lead times, the engine runs scheduled tasks to re-authorize holds before they expire, ensuring payment coverage remains intact.
*   **Reversals**: If an order is canceled or rejected prior to capture, the engine immediately releases the authorization hold, returning the locked credit line to the customer.

---

## 6. CAPTURE ENGINE

The **Capture Engine** is responsible for executing settlement instructions against authorized holds, drawing funds from customer accounts and transitioning them to merchant portals.

```
                         [DELAYED & SPLIT CAPTURES]

                   Consolidated Customer Order (ORD-9845)
                         (Authorized amount: $350)
                                    │
               ┌────────────────────┴────────────────────┐
               ▼                                         ▼
         Warehouse Ship A                          Warehouse Ship B
         (Value: $150)                             (Value: $200)
               │                                         │
        Capture $150                              Capture $200
        (Against $350 hold)                       (Final capture on hold)
```

### 6.1 Settlement Workflows
*   **Immediate Capture (Auth-and-Capture)**: Used for digital goods or instant services, authorizing and drawing funds in a single transaction block.
*   **Delayed Capture (Auth-then-Capture)**: Used for physical goods, placing authorizations during checkout and capturing funds only after items leave fulfillment warehouses.

### 6.2 Partial and Split Captures
*   **Multi-Capture Mapping**: When orders are split and shipped across different warehouses, the engine supports partial captures, drawing proportional values as each package ships.
*   **Balance Releases**: Once the final shipment in an order is captured, the engine voids any remaining authorized balances, releasing unused holds back to customer cards.

### 6.3 Capture Limits and Verification Controls
*   **Threshold Violations**: The engine blocks captures exceeding original authorized values to prevent unauthorized fees.
*   **Automated Verification**: Daily reconciliation tasks compare database capture totals against gateway deposit receipts, identifying and flagging discrepancies.

---

## 7. REFUND ENGINE

The **Refund Engine** processes return requests, verifying transaction histories and returning funds to customer cards or bank accounts.

```
                            [REFUND ENGINE FLOW]

   Refund Command ──► Verify Session Status (Must be Settled/Captured)
                                       │
                       ┌───────────────┴───────────────┐
                       ▼                               ▼
            Verify refund limits (Sum of refunds <= Captured total)
                                       │
                       ┌───────────────┴───────────────┐
                       ▼                               ▼
            Allocate refund priorities (Promo ledger / Gateway refund)
                                       │
                       ┌───────────────┴───────────────┐
                       ▼                               ▼
            Submit refund to gateway ──► Outbox financial events written
```

### 7.1 Integrity Rules for Refunds
*   **Settlement Prerequisite**: Refunds are blocked until parent transactions are fully captured and settled at the gateway level.
*   **Accumulation Controls**: The engine prevents aggregate refund totals from exceeding original purchase amounts:
    $$\sum \text{Refunds Applied} \le \text{Total Cash Captured}$$

### 7.2 Proportional Distribution and Priorities
When processing refunds for orders containing mixed payment methods, the engine applies refunds using a strict sequence:
1.  **Gift Card Balance**: First, returned values are credited back to customer gift card balances.
2.  **Gateway Cards**: Remaining refund values are credited to the customer's credit card or banking account.
3.  **Promotions Recalculation**: If a partial return drops order values below original promotion thresholds, the engine recalculates discount totals, deducting promotion deficits from final refund values.

### 7.3 Maker-Checker Review Controls
*   **Exceeding Review Limits**: Refunds exceeding configured threshold limits (e.g., transactions greater than \$1,000) require secondary approvals from finance managers before submission.
*   **Audit Logging**: The engine captures and preserves complete audit trails for every refund attempt, documenting approval actions and administrative reasons.

### 7.4 Vendor Settlement Adjustments (Clawbacks)
*   For marketplace transactions containing multi-vendor items, the Refund Engine calculates clawback entries against vendor accounts.
*   The clawback event triggers deductions from the vendor's pending payout ledger, adjusting payout balances based on original vendor commission rates.

---

## 8. CHARGEBACKS, DISPUTES & RISK MITIGATION

The **Dispute Engine** tracks and manages cardholder disputes, collecting transaction details and helping merchants challenge unauthorized claims.

```
                         [DISPUTE RESOLUTION PIPELINE]

   Dispute Webhook Alert ──► Record dispute reason & deadline
                             └── Set payment state to DISPUTED / Hold funds
                                           │
                                           ▼
                             Assemble Evidence Package
                             ├── Invoice details & Shipping scans
                             └── Customer transaction histories
                                           │
                                           ▼
                             Submit Representment to Issuer
                                           │
                         ┌─────────────────┴─────────────────┐
                         ▼                                   ▼
                   Dispute Won (Release held funds)    Dispute Lost (Process fee chargeback/
                                                       Write ledger entry)
```

### 8.1 Dispute States and Issuer Mappings
*   **Disputed Alert**: Set on notifications from gateway webhooks, immediately logging the case details, dispute reasons, and response deadlines.
*   **Disputed Holds**: The engine flags disputed transactions and holds equivalent balances, protecting merchant accounts during active cases.

### 8.2 Automated Evidence Package Assembly
To assist with disputes, the engine compiles a structured evidence package including:
*   Customer billing profiles and IP geolocations.
*   Carrier shipping receipts, transit milestones, and signature scans.
*   Order invoicing details and customer correspondence logs.

### 8.3 Representment and Resolution Logging
*   **Submission Tracking**: The engine tracks representment submissions to the gateway, updating progress metrics and monitoring case deadlines.
*   **Case Resolutions**: Updates payment histories and releases locked balances upon receiving final decisions (won or lost). If lost, dispute fees and chargeback entries are written to payout logs.

---

## 9. CANONICAL EVENT CONTRACTS

The Payment Orchestration Engine publishes standard event payloads to the `public.marketplace_event_outbox` table, ensuring consistent downstream tracking.

```
                      [TRANSACTIONAL OUTBOX PIPELINE]

   Parent Transaction Commit ──► Write Outbox Payload ──► Message Queue Dispatch
```

### 9.1 `payment.authorized.v1`
*   **Publisher**: Payment Orchestration Service
*   **Consumers**: Orders Bounded Context, Customer Communication Services
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033580",
      "event_type": "payment.authorized.v1",
      "timestamp": "2026-07-01T00:56:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "payment_session_id": "018f63bb-9ab6-7000-8d59-fc5095033581",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "amount": 250.00,
      "currency": "USD",
      "gateway_code": "STRIPE",
      "gateway_transaction_id": "ch_3Mtg6XClGxEdfK1D1f",
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 9.2 `payment.captured.v1`
*   **Publisher**: Payment Orchestration Service
*   **Consumers**: Orders Bounded Context, Finance Ledger Service, Vendor Settlement Context
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033584",
      "event_type": "payment.captured.v1",
      "timestamp": "2026-07-01T00:57:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "payment_session_id": "018f63bb-9ab6-7000-8d59-fc5095033581",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "amount": 250.00,
      "currency": "USD",
      "gateway_code": "STRIPE",
      "gateway_transaction_id": "ch_3Mtg6XClGxEdfK1D1f",
      "vendor_splits": [
        {
          "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095033540",
          "split_amount": 212.50,
          "commission_amount": 37.50
        }
      ],
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 9.3 `payment.refunded.v1`
*   **Publisher**: Payment Orchestration Service
*   **Consumers**: Orders Bounded Context, Finance Ledger Service, Vendor Settlement Context
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033585",
      "event_type": "payment.refunded.v1",
      "timestamp": "2026-07-01T01:05:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "payment_session_id": "018f63bb-9ab6-7000-8d59-fc5095033581",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "refund_amount": 50.00,
      "currency": "USD",
      "gateway_code": "STRIPE",
      "gateway_transaction_id": "re_3Mtg6XClGxEdfK1D1g",
      "vendor_clawbacks": [
        {
          "vendor_id": "018f63bb-9ab6-7000-8d59-fc5095033540",
          "clawback_amount": 42.50,
          "commission_adjustment": 7.50
        }
      ],
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 9.4 `payment.disputed.v1`
*   **Publisher**: Payment Orchestration Service
*   **Consumers**: Finance Ledger Service, Vendor Settlement Context, Fraud Monitoring Services
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095033586",
      "event_type": "payment.disputed.v1",
      "timestamp": "2026-07-01T01:10:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "payment_session_id": "018f63bb-9ab6-7000-8d59-fc5095033581",
      "order_id": "018f63bb-9ab6-7000-8d59-fc5095033523",
      "dispute_amount": 250.00,
      "currency": "USD",
      "dispute_reason": "FRAUDULENT",
      "evidence_deadline": "2026-07-15T23:59:59Z",
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 9.5 Ordering Guarantees, Retries, and Idempotency
*   **Sequence Controls**: Event routing uses correlation and partition keys based on `payment_session_id`, ensuring events are processed in sequential order (e.g., `payment.authorized` is processed before `payment.captured`).
*   **Outbox Retries & Dead-Letter Queues (DLQ)**: Failed message dispatches trigger exponential backoff retries, eventually routing failing messages to a Dead-Letter Queue (DLQ) for administrative review.
*   **Idempotency Verification**: Downstream event consumers track processed event IDs within persistent tables to avoid duplicate actions.

---

## 10. SECURITY, PRIVACY & COMPLIANCE

The Payment Orchestration Engine applies strict security and privacy standards to protect transaction data and maintain regulatory compliance.

### 10.1 PostgreSQL Row-Level Security (RLS)

To isolate tenant and user data, the database applies Row-Level Security (RLS) policies to payment tables:

```sql
-- Enable RLS on primary payment table
ALTER TABLE public.payment_sessions ENABLE ROW LEVEL SECURITY;

-- Tenant Isolation Policy
CREATE POLICY payment_session_tenant_isolation ON public.payment_sessions
    FOR ALL
    USING (organization_id = (SELECT current_setting('app.current_organization_id', true)::uuid))
    WITH CHECK (organization_id = (SELECT current_setting('app.current_organization_id', true)::uuid));

-- Customer Access Policy
CREATE POLICY payment_session_customer_isolation ON public.payment_sessions
    FOR SELECT
    USING (customer_id = (SELECT current_setting('app.current_user_id', true)::uuid));
```

### 10.2 Role-Based Access Control (RBAC) & Maker-Checker

The engine applies strict Role-Based Access Control (RBAC) to control access to payment operations:

*   **Customer**: Permissions are limited to creating payment sessions and completing authentication challenges for their own transactions.
*   **Vendor / Merchant**: Permissions are restricted to viewing capture statuses and tracking merchant-specific payouts.
*   **Marketplace Administrator**: Authorized to view consolidated payment metrics and manage system configurations.
*   **Maker-Checker Controls**: High-risk financial operations (e.g., executing manual refunds or modifying payment routing rules) require secondary approvals from finance managers to complete.

### 10.3 GDPR / CCPA Compliance & PII Masking
*   **PII Encryption**: Customer billing details, postal codes, and email addresses are encrypted at rest using AES-256 keys managed by a hardware security module (HSM).
*   **PII Masking**: Customer details are masked on administrative panels, displaying masked values (e.g., card numbers displayed as `•••• •••• •••• 4242`) to prevent data leaks.
*   **Right to Be Forgotten**: Upon receiving deletion requests, PII records are securely purged or anonymized, leaving transaction values and tax details intact for audit compliance.

---

## 11. PERFORMANCE & HIGH-SCALE SCALABILITY

To support high transaction volumes without performance degradation, the Payment Engine utilizes optimized database patterns and architectures.

### 11.1 CQRS Lite Segregation & Materialized Views
*   **Write Segregation**: Write operations are optimized for high-speed writes, updating payment aggregates using index structures.
*   **Read Materialization**: Analytics dashboards and customer-facing reports query read-optimized materialized views, isolating analytical queries from core transactional tables.

### 11.2 Optimized Database Indexing

```sql
-- Index on order reference to speed up checkout reconciliation
CREATE INDEX idx_payment_sessions_order_id 
ON public.payment_sessions(order_id);

-- Covering index on organization and status to accelerate tenant reports
CREATE INDEX idx_payment_sessions_org_status 
ON public.payment_sessions(organization_id, fsm_status) 
INCLUDE (amount_authorized, amount_captured);

-- Partial index targeting active disputes
CREATE INDEX idx_payment_sessions_active_disputes 
ON public.payment_sessions(fsm_status) 
WHERE fsm_status IN ('Disputed', 'Chargeback');
```

### 11.3 High-Throughput Processing Workers (SKIP LOCKED)
*   **Non-Blocking Settlement Sweeps**: Background tasks process pending settlements using non-blocking queries, avoiding table locks and maintaining high checkout throughput:
    ```sql
    -- Lock and select uncaptured payments for batch processing
    SELECT id, gateway_code, amount_authorized
    FROM public.payment_sessions
    WHERE fsm_status = 'Authorized'
      AND expires_at > CURRENT_TIMESTAMP
    FOR UPDATE SKIP LOCKED
    LIMIT 100;
    ```
*   **Asynchronous Webhook Ingestion**: Gateway webhook notifications write raw payloads directly to ingestion tables, allowing background tasks to process state transformations asynchronously and keep webhooks responsive.

---

## 12. ARCHITECTURE REVIEW BOARD VALIDATION MATRIX

The **Architecture Review Board (ARB)** uses this checklist to verify that payment implementations align with standard system specifications:

| Dimension | Review Criteria | Verification Method |
| :--- | :--- | :--- |
| **FSM Correctness** | All state changes route through the FSM. Direct database updates are blocked. | Review FSM validation rules and interceptor configurations. |
| **Pricing Integrity** | Consolidated capture values do not exceed original authorized totals. | Verify database constraints and run integration tests. |
| **Context Isolation**| Payment tables do not execute direct writes to the `finance.` schema. | Audit database trigger scripts and transaction configurations. |
| **Resiliency & Failover** | Tripped circuit breakers route transactions to active fallback gateways. | Simulate gateway outages and verify routing responses. |
| **Idempotency** | Duplicate checkout requests containing matching idempotency keys return identical payloads. | Run API tests verifying duplicate request handling. |
| **PCI DSS Compliance**| RAW PAN or CVV records are not stored on system servers. | Run automated scans and verify secure iFrame setups. |
| **Row-Level Security** | Row-Level Security policies are active and verified across all payment tables. | Run database access tests across different tenant configurations. |
| **Performance** | Background settlement tasks run non-blocking queries. | Run load tests and verify database lock contention. |
| **Audit Trails** | All payment state shifts write immutable history logs. | Verify write rules and run audit trail checks. |

---

## 13. DEPENDENT SYSTEM CROSS-REFERENCES

The Payment Orchestration Engine is aligned with the following platform specifications:

*   **JUANET Marketplace Physical Tables**: Defines physical storage engines, database standards, and RLS keys.
*   **JUANET Orders & Checkout Engine**: Governs customer purchasing lifecycles and coordinates checkout validation steps.
*   **JUANET Pricing, Discounts & Promotion Engine**: Resolves promotional discounts and coordinates line-item allocations.
*   **JUANET Enterprise Database Blueprint**: Establishes database conventions, PostgreSQL 16 standards, and transaction configurations.
