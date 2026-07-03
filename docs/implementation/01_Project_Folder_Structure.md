# JUANET Enterprise SaaS Platform — Project Folder Structure
## Phase 3.0.1 — System Architecture, Directory Layout & Domain Organization

**Document Version:** 1.0  
**Status:** Authoritative Implementation Blueprint  
**Target Runtime:** Laravel 12 / PHP 8.4 / Alpine.js / TailwindCSS / PostgreSQL (Supabase)  

---

## 1. ARCHITECTURAL LAYERING PRINCIPLE

To support the massive operational scale of the **JUANET Enterprise SaaS Platform** without incurring spaghetti-code technical debt, the Laravel codebase transitions from the standard default MVC structure to a **Domain-Driven Design (DDD) Hybrid Layered Architecture**. 

This design segregates framework-specific HTTP orchestration from core business logic. Code is organized into three major layers:
1. **Http Layer (`app/Http`)**: Orchestrates inbound HTTP requests, manages validation payloads, returns Blade views, and handles CORS and session cookies. No business logic lives here.
2. **Domain Layer (`app/Domain`)**: The authoritative system of record for business rules, aggregates, entities, value objects, domain events, and state transitions. It is completely framework-agnostic.
3. **Infrastructure Layer (`app/Infrastructure`)**: Integrates external systems, manages the Transactional Outbox Pattern, coordinates Supabase/PostgreSQL connections, handles Redis session stores, and runs MinIO S3-compatible clients.

---

## 2. THE COMPLETE ENTERPRISE DIRECTORY STRUCTURE

Below is the authoritative folder structure of the JUANET monorepo backend, detailing the exact location of every system component, utility, config, and script.

```text
/ (Workspace Root)
├── .env.example                     # Environment template for dev, staging, and production
├── .gitignore                       # Production build block list and secret guards
├── CHANGELOG.md                     # Semantic version history log
├── Dockerfile                       # Multi-stage production-grade PHP 8.4-FPM build file
├── Makefile                         # Unified dev command wrapper for Docker operations
├── README.md                        # Master developer onboarding guide
├── composer.json                    # Package definitions, scripts, and PHP 8.4 constraint mappings
├── composer.lock                    # Immutable package version lock file
├── docker-compose.yml               # Complete orchestration manifest (PHP, Nginx, Redis, MinIO, Mailpit)
├── phpstan.neon                     # PHPStan static analysis configuration (Level 9 Strict)
├── pint.json                        # Laravel Pint style rules matching PSR-12 and strict formatting
├── tailwind.config.js               # TailwindCSS theme tokens and Blade templates parser config
├── tsconfig.json                    # Typings support for Alpine.js front-end assets
├── vite.config.js                   # Vite configuration for parsing CSS and Alpine assets
│
├── app/                             # Core Application Code
│   ├── Console/                     # Scheduler Commands & Console Kernel
│   │   ├── Commands/                # Custom artisan CLI tool definitions
│   │   └── Kernel.php               # Crontab scheduler configuration
│   │
│   ├── Domain/                      # Core Domain Layer (DDD Domain Boundaries)
│   │   ├── Common/                  # Shared Domain Types & Value Objects
│   │   │   ├── ValueObjects/        # Immutable values (e.g. Money, Email, UUIDv7)
│   │   │   └── Contracts/           # Base contracts and abstract aggregates
│   │   │
│   │   ├── Auth/                    # Multi-Tenant Identity, JWT, & RBAC Domain
│   │   │   ├── Models/              # Database entities mapping auth profiles
│   │   │   ├── Actions/             # Self-contained business flows (e.g., RegisterUserTenant)
│   │   │   └── Events/              # Event declarations (e.g., TenantOnboarded)
│   │   │
│   │   ├── Marketplace/             # Marketplace Domain (Products, Orders, Reviews)
│   │   │   ├── ProductCatalog/      # Bounded Context: Product and Inventory
│   │   │   ├── Orders/              # Bounded Context: Purchases and Checkouts
│   │   │   └── Reviews/             # Bounded Context: Reviews, Ratings, and Reputation
│   │   │       ├── Models/          # Review, Appeal, MediaAttachment Eloquent entities
│   │   │       ├── Actions/         # CreateReview, ModerationOverlord, RecordHelpfulnessVote
│   │   │       ├── Events/          # Event definitions matching JSON contracts (review.created.v1)
│   │   │       ├── Services/        # BayesianCalculators, DecayReputationAlgorithms
│   │   │       └── Contracts/       # Repository interfaces
│   │   │
│   │   └── Billing/                 # Ledger, Commission, and Payout Domain (M-PESA)
│   │       ├── Actions/             # ProcessLedgerCredit, ProcessLedgerDebit
│   │       └── Services/            # DarajaMpesaBridge, InvoiceEngine
│   │
│   ├── Http/                        # HTTP Delivery Layer (Framework Orchestration)
│   │   ├── Controllers/             # Ultra-lean HTTP handlers (routing and response delivery)
│   │   │   ├── Api/                 # Public and Internal REST API controllers
│   │   │   │   └── V1/              # Versioned API routes
│   │   │   └── Web/                 # Blade views web controllers
│   │   ├── Middleware/              # TenantIsolation, JWTAccess, SecurityHeaders middleware
│   │   ├── Requests/                # Strict HTTP payload validation controllers
│   │   └── Responses/               # Unified XML and JSON envelope structures
│   │
│   ├── Infrastructure/              # Infrastructure Layer (Adapter Implementations)
│   │   ├── Auth/                    # Supabase JWT & OAuth verification drivers
│   │   ├── Bus/                     # Event bus adapters and outbox listeners
│   │   ├── Database/                # PostgreSQL/Supabase database client extensions
│   │   ├── Integration/             # Outbound third-party SDK bridges (MinIO, SMTP)
│   │   ├── Logging/                 # Custom enterprise audit logger handlers
│   │   ├── Outbox/                  # Transactional Outbox pattern engine
│   │   │   ├── Models/              # Outbox database records model
│   │   │   └── Services/            # OutboxPublisher, OutboxWorker
│   │   └── Queue/                   # Idempotency checks and consumer decorators
│   │
│   └── Providers/                   # Application Service Bootstrapping
│       ├── AppServiceProvider.php   # Core binding container and strict DB configurations
│       ├── EventServiceProvider.php # Event-to-listener registrations
│       ├── RouteServiceProvider.php # Domain-segregated route loader
│       └── TenancyServiceProvider.php # Dynamic multi-tenant configuration loader
│
├── bootstrap/                       # Application Bootstrapping and Environment Initialization
│   ├── app.php                      # Application routing and global middleware configurations
│   ├── cache/                       # Dynamic configuration caches (excluded from git)
│   └── providers.php                # Explicit Service Providers mappings list
│
├── config/                          # Centralized Framework Configuration Subsystem
│   ├── app.php                      # Locale, timezone, encryption key, and security definitions
│   ├── auth.php                     # Auth guards, user models, and token configurations
│   ├── cache.php                    # Redis clustering, cache stores, and TTL presets
│   ├── database.php                 # Supabase PostgreSQL, read-replicas, and credentials
│   ├── filesystems.php              # Local, S3 (MinIO), and cloud assets configurations
│   ├── logging.php                  # Logging levels, slack channels, audit logs, and log rotation
│   ├── queue.php                    # Redis queue connections, failures, and worker limits
│   └── services.php                 # Gateway endpoints (M-PESA, Mailer, AI endpoints)
│
├── database/                        # Database Versioning and Migrations Subsystem
│   ├── factories/                   # Data generation models for testing
│   ├── migrations/                  # Versioned physical SQL schema migrations
│   └── seeders/                     # Initial database seeding scripts
│
├── docker/                          # Docker Container Architecture Configurations
│   ├── nginx/                       # High-performance Nginx reverse-proxy files
│   │   └── conf.d/                  # Site virtual host directives
│   │       └── default.conf         # Custom Nginx virtual host with FastCGI directives
│   ├── php/                         # Custom PHP 8.4-FPM container modifications
│   │   ├── php.ini                  # Memory limits, execution timeouts, upload limits
│   │   └── zz-docker.conf           # PHP-FPM pool tuning configurations
│   ├── redis/                       # Redis production configurations
│   │   └── redis.conf               # Memory eviction rules and disk persistence
│   └── minio/                       # MinIO S3 emulator scripts and data paths
│
├── docs/                            # Unified Enterprise System Documentation Vault
│   ├── database/                    # Phase 2.3.2H physical specifications
│   └── implementation/              # Phase 3.0 construction records and deployment scripts
│
├── public/                          # Public Assets & Gateway Directory
│   ├── index.php                    # Main entry point for all HTTP requests
│   ├── css/                         # Compiled production-grade Tailwind CSS bundles
│   ├── js/                          # Alpine.js script bundles
│   └── storage/                     # Symbolic link to local storage bucket (if used)
│
├── resources/                       # User Interface Asset Directory
│   ├── css/                         # Source Tailwind CSS stylesheets
│   ├── js/                          # Alpine.js scripts and controllers
│   └── views/                       # Blade Templating System
│       ├── components/              # Modular UI components
│       ├── layouts/                 # Base master layout frames
│       └── pages/                   # Feature-specific Blade views
│
├── routes/                          # Framework Routing Subsystem
│   ├── api.php                      # High-throughput REST API definitions (JWT secured)
│   ├── channels.php                 # Real-time WebSocket subscriptions definitions
│   ├── console.php                  # Schedule intervals declarations and commands
│   └── web.php                      # Human-facing web routes (cookie-session secured)
│
└── tests/                           # Testing & Validation Framework (Pest/PHPUnit)
    ├── TestCase.php                 # Base class providing shared system setup states
    ├── Feature/                     # System API and integration test suites
    ├── Unit/                        # Pure domain logic and mathematical calculations tests
    └── Pest.php                     # Pest helpers, global traits, and custom assertions
```

---

## 3. DIRECTORY SEGREGATION RULES & DISCIPLINE

To preserve architectural clarity as the development team grows, the following segregation policies are enforced:

### 3.1 Domain vs. Http Separation
* **Controllers must remain thin**: A controller is strictly responsible for capturing input, invoking a single **Domain Action**, and returning an **HTTP Response** wrapper. 
* **Zero business logic in Http Controllers**: Computations, state mutations, and query validations are forbidden in controllers. They must live in a domain-level Action (e.g. `app/Domain/Marketplace/Reviews/Actions/CreateReview.php`).
* **Models cannot trigger side-effects**: Eloquent models inside `app/Domain/*/Models/` are standard mapping layers. Global side-effects (such as dispatching events or recalculating vendor averages) must be executed by Domain Actions or Services, not implicitly inside Eloquent model lifecycle hooks (`saving`, `created`).

### 3.2 Infrastructure Autonomy
* External clients (e.g. MinIO S3 SDK, SMTP clients, payment integrations) must be abstracted behind clean interface contracts under `app/Domain/*/Contracts/`. 
* The actual implementation of these interfaces must live inside `app/Infrastructure/`. This allows the platform to switch its physical storage backend (e.g., from MinIO to Amazon S3 or Google Cloud Storage) without modifying a single line of domain or controller code.

### 3.3 Transactional Outbox Pattern
* Domain events are never dispatched directly to public message queues within a HTTP request loop. 
* Events are written to the `public.marketplace_event_outbox` table in the same database transaction as the business write. A separate background worker (`app/Infrastructure/Outbox/Services/OutboxWorker.php`) continuously monitors this table and dispatches pending events asynchronously. This guarantees **At-Least-Once delivery** and avoids distributed commit failures.

---

## 4. COMPLIANCE & BEST PRACTICES CHECKLIST

* [x] **No Multi-Framework Pollution**: Strictly PHP 8.4 and Laravel 12. No React/Vue/Inertia files permitted under `resources/`.
* [x] **Strict Domain Isolation**: Core Bounded Contexts are encapsulated inside separate sub-folders under `app/Domain/`. No cross-domain imports of mutable states.
* [x] **Strict Type Constraints**: Every directory is prepared for fully typed PHP 8.4 enforcement. Every single PHP file created under this structure MUST declare `declare(strict_types=1);` at its very beginning.
