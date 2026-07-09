# JUANET Enterprise SaaS Platform

## 🪐 Executive Summary & Overview
The **JUANET Enterprise SaaS Platform** (internally designated as **JUANET EOS** - Enterprise Operating System) is a high-performance digital agency operating system designed to automate lead generation, project scoping, milestone delivery, payment routing, client support ticketing, and double-entry ledger bookkeeping in a secure, tenant-isolated cloud architecture.

JUANET bridges public brand presence, transactional commerce, client coordination, and backend accounting into a single, cohesive multi-tenant workspace. This eliminates tool sprawl and integrates business processes into a centralized double-entry ledger, providing real-time financial and operational truth.

---

## 🎯 Project Vision & Mission
*   **Vision**: To empower modern enterprises, agencies, and freelance networks with an integrated operating system that seamlessly handles lead funnels, client proposals, agile milestones, financial clearing, support workflows, and AI assistance.
*   **Mission**: To eliminate operational friction by unifying fragmented tools (CRM, billing, project tracking, contracts, support, and ledger bookkeeping) into a high-performance, secure, and intuitive web platform.
*   **Tenets & Core Goals**:
    1.  **Zero-Trust Isolation**: Robust database and access policies ensuring absolute data privacy between organizations.
    2.  **Unified Ledger Integrity**: Every financial transaction maps automatically to a double-entry ledger.
    3.  **Low Latency & High Performance**: Clean execution boundaries and caching rules that guarantee sub-second interaction speed.
    4.  **Generative AI Productivity**: Leverage Google Gemini AI to assist with content creation, support auto-routing, and automated proposal generation.

---

## 🛠 Technology Stack & Core Specifications

The platform is designed with a modern, full-stack, enterprise-grade architecture:

### Software & Services Stack
*   **Laravel Framework**: `v12.0` (leveraging modern PHP features, enhanced type-safety, and Laravel's container model).
*   **PHP Version**: `^8.4` (utilizing strict typing, readonly classes, constructor property promotion, and enums).
*   **Node.js**: `v20.x+` (powering the Vite asset-bundling toolchain and dev servers).
*   **PostgreSQL**: `v15+` (used for strong ACID guarantees, JSONB query capabilities, and transactional reliability).
*   **Docker Containerization**: Full multi-container composition for local development and production orchestration.
*   **Supabase Client**: Auth, Row-Level Security (RLS) policies, and Secure Object Storage buckets.
*   **Redis Engine**: Used as the primary High-Performance Cache, Session State Store, and Queue broker.
*   **Queue System**: Laravel Queues powered by Redis with dedicated, prioritized workers (`high`, `default`, `low`).
*   **EventBus**: Custom decoupled in-memory and database-backed message bus coordinating asynchronous workflows.
*   **Daraja M-PESA API**: Real-time STK pushes, balance queries, and instant C2B payment routing with automated callback reconciliation.
*   **Google Gemini AI**: Powered by the official Google Gen AI SDK (`@google/genai`) for secure, server-side content generation.

### Architecture Patterns
*   **Domain-Driven Design (DDD)**: Core business processes are isolated into bounded contexts within `/app/Domain`.
*   **Hexagonal Architecture**: Absolute decoupling of business rules (core domains) from external infrastructure (delivery channels, databases, email gateways, or payment providers).
*   **Repository Pattern**: Standardized read/write persistence interfaces, shielding domains from raw Eloquent configurations.
*   **CQRS Lite (Command Query Responsibility Segregation)**: Distinct structures for read-optimized dashboards (fast queries) and write-optimized transactions (commands).
*   **Transactional Outbox**: Guaranteed message delivery to queue workers, preventing distributed system message loss.
*   **Multi-Tenant Database Isolation**: Tenancy managed via custom database scope patterns and tenant-specific database routing boundaries.

---

## 📂 Project Structure Directory Map

JUANET strictly separates presentation, application routing, domain logic, and infrastructure persistence to maintain high maintainability.

```
/
├── app/                        # Main Application Codebase
│   ├── Domain/                 # Bounded Contexts (Core Business Logic)
│   │   ├── CRM/                # Lead Funnels, Contacts, and Pipeline Controllers
│   │   ├── Contract/           # Proposal Legal Templates, Signed NDAs, PDF Assets
│   │   ├── Finance/            # General Ledgers, Invoices, Expenses, Tax Calculations
│   │   ├── Marketplace/        # Digital Downloads, Pricing Tiers, Purchases
│   │   ├── Notification/       # Multi-Channel Alert Routing Engine
│   │   ├── Project/            # Gantt Timelines, Agile Milestones, Kanban Cards
│   │   ├── Proposal/           # Client Proposal Builders and State Machines
│   │   ├── Shared/             # Shared Value Objects (Money, Currency, Audit Logs)
│   │   └── Workforce/          # Employee Timesheets, Leave Routing, Collaboration
│   ├── Http/                   # Global Middleware, Central Request Validation, Exceptions
│   ├── Infrastructure/         # Concrete Adapters (Supabase, M-PESA API, Gemini Client, Mailgun)
│   ├── Repositories/           # Query abstraction layer implementing domain interfaces
│   └── Services/               # Complex multi-domain operational orchestrators
├── bootstrap/                  # Framework boot settings
├── config/                     # Structured configuration files (database, cache, queues)
├── database/                   # Migrations, factories, and seeders
├── resources/                  # Blade templates, JS controllers, Tailwind components
├── routes/                     # Central Routing: web.php, api.php, console.php
├── src/                        # React Frontend Core (Vite-bundled SPA)
│   ├── components/             # React visual components and domain widgets
│   ├── data/                   # Static dashboard maps, visual constants, types
│   ├── hooks/                  # Custom React hooks managing local states and APIs
│   ├── lib/                    # JS SDK connectors (Supabase, Gemini client wrapper)
│   └── App.tsx                 # Core Dashboard Router and Navigation Frame
├── tests/                      # Automated test suite (PHPUnit, Pest, Feature Tests)
└── storage/                    # Local storage, cache logs, and file attachments
```

---

## 📦 Core Domain Modules & Capabilities

### 1. Unified Authentication & Identity
*   Uses Laravel Sanctum combined with Supabase Auth to protect access.
*   Enforces strict Role-Based Access Control (RBAC) across Super Admin, Project Manager, Employee, and Client roles.

### 2. CRM & Pipeline Tracking
*   Auto-captures public leads into an interactive sales pipeline.
*   Includes automated qualification engines, company tracking, activity timelines, and contact managers.

### 3. Marketplace Store & Digital Assets
*   An online store that allows clients to buy digital products, documents, or developer licenses.
*   Protects product files using secure, expiring download URLs generated only after verified payment.

### 4. Visitor Intelligence & Analytics
*   Provides cookie-less tracking of public page visits, click heatmaps, bounce rates, and traffic sources.
*   Bridges visitor profiles directly to the CRM when they submit contact forms.

### 5. Milestone-Based Project Delivery
*   Agile project workspace featuring Gantt charts, interactive Kanban boards, and timesheets.
*   Automatically notifies clients when a milestone is ready for review and sign-off.

### 6. Client Portal & Support Desk
*   A client portal where customers can view project progress, pay invoices, and download files.
*   Includes an SLA-driven support ticket desk with priority routing and chat features.

### 7. Electronic Proposals & Contracts
*   A proposal engine that supports visual document builders and e-signatures.
*   Signing a proposal automatically provisions the associated client organization, project workspace, and first invoice.

### 8. Centralized Notification Center
*   Coordinates alerts across multiple channels: SMTP email, SMS, push notifications, and Slack hooks.
*   Provides users with granular control over their notification preferences.

### 9. Workforce & Leave Management
*   Internal HR portal that handles employee records, work logs, leave requests, and performance tracking.
*   Features structured approval flows for team leaders and HR managers.

### 10. Enterprise Finance Core & Double-Entry Ledger
*   A multi-currency billing engine that handles invoice generation, expenses, tax rates, and recurring bills.
*   Reconciles all cash flows automatically via an immutable double-entry general ledger.

### 11. Public-Facing Portal & CMS
*   A fast, SEO-optimized public website with a built-in markdown blog and landing page generator.

---

## 🛠 Docker Orchestration Setup

The recommended way to run JUANET EOS locally is using the optimized Docker Compose environment. This starts all required services with correct configurations.

### Prerequisites
*   Docker Desktop (v24.0 or newer)
*   Docker Compose V2

### 1. Build & Run Services
To spin up all services (PostgreSQL, Redis, Laravel, Vite, MinIO, Mailpit) in the background:
```bash
docker compose up -d
```

### 2. View Active Container Status
```bash
docker compose ps
```

### 3. Stream System Logs
```bash
docker compose logs -f
```

### 4. Rebuild Container Assets
If package.json or composer.json packages change, rebuild the images:
```bash
docker compose build --no-cache
```

### 5. Tear Down Environment
Stop and remove all containers, networks, and shared volumes:
```bash
docker compose down -v
```

### Docker Services Reference:
*   **laravel.test**: Port `8080` (handles PHP, Apache, and Laravel runtime API routing).
*   **vite**: Port `3000` (Vite dev server with HMR enabled).
*   **postgres**: Port `5432` (database container).
*   **redis**: Port `6379` (cache and queue manager).
*   **minio**: Port `9000` (local S3 alternative) / Console on `9001`.
*   **mailpit**: Port `1025` (SMTP mock server) / Web Dashboard on `8025`.

---

## 💻 Manual Setup (Without Docker)

If you prefer to run the application on your local machine using native runtimes, follow this step-by-step guide.

### Prerequisites
*   **PHP**: `v8.4` or newer (with extensions: `pdo_pgsql`, `redis`, `gd`, `zip`, `xml`, `mbstring`)
*   **Node.js**: `v20.x+` (with `npm` package manager)
*   **PostgreSQL**: `v15+` (configured with a local database named `juanet_platform`)
*   **Redis Server**: Running locally on default port `6379`

### Step 1: Clone & Access Project
```bash
git clone https://github.com/juanetsolutions-code/juanet-erp.git juanet-platform
cd juanet-platform
```

### Step 2: Install Composer Backend Dependencies
```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
```

### Step 3: Install Node Frontend Dependencies
```bash
npm install
```

### Step 4: Configure Environment Variables
Copy the template configuration to generate your local configuration:
```bash
cp .env.example .env
```
*Open `.env` in your text editor and update database credentials, Redis connections, and external API keys.*

### Step 5: Generate Cryptographic App Key
```bash
php artisan key:generate
```

### Step 6: Create Storage Simlink
```bash
php artisan storage:link
```

### Step 7: Run Database Migrations & Seeders
Build the database tables and seed them with default admin records and system constants:
```bash
php artisan migrate --seed
```

### Step 8: Start Services

Run these commands in separate terminal sessions:

*   **Laravel Local Server**:
    ```bash
    php artisan serve --port=8080
    ```
*   **Vite Frontend Dev Tool**:
    ```bash
    npm run dev
    ```
*   **Queue Worker Engine**:
    ```bash
    php artisan queue:work --queue=high,default,low --tries=3
    ```
*   **Laravel Cron Scheduler**:
    ```bash
    php artisan schedule:work
    ```

Now open [http://localhost:3000](http://localhost:3000) in your browser to access the platform.

---

## 🧪 Running Automated Tests

JUANET uses a Pest and PHPUnit testing workflow to verify core domain states and ledger balance calculations.

### Run All Tests
```bash
php artisan test
```

### Run a Specific Test File
```bash
php artisan test tests/Feature/EnterpriseFinanceBillingTest.php
```

### Filter and Run Specific Test Scenario
```bash
php artisan test --filter=payments_transition_invoice_status
```

---

## ⚡ Asynchronous Architecture & Queues

JUANET offloads slow, external operations to Redis queue workers to maintain sub-second response times.

1.  **Queue Workers**: Run `php artisan queue:work` to consume tasks from Redis.
    *   `high`: Processes M-PESA payment receipts and user login sessions.
    *   `default`: Handles PDF invoice generation, CRM actions, and proposal updates.
    *   `low`: Sends promotional newsletters and builds system logs.
2.  **Scheduler**: Laravel's Task Scheduler runs background jobs like:
    *   Evaluating overdue invoice penalties (daily).
    *   Processing recurring monthly subscription billing templates (daily).
    *   Cleaning expired login tokens (hourly).
3.  **Transactional Outbox**: To guarantee event reliability, events are stored in the database (`outbox_events` table) before being dispatched, preventing data loss if an external queue manager goes down.

---

## 🚀 Production Deployment Guidelines

When deploying JUANET to production (such as a Linux VPS using CloudPanel, Laravel Forge, or Docker Compose), follow these best practices:

1.  **Web Server (Nginx)**: Configure Nginx to forward external traffic on port `443` to local port `3000` (Vite client SPA) and pass API requests on `/api/*` directly to port `8080` (Laravel).
2.  **Process Monitoring**: Use **Supervisor** to manage queue workers and keep them running continuously:
    ```ini
    [program:juanet-worker]
    process_name=%(program_name)s_%(process_num)02d
    command=php /var/www/juanet/artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --max-time=3600
    autostart=true
    autorestart=true
    stopasgroup=true
    killasgroup=true
    user=www-data
    numprocs=4
    redirect_stderr=true
    stdout_logfile=/var/www/juanet/storage/logs/worker.log
    ```
3.  **Cron Scheduler**: Add a single cron entry to your production server to trigger the background schedule:
    ```cron
    * * * * * cd /var/www/juanet && php artisan schedule:run >> /dev/null 2>&1
    ```
4.  **OPcache & Optimization**: Run optimization tasks in your deployment pipeline to speed up PHP execution:
    ```bash
    composer install --no-dev --optimize-autoloader
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

---

## 🔍 Troubleshooting Guide

### 1. PHP/Composer Dependency Collisions
*   *Issue*: PHP memory limit exhausted during installation.
*   *Fix*: Run composer with an unlimited memory setting:
    ```bash
    COMPOSER_MEMORY_LIMIT=-1 composer install
    ```

### 2. Node/Vite Port 3000 Collision
*   *Issue*: Vite fails to start because port 3000 is already in use.
*   *Fix*: Release port 3000 or configure Vite to use another port in `vite.config.ts`.
    ```bash
    sudo kill -9 $(lsof -t -i:3000)
    ```

### 3. Redis Connection Failures
*   *Issue*: Laravel throws connection errors when reaching out to Redis.
*   *Fix*: Verify the Redis server is active using `redis-cli ping` and check that the `REDIS_HOST` in `.env` matches your running container name or local IP.

### 4. Storage Bucket Access Denied
*   *Issue*: Uploaded files return 404 or access denied errors.
*   *Fix*: Verify that storage directories are writable by the server user (`chmod -R 775 storage bootstrap/cache`) and make sure to run `php artisan storage:link`.

---

## 📝 License
This platform is proprietary intellectual property. Unauthorized copying, distribution, or modifications are strictly prohibited.
