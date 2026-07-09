# JUANET Master Specification (Project Constitution)
## Version 1.3 — Enterprise Client & Project Management Platform

> **This document is the authoritative source of truth for the JUANET platform. All future development must conform to this specification. No architectural changes should be made unless this specification is formally updated.**

---

## 1. Project Overview

### Project Name
JUANET Platform (Internal Architecture: JUANET EOS)

### External Positioning
**JUANET — Enterprise Client & Project Management Platform**  
A high-performance digital agency operating system designed to automate lead generation, project scoping, milestone delivery, M-PESA payment routing, client support ticketing, and double-entry ledger bookkeeping in a secure multi-tenant architecture.

### Vision
To empower digital agencies, freelancers, and enterprise networks with a unified workspace that bridges public portal visibility, lead generation, project execution, automated double-entry ledger bookkeeping, real-time customer support, and autonomous AI productivity.

### Mission
To eliminate the friction of juggling multiple software-as-a-service (SaaS) platforms by consolidating CRM pipelines, automated client proposals, milestone-based agile project delivery, payment clearing (via Daraja M-PESA), support ticketing, content management systems, and workflow automation into a single secure multi-tenant operational canvas.

### Objectives
1. **Zero-Trust Multi-Tenancy**: Provide isolated container organizations enabling future software-as-a-service expansion.
2. **Unified Core Ledger**: Reconcile all financial entries (proposals, invoices, marketplace payments, operational expenses) under a double-entry ledger database pattern.
3. **Omnipresent AI Intelligence**: Integrate AI routing systems via Gemini to deliver smart summaries, contextual support, and auto-generated contract scopes.
4. **Sub-Second Core Latency**: Standardize asset rendering, search indices, and API delivery to guarantee high-performance operational throughput.

---

## 2. Platform Overview

JUANET is designed as a modular, federated, and highly integrated enterprise operating platform. It acts as the backbone for both public-facing digital properties and private back-office operational systems.

### Core Capabilities
* **Dynamic Lead Funneling**: Bridges public blog and landing forms directly into central CRM pipelines.
* **Automated Client Conversions**: Converts qualified CRM leads into detailed service proposals, which on electronic signature trigger automatic database organization, project, and channel provisioning.
* **Financial Clearance**: Integrates M-PESA checkout (STK Push) directly with double-entry chart accounts to mark bills cleared immediately on Safaricom webhook callback.
* **Proactive Automation**: Triggers workflows asynchronously, letting user actions initiate multi-channel notification updates, SMTP dispatches, or AI-generated analytics reports.

---

## 3. Technology Stack

```
+----------------------------------------------------------------------------------+
|                                  FRONTEND LAYER                                  |
|  - React 18+ (Vite) / Tailwind CSS (v4)                                          |
|  - Motion (React animations) / Lucide-React (Icons)                              |
|  - Recharts / D3.js (Data Visualizations)                                        |
+----------------------------------------------------------------------------------+
                                         |
                                         v
+----------------------------------------------------------------------------------+
|                                  BACKEND LAYER                                   |
|  - PHP 8.4+ / Laravel 12 (Framework Core)                                        |
|  - RESTful APIs / Server-Sent Events (SSE) / WebSockets / Queues                 |
+----------------------------------------------------------------------------------+
                                         |
                                         v
+----------------------------------------------------------------------------------+
|                              INFRASTRUCTURE LAYER                                |
|  - Laravel Sanctum / Supabase Auth & RLS Core Policies                           |
|  - PostgreSQL Database (via local Docker instance or Supabase Cloud)             |
|  - Daraja M-PESA Gateway Integration (Digital Payment Receipts)                  |
|  - Google Gemini API SDK (@google/genai)                                         |
|  - CloudPanel VPS / Docker Orchestration / Nginx Reverse Proxy                   |
+----------------------------------------------------------------------------------+
```

### Frontend
* **Core Framework**: React 18+ bootstrapped with Vite for fast Single Page Application (SPA) load times and immediate build compilation.
* **Styling & Theme Engine**: Tailwind CSS v4 utility-first framework. High-contrast designs matching Space Grotesk display typography and JetBrains Mono accent layouts.
* **Animations**: Motion (imported from `motion/react`) for spatial movement, canvas slides, and micro-interactions.
* **Icons**: `lucide-react` library exclusively. No custom inline or raw SVGs permitted.
* **Data Visualization**: `recharts` for complex bento-grid analytics panels; `d3` for high-fidelity custom SVG layout graphs (such as ERD nodes).

### Backend
* **Core Runtime**: PHP 8.4+ providing strict typing, constructor property promotion, readonly properties, and native enums.
* **Web Framework**: Laravel 12 framework handling secure request validation, Eloquent ORM mapping, background job queues, mail dispatch, and transactional routing.

### Database & Security
* **Database Engine**: PostgreSQL (v15+) optimized for relational consistency, JSONB querying, transactional rollback, and tenant isolation scopes.
* **ORM & Querying**: Eloquent ORM featuring unified global scopes for multi-tenant isolation, polymorphic ledger records, and transactional outbox event tracking.
* **Authentication**: Laravel Sanctum combined with Supabase Auth for JWT security, token tracking, invitation flows, and role-based policies.

---

## 4. Architecture Decision Records (ADRs)

### ADR 001: React/Vite vs. Next.js for App Dashboards
* **Context**: We need to decide on the core web architecture for our private client and admin dashboards.
* **Decision**: Use React (Vite) as a client-side SPA for the private dashboards (`apps/client-dashboard` and `apps/admin-dashboard`), but use Next.js for the public portal (`apps/marketing-site`).
* **Reasoning**: Next.js is optimal for public SEO, server-side page speed, and content management. However, for private, highly stateful dashboard tools (e.g. interactive Gantt charts, complex ledger audits, Kanban boards), a client-side SPA built with Vite offers faster interactions, reduced server-side processing overhead, simpler container state synchronization, and lower hosting costs.

### ADR 002: Supabase (Auth/Storage) with PostgreSQL vs. Firebase
* **Context**: Selecting the backend-as-a-service infrastructure and authentication framework.
* **Decision**: Adopt Supabase (PostgreSQL with Row-Level Security) instead of Firebase.
* **Reasoning**: Firebase is non-relational (NoSQL), making double-entry bookkeeping ledgers and complex financial ledger calculations extremely difficult to model and maintain cleanly. PostgreSQL provides transaction isolation (ACID), strong relational typing, native schema verification, and Supabase Auth binds seamlessly with Row-Level Security (RLS) at the database layer.

### ADR 003: Laravel 12 vs. Express (Node.js) for Enterprise Bounded Contexts
* **Context**: Selecting the core routing, API, and domain logic framework for our central transactional services.
* **Decision**: Standardize on Laravel 12 (PHP 8.4) instead of Express/Node.js.
* **Reasoning**: Enterprise CRM and finance ledger platforms require high-security validation, strict transactional boundaries, queuing pipelines, structured email delivery, and standardized database migrations. Laravel provides a robust, standardized environment with built-in artisan utilities, powerful queue workers, secure Eloquent database scopes, and native PSR integration, eliminating the excessive fragmentation and security risks inherent to lightweight Node.js/Express libraries.

### ADR 004: Bounded Contexts & DDD Directory Mapping
* **Context**: Structuring backend directories to handle growing enterprise capabilities without cluttering.
* **Decision**: Adopt Domain-Driven Design (DDD) to isolate domain operations into self-contained Bounded Contexts located inside `app/Domain/`.
* **Reasoning**: Standard MVC layouts split logical components across directories, making large business processes difficult to audit. Moving leads, contracts, ledger postings, and support rules into co-located Bounded Contexts ensures high modularity, simplified testing, and straightforward maintainability as functional requirements scale.

### ADR 005: PostgreSQL as the Primary Database Engine
* **Context**: Selecting the storage engine.
* **Decision**: PostgreSQL (v15+) is designated as the sole transactional database engine.
* **Reasoning**: Standard relational joins, rich JSONB operations, index creation speeds, foreign key constraints, and strict schema structures are absolute prerequisites for financial ledgers, auditing compliance, and robust enterprise analytics.

### ADR 006: Google Gemini API SDK (`@google/genai`)
* **Context**: Selecting our core generative AI interface provider.
* **Decision**: Adopt the Google Gemini API using the modern `@google/genai` TypeScript SDK.
* **Reasoning**: Gemini models (`gemini-2.5-flash` and `gemini-2.5-pro`) offer superior context lengths (up to 2M tokens), exceptionally low API invocation costs, native tool calling, and superior performance for code-generation and proposal summarization.

---

## 5. Unified Monorepo Layout & Integration

The platform combines the high-fidelity presentation layer and the robust Laravel 12 backend into a unified multi-framework repository, integrated using Vite asset processing.

```
/JUANET-EOS (Unified Repository Root)
├── app/                      # Laravel 12 Backend Application Root
│   ├── Domain/               # Bounded Contexts (Core Business Domains)
│   │   ├── CRM/              # Lead Funnels & Pipeline Controllers
│   │   ├── Contract/         # Electronic Signatures, Proposals, NDA PDF Engines
│   │   ├── Finance/          # Immutable Double-entry Ledger & Invoice Systems
│   │   ├── Marketplace/      # Digital Store Asset Management & Store Modules
│   │   └── Notification/     # Multi-channel Dispatch and Event Routing Engines
│   ├── Http/                 # Controller, Middleware, and Request Filters
│   ├── Models/               # Core Eloquent Models
│   └── Providers/            # Service Providers (Gateway Registries, Event Registers)
├── bootstrap/                # Laravel Application Bootstrap Code
├── config/                   # Configuration Files (database, mail, queues, sanctum)
├── database/                 # Migrations, Seeders, and Model Factories
├── resources/                # Public Blade Views & Asset entry files
├── routes/                   # API, Web, and Console Route Mappings
├── src/                      # Vite & React 18 SPA Frontend Source
│   ├── components/           # Extracted UI components (Workforce, Finance, CRM)
│   ├── lib/                  # Frontend initialized SDKs
│   └── App.tsx               # Primary Client/Admin SPA Dashboard Code
├── tests/                    # Automated PHPUnit, Pest, and Integration Test Suite
└── package.json              # Front-end Asset Bundling and Dev Scripts
```

---

## 6. Folder Structure Standards

All folders across standard client and admin applications must adhere to the following naming conventions to preserve codebase readability.

### Project Directory Layout (React/Vite apps)
```
/src
├── components/           # Extracted modular UI components
│   ├── ui/               # Base design system primitives (Button, Input, Card)
│   └── blocks/           # Complex composite visual groups (Navbar, FileTree)
├── data/                 # Static mock maps, charts models, and schema types
├── hooks/                # Custom React Hooks managing clean lifecycles
├── lib/                  # Initialized SDK configurations (supabase, gemini)
├── utils/                # Pure mathematical, formatting, or parsing functions
├── index.css             # Entry stylesheet importing Tailwind imports
├── main.tsx              # React mounting root
└── App.tsx               # Main application entry layout
```

### Standards
* **Casing**: Lowercase with hyphens for directories (e.g. `client-dashboard`). PascalCase for React component files (e.g. `ERDVisualizer.tsx`). camelCase for utility files and custom hooks (e.g. `useMpesaPayment.ts`).
* **Imports**: Absolute paths mapped via tsconfig paths (e.g. `@/components/ui/button`).
* **Barrel Exports**: Avoid index file grouping. Explicitly import from component target files to support treeshaking and eliminate circular dependency issues.

---

## 7. Coding Standards

### TypeScript
1. **Explicit Types**: Never use `any`. Specify interface structures, record maps, or standard union declarations.
2. **Type Imports**: All imports must be placed at the top of the file. No inline require statements.
3. **Enums**: Standard enum declarations only. No `const enum` blocks to ensure compatibility across Node runtimes and CJS/ESM bundling processes.

### React
1. **Functional Primitives**: Standardize on functional components. Use custom hooks to isolate API mutations and async fetching patterns.
2. **Effect Discipline**: Avoid infinite re-renders. Every dependency array inside `useEffect` must contain primitive keys or heavily memoized references. Never update state directly in the component body.
3. **Rendering Safeguards**: Guard mapping lists with unique ID keys (never array indexes). Implement standard React Boundary elements to catch exceptions.

### SOLID & Clean Architecture
* **Single Responsibility**: Each component/function does exactly one job. Separate layout presentation from background networking states.
* **Dependency Inversion**: Pass interfaces, not raw concrete clients. Let API handlers depend on high-level models, enabling simple unit testing with mock services.

---

## 8. UI/UX Design System Standards

The platform features a distinct **Space Slate Theme** designed for technical professionals.

### Color Palette
* **Deep Space Base**: `#0B0F19` (Canvas background)
* **Slate Surface**: `#161F30` (Interactive cards, panels)
* **Aero Accents**: `#00D2FF` (Cyan focus highlights, links, glowing highlights)
* **Text High**: `#FFFFFF` (Headings, primary typography)
* **Text Muted**: `#8A99AD` (Filing descriptions, labels, helper blocks)

### Typography
* **Display Font**: Space Grotesk (geometric, technical displays for cards and headlines).
* **UI Base**: Inter (highly legible sans-serif for settings, inputs, lists).
* **Data Font**: JetBrains Mono (monospaced tracking for numbers, database constraints, code logs).

### Design Grid & Controls
* **Border Radius**: Unified 8px (`rounded-lg`) for cards, buttons, and system modals.
* **Shadows**: Low-glow cyan borders rather than heavy standard dropshadows:
  * Default: `border border-slate-800`
  * Active/Focus State: `border border-cyan-500/50 shadow-[0_0_15px_rgba(0,210,255,0.15)]`
* **Button Hierarchy**:
  * *Primary*: Solid background `#00D2FF`, black text, Space Grotesk font.
  * *Secondary*: Outline slate surface with a subtle cyan glow on hover.
  * *Danger*: Solid deep red background, white text.
* **Skeletons**: Staggered pulsing panels built using Tailwind’s standard `animate-pulse` utility.
* **Toasts**: Custom transient popups anchored bottom-right with matching status colors (cyan for info, red for error, green for success).

---

## 9. Business Domains & Relationships

### Domain Relationship Diagram
The following layout maps the business flow of a single corporate engagement through the platform:

```
[ WEBSITE PORTAL ]
       │
       ▼ (Lead Form Submission)
[ CRM PIPELINES ]
       │
       ▼ (Conversion Action)
[ PROPOSAL ENGINE ]
       │
       ▼ (Electronic Signature / Client Approval)
[ PROJECT DELIVERY SYSTEM ] ────> Auto-triggers ────> [ LEGAL CONTRACTS (PDF Vault) ]
       │
       ▼ (Progress Tracking)
[ MILESTONES & KANBAN TASKS ]
       │
       ▼ (Completion Trigger)
[ INVOICES ENGINE ]
       │
       ▼ (Register Payment Intent)
[ CENTRAL PAYMENT GATEWAY HUB ] ── (Routes by currency) ──► [ Daraja / PayHero / Stripe / Paystack ]
       │
       ▼ (Normalized Callback Webhook Ingestion)
[ UNIFIED PAYMENT RECEIPTS MODEL ] ──► Reconciles ──► [ DOUBLE-ENTRY ACCOUNTS ]
```

---

## 10. User Roles & Permissions

JUANET operates under a strict Role-Based Access Control (RBAC) model combined with Row-Level Security (RLS) policies.

```
                  ┌─────────────────────────────────┐
                  │           SUPER ADMIN           │
                  │  (Global System Control / RLS)  │
                  └────────────────┬────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│ PROJECT MANAGER │       │ FINANCE OFFICER │       │ CLIENT ADMIN    │
│ (Assigns Tasks) │       │ (Ledger Sync)   │       │ (Approves Work) │
└────────┬────────┘       └─────────────────┘       └────────┬────────┘
         │                                                   │
         ▼                                                   ▼
┌─────────────────┐                                 ┌─────────────────┐
│ DEVS & DESIGNERS│                                 │ CLIENT USER     │
│ (Logs Hours)    │                                 │ (Submits Files) │
└─────────────────┘                                 └─────────────────┘
```

### Permission Matrix

| Module | Super Admin | Project Manager | Developer / Designer | Client Admin | Client User |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **CRM Pipelines** | Full (CRUD) | Read-Only | None | None | None |
| **Proposals** | Full (CRUD) | Manage | None | Sign / Approve | Read-Only |
| **Projects** | Full (CRUD) | Full (CRUD) | View Assigned | View Assigned | View Assigned |
| **Milestones** | Full (CRUD) | Full (CRUD) | Update State | Approve | View |
| **Tasks & Hours** | Full (CRUD) | Manage | CRUD (Assigned) | None | None |
| **Finance Ledger**| Full (CRUD) | None | None | None | None |
| **Invoices** | Full (CRUD) | Read-Only | None | View & Pay | View |
| **Support Tickets**| Full (CRUD)| View | None | CRUD | CRUD |
| **Marketplace Store**| Full (CRUD)| None | None | Purchase | Purchase |
| **System Settings**| Full (CRUD)| None | None | Manage Company | View |

---

## 11. State Machines & Lifecycles

```
1. PROPOSAL LIFECYCLE
   [ Draft ] ──(Send)──> [ Sent ] ──(Open)──> [ Viewed ] ───┬──(Accept)──> [ Accepted ] (Auto-provisions Project)
                                                            ├──(Decline)─> [ Declined ]
                                                            └──(Timeout)─> [ Expired ]

2. PROJECT LIFECYCLE
   [ Scoping ] ──(Kickoff)──> [ Active ] ───┬──(Pause)───> [ Paused ] ──(Resume)──> [ Active ]
                                            ├──(Complete)─> [ Completed ]
                                            └──(Terminate)> [ Terminated ]

3. INVOICE LIFECYCLE
   [ Unpaid ] ───┬──(Partial payment)──> [ Partially Paid ] ──(Balance paid)──> [ Paid ]
                 ├──(Full payment)─────> [ Paid ]
                 └──(Admin action)─────> [ Voided ]

4. SUPPORT TICKET LIFECYCLE
   [ Open ] ──(Assign Staff)──> [ In Progress ] ──(Propose Solution)──> [ Resolved ] ──(Close)──> [ Closed ]
```

---

## 12. System Architecture

```
+-----------------------------------------------------------------------------------+
|                                PRESENTATION LAYER                                 |
|               React Single-Page Applications (Client/Admin Portals)               |
+-----------------------------------------------------------------------------------+
                                          | (HTTPS / WSS)
                                          v
+-----------------------------------------------------------------------------------+
|                                 APPLICATION LAYER                                 |
|                       Laravel Routing Controllers & Middlewares                   |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                                   DOMAIN LAYER                                    |
|             Services: AI, Billing, Notification, Project Delivery Engines          |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                                INFRASTRUCTURE LAYER                               |
|                     PostgreSQL Database (Row-Level Security / RLS)                |
+-----------------------------------------------------------------------------------+
```

### Layer Responsibilities
* **Presentation Layer**: Captures user input, handles client routing transitions via custom Motion slide-ins, and manages state reactivity.
* **Application Layer**: Sanitizes input parameters, decodes JWT user payload structures, and enforces rate limit controls.
* **Domain Layer**: Implements core business logic. No raw DB calls are allowed here; all computations must utilize service wrappers.
* **Infrastructure Layer**: Executes structured database queries, manages storage buckets, and establishes external HTTP client payloads (Daraja/Gemini).

---

## 13. System Event Catalogue

All async operations on JUANET are governed by real-time events.

| Event Key | Triggering Source | Payload Data | Downstream System Action |
| :--- | :--- | :--- | :--- |
| `lead.converted` | Admin clicks "Convert" | `lead_id`, `client_email` | Generates a new Proposal template matching lead metrics. |
| `proposal.approved` | Client signs proposal | `proposal_id`, `client_id` | Provision new Project records, milestone templates, and Invoice `INV-001`. |
| `invoice.created` | Milestone marked completed | `invoice_id`, `amount`, `client_id` | Dispatches formal Invoice via SMTP and schedules a weekly payment reminder queue job. |
| `payment.received` | Safaricom webhook verified | `mpesa_receipt`, `checkout_id` | Adjust double-entry bookkeeping ledger, flag Invoice "Paid", dispatch Slack notification. |
| `milestone.completed`| PM approves milestones | `milestone_id`, `project_id` | Recalculate Project total progress percentage. If final, flag Project complete. |
| `ticket.message` | Support client submits message | `ticket_id`, `message_text` | Sends active alert notification to assigned support engineer. |

---

## 14. Notification Catalogue

| Event Context | Recipient Target | Notification Channel | Dynamic Template Payload |
| :--- | :--- | :--- | :--- |
| **New Invoice** | Client Admin | In-app Portal + SMTP Email | "An invoice INV-{{invoice_number}} of KES {{amount}} is due on {{due_date}}." |
| **Payment Success** | Client & Super Admin | In-app Portal + SMTP Email | "Payment of KES {{amount}} cleared. Safaricom Receipt: {{receipt}}." |
| **Milestone Completed**| Client Admin | In-app Portal + Push Alert | "Milestone '{{title}}' is now under review. Please approve to clear invoicing." |
| **Critical Support Ticket**| Assigned Agent | Push Alert + SMS Message | "Critical Support Ticket #{{id}} has been filed by {{client_company}}." |
| **Workflow Run Error** | Super Admin | Push Alert + SMTP Email | "Workflow Run Error: Automation '{{name}}' failed on step {{step}}." |

---

## 15. File Storage Standards

JUANET uses secure cloud storage disks (S3, MinIO, or local) managed through Laravel's native Storage system, protecting assets under strict access control boundaries.

### Storage Buckets List
1. `contracts`: Holds client NDAs, signed proposals, and scope documents.
   * *Permissions*: Private. Only accessible by company owners, system admins, and linked signatories.
   * *Retention*: Infinite. Legal retention requirement.
2. `project-files`: Active project deliverables, code revisions, and design feedback mocks.
   * *Permissions*: Read access for assigned staff and project clients. Write permission for project workspace members.
   * *Retention*: Retained up to 2 years post-project-termination before cold archiving.
3. `portfolio-assets`: Public graphics, company logos, blog feature images, and testimonials branding.
   * *Permissions*: Public. All users can read. Write access is restricted to marketing and admin.
   * *Retention*: Permanent.
4. `product-downloads`: Purchased digital store assets, scripts, and document templates.
   * *Permissions*: Private. Accessible via expiring sign links issued only upon verified M-PESA payment.

### Security Controls
* **File Types Allowed**:
  * Documents: `.pdf`, `.docx`, `.xlsx`
  * Imagery: `.png`, `.jpg`, `.webp`, `.svg`
  * Archives: `.zip` (restricted to marketplace product deliverables)
* **Maximum Upload Limit**: 50MB per single file payload for client documents. 500MB for product downloads.
* **Virus Filter**: All file streams landing on storage servers trigger background antivirus scans.

---

## 16. Deployment Architecture

### Server Environment (VPS + CloudPanel)
* **Web Server**: Nginx acts as the front-end reverse proxy, handling SSL validation and request routing.
* **Process Manager**: PHP-FPM, Laravel Queue Workers, and Docker Compose manage runtime execution and background jobs for Laravel Core services.
* **Reverse Proxy Configuration**:
  * External port `443` routes directly to internal target port `80` or containerized service ports for primary API operations.
  * Static compiled assets are cached directly at the proxy layer using long-term expiration headers.

### High Availability Scaling Strategy
* **Core Application Scaling**: Deploy stateless Laravel containers behind an Nginx load balancer.
* **Database Scaling**: Read replication clusters separating analytics queries from live payment processing pipelines.

---

## 17. Security Architecture

### Authentication & JWT Security
* All user authentication records are handled by cryptographically signed JWT strings.
* Session tokens utilize secure HTTP-only cookies to eliminate Cross-Site Scripting (XSS) vectors.

### Row-Level Security (RLS) Philosophy
* Every SQL table mapping client-authored details must carry an `organization_id` constraint.
* Security rules verify tenancy before returning queries:
  ```sql
  CREATE POLICY "Tenant Isolation" ON public.projects
    FOR SELECT USING (organization_id = auth.user_tenant_id());
  ```

### Additional Controls
* **Input Sanitization**: Strictly validate REST payloads via structured schema parsing tools (e.g. Zod).
* **Rate Limiting**: Limit API routes to 100 requests per minute from a single IP to mitigate DDoS vectors.
* **CORS Settings**: Restrict CORS origins strictly to designated platform subdomains.

---

## 18. AI Architecture

```
+-----------------------------------------------------------------------------------+
|                                 AI API GATEWAY                                    |
|            Manages Provider Registries, Switchers, and Prompt Blueprints          |
+-----------------------------------------------------------------------------------+
           | (Request Routed)                             | (Token Exceeded)
           v                                              v
+------------------------------------+          +-----------------------------------+
|         PRIMARY PROVIDER           |          |         FALLBACK ENGINE           |
|         Gemini 2.5 Flash           |          |         Gemini 2.5 Pro            |
+------------------------------------+          +-----------------------------------+
```

### Smart Memory & Versioning
* **Prompt Versioning**: Prompts are stored as individual database entities. Changes are revision-tracked to prevent service degradation.
* **Context Compression**: Conversation records are automatically summarized once the token length exceeds the buffer thresholds.

---

## 19. Automation Architecture

### The Visual Workflow Engine
Workflows process sequentially using three criteria:
1. **Triggers**: Event triggers (e.g., `ticket.created`, `invoice.overdue`).
2. **Conditions**: JSON rules (e.g., `ticket.priority == 'critical'`).
3. **Actions**: Execution steps (e.g., `smtp.send_email`, `notification.push`).

### Worker Queue System
* Async jobs are managed in the database using a custom background worker queue (e.g., `job_queue` table).
* Active retry loops are executed with exponential backoff delays.

---

## 20. API Lifecycle & Versioning Policy

### Core Conventions
All API endpoints follow strict REST guidelines:
* **GET** `/api/v1/projects`: Retrieve projects.
* **POST** `/api/v1/projects`: Create a project.
* **PUT** `/api/v1/projects/:id`: Update a project.
* **DELETE** `/api/v1/projects/:id`: Remove a project.

### Versioning Rules
1. **Incremental Changes**: Minor additions (e.g., adding a non-required attribute to a JSON response) are updated directly on `/v1` and documented in release logs.
2. **Major Overhauls (`/v2`)**: A major version bump is triggered when endpoint behaviors break backward-compatibility (e.g., deleting a key database entity mapping or restructuring standard nested lists).
3. **Deprecation Process**: When a new version is released, older routes continue executing for 6 months. Deprecated responses append a standard HTTP Header: `X-API-Deprecation-Date: YYYY-MM-DD`.

---

## 21. Production Environment Variables Reference

The following credentials map the necessary production keys of the JUANET system.

```env
# ==========================================
# SECTION 1: DATABASE & AUTHENTICATION
# ==========================================
DATABASE_URL=postgresql://postgres:[PASSWORD]@db.supabase.co:5432/postgres
SUPABASE_URL=https://[PROJECT-ID].supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# ==========================================
# SECTION 2: GOOGLE GEMINI AI
# ==========================================
GEMINI_API_KEY=AIzaSy...

# ==========================================
# ==========================================
# SECTION 3: ENTERPRISE PAYMENT GATEWAYS
# ==========================================
# Master credentials encryption key used for JSONB at-rest security
PAYMENT_ENCRYPTION_SECRET=sec_aes_gcm_sha256_09ca828...

# Local Kenya Gateways
MPESA_CONSUMER_KEY=v8fJG...
MPESA_CONSUMER_SECRET=Yx9G...
MPESA_PASSKEY=bfb279f9...
MPESA_SHORTCODE=174379
PAYHERO_API_KEY=ph_live_...

# Regional & Pan-African Gateways
PAYSTACK_SECRET_KEY=sk_live_55ab...
PAYSTACK_PUBLIC_KEY=pk_live_00ca...
PESAPAL_CONSUMER_KEY=pesa_...
PESAPAL_CONSUMER_SECRET=pesasec_...

# International Card Gateways
STRIPE_SECRET_KEY=sk_live_99ca...
STRIPE_WEBHOOK_SECRET=whsec_88bc...
PAYPAL_CLIENT_ID=pay_cli_...
PAYPAL_CLIENT_SECRET=pay_sec_...

# ==========================================
# SECTION 4: REAL-TIME SERVICES (SOCKETS)
# ==========================================
PUSHER_APP_ID=1827419
PUSHER_KEY=a7d239e...
PUSHER_SECRET=f98a23...
PUSHER_CLUSTER=ap2

# ==========================================
# SECTION 5: SMTP MAIL SERVICE
# ==========================================
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USER=postmaster@mg.juanet.cloud
SMTP_PASSWORD=mailgun_secret_pass
SMTP_SENDER_EMAIL=billing@juanet.cloud
```

---

## 22. Database Standards

Do NOT generate SQL.

### Key Guidelines
1. **Primary Keys**: Every database record must use a `UUID` primary key (`gen_random_uuid()`). Auto-incrementing integer keys are prohibited.
2. **Foreign Keys**: Enforce referential integrity using explicit `FOREIGN KEY` constraints.
3. **Organization Isolation**: Every table with tenant-authored data must include `organization_id uuid REFERENCES public.organizations(id) ON DELETE CASCADE`.
4. **Soft Deletes**: Use a `deleted_at` timestamp. Records are marked deleted instead of physically purged, protecting client records from accidental loss.
5. **Auditing Fields**: Every table must include `created_at` and `updated_at` timestamp fields.
6. **Indexes**: Generate index keys on all frequently queried foreign keys.

---

## 23. Logging & Monitoring

### Standard Log Types
1. **Security Logs**: Logs login attempts, IP changes, permission alterations, and token revocations.
2. **Double-Entry Ledger Logs**: Immutable database triggers logging ledger changes.
3. **AI Gateway Metrics**: Tracks prompt token usages, provider execution speed, and API cost allocation.
4. **Application Logs**: Tracks application errors, service health indexes, and external endpoint response times.

---

## 24. Testing Strategy

```
+-----------------------------------------------------------------------------------+
|                                  TEST SUITE                                       |
+-----------------------------------------------------------------------------------+
         │
         ├───> [ Unit Testing ]         # Verify utility helpers (MPESA checksums) via PHPUnit/Pest
         ├───> [ Integration Testing ]  # Validate double-entry ledger database states using Laravel RefreshDatabase
         └───> [ End-to-End (E2E) ]     # Automated client flows (checkout to download) via Playwright / Cypress
```

---

## 25. Error Handling Standards

### Layered Resolution
* **Database Layer**: Captures and handles foreign key constraint failures, and rolls back transactions on failure.
* **Backend Layer**: Sanitizes raw infrastructure database errors into secure API error logs.
* **Frontend Layer**: Gracefully catches network drops using local state caching and displays clean, descriptive error modals to the user.

---

## 26. Performance Standards

* **Client Caching**: Utilize state manager caching (e.g., React Query or Pinia) to limit redundant server roundtrips.
* **Assets Rendering**: All upload pipelines convert media to web-optimized formats before saving to storage buckets.
* **Database Query Optimization**: Implement database indexes on all foreign key constraints and limit paginated data returns to 50 records per page.

---

## 27. Future Roadmap

```
Phase 1: Architecture Hub & Master Specification (COMPLETED)
   │
   v
Phase 2: PostgreSQL Schema Layout, Indexes & RLS Configurations (NEXT)
   │
   v
Phase 3: Custom Laravel 12 API Architecture & Domain Bounded Contexts
   │
   v
Phase 4: Client and Admin Dashboard React Build
   │
   v
Phase 5: CloudPanel Host Setup, Laravel Queue Workers & Redis Optimization
   │
   v
Phase 6: Native iOS & Android Workspace Portals (React Native)
   │
   v
Phase 7: SaaS Subscription Packages & Global Corporate Tenancy Multi-Billing
```

---

## 28. Engineering Principles

* **Single Source of Truth**: System states must resolve from a single origin. Avoid duplicating parameters across components.
* **Don't Repeat Yourself (DRY)**: Abstract shared mathematical operations, date formatters, and security filters into centralized package utilities.
* **Security by Default**: All API endpoints must require explicit authorization unless verified as a public asset.
* **Mobile-First APIs**: Design API schemas to deliver fast responses on mobile devices.

---

## 29. Enterprise Payment Gateway Architecture

JUANET implements a production-grade, provider-agnostic centralized payment infrastructure capable of managing multiple local, regional, and international payment gateways concurrently. This modular architecture decouples core business logic from direct payment service provider (PSP) interfaces, assuring that any future gateway can be added as a plug-and-play adapter without altering the database schemas or invoicing state-machines.

### A. Architectural Layers

The architecture is split into four distinct conceptual boundaries:

1.  **Presentation Layer (Checkout & Admin Dashboard)**:
    *   **Customer Checkout Interface**: Dynamically renders eligible payment methods based on the transaction currency and country. Relies entirely on hosted payment fields (such as Stripe Elements) or Sim-toolkit STK prompts to maintain a zero-compromise PCI-DSS footprint.
    *   **SaaS Super Admin Panel**: Allows corporate operators to enable/disable specific gateway integrations, customize API sandbox credentials, set transaction timeouts, configure retry parameters, and monitor gateway health indices in real-time.
2.  **Core Application & Routing Layer (Central Gateway Manager)**:
    *   **Payment Intent Registrar**: Registers an agnostic transaction session (`payment_intents`) with an idempotent reference key to prevent double charging.
    *   **Intelligent Routing Engine**: Evaluates active parameters (Currency, MSISDN Country Code, Configured Gateway Priority, and Live PSP Status) to dispatch the transaction request to the highest scoring active adapter.
3.  **Adapter Interface Layer (Normalized Contracts)**:
    *   An abstract contract pattern (`GatewayProviderAdapter`) forcing all installed adapters to expose uniform, normalized methods. Decouples the core engine from provider-specific JSON parameters.
4.  **Provider Integration Network (External PSPs)**:
    *   External API gateways (Safaricom Daraja API, PayHero REST, Stripe Intent, Paystack Checkout, Pesapal Hub, PayPal SDK) executing payments, issuing receipts, or triggering webhook callbacks.

```
       [ Client Checkout UI ]                   [ SaaS Admin Configuration Panel ]
                 │                                               │
                 ▼                                               ▼
     ┌───────────────────────────────────────────────────────────────────────┐
     │                     CENTRAL PAYMENT GATEWAY MANAGER                   │
     │  - Allocates payment_intents reference                                │
     │  - Dynamically evaluates optimal route based on Currency & Country    │
     └──────────────────────────────────┬────────────────────────────────────┘
                                        │ (Dispatches to)
                                        ▼
     ┌───────────────────────────────────────────────────────────────────────┐
     │                 GATEWAY PROVIDER ADAPTER INTERFACE                    │
     │  - Enforces unified TypeScript signatures across all installations    │
     └──────┬───────────────────────────┬────────────────────────────┬───────┘
            │                           │                            │
            ▼                           ▼                            ▼
  [ Safaricom Daraja ]          [ Stripe Checkout ]          [ Paystack Africa ]
   (Local Mobile Money)        (Global Card Network)         (Regional Webhook)
```

---

### B. The Unified Payment Data Model

decoupling PSP fields from core financial tables is achieved by registering two normalized entities:

#### 1. Payment Intents (`payment_intents`)
Stores active transactional metadata while keeping a single tracking reference:
*   `id` (UUID): Unified system-wide tracking key.
*   `organization_id` (UUID): Supports multi-tenant branding and allocation.
*   `invoice_id` (UUID): Optional link to billing targets.
*   `gateway_id` (UUID): References the active `payment_gateways` configuration used.
*   `amount` (Numeric): Gross transaction cost.
*   `currency` (Varchar): ISO currency (e.g., KES, USD, EUR, NGN).
*   `payment_method` (Varchar): Normalised token representing the method (e.g. `mpesa_stk`, `card_visa`, `paypal`).
*   `provider_transaction_id` (Varchar): External reference ID returned by the PSP (e.g., CheckoutRequestID).
*   `reference_number` (Varchar): Unique, idempotent tracking code prefixed with currency and date metrics.
*   `status` (Varchar): State-machine value: `pending`, `processing`, `completed`, `failed`, `cancelled`, `refunded`.
*   `callback_payload` (JSONB): Raw metadata payload returned by the webhook for auditing and debugging.

#### 2. Payment Receipts (`payment_receipts`)
Stores immutable financial ledger data once signature checks clear:
*   `id` (UUID): Unique billing receipt ID.
*   `intent_id` (UUID): Link to verified intent records.
*   `receipt_number` (Varchar): Unique external receipt string (e.g., M-PESA receipt code or Stripe Charge ID).
*   `amount` (Numeric): Reconciled gross payment.
*   `fees` (Numeric): Transaction processing cost deducted by the PSP.
*   `net_amount` (Numeric): Net business revenue posted to the double-entry charts (`gross_amount - fees`).
*   `payer_identity` (Varchar): MSISDN or email associated with payment source.

---

### C. Gateway Provider Adapter Specification

Every payment gateway adapter must implement the complete `GatewayProviderAdapter` interface:

| Method Signature | Input Arguments | Output Model | Functional Domain |
| :--- | :--- | :--- | :--- |
| **`initializePayment`** | `intent: PaymentIntent`, `payer_meta: JSON` | `Promise<PSPResponse>` | Requests a gateway session, returns payment links, STK push responses, or checkout tokens. |
| **`verifyPayment`** | `provider_txn_id: string` | `Promise<NormalizedStatus>` | Actively polls external PSP APIs to update stale transactions. |
| **`receiveCallback`** | `headers: JSON`, `raw_payload: JSON` | `Promise<CallbackResult>` | Decodes incoming webhooks and verifies cryptographic signatures. |
| **`cancelPayment`** | `provider_txn_id: string` | `Promise<boolean>` | Explicitly aborts pending checkout sessions on external servers. |
| **`refundPayment`** | `provider_txn_id: string`, `amount: numeric` | `Promise<RefundResult>` | Triggers a secure transaction reversal and posts reversal balance offsets. |
| **`generatePaymentLink`** | `intent: PaymentIntent` | `Promise<string>` | Returns secure redirect URLs for hosted hosted payment flows. |

---

### D. Intelligent Routing Algorithm

When a payment is triggered, the routing engine calculates scores using the following linear scoring rules:

$$\text{Gateway Score} = 100 - \text{Priority Penalty} - \text{Currency Penalty} - \text{Status Penalty}$$

Where:
*   **Currency Check**: If the gateway supports the target currency, penalty is `0`. If not, penalty is `100` (disqualifying the gateway).
*   **Priority Penalty**: Managed via Super Admin. Calculated as $(Priority - 1) \times 10$. (Priority 1 is the primary router, Priority 2 gets a 10-point penalty, etc.).
*   **Status Penalty**: If gateway status is `healthy`, penalty is `0`. If `degraded`, penalty is `40`. If `offline`, penalty is `100`.

The engine automatically routes the payment session through the highest scoring eligible gateway, guaranteeing automatic fallback to secondary providers (e.g., PayHero if Daraja is degraded) without client-side interruption.

---

### E. Webhook Security & Integrity Protocols

JUANET enforces strict validation protocols before marking an invoice as paid:

1.  **Asymmetric Signature Check**: The incoming webhook header `x-webhook-signature` or hashing checksum is dynamically matched against the stored `webhook_secret` using HMAC SHA256. If signatures differ, the request is immediately rejected and logged under security audits.
2.  **Replay Attack Prevention**: Webhook headers must include a request timestamp. Webhook routers compare this timestamp against system time; differences exceeding 300 seconds are rejected.
3.  **Strict Idempotency Verification**: The ledger checks if the provider's transaction receipt or unique payload reference has already been committed to `payment_receipts`. If found, the webhook is immediately acknowledged as "processed" without duplicate ledger posting.
4.  **Data Credential Encryption**: Gateway secrets, credentials, and merchant passwords are encrypted before storage in PostgreSQL using AES-256-GCM using the master server variable `PAYMENT_ENCRYPTION_SECRET`.

---

### F. Step-by-Step Provider Expansion Strategy

To install a new payment provider (e.g., Flutterwave or a local regional bank API) in the future:
1.  **Step 1: Declare the Provider Code**: Add the provider key to the database `is_active` constraints (e.g., `flutterwave`). No changes to schemas or table columns are required.
2.  **Step 2: Code the Adapter**: Create a new class implementing the `GatewayProviderAdapter` interface (e.g., `src/services/payments/adapters/FlutterwaveAdapter.ts`). Code the specific mapping for initialization, status checking, and signature verification.
3.  **Step 3: Register in Gateway Registry**: Import the new adapter class in the Gateway Manager Registry (`src/services/payments/GatewayRegistry.ts`).
4.  **Step 4: Save Config in Admin Panel**: Save the new provider credentials, Webhook secret, and country/currency scopes through the Super Admin UI. The system is immediately live with the new gateway, and routing recalculations take effect on the next client checkout.

---

### End of Master Specification Document (Version 1.3)
