# JUANET Enterprise SaaS Platform — Complete Repository File Tree

This document provides a comprehensive, complete, and exhaustive tree of the JUANET repository files as of July 2026.

---

## 1. ROOT LEVEL CONFIGURATIONS & RUNTIMES

```text
/ (Workspace Root)
├── .dockerignore                     # Docker ignore rules
├── .editorconfig                     # Coding style editor settings
├── .env.example                      # Environment template for local/dev/prod environments
├── .gitattributes                    # Git path attributes
├── .gitignore                        # Git ignore patterns (ignores vendor/, node_modules/, dist/)
├── DEVELOPMENT_Dockerfile            # Custom multi-stage Docker development configuration
├── Dockerfile                        # Multi-stage production PHP 8.4-FPM container builder
├── Makefile                          # Unified build commands and Docker utility wrapper
├── PRODUCTION_Dockerfile             # Custom production Docker configuration
├── README.md                         # Authoritative repository introduction
├── backup.sh                         # System-level backup automation script
├── composer.json                     # PHP/Laravel package manifest & constraints
├── deploy.sh                         # Production VPS deployment automation script
├── docker-compose.development.yml    # Docker services orchestration for dev (Redis, DB, etc.)
├── docker-compose.production.yml     # Production-grade Docker Compose manifest
├── docker-compose.yml                # Main Docker Compose orchestration manifest
├── index.html                        # Vite / React index HTML document
├── metadata.json                     # AI Studio Applet capabilities and name configurations
├── package-lock.json                 # Frontend package lock file
├── package.json                      # Node.js dependencies, scripts, and server configuration
├── production.env.example            # Production environment variables guide
├── rollback.sh                       # Deployment rollback automation utility script
├── server.ts                         # Custom dev/production Node.js backend & proxy server
├── tsconfig.json                     # Global TypeScript compiler options
└── vite.config.ts                    # Vite compilation config for React and asset bundling
```

---

## 2. APP LAYER (`/app`)

The app folder contains the core backend implementation of the system, designed around standard **Domain-Driven Design (DDD)** patterns.

```text
app/
├── Contracts/                             # System-wide Interface Contracts
│   ├── AiPromptInterface.php
│   ├── CmsPageInterface.php
│   ├── CrmLeadInterface.php
│   ├── EventBus.php
│   ├── FinanceInvoiceInterface.php
│   ├── MarketplaceListingInterface.php
│   ├── ProjectTaskInterface.php
│   └── SupportTicketInterface.php
│
├── Domain/                                # DDD Domain Boundaries & Bounded Contexts
│   ├── CRM/                              # Customer Relationship Management Domain
│   │   ├── Activities/                  # Activity & Timeline Engine
│   │   │   ├── Contracts/               # Activity Repository contracts
│   │   │   ├── DTO/                     # Data Transfer Objects
│   │   │   ├── Enums/                   # ActivityPriority.php, ActivityType.php
│   │   │   ├── Events/                  # Activity domain events (Created, Completed, etc.)
│   │   │   ├── Jobs/                    # ProcessReminderJob.php
│   │   │   ├── Models/                  # Activity.php, ActivityAttachment.php, ActivityNote.php, ActivityReminder.php
│   │   │   ├── Notifications/           # ReminderNotification.php
│   │   │   ├── Policies/                # ActivityPolicy.php
│   │   │   ├── Repositories/            # ActivityRepository.php
│   │   │   ├── Resources/               # TimelineResource.php, ActivityResource.php
│   │   │   └── Services/                # TimelineEngine.php, ActivityService.php, ReminderQueueManager.php
│   │   │
│   │   ├── Contracts/                   # Base repositories contracts for CRM
│   │   │   ├── CompanyRepositoryInterface.php
│   │   │   ├── ContactRepositoryInterface.php
│   │   │   ├── LeadRepositoryInterface.php
│   │   │   ├── OpportunityRepositoryInterface.php
│   │   │   └── PipelineRepositoryInterface.php
│   │   │
│   │   ├── Controllers/                 # REST & API Handlers for CRM Context
│   │   │   ├── Api/                     # Company, Contact, Lead, Opportunity, Activity Api Controllers
│   │   │   └── Web/                     # CrmWebController.php
│   │   │
│   │   ├── Events/                      # 50+ Specialized Enterprise Domain Events
│   │   │   ├── LeadCaptured.php, LeadAssigned.php, LeadStatusChanged.php, LeadConverted.php, LeadDeleted.php
│   │   │   ├── ContactCreated.php, ContactUpdated.php, ContactDeleted.php, ContactConsentChanged.php
│   │   │   ├── CompanyCreated.php, CompanyUpdated.php, CompanyDeleted.php
│   │   │   ├── OpportunityCreated.php, OpportunityUpdated.php, OpportunityStageChanged.php
│   │   │   └── BehaviorProfileUpdated.php, PurchaseIntentDetected.php, PageViewed.php
│   │   │
│   │   ├── Models/                      # Eloquent Entities mapping physical PostgreSQL schemas
│   │   │   ├── Lead.php, LeadSource.php, LeadActivity.php, LeadStatusHistory.php, LeadAssignmentHistory.php
│   │   │   ├── Contact.php, ContactAddress.php, ContactMethod.php, ContactRelationship.php, ContactConsent.php
│   │   │   ├── Company.php, CompanyLocation.php, ContactCompanyAssociation.php
│   │   │   ├── Opportunity.php, OpportunityProduct.php, Pipeline.php, PipelineStage.php
│   │   │   ├── Visitor.php, VisitorSession.php, VisitorPageView.php, VisitorBehaviorProfile.php
│   │   │   └── Tag.php, CustomField.php, Industry.php
│   │   │
│   │   ├── Observers/                   # Observers for Audit Logs (Lead, Contact, Company, Opportunity, Pipeline)
│   │   ├── Policies/                    # CRM Security policies
│   │   ├── Repositories/                # LeadRepository, ContactRepository, CompanyRepository, OpportunityRepository, PipelineRepository
│   │   ├── Requests/                    # Strict Payload validation requests
│   │   ├── Resources/                   # API Serialization resources
│   │   └── Services/                    # Core business logic orchestrators
│   │       ├── LeadService.php, LeadStateMachine.php, LeadScoringEngine.php, LeadAssignmentService.php
│   │       ├── ContactService.php, ContactMergeService.php, ContactDuplicateDetector.php, ContactHealthService.php
│   │       ├── CompanyService.php, OpportunityService.php, PipelineService.php, PipelineStateMachine.php
│   │       └── VisitorTrackerService.php, VisitorBehaviorService.php, VisitorAnalyticsService.php
│   │
│   ├── Contract/                         # Contract Domain Core
│   │   └── Models/
│   │
│   ├── Finance/                          # Financial Ledger, Invoicing, Billing, & Tax Domain
│   │   ├── Controllers/                 # FinanceApiController.php
│   │   ├── Events/                      # InvoicePaid.php, InvoiceSent.php, RecurringInvoiceGenerated.php
│   │   ├── Models/                      # CreditNote.php, Estimate.php, Expense.php, Invoice.php, Payment.php, Refund.php, Transaction.php
│   │   └── Services/                    # FinanceService.php (handles invoices, ledgers, and transactions)
│   │
│   ├── Marketplace/                      # Digital Listing, Licensing, Cart, & Order Domain
│   │   ├── Contracts/                   # Product & Category Repository Contracts
│   │   ├── Events/                      # ProductViewed.php, ItemAdded.php, CartMerged.php, OrderCreated.php
│   │   ├── Models/                      # Cart.php, CartItem.php, Order.php, OrderItem.php, MarketplaceProduct.php, LicenseKey.php
│   │   ├── Repositories/                # MarketplaceProductRepository, MarketplaceCartRepository
│   │   └── Services/                    # MarketplaceCartService, CheckoutService, SearchService, MpesaGateway
│   │
│   ├── Notification/                     # Notifications Context Models, Templates & Engines
│   │   ├── Http/Controllers/            # Preference and Delivery controls
│   │   ├── Listeners/                   # NotificationEventSubscriber.php
│   │   ├── Models/                      # NotificationTemplate.php, NotificationLog.php, NotificationDelivery.php
│   │   ├── Repositories/                # Preference and Log repository implementations
│   │   └── Services/                    # NotificationDispatcher.php, NotificationTemplateService.php
│   │
│   ├── Project/                          # Dynamic PM/Milestones & Workspace Context
│   │   ├── Models/                      # Project.php, ProjectMilestone.php, ProjectTask.php, ProjectComment.php
│   │   ├── Repositories/                # ProjectRepository.php
│   │   └── Services/                    # ProjectService.php, ProjectCollaborationService.php
│   │
│   ├── Proposal/                         # Proposals & Dynamic e-Contracts Context
│   │   ├── Events/                      # ProposalCreated, ProposalSigned, ProposalAccepted, ClientInvited
│   │   ├── Models/                      # Proposal.php, ProposalItem.php, ProposalSection.php, ProposalRevision.php
│   │   └── Services/                    # ProposalService.php
│   │
│   ├── Shared/                           # Shared Kernel Context
│   │   ├── Casts/                       # MoneyCast.php
│   │   ├── Contracts/                   # MoneyFormatter interface
│   │   ├── Exceptions/                  # CurrencyMismatchException.php
│   │   ├── Services/                    # LocaleMoneyFormatter.php, MoneyCalculator.php
│   │   └── ValueObjects/                # Immutable structures (Currency.php, Money.php)
│   │
│   └── Workforce/                        # HR, Schedules, Assignments, & Time entries Context
│       ├── Controllers/                 # WorkforceController.php
│       ├── Events/                      # EmployeeAssigned, TimeLogged, LeaveRequested, LeaveApproved
│       ├── Models/                      # EmployeeProfile.php, TimeEntry.php, LeaveRequest.php, WorkSchedule.php, Team.php, Skill.php
│       ├── Repositories/                # WorkforceRepository.php, WorkforceRepositoryInterface.php
│       └── Services/                    # WorkforceService.php
│
├── Events/                               # System Core Event Architecture
│   ├── Interfaces/                      # DomainEventInterface.php
│   ├── CacheInvalidatedEvent.php
│   ├── DomainEvent.php
│   ├── ImmediateEvent.php
│   ├── InternalEvent.php
│   ├── NotificationSentEvent.php
│   ├── QueuedEvent.php
│   ├── ScheduledEvent.php
│   └── WebhookEvent.php
│
├── Helpers/                              # Lightweight Utility Helpers
│   ├── ActivityHelper.php, ArrayHelper.php, CollectionHelper.php, CurrencyHelper.php, DateHelper.php
│   ├── ExceptionHelper.php, FileHelper.php, MoneyHelper.php, NumberFormatter.php, PaginationHelper.php
│   ├── ResponseBuilder.php, StringHelper.php, TimezoneHelper.php, UuidHelper.php, ValidationHelper.php
│
├── Http/                                 # HTTP Core Port
│   ├── Controllers/                     # Primary Core Web / Ingress Controllers
│   │   ├── Api/                         # PublicLeadController, VisitorCrmController, VisitorTrackingController
│   │   ├── Auth/                        # ForgotPassword, Login, Register, Profile, ResetPassword Controllers
│   │   ├── ClientPortalController.php
│   │   ├── CompareController.php
│   │   ├── Controller.php               # Base Controller
│   │   ├── DashboardController.php
│   │   ├── FileController.php
│   │   ├── MarketplaceController.php (including Cart, Wishlist, Compare, Search, Newsletter)
│   │   ├── NotificationController.php
│   │   ├── ProjectController.php
│   │   ├── ProposalController.php
│   │   ├── SearchController.php
│   │   └── SettingsAdminController.php
│   │
│   ├── Middleware/                      # Middleware pipelines
│   │   ├── ExceptionLoggingMiddleware.php
│   │   ├── RequirePermission.php
│   │   └── ResolveTenant.php
│   │
│   └── Requests/                        # Public Form validation rules
│       └── PublicLeadRequest.php
│
├── Infrastructure/                       # Integration adapters
│   └── Events/                          # LaravelEventBus.php (Transactional Outbox integration)
│
├── Jobs/                                 # Background Queue Workers
│   ├── DispatchEventJob.php
│   ├── ProcessOutboxJob.php             # Outbox poller daemon job
│   ├── PruneOutboxJob.php
│   ├── PublishOutboxCommandJob.php
│   └── Logging/                         # LogActivityJob, LogAuditJob, LogExceptionJob, LogSecurityJob
│
├── Listeners/                            # App-level event observers
│   ├── AuthEventListener.php
│   ├── CrmDomainEventSubscriber.php
│   ├── EloquentAuditListener.php
│   └── UpdateSearchIndexListener.php
│
├── Models/                               # Primary shared Eloquent models & Logs
│   ├── ActivityLog.php, AuditLog.php, ExceptionLog.php, SecurityLog.php
│   ├── BetaEnrollment.php, FeatureFlag.php, Setting.php
│   ├── EventDlq.php, EventOutbox.php, IdempotentKey.php
│   ├── Notification.php, NotificationPreference.php
│   ├── Organization.php, OrganizationMember.php
│   ├── Role.php, Permission.php
│   ├── Session.php, StoredFile.php
│   └── SearchIndex.php, SearchablePlaceholder.php
│
├── Notifications/                        # Central system mailer notification templates
│   └── EnterpriseNotification.php
│
├── Observers/                            # Model Observers
│   └── StoredFileObserver.php
│
├── Providers/                            # Service Providers container definitions
│   ├── AppServiceProvider.php
│   └── UtilityServiceProvider.php
│
├── Repositories/                         # Repository abstractions & implementations
│   ├── Eloquent/                        # Eloquent database readers/writers (Users, Orgs, Settings, Files, Search, Audits)
│   └── Interface/                       # UserRepositoryInterface, RoleRepositoryInterface, SearchRepositoryInterface, etc.
│
├── Services/                             # Core Backend Services
│   ├── Cache/                           # CacheService, CacheInvalidator, TenantCacheManager, RedisRepository
│   ├── Configuration/                   # ConfigurationService
│   ├── Crm/                             # LeadCaptureService, LeadSpamFilter, VisitorTrackerService
│   ├── DTO/                             # PublicLeadDto, SearchIndexableDto, SearchResultDto
│   ├── EventBus/                        # OutboxPublisher, OutboxConsumer, DeadLetterQueue, IdempotencyChecker, RetryService
│   ├── Search/                          # SearchService, SearchableInterface
│   ├── File/                            # UploadService, DownloadService, FileValidator, SignedUrlService
│   ├── Media/                           # ImageOptimizationService, ThumbnailGenerator, VirusScanner
│   └── DomainServices/                  # ActivityLogService, AuditLogService, ExceptionLogService, NotificationService, SecurityLogService
│
└── Traits/                               # Reusable trait snippets
    ├── Auditable.php                     # Self-auditing entity fields listener
    ├── HasOptimisticLocking.php          # Prevents race conditions during updates
    ├── HasUuidV7.php                     # Generates lexicographically sortable UUIDv7 IDs
    └── Searchable.php                    # Integrates indexing triggers for search tables
```

---

## 3. BOOTSTRAP, CONFIG, ROUTES, DATABASE & RESOURCES

```text
├── bootstrap/                            # Laravel Core Bootstrappers
│   ├── app.php                          # HTTP Kernel, exceptions, and global middlewares definitions
│   └── providers.php                    # Explicit list of Service Providers to boot
│
├── config/                               # Static configurations subsystem
│   ├── app.php, auth.php, cache.php, database.php, filesystems.php
│   └── logging.php, queue.php, services.php, session.php
│
├── database/                             # Physical schemas, seeders, and factories
│   ├── factories/                       # Organization, OrgMember, Permission, Role, User factories
│   ├── migrations/                      # 32 Physical PostgreSQL table creation/update blueprints
│   └── seeders/                         # DatabaseSeeder.php
│
├── resources/                            # Laravel Blade templates directory
│   └── views/                           # Dynamic Blade templates
│       ├── auth/                        # Forgot, Login, Register, Profile views
│       ├── client_portal/               # Workspace, Intakes, Proposals and Projects dashboards
│       ├── components/                  # 30+ custom styling UI helpers (cards, buttons, dropdowns, etc.)
│       ├── crm/                         # Company, Contact, Lead, and Pipeline CRM panels
│       ├── layouts/                     # Master templates (App, Auth, Error, Public, etc.)
│       ├── marketplace/                 # Checkout, Wishlist, Comparisons, Store components
│       ├── notifications/               # Inbound/Outbound notification panels
│       ├── organization/                # Organization configurations & member setups
│       ├── settings/                    # Settings boards & global variables panel
│       └── search/                      # Advanced multi-indexer search panel
│
└── routes/                               # Route definitions maps
    ├── api.php                          # JWT and Sanctum secured REST APIs
    ├── console.php                      # Scheduler crontabs and CLI commands
    └── web.php                          # Human cookies and sessions routes
```

---

## 4. FRONTEND & TEST LAYERS (`/src` & `/tests`)

```text
├── src/                                  # Immersive Client Single-Page React App
│   ├── components/                      # Rich Interactive dashboards components
│   │   ├── FinanceTab.tsx               # Ledger billing, tax counters, and invoices builder UI
│   │   └── WorkforceTab.tsx             # Staff rotas, schedules, timesheets, and assignments planner UI
│   ├── data/
│   │   └── architectureData.ts          # Core JSON data feeding the interactive blueprint views
│   ├── App.tsx                          # Primary React single-page controller (Core GUI Hub & Docs viewer)
│   ├── index.css                        # Global source Tailwind & font importing styles
│   └── main.tsx                         # Client-side React 19 entry points
│
└── tests/                                # Fully fleshed feature tests
    ├── TestCase.php                     # TestCase base state setup
    └── Feature/                         # Comprehensive feature-by-feature test suite (28 system specs files)
```

---

## 5. REPOSITORY DOCUMENTATION VAULT (`/docs`)

```text
docs/
├── architecture/                        # Platform-wide designs and specification blueprints
│   ├── ARCHITECTURE.md                  # Domain architecture overview
│   ├── BRAND_GUIDELINES.md              # Brand designs, typography and color palettes
│   ├── JUANET_Master_Specification.md   # Authority blueprint specification doc
│   ├── UI_DESIGN_SYSTEM.md              # Global CSS & visual component standards
│   └── WEBSITE_INFORMATION_ARCHITECTURE.md
│
├── database/                            # Deep SQL specs for every module
│   ├── 01_Foundations/                  # Base platform tables layout
│   ├── 02_Core_Modules/                 # Lead and general operations physical schemas
│   ├── 03_Financial_Ledger/             # Ledger and transactional billing schemas
│   ├── 04_Operations_Standards/         # General logs and security audits database structures
│   ├── 05_Support_Desk/                 # Support Tickets schemas
│   ├── 06_Content_Management/           # CMS and blog physical tables
│   └── 07_Marketplace/                  # Order fulfillment and storefront payment databases
│
├── development/                         # Setup and workflow guides
│   ├── CHANGELOG.md                     # Semantic version track logs
│   ├── CONTRIBUTING.md                  # Open-source contributions guidelines
│   ├── DEVELOPMENT.md                   # Full developer manual
│   ├── INSTALL.md                       # Setup and installation step-by-step
│   └── SYSTEM_BOOTSTRAP_REPORT.md       # Operational startup log report
│
├── implementation/                      # Folder layout maps
│   └── 01_Project_Folder_Structure.md
│
├── release_and_deployment/              # Guides for deploying to production
│   ├── BUSINESS_WORKFLOW_VALIDATION.md
│   ├── COMMERCIAL_LAUNCH_GUIDE.md
│   ├── DEMO_DATA_GUIDE.md
│   ├── FINAL_DEPLOYMENT_CHECKLIST.md
│   ├── GO_LIVE_CHECKLIST.md
│   ├── PHASE7_COMPLETION_REPORT.md
│   ├── PRODUCTION_READINESS_REPORT.md
│   ├── RC2_PREPARATION_REPORT.md
│   ├── RELEASE_CANDIDATE_VERIFICATION.md
│   ├── SYSTEM_DEPLOYMENT_HANDBOOK.md
│   ├── SYSTEM_HEALTH_GUIDE.md
│   └── SYSTEM_INTEGRATION_REPORT.md
│
└── reports_and_audits/                  # Third-party certifications, accessibility, and security reports
    ├── ACCESSIBILITY_REPORT.md
    ├── BUG_FIX_SUMMARY.md
    ├── INTEGRATION_TEST_REPORT.md
    ├── PERFORMANCE_OPTIMIZATION_REPORT.md
    ├── PERFORMANCE_REPORT.md
    ├── SECURITY_AUDIT_REPORT.md
    ├── SEO_IMPLEMENTATION_REPORT.md
    ├── SYSTEM_HEALTH_REPORT.md
    └── USER_EXPERIENCE_REPORT.md
```
