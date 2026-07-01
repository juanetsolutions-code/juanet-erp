# JUANET Customer Satisfaction, QA & Feedback Engine Specification
## Phase 2.3.2F.5 — Customer Satisfaction and Quality Assurance
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & Quality Architecture  

---

## 1. CUSTOMER SATISFACTION PHILOSOPHY

The JUANET Support Platform operates on the principle that customer feedback is a vital operational dataset, but one that must remain strictly decoupled from the core workflow history. This separation protects system integrity while enabling a continuous improvement lifecycle.

```
       OPERATIONAL REALM                             EVALUATION REALM
  +-------------------------+                   +-------------------------+
  |  public.tickets (SoR)   |                   |  public.ticket_surveys  |
  |  - Workflows & States   | ──(Resolution)──> |  - CSAT / NPS / CES     |
  |  - SLA Achievements     |                   |  - Client Experience    |
  |  - Immutable History    |                   +-------------------------+
  +-------------------------+                                │
                                                             ▼
                                                +-------------------------+
                                                |  public.quality_reviews |
                                                |  - Scorecards & Rubrics |
                                                |  - Agent Coaching Logs  |
                                                +-------------------------+
```

### 1.1 Core Conceptual Distinctions
*   **Customer Experience (CX)**: The cognitive and emotional perception of the customer throughout their support journey. This is evaluated using qualitative metrics (such as survey ratings, sentiment indices, and text comments).
*   **Service Quality (SQ)**: The objective operational standard achieved by the support team, measured against platforms rules (such as SLA compliance, average response time, accuracy of documentation, and alignment with corporate policies).
*   **Customer Satisfaction (CSAT)**: A transactional metric evaluating a customer's happiness with a specific interaction or resolution.
*   **Continuous Improvement**: The feedback loop that turns raw survey responses and internal quality reviews into actionable coaching plans and system updates.
*   **Closed Feedback Loop**: A mechanism ensuring that any negative customer score triggers an automatic operational workflow, prompting a team lead to review, contact the client, and resolve the underlying issue.

### 1.2 Separation of Feedback and Core Ticket History
To protect transactional data integrity, **customer feedback must never directly alter a ticket's operational history**. This rule is enforced for several reasons:
1.  **Immutability of Audit Trails**: Tickets are legal records of security compliance, SLA calculations, and technical modifications. Modifying a completed ticket's history based on subjective customer opinions would violate financial and regulatory audit standards.
2.  **SLA Accuracy**: SLA achievements must remain objective, server-calculated realities. If a frustrated customer leaves a low CSAT score, this must not alter the objective fact that the first response was delivered within the configured 15-minute SLA target.
3.  **Conflict Separation**: Decoupling feedback allows the system to analyze gaps between subjective customer opinions and objective operational metrics (e.g., highlighting cases where an agent met all SLA deadlines but received a poor customer rating due to a rude tone).

---

## 2. FEEDBACK ARCHITECTURE & DATA FLOW

The feedback and quality assurance lifecycle operates as a continuous, unidirectional workflow that connects customer sentiment with internal coaching and performance reporting:

```
[Ticket Resolved]
       │
       ▼
[Survey Scheduled & Sent]
       │
       ▼
[Customer Response Ingested]
       │
       ▼
[AI Advisory Analysis] ─────────> [Closed-Loop Alert on Low Scores]
       │
       ▼
[QA Sampling (Random/Rule-Based)]
       │
       ▼
[QA Scorecard Review completed]
       │
       ▼
[Coaching Session Assigned]
       │
       ▼
[Agent Performance Metrics Update]
       │
       ▼
[Continuous Service Improvement]
```

1.  **Resolution Trigger**: A ticket enters the `resolved` state, triggering the survey scheduler.
2.  **Survey Dispatch**: A customized survey link is generated and sent to the customer based on their preferred communication channel.
3.  **Response Ingestion**: The customer's response is validated, parsed, and logged securely, keeping PII data masked.
4.  **AI Analysis**: The AI engine runs sentiment scoring, extracts frustration indicators, and detects key topics. Low scores trigger alerts to the customer success team.
5.  **Quality Sampling**: Tickets are selected for QA reviews based on defined sampling rules (e.g., random draws, high-value accounts, or escalated tickets).
6.  **Scorecard Evaluation**: Quality reviewers grade selected tickets against weighted rubrics (e.g., empathy, technical accuracy, policy compliance).
7.  **Coaching Loop**: QA scores feed directly into the coaching engine, automatically scheduling coaching sessions for agents with low scores.
8.  **Metrics Update**: Aggregated survey ratings and QA review scores are compiled into performance dashboards.

---

## 3. CUSTOMER SURVEY ENGINE

The JUANET Survey Engine is highly configurable, allowing organizations to collect customer feedback across multiple metrics and channels.

### 3.1 Supported Metric Formats
*   **Customer Satisfaction (CSAT)**: Measures satisfaction with a specific resolution on a 1-to-5 scale:
    $$\text{CSAT \%} = \left( \frac{\text{Responses with 4 or 5 Rating}}{\text{Total Responses}} \right) \times 100$$
*   **Net Promoter Score (NPS)**: Measures long-term brand loyalty on a 0-to-10 scale, classifying respondents into Promoters (9-10), Passives (7-8), and Detractors (0-6):
    $$\text{NPS} = \% \text{ Promoters} - \% \text{ Detractors}$$
*   **Customer Effort Score (CES)**: Evaluates the ease of resolving an issue on a 1-to-7 scale (strongly agree to strongly disagree), helping identify bottlenecks in the support process.
*   **Custom Multi-Question Surveys**: Administrators can define custom forms containing multiple questions with conditional logic (e.g., "If score is below 3, display a required text box asking for details").

### 3.2 Ingestion & Access Standards
*   **Anonymous Feedback**: Supports anonymous survey links where the respondent's user ID and ticket details are masked in the database to encourage honest feedback.
*   **Authenticated Feedback**: Portal-based surveys require active JWT sessions to link feedback directly to customer and organization records.
*   **Expiration Policies**: Surveys expire automatically 7 days after dispatch. Responses submitted after this window are rejected.
*   **One Response Per Ticket Guard**: A unique database constraint on `(organization_id, ticket_id)` prevents users from submitting multiple survey responses for the same ticket.
*   **Automated Reminders**: If a survey remains uncompleted after 48 hours, the system can dispatch a single polite reminder notification before expiration.

---

## 4. SURVEY LIFECYCLE FINITE STATE MACHINE

Survey processes transition through a controlled state machine to manage delivery, response collection, and expiration:

```
 [Draft] ──> [Scheduled] ──> [Sent] ──> [Delivered] ──> [Opened] ──> [Completed]
                                │                                         ▲
                                └────────────────> [Expired] ─────────────┘
```

### 4.1 State Definitions and Transition Rules

#### 4.1.1 `draft`
*   **Purpose**: The survey template or dispatch ruleset is being configured.
*   **Allowed Entry Transitions**: None (origin state).
*   **Allowed Exit Transitions**: `scheduled`, `archived`.
*   **Forbidden Transitions**: Cannot transition directly to `sent` or `completed`.

#### 4.1.2 `scheduled`
*   **Purpose**: A ticket enters the resolved state, and a survey link is scheduled for dispatch after a configured cooldown delay (e.g., 2 hours).
*   **Allowed Entry Transitions**: `draft`.
*   **Allowed Exit Transitions**: `sent`, `cancelled`.

#### 4.1.3 `sent`
*   **Purpose**: The survey notification has been dispatched to the delivery channel (Email, SMS, or WhatsApp).
*   **Allowed Entry Transitions**: `scheduled`.
*   **Allowed Exit Transitions**: `delivered`, `failed`, `expired`.

#### 4.1.4 `delivered`
*   **Purpose**: Confirmation has been received from the communication provider that the notification was delivered.
*   **Allowed Entry Transitions**: `sent`.
*   **Allowed Exit Transitions**: `opened`, `expired`.

#### 4.1.5 `opened`
*   **Purpose**: The customer has clicked the link and loaded the survey form in their browser.
*   **Allowed Entry Transitions**: `delivered`, `sent` (fallback if provider receipts are missing).
*   **Allowed Exit Transitions**: `completed`, `expired`.

#### 4.1.6 `completed`
*   **Purpose**: The user has successfully submitted the survey form.
*   **Allowed Entry Transitions**: `opened`, `delivered`.
*   **Allowed Exit Transitions**: None (terminal state).
*   **Action**: Updates operational metrics and dispatches events.

#### 4.1.7 `expired`
*   **Purpose**: The survey link has passed its expiration window (e.g., 7 days) without submission.
*   **Allowed Entry Transitions**: `sent`, `delivered`, `opened`.
*   **Allowed Exit Transitions**: None.

---

## 5. QUALITY ASSURANCE REVIEW ENGINE

The Quality Assurance Review Engine enables team leaders, peer reviewers, and QA analysts to evaluate completed support interactions.

### 5.1 QA Case Selection & Sampling Models

```
                                  QA INGESTION QUEUE
                                           │
         ┌─────────────────────────────────┼─────────────────────────────────┐
         ▼                                 ▼                                 ▼
+------------------+             +------------------+             +------------------+
|  Random Sampling |             |   Risk-Based     |             |   Escalation     |
|  - 2% of volume  |             |  - Big Spend CS  |             |  - SLA Breaches  |
|  - Representative|             |  - PII exposure  |             |  - Low CSAT      |
+------------------+             +------------------+             +------------------+
```

To ensure evaluation integrity, the system selects tickets for QA review using several sampling models:

1.  **Random Representative Sampling**: Selects a configurable percentage (e.g., 2%) of completed tickets per agent every week, providing a baseline score for performance reviews.
2.  **Risk-Based Selection**: Automatically flags and prioritizes tickets for QA reviews based on business impact:
    *   *VIP Clients*: Tickets belonging to high-tier or premium corporate accounts.
    *   *PII Exposure Warnings*: Tickets flagged with potential compliance alerts or privacy overrides.
3.  **Trigger-Based Selection**: Automatically adds tickets to the QA queue when specific events occur:
    *   *SLA Breaches*: Any ticket that missed its first response or resolution SLA.
    *   *Low Customer Feedback*: Any ticket returning a CSAT score of 2 or below.
    *   *Customer Complaints*: Sentiment analysis identifying expressions of customer frustration.

### 5.2 Review Formats & Collaboration
*   **Supervisor Reviews**: Standard evaluations conducted by designated QA managers or team leads.
*   **Peer Reviews**: Enables mutual learning by allowing agents to review each other's tickets anonymously.
*   **Calibration Reviews**: Multiple QA analysts evaluate the same ticket independently, helping align scoring standards across the management team.

---

## 6. QUALITY SCORECARD ENGINE

Scorecards define the criteria used to evaluate support quality, allowing teams to construct balanced scorecards with weighted question categories.

### 6.1 Scorecard Structure & Evaluation Rubric

| Rubric Dimension | Max Points | Evaluation Objectives | Automatic Failure? |
| :--- | :---: | :--- | :---: |
| **Greeting & Professionalism** | 10 | Used correct tone, addressed the customer by name, and followed brand standards. | No |
| **Empathy & Active Listening**| 15 | Acknowledged customer frustration, matched their pace, and showed genuine understanding. | No |
| **Communication Quality** | 15 | Used clear language, formatted responses with clean layouts, and avoided technical jargon. | No |
| **Technical Accuracy** | 25 | Provided the correct solution, followed diagnostic protocols, and avoided guessing. | **YES** |
| **Policy Compliance** | 15 | Followed security processes, verified account ownership, and protected customer privacy. | **YES** |
| **Documentation Standards** | 10 | Logged clear internal notes, updated metadata, and maintained accurate ticket summaries. | No |
| **Knowledge Base Usage** | 10 | Linked relevant knowledge articles to the ticket or flagged gaps for new documentation. | No |

### 6.2 Score Calculation Mechanics
*   **Weighted Scoring**: The total QA score is calculated as a weighted percentage:
    $$\text{QA Score \%} = \left( \frac{\text{Points Awarded}}{\text{Points Attempted}} \right) \times 100$$
*   **Passing Threshold**: Standard operational policies require a passing QA score of **85.00%**.
*   **Automatic Failure Guard**: Critical dimensions (e.g., security compliance breaches or sharing incorrect technical configurations) are flagged as critical failures. Scoring a failure in these categories automatically drops the entire scorecard to a **0.00%** score, overriding other points awarded, and immediately alerts the team lead.

---

## 7. COACHING WORKFLOW & PERFORMANCE RECOVERY

When QA scores identify areas for improvement, the coaching engine manages the training and development process:

```
[QA Score < Threshold] ──> [Assign Coaching] ──> [Agent Ack] ──> [Coaching Session] ──> [Verify Outcomes]
```

1.  **Automatic Session Assignment**: If an agent's weekly QA score falls below the 85% threshold, the system automatically schedules a coaching assignment.
2.  **Agent Acknowledgment**: The agent receives a notification containing their scored ticket, reviewed criteria, and reviewer notes, requiring acknowledgment in the portal.
3.  **Coaching Session**: The supervisor and agent hold a one-on-one review session. They discuss performance gaps, review model ticket examples, and log a training summary.
4.  **Improvement Plans**: The supervisor outlines an improvement plan with targeted goals (e.g., "Complete empathy training module" or "Verify database configurations with senior engineers before replying").
5.  **Follow-Up Audits**: The system places the agent's next 5 completed tickets into a high-priority QA queue to verify that coaching insights have been applied successfully.
6.  **Performance Recovery**: If the agent meets passing thresholds in subsequent reviews, the coaching log is resolved as successful.
    *   *Escalation Path*: If performance does not improve after 3 coaching sessions, the system alerts the department director for advanced review.

---

## 8. AGENT PERFORMANCE METRICS

To compile a holistic view of agent capability, the system aggregates multiple operational and qualitative metrics into a single profile:

### 8.1 Key Performance Indicators (KPIs)

#### 8.1.1 First Contact Resolution (FCR)
Calculates the percentage of tickets resolved with a single customer interaction (no customer follow-ups required):
$$\text{FCR \%} = \left( \frac{\text{Tickets Resolved with 1 Agent Message}}{\text{Total Resolved Tickets}} \right) \times 100$$

#### 8.1.2 Productivity Index
Computes an efficiency rating by comparing the agent's completed tickets and average handle times against team baselines:
$$\text{Productivity Index} = \left( \frac{\text{Agent Completed Tickets}}{\text{Team Average Completed Tickets}} \right) \times 100$$

#### 8.1.3 Consolidated Agent Scorecard
The agent's overall score is calculated as a balanced index combining multiple metrics:
*   *Average QA Score*: Weighted at **40%**.
*   *Average CSAT Rating*: Weighted at **30%**.
*   *SLA Compliance Rate*: Weighted at **20%**.
*   *FCR Rate*: Weighted at **10%**.

---

## 9. CUSTOMER SENTIMENT ENGINE

The system monitors conversation text and customer feedback to track customer satisfaction trends.

### 9.1 Sentiment and Emotion Monitoring
*   **Sentiment Scores**: The engine runs natural language processing on incoming customer messages and scores sentiment from `-1.00` (extremely frustrated) to `+1.00` (highly satisfied).
*   **Emotion and Tone Detection**: Detects key indicators of customer frustration, such as capital letter patterns, aggressive punctuation, or terms like "unacceptable", "terrible service", and "broken system".
*   **Frustration Escalation Triggers**: If a customer's message score falls below `-0.75` (indicating severe frustration), the engine sets the priority to `Urgent`, alerts the team lead, and suggests escalation options.
*   **False-Positive Filtering**: AI-detected frustration patterns include confidence ratings. If confidence falls below 75%, the alert is held for human confirmation before altering priorities, preventing unneeded escalations.

---

## 10. QUALITY ANALYTICS DASHBOARDS

The platform compiles performance metrics into analytics dashboards tailored for supervisors, directors, and agents:

*   **Team Quality Dashboard**: Displays team CSAT trends, NPS performance, and average QA scores, helping managers track overall quality.
*   **Agent Rankings**: Compiles peer metrics and highlights high-performing agents, helping identify mentors and training candidates.
*   **Calibration Audits**: Compares scores awarded by different QA analysts on identical tickets, helping identify and align divergent review standards.
*   **CSAT-to-QA Gaps**: Highlights tickets that received high CSAT scores but low QA ratings (or vice versa), helping identify gaps between client perception and technical accuracy.

---

## 11. AI ADVISORY BOUNDARIES

To assist quality analysts and supervisors, the system leverages artificial intelligence to analyze feedback trends and suggest improvements.

### 11.1 AI Advisory Features
*   **Review Summarizations**: Summarizes long, complex, multi-message support threads to help QA analysts understand the ticket context before scoring.
*   **Root-Cause Analysis**: Clusters negative survey responses into categories (e.g., "slow response times", "buggy software", "unhelpful agents"), helping identify systemic process issues.
*   **Coaching Recommendations**: Suggests personalized training programs and model ticket templates based on an agent's specific scorecard failures.
*   **Anomaly Audits**: Detects scoring anomalies (such as an analyst consistently awarding higher scores to certain agents), helping maintain grading objectivity.

### 11.2 Governance and Verification Gates
*   **Human Authoritative Rule**: AI coaching suggestions and root-cause analyses are advisory. Supervisors must review, modify, and authorize recommendations before they are published to agents.

---

## 12. EVENT CONTRACTS

The Customer Satisfaction & Quality Assurance engine publishes transactional events to keep other systems aligned with feedback and reviews.

### 12.1 Core System Events

#### 12.1.1 `survey.sent`
*   **Trigger**: A survey link is successfully generated and dispatched.
*   **Payload Schema**:
```json
{
  "event_id": "evt_6610a838-15ba-4abc-9922-51a221f110c4",
  "event_type": "survey.sent",
  "timestamp": "2026-06-29T06:50:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "survey_id": "srv_1234a567-b89c-12d3-a456-426614177000",
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "recipient_email": "client@customer.com",
    "delivery_channel": "email",
    "expiration_timestamp": "2026-07-06T06:50:00Z"
  }
}
```

#### 12.1.2 `survey.completed`
*   **Trigger**: A customer successfully submits their survey response.
*   **Payload Schema**: Includes `survey_id`, `ticket_id`, `scores` (CSAT/NPS/CES values), and text comment fields.

#### 12.1.3 `qa.review.completed`
*   **Trigger**: A QA scorecard review is finalized and saved by an analyst.
*   **Payload Schema**: Includes `review_id`, `ticket_id`, `agent_user_id`, `reviewer_user_id`, `total_score_percent`, and `is_critical_failure` status.

#### 12.1.4 `coaching.assigned`
*   **Trigger**: A low QA score or manager review schedules a new coaching assignment.
*   **Payload Schema**: Includes `coaching_id`, `agent_user_id`, `assigned_by_user_id`, and `reason_code`.

---

## 13. SECURITY, PRIVACY & COMPLIANCE

The system applies strict access controls and privacy guards to comply with global data regulations:

*   **Row-Level Security (RLS)**: Enforces strict database-level isolation. Survey responses and coaching history are partitioned by `organization_id` to prevent cross-tenant exposure.
*   **Survey Anonymization**: When anonymous surveys are submitted, the engine strips ticket IDs and user IDs from the response record, storing only the scores and comments.
*   **GDPR "Forget Me" Compliance**: Comments in surveys and customer feedback are subject to redaction workflows. Approved requests scrub personal details and replace comments with `[REDACTED]`.
*   **Manager-Only Visibility**: QA reviews, calibration results, and coaching histories are classified as sensitive. Access is restricted to supervisors and QA managers, preventing agents from viewing reviews of other team members.

---

## 14. PERFORMANCE & HIGH-VOLUME SCALABILITY

To support heavy survey and review traffic without degrading database performance, the system applies the following strategies:

*   **Database Partitioning**: Historical survey response tables and quality metrics are range partitioned monthly on the `created_at` column.
*   **FTS and Trigram Indexing**: Text comments in survey responses are indexed using pg_trgm and GIN indexes, allowing rapid fuzzy text searches.
*   **Batch Notifications**: Automated survey dispatches and reminders are batched and processed in background workers to minimize database connection spikes.

---

## 15. VALIDATION MATRIX

Below is the verification checklist to ensure the Customer Satisfaction, QA, and Feedback Engine functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **Survey Engine** | Customer attempting to submit duplicate survey responses | **Rejected**. Unique constraints block duplicate submissions on the same ticket. |
| **Survey Engine** | Processing an expired survey link | **Rejected**. The ingestion engine blocks submission and displays an expiration notice. |
| **Quality Engine** | Scorecard with critical compliance failure | **Automatic Failure**. Sets total QA score to 0% and alerts the team lead. |
| **Coaching Engine** | Weekly QA score falling below 85% | **Success**. Automatically schedules a coaching session and alerts the agent. |
| **Security** | Agent attempting to query peer coaching histories | **Access Denied**. RBAC rules block unauthorized access to peer QA data. |
| **GDPR Compliance** | Process approved customer deletion request | **Redacted**. Replaces survey comments with placeholders and masks associated metadata. |

---

This document serves as the architectural reference for implementing customer satisfaction, quality assurance, and agent coaching workflows within the JUANET Platform. All components must adhere strictly to these specifications.
