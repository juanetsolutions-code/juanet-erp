# JUANET Marketplace Reviews, Ratings & Reputation Engine Manual
## Phase 2.3.2H.8 — Customer Reviews, Weighted Ratings, Trust Scoring, Moderation Workflows, and Abuse Prevention

**Document Version:** 2.0  
**Author:** Principal Enterprise Solutions Architect, VP of Trust & Safety Engineering, and Technical Review Board  
**Classification:** Public / Enterprise Specification and Operational Standard  

---

## 1. REPUTATION ARCHITECTURE PHILOSOPHY

The **JUANET Reviews, Ratings & Reputation Engine** acts as the authoritative boundary governing the creation, validation, moderation, and aggregation of user feedback across products, merchants, and regional delivery operations. Positioned as a mission-critical trust engine, it ensures high-integrity marketplace feedback, protecting customer-facing storefronts from review fraud, manipulation, and coordinate review bombing.

```
                  [REVIEWS & REPUTATION BOUNDED CONTEXT ISOLATION]

   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                           CUSTOMER PORTAL & CART SERVICES                       │
   └────────────────────────────────────────┬────────────────────────────────────────┘
                                            │
                             (Emit: order.delivered.v1)
                                            ▼
   ┌─────────────────────────────────────────────────────────────────────────────────┐
   │                      REVIEWS & REPUTATION BOUNDED CONTEXT                       │
   │                                                                                 │
   │  ┌───────────────────────┐         ┌───────────────────┐    ┌────────────────┐  │
   │  │    Review Aggregate   │ ◄─────► │  Moderation/Abuse │ ◄─►│ Reputation     │  │
   │  │    (Root: Review ID)  │         │  AI Profanity /   │    │ Weighting      │  │
   │  │                       │         │  Fraud Filters    │    │ Calculators    │  │
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
   │  │   Product Catalog    │   │  Vendor Operations   │   │    CRM & Support     │ │
   │  │ - Materialized View  │   │ - SLA Adjustments    │   │ - Customer Alerts    │ │
   │  │   Avg Rating Update  │   │ - Tier Suspensions   │   │ - Dispute Tracking   │ │
   │  └──────────────────────┘   └──────────────────────┘   └──────────────────────┘ │
   └─────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Core Principles
To maintain Domain-Driven Design (DDD) isolation and guard database performance, the Reviews & Reputation bounded context enforces the following architectural principles:
*   **Reviews as Immutable Customer Opinions**: Once a review transitions to `Published`, its original text and star allocation are frozen. Updates write new historical snapshots to ensure complete audit trails.
*   **Ratings as Structured Numerical Evaluations**: Ratings are structured integers ($1$ to $5$) stored separately from review text to allow fast, direct database computations.
*   **Reputation as an Aggregated Read Model**: Vendor, store, and product reputation scores are calculated using asynchronous background workers. Results are saved in high-performance query cache tables rather than compiled on the fly.
*   **Review Independence**: Product reviews remain structurally distinct from vendor/store service evaluations. This ensures that physical product quality defects do not directly damage a vendor’s shipping or fulfillment reputation, and vice-versa.
*   **Vendor Neutrality**: Vendors have zero permission to delete, modify, or conceal negative customer reviews directly. All review disputes must execute via formal appeal workflows.
*   **Customer Authenticity**: Verified purchases are validated prior to review acceptance, checking active order transaction logs in the Orders domain asynchronously.
*   **Human-in-the-Loop Moderation**: Automated systems handle profanity checks and toxicity analyses, while final appeal approvals and edge-case moderations require physical review from marketplace Trust & Safety teams.

### 1.2 Absolute Bounded Boundaries: Why Reviews Never Directly Writes
To protect transactional performance and maintain clean architectural separation, the Reviews & Reputation Engine is strictly forbidden from executing direct database writes to the following bounded contexts:
*   **Product Catalog**: The Reviews context never directly modifies product tables. It publishes `rating.updated.v1` events, allowing catalog services to update product ratings asynchronously.
*   **Orders**: Customer order tables are read-only. Reviews can query order states via API endpoints but cannot write checkout data.
*   **Finance**: Financial payouts, commission rates, and payouts remain completely separated from reviews. The system cannot adjust finances, even if vendor ratings drop below target thresholds.
*   **Inventory**: Stock allocations, bin locations, and physical warehouse configurations are completely decoupled.
*   **CRM & Support**: Customer interaction histories are updated asynchronously via outbox events. The reviews engine never directly writes to CRM contact logs.

---

## 2. REVIEW AGGREGATE MODEL

The **Review Aggregate** coordinates and secures all review-related entities. The parent table `public.reviews` manages and coordinates all sub-entities to ensure atomic writes and enforce consistent system rules.

```
                    [REVIEW AGGREGATE ENTITY RELATIONSHIPS]

┌──────────────────────────────────────────────────────────────────────────────┐
│  public.reviews (Aggregate Root)                                             │
│  - id (UUIDv7 Primary Key)                                                   │
│  - organization_id (Tenant Isolation Key)                                    │
│  - target_type (Enum: 'PRODUCT', 'VENDOR', 'STORE')                          │
│  - target_id (Associated entity UUID)                                        │
│  - reviewer_id (Customer UUID)                                               │
│  - rating (Integer 1-5)                                                      │
│  - is_verified_purchase (Boolean)                                            │
│  - moderation_status (Enum FSM State)                                        │
│  - version (Optimistic Lock Version)                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.review_media_attachments (1:N Relationship)                  │   │
│   │  - id, media_type, s3_key_hash, scan_status, file_size_bytes         │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.review_replies (1:N Relationship - Vendor Responses)         │   │
│   │  - id, author_type, author_id, reply_text, is_moderated, created_at  │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.review_moderation_audits (1:N Relationship)                  │   │
│   │  - id, moderator_id, action_taken, rationale_notes, system_risk_score│   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.review_appeals (1:1 Relationship)                            │   │
│   │  - id, requester_id, appeal_reason, resolution_status, resolved_at   │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
│   ┌──────────────────────────────────────────────────────────────────────┐   │
│   │  public.review_helpfulness_votes (1:N Relationship)                  │   │
│   │  - id, voter_id, vote_type (UP/DOWN), client_ip, created_at          │   │
│   └──────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Complete Entity Ownership Dictionary

| Sub-Entity | Physical Table Name | Domain Purpose | Ownership & Mutation Policy |
| :--- | :--- | :--- | :--- |
| **Product Reviews** | `public.reviews` | Parent aggregate record containing reviews target keys, ratings, customer IDs, verified statuses, and states. | **Absolute Root.** Only mutable via FSM transition rules. |
| **Vendor Reviews** | `public.reviews` | Service evaluations analyzing delivery speeds, packaging quality, and communication. | Owned by Root. Handled under target type `'VENDOR'`. |
| **Store Reviews** | `public.reviews` | Storefront reviews assessing general customer experiences, catalog completeness, and pricing. | Owned by Root. Handled under target type `'STORE'`. |
| **Ratings** | Column on `reviews` | Structured values ($1$ to $5$) used in average calculators. | Immutable after review approval. |
| **Media Attachments** | `public.review_media_attachments` | Records mapping uploaded product photos and customer unboxing videos. | Owned by Root. Frozen once media safety checks pass. |
| **Review Images** | Columns on `review_media_attachments` | Customer unboxing images stored securely with encrypted storage paths. | Owned by Root. Must pass virus and content scans. |
| **Review Videos** | Columns on `review_media_attachments` | Customer unboxing videos mapping secure cloud storage links. | Owned by Root. Subject to validation limits. |
| **Review Replies** | `public.review_replies` | Official vendor responses or customer follow-ups displayed under reviews. | Owned by Root. Modifying or deleting replies is audited. |
| **Moderator Notes** | `public.review_moderation_audits` | Rationale notes and system audits written during manual reviews. | Append-only. Modifying or deleting notes is blocked. |
| **Appeals** | `public.review_appeals` | Review appeal cases opened by customers or vendors to contest moderation decisions. | Owned by Root. Only mutable by Trust & Safety teams. |
| **Review Flags** | `public.review_flags` | Tracking flags triggered by customers or automated anomaly detectors. | Owned by Root. Resolved by Trust & Safety teams. |
| **Abuse Reports** | `public.review_abuse_reports` | Detailed abuse logs flagging reviews for fraud, spam, or malicious behavior. | Owned by Root. Log entries are immutable. |
| **Verification Status** | Column: `is_verified_purchase` | Direct indicator verifying if reviews map back to valid customer order invoices. | Set at creation; immutable. |
| **Helpfulness Votes** | `public.review_helpfulness_votes` | Records tracking customer helpfulness voting, used in ranking algorithms. | Owned by Root. Multiple votes from a single user are blocked. |
| **Reputation Scores** | `public.reputation_scores` | Processed read tables tracking aggregate trust rankings for products, vendors, and reviewers. | Managed by background workers. |
| **Review Metadata** | Column: `metadata` on `reviews` | Flexible JSONB container holding browser profiles, languages, and client parameters. | Managed by Root. |
| **Review Audit Records**| `public.review_audit_logs` | Security records capturing change histories, IP addresses, and session tokens. | **Immutable Append-Only.** Updates are blocked. |
| **AI Moderation Results**| Column: `ai_moderation_payload` | Machine learning logs tracking toxicity metrics, PII flags, and spam scores. | Managed by Root. Read-only records. |
| **Sentiment Scores** | Column: `sentiment_score` | Numerical sentiment metrics generated during automated AI reviews. | Managed by Root. Range: $-1.00$ to $+1.00$. |

### 2.2 Aggregate Consistency Rules
*   **One Review Per Purchase**: Customers can write only one review per unique product purchase within a specific order context:
    $$\text{Count}\left( \text{reviews} \text{ where } \text{reviewer\_id} = X \land \text{order\_id} = Y \land \text{target\_id} = Z \right) \le 1$$
*   **Rating Integrity Constraints**: All physical records in `public.reviews` must register integer ratings ranging between $1$ and $5$.
*   **Verified Purchase Check**: The `is_verified_purchase` flag is checked at review creation. It cannot be updated or toggled manually afterwards.
*   **Optimistic Lock Guarding**: The parent table `public.reviews` employs a `version` column to prevent concurrent edits from corrupting review states.

---

## 3. REVIEW SUBMISSION WORKFLOW

The Review Submission Workflow enforces strict data validations, verified purchase checks, and multi-layered moderation filters before reviews are displayed on public storefronts.

```
                            [REVIEW SUBMISSION FLOW]

   Customer Submits Review ──► Validate Order & Review Eligibility
                                              │
                                              ▼
                                   Initialize state: 'Submitted'
                                              │
                                              ▼
                                   Run automated AI filters
                                   ├── PII scan
                                   ├── Toxicity screening
                                   └── Abuse anomaly detection
                                              │
                       ┌──────────────────────┴──────────────────────┐
                       ▼                                             ▼
               Passed (Toxicity <= 0.3)?                     Failed (Toxicity > 0.3)?
                       │                                             │
                       ▼                                             ▼
               Publish Review                             Queue for Human Moderation
```

### 3.1 Step-by-Step Submission Workflow
1.  **Purchase Verification**: The system verifies that the customer has a completed order for the target item within the allowed review eligibility window (e.g., within 180 days of delivery).
2.  **Review Creation**: The customer submits a rating, review text, and optional media attachments, creating a record in the `Submitted` state.
3.  **Media Upload & Malware Scan**: Uploaded media is routed to isolated storage bins. Background tasks run virus scans and content checks before files are approved.
4.  **Automated AI Screening**: Machine learning tools run basic validation checks:
    *   *Toxicity Analysis*: Flags reviews containing profanity or threatening language.
    *   *PII Redaction*: Automatically flags or redacts social security numbers, emails, phone numbers, or credit card details.
    *   *Spam Checks*: Flags duplicate text patterns or rapid submission velocities.
5.  **Moderation Queue Routing**:
    *   *Auto-Publish*: Reviews passing all automated checks are set to `Published`.
    *   *Manual Review Queue*: Reviews flagging safety thresholds are set to `Pending Moderation` and routed to Trust & Safety team queues.
6.  **Human Moderation & Maker-Checker Reviews**: Designated operators review flagged content. High-impact decisions (e.g., blocking top-tier sellers) require dual-auth checks (Maker-Checker).
7.  **Sovereign Appeal Workflows**: If a review is rejected, customers or vendors can open formal appeal cases, prompting secondary reviews by senior managers.

---

## 4. REVIEW LIFECYCLE FINITE STATE MACHINE (FSM)

The review lifecycle is managed by a strict Finite State Machine (FSM) to enforce valid transitions and preserve complete audit trails.

```
                        [REVIEW LIFECYCLE STATE MACHINE]

                            ┌──────────────────────┐
                            │        Draft         │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │      Submitted       │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │  Pending Moderation  │
                            └──────────┬───────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │      AI Review       │
                            └──────────┬───────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │     Human Review     │       │   Rejected   │
                            └──────────┬───────────┘       └──────┬───────┘
                                       │                          │
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │       Approved       │       │   Appealed   │
                            └──────────┬───────────┘       └──────┬───────┘
                                       │                          │
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │      Published       │◄──────┤   Restored   │
                            └──────────┬───────────┘       └──────────────┘
                                       ├──────────────────────────┐
                                       ▼                          ▼
                            ┌──────────────────────┐       ┌──────────────┐
                            │       Flagged        │       │    Hidden    │
                            └──────────┬───────────┘       └──────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │       Archived       │◄───────────────────────┐
                            └──────────┬───────────┘                        │
                                       │                                    │
                                       ▼                                    │
                            ┌──────────────────────┐                        │
                            │       Deleted        ├────────────────────────┘
                            └──────────────────────┘
```

### 4.1 State Definitions and Transition Rules

#### 4.1.1 State Dictionary
1.  **Draft**: Review drafted by customer but not yet submitted to the moderation pipeline.
2.  **Submitted**: Review submitted; awaiting automated validation tasks.
3.  **Pending Moderation**: Blocked from public view; awaiting manual validation queue review.
4.  **AI Review**: Machine learning models actively screening reviews for safety, toxicity, and PII.
5.  **Human Review**: Routed to Trust & Safety queues; awaiting manual review by a moderator.
6.  **Approved**: Checked and approved by moderation staff, preparing for public listing.
7.  **Published**: Review is live and visible on public product or store pages.
8.  **Flagged**: Highlighted by customers or automated scanners for potential abuse; remains visible during active audits.
9.  **Hidden**: Suspended from public view due to unresolved abuse flags or complaints.
10. **Rejected**: Content violated platform terms. The review is blocked from public view.
11. **Appealed**: Appeal opened by customer or merchant to contest rejection.
12. **Restored**: Review restored to active status after passing appeal reviews.
13. **Archived**: Archived historical reviews preserved for audit and dispute resolution.
14. **Deleted**: Masked review data preserved strictly to meet system audit requirements.

#### 4.1.2 Transition Matrix

| Current State | Target State | Triggering Mechanism | Validation Constraints & System Rules |
| :--- | :--- | :--- | :--- |
| **Draft** | **Submitted** | Customer Submit | Verifies purchase eligibility; checks character counts. |
| **Submitted** | **AI Review** | System Scheduler | Automatically dispatches content checking tasks. |
| **AI Review** | **Published** | Safety Check Pass | Auto-publishes reviews when toxicity scores are low. |
| **AI Review** | **Pending Moderation**| Toxicity Flag | Flags reviews exceeding toxicity or PII thresholds. |
| **Pending Moderation**| **Human Review** | Moderator Claims | Allocates flagged reviews to active moderator dashboards. |
| **Human Review** | **Approved** | Moderator Pass | Validates moderator decision flags and rationale notes. |
| **Human Review** | **Rejected** | Moderator Block | Requires detailed rejection reasons and audit logs. |
| **Approved** | **Published** | Publisher Run | Re-calculates and caches average star ratings. |
| **Published** | **Flagged** | Customer Complaint | Exposing flags does not hide reviews during active reviews. |
| **Flagged** | **Hidden** | Multi-Flag Trigger | Automatically hides reviews when safety flags exceed limits. |
| **Rejected** | **Appealed** | Appeal Created | Customers must appeal within 30 days of rejection. |
| **Appealed** | **Restored** | Senior Review Pass | Restores original reviews and recalculates ratings. |

---

## 5. RATING ENGINE

The **Rating Engine** calculates aggregated star ratings. To protect system performance, it avoids processing raw table calculations during active customer requests.

```
                           [RATING CACHING ENGINE]

   Customer Writes Review ──► Re-Calculate Average Star Rating
                                             │
                                             ▼
                                  Update Materialized Views
                                             │
                                             ▼
                                   Sync to Redis Caches
                                             │
                                             ▼
                                 Display on Buyer Storefront
```

### 5.1 Mathematical Calculations & Formulas

To prevent ratings from being skewed by small sample sizes, the system employs **Bayesian Averaging** alongside standard arithmetic averages:

#### 5.1.1 Weighted Bayesian Average Formula
$$\text{Bayesian Average} = \frac{C \cdot m + \sum_{i=1}^{N} r_i}{C + N}$$

Where:
*   $N$: Total number of reviews for the product or vendor.
*   $r_i$: Individual ratings ($1$ to $5$).
*   $m$: Prior mean (the default average rating across the entire marketplace, e.g., $3.5$).
*   $C$: Prior weight (the confidence coefficient representing the minimum review threshold, e.g., $10$ reviews).

#### 5.1.2 Time-Decayed Weighted Average
To reflect changes in product or merchant quality over time, the engine applies a **Time-Decayed Weighting** model:

$$\text{Decayed Rating} = \frac{\sum_{i=1}^{N} r_i \cdot e^{-\lambda \cdot t_i}}{\sum_{i=1}^{N} e^{-\lambda \cdot t_i}}$$

Where:
*   $t_i$: Age of the review in days.
*   $\lambda$: Decay constant, calculated from the target half-life ($H$ in days):
    $$\lambda = \frac{\ln(2)}{H}$$
*   *Verified Purchase Bonus*: Reviews with `is_verified_purchase = true` are assigned a $1.2\times$ multiplier in overall score calculations.

---

## 6. REVIEW MODERATION ENGINE

The **Moderation Engine** screens reviews for content violations, managing queues and routing tasks to the Trust & Safety team.

```
                           [MODERATION ENGINE QUEUES]

   Incoming Flagged Review ──► Categorize Infraction
                                      │
              ┌───────────────────────┼───────────────────────┐
              ▼                       ▼                       ▼
      Queue 1: Profanity      Queue 2: Suspicious IP     Queue 3: Appeals
              │                       │                       │
      Moderator Review        Fraud Team Audit        Senior Team Audit
```

### 6.1 Content Rules & Policies
*   **Profanity & Toxicity**: Automated regex libraries and sentiment analysis tools flag obscene language and personal attacks.
*   **PII Screening**: Scans review text for addresses, phone numbers, email keys, or banking details to prevent data leaks.
*   **Maker-Checker Controls**: Moderators can approve or reject reviews. If a moderator flags a vendor-owned storefront for review fraud, a senior compliance manager must confirm the decision (Maker-Checker).

---

## 7. ABUSE PREVENTION ENGINE

The **Abuse Prevention Engine** protects the platform from coordinated review manipulation, spam attacks, and fake feedback.

```
                           [FRAUD SCREENING ENGINE]

   Review Submission ──► Check Submission Velocity (IP / Device)
                                      │
                                      ▼
                           Compare IP Geolocations
                                      │
                                      ▼
                           Check Employee / Self-Review Matches
                                      │
                                      ▼
                          Update Anomaly Score Index
```

### 7.1 Security & Fraud Detection
*   **IP & Fingerprint Tracking**: Monitors IP subnets, device footprints, and submission locations to detect botnets and coordinated review bombing.
*   **Submission Velocity Thresholds**: Sets submission limits per IP and device (e.g., maximum 3 reviews per minute) to prevent spam.
*   **Self-Review & Affiliate Checks**: Compares reviewer accounts with vendor registration details and employee logs to block self-reviews.
*   **Anomaly Risk Calculations**: Assigns risk scores based on submission patterns, routing reviews to manual queues when scores exceed safety thresholds.

---

## 8. VERIFIED PURCHASE ENGINE

The **Verified Purchase Engine** verifies that reviews map back to valid customer order invoices, preventing fake feedback.

```
                         [VERIFIED PURCHASE ENGINE]

   Review Request ──► Query Orders database (Asynchronous API)
                                 │
                   ┌─────────────┴─────────────┐
                   ▼                           ▼
         Completed Order Found?        No Order / Cancelled?
                   │                           │
          Toggle: verified_purchase    Clear verified_purchase flag
```

### 8.1 Validation Rules
*   **Order Validation**: Cross-references reviewer IDs and product IDs with completed order records in the Orders Bounded Context.
*   **Cancellations & Refunds**: If an order is canceled or fully refunded prior to review submission, the `is_verified_purchase` flag is disabled.
*   **One Review Per Purchase**: Restricts customers to one review per unique product purchase to prevent rating manipulation.

---

## 9. HELPFULNESS & COMMUNITY VOTING

The system enables customers to rate the helpfulness of reviews, using these metrics to optimize review sorting and placement.

```
                           [VOTE PROCESSING PIPELINE]

   Customer Votes ──► Validate Vote Uniqueness (User ID + Review ID)
                                    │
                                    ▼
                         Update helpfulness scores
                                    │
                                    ▼
                         Refresh sorting ranking index
```

### 9.1 Sorting & Ranking Algorithms

To prevent review sorting from being skewed by a small number of votes, the system applies **Wilson Score Intervals** to calculate helpfulness rankings:

$$\text{Score} = \frac{\hat{p} + \frac{z^2}{2n} - z \cdot \sqrt{\frac{\hat{p}(1-\hat{p})}{n} + \frac{z^2}{4n^2}}}{1 + \frac{z^2}{n}}$$

Where:
*   $n$: Total votes (Helpful + Not Helpful).
*   $\hat{p}$: Proportion of helpful votes:
    $$\hat{p} = \frac{\text{Helpful Votes}}{n}$$
*   $z$: $1.96$ (representing a $95\%$ confidence level).

---

## 10. VENDOR RESPONSES

The system enables vendors to reply to customer reviews, helping them resolve issues and communicate with buyers.

```
                           [VENDOR REPLY WORKFLOW]

   Vendor Submits Reply ──► Validate Permissions & Roles
                                      │
                                      ▼
                           Run automated safety filters
                                      │
                                      ▼
                           Publish Reply & Alert Customer
```

### 10.1 Interaction Rules
*   **Role Constraints**: Only authorized merchant staff (e.g., Owner, Customer Support) can reply to reviews on behalf of the vendor.
*   **Moderation & Safety Filters**: Vendor replies must pass profanity, PII, and toxicity checks before publication.
*   **Public Thread Integrity**: Vendors can submit only one reply per review, and replies cannot be deleted without leaving an audit trail.

---

## 11. REPUTATION ENGINE

The **Reputation Engine** calculates overall trust scores for vendors, stores, products, and reviewers.

```
                         [REPUTATION PROCESSOR ENGINE]

   Read Review Ratings ──► Fetch Seller SLA Violations ──► Calculate Trust Score
                                                                    │
                                                                    ▼
                                                         Update Trust Badges
```

### 11.1 Mathematical Formula for Vendor Reputation

The system evaluates vendor reputation based on ratings, SLA performance, and customer resolutions:

$$\text{Reputation Score} = w_1 \cdot \text{Bayesian Average} + w_2 \cdot \left(1 - \text{Complaint Ratio}\right) + w_3 \cdot \text{Resolution Ratio} - \text{Decay Surcharges}$$

Where:
*   $w_1, w_2, w_3$: Standard weight factors summing to $1.0$.
*   $\text{Complaint Ratio}$: Number of support disputes compared to total orders.
*   $\text{Resolution Ratio}$: Percentage of disputes successfully resolved by the vendor.
*   $\text{Decay Surcharges}$: Surcharges applied for recent unexcused SLA violations or compliance warnings.

---

## 12. AI ADVISORY INTEGRATION

The platform integrates AI capabilities to analyze customer feedback and provide insights, operating strictly as an advisory system.

```
                            [AI ADVISORY WORKFLOW]

   Read Customer Reviews ──► Extract Topics & Sentiment ──► Compile Insights
                                                                   │
                                                                   ▼
                                                         Suggest Product Updates
```

### 12.1 Analytical Scenarios
*   **Sentiment & Toxicity Analysis**: Flags reviews containing high toxicity or personal attacks for manual moderation.
*   **Trend & Topic Clustering**: Groups reviews to identify common themes (e.g., "broken zipper" or "fast delivery").
*   **Review Summarization**: Generates summaries of product reviews to help customers understand common feedback.
*   **Fraud Detection Recommendations**: Flags suspicious review patterns, suggesting reviews for Trust & Safety review.

---

## 13. ANALYTICS & DASHBOARDS

The analytics platform compiles reviews and ratings data to provide stakeholders with actionable insights.

```
                        [ANALYTICS COMPILATION ENGINE]

   FSM State Changes ──► Run Aggregator Workers ──► Refresh Dashboard Views
```

### 13.1 User Role Dashboards
*   **Trust & Safety Moderators**: Track queue sizes, backlog aging, and review accuracy.
*   **Vendor Managers**: Monitor average ratings, customer sentiment, and reply times.
*   **Executive Leadership**: Analyze marketplace-wide satisfaction ratings, fraud rates, and trust metrics.

---

## 14. CANONICAL EVENT CONTRACTS

The Reviews & Reputation Bounded Context writes standard event payloads to the `public.marketplace_event_outbox` table, ensuring consistent downstream tracking.

```
                      [TRANSACTIONAL OUTBOX PIPELINE]

   Parent Transaction Commit ──► Write Outbox Payload ──► Message Queue Dispatch
```

### 14.1 `review.created.v1`
*   **Publisher**: Reviews & Reputation Service
*   **Consumers**: Outbox Processing Engines, Trust & Safety Alerts
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095035000",
      "event_type": "review.created.v1",
      "timestamp": "2026-07-01T01:14:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "review_id": "018f63bb-9ab6-7000-8d59-fc5095035001",
      "target_type": "PRODUCT",
      "target_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
      "reviewer_id": "018f63bb-9ab6-7000-8d59-fc5095035010",
      "rating": 5,
      "is_verified_purchase": true,
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 14.2 `review.published.v1`
*   **Publisher**: Reviews & Reputation Service
*   **Consumers**: Product Catalog, Vendor Analytics Service, CRM & Notification Service
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095035002",
      "event_type": "review.published.v1",
      "timestamp": "2026-07-01T01:16:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "review_id": "018f63bb-9ab6-7000-8d59-fc5095035001",
      "target_type": "PRODUCT",
      "target_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
      "rating": 5,
      "correlation_id": "corr_018f63bb-9ab6-7000-8d59-fc5095033582",
      "trace_id": "trace_018f63bb-9ab6-7000-8d59-fc5095033583"
    }
    ```

### 14.3 `rating.updated.v1`
*   **Publisher**: Reviews & Reputation Service
*   **Consumers**: Product Catalog Bounded Context, search indexing engines
*   **Payload Schema**:
    ```json
    {
      "event_id": "018f63bb-9ab6-7000-8d59-fc5095035003",
      "event_type": "rating.updated.v1",
      "timestamp": "2026-07-01T01:18:00Z",
      "organization_id": "018f63bb-9ab6-7000-8d59-fc5095033522",
      "target_type": "PRODUCT",
      "target_id": "018f63bb-9ab6-7000-8d59-fc5095033525",
      "total_reviews_count": 142,
      "simple_average_rating": 4.62,
      "bayesian_average_rating": 4.45,
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
ALTER TABLE public.reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.review_replies ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.review_appeals ENABLE ROW LEVEL SECURITY;

-- Apply Select & Update RLS Security Policies
CREATE POLICY reviews_tenant_isolation ON public.reviews
  FOR ALL
  USING (organization_id = CURRENT_SETTING('app.current_organization_id', true));

CREATE POLICY replies_tenant_isolation ON public.review_replies
  FOR ALL
  USING (organization_id = CURRENT_SETTING('app.current_organization_id', true));
```

### 15.2 Data Privacy & Compliance
*   **PII Masking**: Automatically masks personal data (e.g., customer names or contact details) on public pages to comply with GDPR and CCPA requirements.
*   **Data Retention**: Deletes or anonymizes review records after specified retention periods upon user request, while preserving aggregated statistical metrics.

---

## 16. PERFORMANCE & SCALABILITY

To support high-volume review ingestion and real-time rating queries, the platform employs optimized database indexes and caching layers.

### 16.1 SQL Query Optimization & Indexing

```sql
-- Composite index for fast product and merchant rating queries
CREATE INDEX CONCURRENTLY idx_reviews_target_lookup
ON public.reviews (target_type, target_id)
WHERE moderation_status = 'Published';

-- Partial index for manual moderation queues
CREATE INDEX CONCURRENTLY idx_reviews_moderation_queue
ON public.reviews (moderation_status, created_at)
WHERE moderation_status IN ('Submitted', 'Pending Moderation');

-- Trigram index for full-text search and profanity scanning
CREATE INDEX CONCURRENTLY idx_reviews_text_trgm
ON public.reviews USING gin (review_text gin_trgm_ops);
```

### 16.2 Caching Strategies
*   **Redis Cache**: Caches compiled rating summaries and reviews text. Rating updates invalidate corresponding cache keys automatically.
*   **Materialized Views**: Aggregated ratings are processed using materialized views, which are refreshed incrementally every 10 minutes to protect database performance.

---

## 17. VALIDATION MATRIX

The Architecture Review Board (ARB) validates the reviews and reputation engine against key operational criteria:

### 17.1 ARB Validation Checklist

| Target Domain | Verification Test | Pass Criteria | Status |
| :--- | :--- | :--- | :--- |
| **Verified Purchase**| Purchase Eligibility Validation | Verifies that reviewers have a completed order invoice for the target item; blocks unverified submissions when verified reviews are required. | [PASS] |
| **FSM Transitions** | Review Lifecycle State Changes | Reviews move strictly through permitted states; unauthorized transitions are blocked and logged. | [PASS] |
| **Rating Calculation**| Weighted Bayesian Calculation | Bayesian ratings are calculated accurately and match reference values across varying sample sizes. | [PASS] |
| **Abuse Prevention** | Coordinated Review Protection | Velocity limits and IP monitoring tools successfully block simulated spam attacks and fake reviews. | [PASS] |
| **Reputation System** | Vendor Trust Scoring | SLA violations and dispute resolutions correctly update vendor trust scores and badges. | [PASS] |
| **Multi-Tenancy** | RLS Policy Enforcement | Multi-tenant RLS filters successfully restrict vendor data access to authorized organization IDs. | [PASS] |
| **Performance** | Rating Summaries Caching | Database read times remain within SLA targets (<50ms) under heavy simulated traffic loads. | [PASS] |

---

## 18. CONCLUDING ARCHITECTURAL CONFIRMATION

The **JUANET Reviews, Ratings & Reputation Engine** is architected to protect customer experiences and maintain marketplace integrity. By decoupling reviews data from transactional contexts, enforcing strict validation workflows, and utilizing weighted rating algorithms, the platform delivers a secure, scalable, and high-performance reputation engine. All core designs are fully aligned with PostgreSQL 16 standards and multi-tenant SaaS security policies.
