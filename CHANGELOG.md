# Changelog

All notable changes to the **JUANET Enterprise SaaS Platform** (JUANET EOS) will be documented in this file.

This project adheres to **Semantic Versioning** (`MAJOR.MINOR.PATCH`).

---

## [1.3.0] — 2026-07-09

This release introduces the **Enterprise Finance Core** and the **Workforce & Collaboration** domains, providing a fully integrated ERP and financial ledger system.

### Added
*   **Immutable Double-Entry Ledger System**: Reconciles billing, invoice completions, payments, and operational expenses in real-time.
*   **Daraja M-PESA Integration**: Real-time payment verification and instant callback routing.
*   **Workforce Portal**: Timesheet logging, automated leave approvals, and employee records.
*   **Automated Billing Scheduler**: Automated monthly invoices and recurring subscription checks.
*   **Comprehensive Test Coverage**: Added the `EnterpriseFinanceBillingTest` suite to verify financial ledgers and billing automated processes.

### Changed
*   Upgraded standard routing tables inside `/routes/api.php` to map domains explicitly.
*   Refactored the React dashboard sidebar navigation, adding the "Enterprise Finance Core" and "Workforce & Collaboration" tabs.

### Fixed
*   Resolved potential infinite loops in React chart render components.
*   Fixed database foreign key errors when archiving tenant records.

---

## [1.2.0] — 2026-05-15

This release focuses on **Proposal Management, E-Contracts, and the Client Portal**.

### Added
*   **Client Proposal Builder**: Electronic proposal builder with support for multiple line items, tax-rates, and digital signatures.
*   **Legal Contract Vault**: Automates PDF generation for contracts and stores them securely inside Supabase storage buckets.
*   **E-Signature Processor**: Auto-triggers project workspace setup and generates the first milestone invoice as soon as a client signs a proposal.
*   **Secure Client Portal**: A dedicated client-facing workspace for tracking milestones, submitting feedback, and processing outstanding invoices.

### Fixed
*   Resolved database schema lockouts when registering dual tenant entities concurrently.
*   Fixed minor UI bugs on mobile viewport navigation menus.

---

## [1.1.0] — 2026-03-01

This release delivers the **CRM Pipeline Engine, Visitor Analytics, and SLA Support Ticket Hub**.

### Added
*   **Dynamic CRM Sales Pipelines**: Multi-stage Kanban pipeline for tracking leads from capture to conversion.
*   **Visitor Behavior Tracker**: Privacy-safe, cookie-less visitor analytics that track page views and traffic sources.
*   **SLA Support Ticket Workspace**: Helpdesk ticketing system with automatic priority calculation and customer chat widgets.

### Changed
*   Refactored the dashboard's visual appearance to use the **Space Slate Theme**.

---

## [1.0.0] — 2025-12-15

The initial release of the **JUANET Enterprise SaaS Platform** core architecture.

### Added
*   **Core Monorepo Structure**: Setup the Laravel backend and React/Vite/Tailwind frontend.
*   **Tenant Separation Models**: Implemented logical database tenant isolation using foreign key constraints and global query scopes.
*   **Supabase Client Integrations**: Configured central user authentication and S3 storage adapters.
*   **Mock Email Server**: Configured Mailpit to trap outgoing emails locally during development.
