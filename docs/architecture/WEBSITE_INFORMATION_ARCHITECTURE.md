# JUANET EOS: Website Information Architecture

This document maps out the navigation, content hierarchy, sitemap, and data models for the **JUANET EOS Public Website**.

---

## 🗺️ 1. Complete Website Sitemap

Our public-facing website architecture is streamlined for organic discovery, commercial credibility, and high-conversion pathways:

```
/ (Home: Pitch & Core Modules)
├── about (Mission, Bounded Domains, DDD Architecture)
├── services (Enterprise Implementation, Cloud migration)
├── portfolio (SaaS Tenant Case Studies, Client successes)
├── pricing (SaaS Tiered Packages: Startup, Grow, Enterprise)
├── blog (Technical Articles, ERP Industry Trends)
│   └── [slug] (Dynamic Blog Post Layouts)
├── careers (Engineering and Sales Openings)
├── contact (Commercial Sales & Consultation Inquiries)
│   └── quote-wizard (Premium Proposal Scoping & Budget Estimator)
├── legal
│   ├── privacy-policy (Tenant Data Storage Standards)
│   ├── terms-of-service (Service Level Agreements - SLA)
│   └── cookie-policy (Privacy & Consent Controls)
└── client-portal (Gateways to Client Dashboard, Milestone, and Invoices)
```

---

## 📑 2. Detailed Page Spec Definitions

### A. Dynamic Quote Wizard (`/contact/quote-wizard`)
*   **Purpose**: Intelligently capture client metadata, budget limits, timeline targets, and document uploads to create a scoped lead.
*   **Step-by-step Structure**:
    1.  **Step 1: Contact Metadata** — Name, organization email, phone number, and industry classification.
    2.  **Step 2: Operational Requirements** — Bounded domains needed (e.g., General Ledger, CRM, Project Delivery).
    3.  **Step 3: Financial Targets & Timelines** — Selectable budget tiers (KES 200k, 500k, 1M+) and target launch timeline (1-3 months, 3-6 months).
    4.  **Step 4: Document Upload** — Secure drag-and-drop file uploader for current RFPs or operational files.
    5.  **Step 5: AI Scoping Summary** — Integrates with the Google Gemini API to analyze the requirements and generate a structured project proposal.
*   **System Action**: On completion, the system automatically inserts the lead into the database, updates the CRM dashboard, and dispatches an email notification to the client.

### B. Dynamic Blog System (`/blog`)
*   **Data Structure**:
    ```json
    {
      "id": "uuid",
      "title": "String",
      "slug": "String (indexed)",
      "author": "String",
      "published_at": "DateTime",
      "content": "Markdown / Rich Text",
      "meta_description": "String (155 chars max)"
    }
    ```
*   **System Action**: Uses server-side caching to fetch posts in under `50ms` using Redis key storage.

---

## 🗄️ 3. Client & Employee Dashboard Information Hierarchy

### I. Client Workspace Hub
*   **Objective**: Facilitate secure client collaboration, bill tracking, and milestone reviews.
*   **Data Panels**:
    *   *Active Project Boards*: View milestones, deliverables, and outstanding tasks in real time.
    *   *Billing Center*: View, pay, and download PDF receipts for invoices.
    *   *Support Portal*: Direct connection to technical account managers and CRM representatives.

### II. Employee Dashboard Workspace
*   **Objective**: Drive daily execution, billing records, and personal calendars.
*   **Data Panels**:
    *   *Time Logs*: Track and log billable hours against active milestones.
    *   *Leave Portal*: Submit and track multi-tier annual leave requests.
    *   *Collaboration Feed*: Live team updates and task assignments.
