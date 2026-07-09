# JUANET EOS: Business Workflow Validation

This document describes the end-to-end business workflows supported by **JUANET EOS**. It outlines how individual domains integrate to form a single, reliable transactional loop.

---

## 🔁 1. The Core Lifecycle Sequence

JUANET EOS connects client acquisition, contractual onboarding, active delivery, workforce accounting, digital payments, and immutable financial logging into a single automated pipeline:

```
[ CRM Lead ] ──────► [ Proposal ] ──────► [ Signed Contract ] ──┐
                                                                 │ (Onboarding Trigger)
                                                                 ▼
[ Ledger Entry ] ◄─── [ M-PESA ] ◄─── [ Invoice ] ◄─── [ Active Project ]
```

---

## 🛠️ 2. Workflow Step Specifications

### Step 1: Lead to Proposal
*   **Trigger**: A potential lead reaches 80% qualification status in the CRM funnel.
*   **System Action**: The CRM domain invokes the **AI Proposal Assistant** (Gemini Grounding Engine) to analyze captured client requirements and automatically assemble a structured service proposal.
*   **Output**: A draft PDF proposal containing customized milestones, timelines, and pricing estimates.

### Step 2: Proposal to Contract
*   **Trigger**: Client approves the proposal via their sandbox portal.
*   **System Action**: The system promotes the proposal to an active contract, generates secure electronic signature URLs, and sends invite notifications to both parties.
*   **Output**: An e-contract waiting for secure legal e-signature input.

### Step 3: Contract to Project Onboarding
*   **Trigger**: Both parties submit cryptographically signed e-signatures.
*   **System Action**:
    1.  The Contract domain publishes a `ContractSignedEvent` to the transactional outbox.
    2.  The Project domain listens and auto-provisions a blank project workspace.
    3.  The CRM domain updates the target lead to `Closed-Won`.

### Step 4: Workforce Assignment & Time Tracking
*   **Trigger**: Project manager sets tasks and assigns specialized staff.
*   **System Action**: Assigned employees gain access to log daily timesheets against specific project tasks. Timesheet logs are restricted to active task timelines and validated against the employee's max weekly hours.

### Step 5: Project Progress to Invoice Generation
*   **Trigger**: Workforce logs complete a planned project milestone.
*   **System Action**: The Project domain generates a milestone completion report, and the Finance service builds an automated progress invoice based on logged billable hours and pre-agreed contract rates.

### Step 6: Invoice Billing via M-PESA
*   **Trigger**: Invoice status changes to `Sent`.
*   **System Action**: The system initiates a secure M-PESA Daraja STK-Push to the client's registered mobile number.
*   **Output**: Client receives a secure pin prompt on their physical device.

### Step 7: Payment to Ledger & Analytics Posting
*   **Trigger**: Safaricom API posts a successful callback receipt to the webhook gateway.
*   **System Action**:
    1.  Marks the target invoice as `Paid`.
    2.  Increments the organization's real-time **Daily Revenue Analytics**.
    3.  Posts a debit and credit entry to the **General Ledger** to update cash asset and deferred liability accounts.
    4.  Dispatches an automated payment receipt via email and SMS.
