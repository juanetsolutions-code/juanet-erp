# JUANET Support SLA & Escalation Engine Specification
## Phase 2.3.2F.2 — SLA & Escalation Engine
**Document Version:** 1.0  
**Author:** Chief Enterprise Solutions Architect, JUANET Platform  
**Classification:** Technical / Engineering & SLA Architecture  

---

## 1. ARCHITECTURAL PHILOSOPHY

The JUANET Service Level Agreement (SLA) & Escalation Engine operates as a decoupled, high-performance, event-driven subsystem. It monitors compliance targets, tracks active deadlines, manages multi-zone business calendars, and automates operational escalations across the Support domain.

Its design is guided by these architectural principles:

*   **SLA Engine as an Independent Subsystem**: The SLA engine runs asynchronously. It calculates deadlines, processes pauses, and evaluates rulesets in isolation from the synchronous HTTP request-response lifecycle of the ticket interface.
*   **Ticket Lifecycle as a Consumer**: State modifications in the `Ticket Lifecycle Engine` emit events that serve as triggers for the SLA engine. The SLA engine updates status flags on ticket records (`first_response_sla_breached`, `resolution_sla_breached`) and logs activity, but does not embed ticket operational logic within the timer processing code.
*   **Event-Driven Timer Processing**: Deadlines and warnings are calculated and registered as delayed event payloads. The engine schedules evaluation triggers via transaction outbox patterns, minimizing polling over overhead.
*   **Stateless SLA Evaluation**: To ensure scalability, SLA calculation functions are pure and stateless. They compute active remaining intervals and deadlines using parameters stored in the database (`sla_targets`, `business_hours`, `holiday_calendars`) and current transaction timestamps.
*   **Immutable SLA History**: Every timer activation, suspension, breach, or warning event logs a corresponding record in `public.ticket_activity_logs`. Existing logs are read-only and serve as the audit baseline for analytics and client reporting.
*   **Multi-Tenant Isolation & RLS**: All policy configurations, holiday profiles, and active instances are partitioned by `organization_id`. Database-level Row-Level Security (RLS) ensures that tenants cannot access or modify each other's SLA configurations.
*   **Business-Calendar Awareness**: Timers calculate remaining hours by traversing tenant-defined business calendars. Off-hours, weekends, and holidays are excluded from calculation, ensuring accurate SLA tracking.
*   **Declarative Policies**: SLA metrics (warning thresholds, response targets, and escalation pathways) are defined in configuration tables, preventing hardcoded values in application code.

---

## 2. SLA ARCHITECTURE & RELATIONSHIP INTERACTION

The SLA subsystem utilizes a structured relationship model to evaluate compliance targets dynamically. The diagram below illustrates how these entities interact:

```
[public.tickets] ──> Match Rules ──> [sla_policies]
                                        │
                         ┌──────────────┴──────────────┐
                         ▼                             ▼
                  [sla_targets]                 [sla_calendars]
                         │                             │
                ┌────────┴────────┐             ┌──────┴────────┐
                ▼                 ▼             ▼               ▼
          [Metric Config]  [Warning Calc] [business_hours] [holiday_calendars]
                │                                       │
                └───────────────┬───────────────────────┘
                                ▼
                   [ticket_sla_instances]
                                │
                                ▼
                       [escalation_rules] ──> [escalation_actions]
```

### 2.1 Component Interactions

1.  **Ticket Ingest / Update**: A ticket state change triggers the rule evaluator. It matches ticket attributes (such as VIP tier, department, category, and priority) against the priority rules defined in `sla_policies`.
2.  **Target and Calendar Extraction**:
    *   Resolves the configured `sla_targets` for the matched policy (e.g., first response within 30 minutes, resolution within 4 hours).
    *   Retrieves the corresponding `sla_calendars` to extract the timezone, active `business_hours` blocks, and registered `holiday_calendars` dates.
3.  **Active Instance Creation**:
    *   Calculates the warning and target deadline timestamps by overlaying the target minutes onto the active business schedules.
    *   Inserts active evaluation rows into `public.ticket_sla_instances`.
4.  **Breach Monitor & Escalation Evaluation**:
    *   As deadlines approach or expire, background workers trigger matched `escalation_rules`.
    *   Executes defined `escalation_actions` (such as re-routing queues, notifying managers, or elevating priorities).

---

## 3. SLA TYPES

The engine supports the following SLA categories:

### 3.1 Response SLA
*   **First Response SLA**: Measures the duration from ticket creation (`tickets.created_at`) to the first public message logged by an authorized support agent (`ticket_messages.is_internal = false`). Automated responses and system notifications do not satisfy the response SLA.
*   **Next Response SLA**: Activated upon receiving a public customer message on an already assigned ticket. Measures the duration until the next public agent reply is logged.

### 3.2 Resolution SLA
*   Measures the total duration from ticket creation to the timestamp when the ticket enters the `resolved` state.
*   This timer runs continuously across multiple agent handoffs, pause events, and reassignments.

### 3.3 Update SLA (Silence Interval)
*   Enforces a maximum silence period to prevent tickets from stalling.
*   Measures the duration since the last public update on the ticket. If the silence limit is reached without update, the ticket escalates to warn the assigned agent.

### 3.4 Escalation SLA
*   Measures the duration a ticket can remain assigned to a specific tier or group (e.g., Tier 1) before triggering an automatic transfer to a senior tier (e.g., Tier 2).

### 3.5 Customer Response SLA (Waiting Timeout)
*   Measures the duration a ticket remains in the `waiting_for_customer` state.
*   Acts as a reverse SLA. If the customer does not respond within the configured timeout window (e.g., 72 hours), the engine auto-resolves the ticket.

### 3.6 Internal Approval SLA
*   Measures the duration a ticket remains in `pending_approval` awaiting reviewer sign-off.
*   If the approval deadline passes, the system triggers an escalation alert to prevent operational bottlenecks.

---

## 4. SLA LIFECYCLE

Each SLA target is managed by a structured state machine modeled inside the `public.ticket_sla_instances` table:

```
[Created] ──> [Active] ──> [Warned] ──> [Breached] ──> [Completed]
                │            │            │
                ├────────────┴────────────┤
                ├───────> [Paused] <──────┤
                │                         │
                └────────> [Cancelled] <──┘
```

### 4.1 Lifecycle Phases and Transitions

#### 4.1.1 Creation and Activation
*   **Trigger**: A ticket is created or updated, matching an active SLA policy.
*   **Action**: The system resolves targets, calculates deadlines, and inserts records into `public.ticket_sla_instances` with status `active`.
*   **Event**: Publishes `sla.instance.created` and `sla.started` events.

#### 4.1.2 Pause
*   **Trigger**: The ticket transitions to a status that pauses the SLA (e.g., `waiting_for_customer`, `waiting_for_third_party`, `pending_approval`).
*   **Action**: The system sets the status to `paused`, records the `paused_at` timestamp, and suspends countdown calculations.
*   **Event**: Publishes `sla.paused` event.

#### 4.1.3 Resume
*   **Trigger**: The ticket transitions back to an active status (e.g., customer replies, moving ticket back to `open`).
*   **Action**: The system calculates the paused duration (`now() - paused_at`) and extends the target deadlines (`target_deadline` and `warning_at`) by that duration. The status is set back to `active`.
*   **Event**: Publishes `sla.resumed` event.

#### 4.1.4 Warning and Breach
*   **Warning Threshold**: If the current time passes `warning_at` while status is `active`, the status updates to `warned` and dispatches warning notifications.
    *   **Event**: Publishes `sla.warning` event.
*   **Breach Threshold**: If the current time passes `target_deadline` while status is `active` or `warned`, the status updates to `breached`. The ticket record is updated (`first_response_sla_breached` or `resolution_sla_breached` set to `true`), and escalation actions are triggered.
    *   **Event**: Publishes `sla.breached` event.

#### 4.1.5 Completion
*   **Trigger**: The SLA metric is met (e.g., first response is logged, ticket transitions to `resolved`).
*   **Action**: The system sets the status to `achieved` and records the `achieved_at` timestamp.
*   **Event**: Publishes `sla.completed` event.

#### 4.1.6 Cancellation
*   **Trigger**: The ticket is canceled, merged, or its priority changes, rendering the current SLA policy invalid.
*   **Action**: The system sets the status to `cancelled` and stops all active monitoring.
*   **Event**: Publishes `sla.cancelled` event.

#### 4.1.7 Deletion Policy
*   Physical deletion of records from `public.ticket_sla_instances` is prohibited. Cancelled or historical instances are preserved to maintain data integrity for reporting and analytics.

---

## 5. TIMER ENGINE

The Timer Engine computes deadlines and monitors warnings. To ensure accuracy across different timezones and schedules, it operates under the following standards:

### 5.1 Business-Hours Countdown Calculation (SQL Implementation Pattern)
To compute a target deadline that spans business hours, weekends, and holidays, the system uses an iterative timezone-aware calculation process.

```
Input: StartTime, TargetMinutes, CalendarID, TimeZone
Output: CalculatedDeadlineTimestamp
```

1.  **Normalize Timezone**: Converts `StartTime` to the timezone defined in the target calendar (`public.sla_calendars.time_zone`).
2.  **Iterative Duration Mapping**:
    *   Queries `public.holiday_calendars` and `public.business_hours` for the calendar.
    *   Starting from `StartTime`, the engine steps forward day by day.
    *   For each day, if the date matches a registered holiday or weekend (where `day_of_week` is not defined in `business_hours`), the entire 24-hour block is skipped.
    *   If it is a working day, the engine calculates the overlap between the business hours (`start_time` to `end_time`) and the remaining target minutes:
        *   If the remaining target minutes fit within the active business hour window, the deadline is set within that day's window.
        *   If the target minutes exceed the active window, the engine consumes the available business hours for that day, advances to the start of the next working day's business hours, and repeats the calculation.

### 5.2 Dynamic Timer Evaluations and Scheduler Polling
*   **Warning and Breach Checks**: Background cron processes run every 60 seconds.
*   **Optimized Queries**: To minimize database overhead, the cron queries only active instances with deadlines in the past or warnings that need to be triggered:
    ```sql
    SELECT * FROM public.ticket_sla_instances
    WHERE status IN ('active', 'warned')
      AND (warning_at <= now() OR target_deadline <= now());
    ```
*   **Clock Drift and DST Management**: Timestamps are stored using the `timestamp with time zone` (TIMESTAMPTZ) type. Calculations convert UTC storage values to the local timezone of the calendar first, ensuring DST transitions do not cause calculation errors.

---

## 6. BUSINESS CALENDAR ENGINE

SLA policies use calendars to track regional schedules, shift structures, and holiday dates.

### 6.1 Multi-Shift & Weekend Support
*   **Shift Definitions**: Represented in the `public.business_hours` table.
*   **Split Shifts**: Supports multiple shift intervals per day (e.g., Morning Shift: 08:00 to 12:00, Afternoon Shift: 13:00 to 17:00).
*   **Weekend Configuration**: Indicated by omitting days from the `public.business_hours` table. If no records are present for a given day (e.g., `day_of_week` 0 or 6), that day is treated as a non-working weekend day.

### 6.2 Holiday Calculations
*   **Fixed Holidays**: Stored in `public.holiday_calendars` with a specific date (e.g., Christmas: `2026-12-25`).
*   **Recurring Holidays**: Flagged with `is_recurring = true`. The calculation engine ignores the year component and evaluates the match based only on the month and day, ensuring annual holidays don't need to be re-entered every year.
*   **Emergency Closures**: Ad-hoc closure dates can be added to `holiday_calendars` dynamically to handle unexpected events.
*   **Calendar Inheritance**: Tenants can define global organization calendars that local department calendars inherit from, simplifying the management of common holidays.

---

## 7. PAUSE & RESUME RULES

SLA countdown timers are suspended when a ticket requires external action, pending input, or approval.

### 7.1 Pause Triggers and Status Codes
The SLA engine pauses timers when a ticket transitions to specified statuses:

*   **`waiting_for_customer`**: Paused while awaiting customer input, log files, or validation.
*   **`waiting_for_third_party`**: Paused while awaiting external vendor or engineering feedback.
*   **`pending_approval`**: Paused while awaiting manager or financial approval.
*   **Scheduled Maintenance Hold**: Paused during pre-arranged tenant maintenance windows.
*   **Security / Legal Investigation**: Paused during audits or compliance investigations.

### 7.2 Manual Pause Authorization
*   Agents cannot pause SLAs manually. Pausing is driven strictly by ticket status changes.
*   Authorized managers can apply a manual pause override by updating the status to `paused` and logging a justification in `public.ticket_activity_logs`.

---

## 8. ESCALATION ENGINE

Escalations are triggered when SLA targets are breached, critical blocks occur, or senior assistance is requested.

```
[SLA Target Breached] ──> [Evaluate Escalation Rules] ──> [Execute Actions] ──> [Notify Stakeholders]
```

### 8.1 Escalation Rules & Levels

```
Tier 1 Agent ──[First Response SLA Breach]──> Auto-route to Tier 2 Support Queue
                                                  │
                                   [Resolution SLA Warning]
                                                  │
                                                  ▼
                                       Notify Support Manager
                                                  │
                                    [Resolution SLA Breached]
                                                  │
                                                  ▼
                                       Alert Engineering Director
```

*   **Tier 1: Warning Stage**
    *   **Trigger**: Ticket SLA status changes to `warned`.
    *   **Actions**: Sets warning flags, highlights the ticket in the agent portal, and alerts the assigned agent.
*   **Tier 2: Escalated Stage**
    *   **Trigger**: First Response SLA is breached.
    *   **Actions**: Updates ticket priority to `Urgent`, transfers the ticket to the Tier 2 Support Queue, and alerts the team lead.
*   **Tier 3: Executive Stage**
    *   **Trigger**: Resolution SLA is breached.
    *   **Actions**: Escalates the ticket to the Department Director, logs the event, and alerts the customer success manager.

### 8.2 Escalation Overrides & Suppression Rules
*   **Active Escalation Flag**: Tickets undergoing active engineering reviews or customer-approved holds can be exempted from automatic escalation by setting `escalation_suppressed = true`. This bypass must be authorized by a manager and is logged for audit purposes.

---

## 9. NOTIFICATION INTEGRATION

When warning or breach thresholds are crossed, the system dispatches multi-channel alerts to maintain visibility:

| Recipient Role | Trigger Event | Primary Channel | Notification Payload / Message Template |
| :--- | :--- | :--- | :--- |
| **Assigned Agent** | SLA Warning (80% Elapsed) | In-App Alert & Slack | `"SLA Warning: Ticket JUANET-{Num} has 20% remaining before breach."` |
| **Team Lead** | SLA Breach | Slack Channel | `"SLA BREACH: Ticket JUANET-{Num} has breached First Response target."` |
| **Executive / Director** | Resolution Breach | Email | `"Critical Resolution SLA Breach: Ticket JUANET-{Num} requires attention."` |
| **Customer** | Ticket Escalation | Portal Update & Email | `"Your request (JUANET-{Num}) has been escalated to our senior team."` |

---

## 10. AI-ASSISTED SLA COMPLIANCE

The Support domain integrates AI capabilities to help teams meet SLA compliance targets. These outputs are stored in dedicated tables and serve as advisory insights for agents.

### 10.1 AI Support Capabilities
*   **Breach Prediction**: Evaluates historical patterns and text sentiment to predict the risk of an active ticket breaching its SLA. Predicted risks are stored in `public.ai_ticket_classifications` (e.g., `breach_probability: 85%`).
*   **Smart Risk Scoring**: Flags complex, multi-topic, or negative-sentiment tickets upon ingestion, recommending higher priorities to prevent breaches.
*   **Workload Balancing**: Analyzes agent capacities and predicts resolution times to recommend optimal routing paths, helping prevent queue bottlenecks.

---

## 11. ANALYTICS & KPI ENGINE

To measure support performance, the system aggregates SLA transaction logs into key performance indicators (KPIs):

### 11.1 Core Metrics and Calculations

#### 11.1.1 First Response Time (FRT)
Calculates the duration between ticket creation and the first public agent response, accounting for business hours:
$$\text{FRT} = \text{FirstPublicMessageTime} - \text{TicketCreationTime} \quad [\text{Evaluated against Business Hours}]$$

#### 11.1.2 Average Resolution Time (ART)
Calculates the average duration to resolve tickets, excluding paused intervals:
$$\text{ART} = \text{ResolvedTime} - \text{CreationTime} - \sum(\text{PausedIntervals})$$

#### 11.1.3 SLA Compliance Rate
Calculates the percentage of tickets resolved within SLA targets:
$$\text{Compliance Rate} = \left( \frac{\text{Tickets Resolved Within SLA}}{\text{Total Resolved Tickets}} \right) \times 100$$

#### 11.1.4 Reopen Rate
Calculates the percentage of resolved tickets that were reopened:
$$\text{Reopen Rate} = \left( \frac{\text{Tickets Reopened}}{\text{Total Resolved Tickets}} \right) \times 100$$

---

## 12. PERFORMANCE & SCALABILITY

To support high transaction volumes without degrading system performance, the database applies the following strategies:

*   **Database Partitioning**: Historical SLA logs and activity records are partitioned monthly on the `created_at` column.
*   **Covering Indexes**: Covering indexes are applied to SLA status and deadline columns to optimize polling queries:
    ```sql
    CREATE INDEX active_sla_instances_idx 
    ON public.ticket_sla_instances (organization_id, status, target_deadline) 
    INCLUDE (ticket_id, warning_at) 
    WHERE status IN ('active', 'warned');
    ```
*   **Materialized Views**: SLA compliance metrics and agent performance reports are queried from materialized views refreshed during off-peak hours.

---

## 13. EVENT CONTRACTS

The SLA engine publishes transactional events to keep other systems aligned with ticket deadlines and compliance status.

### 13.1 SLA Event Catalog

#### 13.1.1 `sla.paused`
*   **Trigger**: A ticket status change pauses the active SLA timer.
*   **Payload**:
```json
{
  "event_id": "evt_2210a438-92ba-4abc-8822-21a221f110a1",
  "event_type": "sla.paused",
  "timestamp": "2026-06-29T06:10:00Z",
  "organization_id": "org_9831a238-bfbc-4122-a9b3-1f19f2a00d41",
  "payload": {
    "ticket_id": "tkt_1234a567-b89c-12d3-a456-426614174000",
    "sla_instance_id": "sla_731a89c2-20ba-4cbb-b21a-1f03bc189012",
    "metric_type": "resolution",
    "paused_at": "2026-06-29T06:10:00Z",
    "reason_code": "waiting_for_customer"
  }
}
```

#### 13.1.2 `sla.breached`
*   **Trigger**: An active SLA target passes its calculated deadline.
*   **Payload**: Includes `ticket_id`, `sla_instance_id`, `metric_type`, `deadline_timestamp`, and `breached_timestamp`.

#### 13.1.3 `sla.warning`
*   **Trigger**: An active SLA target passes its warning threshold.
*   **Payload**: Includes `ticket_id`, `sla_instance_id`, `metric_type`, and `warning_timestamp`.

#### 13.1.4 `sla.completed`
*   **Trigger**: An active SLA target is met successfully.
*   **Payload**: Includes `ticket_id`, `sla_instance_id`, `metric_type`, `achieved_at`, and `duration_seconds`.

---

## 14. SECURITY & COMPLIANCE

SLA modifications are restricted to prevent unauthorized adjustments and preserve audit integrity.

### 14.1 Access Controls & Overrides
*   **Role-Based Access Control (RBAC)**: Only users with the `Support Administrator` or `System Administrator` roles can create or modify SLA policies, calendars, or holiday schedules.
*   **Immutable History**: SLA achievement records and activity logs are append-only. Manual adjustments or deletions of compliance history are blocked at the database level.
*   **Manual Overrides**: In rare cases where a breach was caused by system downtime, an administrator can apply a manual override. The override must include a documented reason, which is permanently saved to the audit log.

---

## 15. VALIDATION MATRIX

Below is the verification checklist to ensure the SLA and Escalation Engine functions correctly:

| Area | Test Scenario | Expected Result |
| :--- | :--- | :--- |
| **Business Hour Logic** | Calculate SLA during weekend | **Weekend Skipped**. Countdown pauses and resumes on the next working day. |
| **Holiday Logic** | SLA deadline landing on registered holiday | **Holiday Skipped**. Countdown pauses and resumes on the next working day. |
| **Pause/Resume** | Ticket transitions to `waiting_for_customer` and back | **Timer Suspended**. Deadline is extended by the paused duration. |
| **Concurrency** | Multiple agents updating ticket status simultaneously | **Optimistic Locking Guard**. Outdated updates are rejected. |
| **SLA Warning** | Timer passes calculated `warning_at` | **SLA Warned**. Status updates to `warned`, dispatches agent alerts. |
| **SLA Breach** | Timer passes calculated `target_deadline` | **SLA Breached**. Sets breach flag on ticket and triggers escalation. |
| **Security** | Non-admin attempting to edit SLA policy | **Rejected**. Access control blocks unauthorized policy modifications. |

---

This document serves as the architectural reference for implementing SLA compliance, business calendars, and escalation workflows within the JUANET Platform. All components must adhere strictly to these specifications.
