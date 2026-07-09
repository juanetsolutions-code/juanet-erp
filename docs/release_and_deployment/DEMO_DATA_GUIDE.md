# JUANET EOS: Demo Data & Seeder Guide

This guide details the **Demo Environment & Seeder System** designed for Phase 8. It allows operators, QA engineers, and clients to immediately spin up sandbox environments modeling realistic business structures.

---

## 🏢 1. Pre-Configured Demo Organizations

To demonstrate the versatility of JUANET EOS across industries, the seeder suite implements seven diverse business profiles. Each organization is populated with realistic operational records.

| Profile | Organization Name | Primary Domain | Core Testing Scenario |
| :--- | :--- | :--- | :--- |
| **Startup** | Apex Labs Inc. | `apex.juanet.cloud` | High-velocity agile sprints, seed-funding burn tracking, and fast AI feature iteration. |
| **School** | Horizon International School | `school.juanet.cloud` | Long-term asset development, student portal updates, and recurrent invoice tuition cycles. |
| **Hospital** | St. Jude Care Network | `hospital.juanet.cloud` | Strict access control, medical equipment procurement projects, and high-security compliance logs. |
| **NGO** | GreenEarth Global | `ngo.juanet.cloud` | Grant allocation tracking, volunteer workforce assignment, and donor-supported milestone reporting. |
| **Retail** | Zendo Smart Retailers | `retail.juanet.cloud` | High-frequency physical store rollouts, vendor supply-chain contracts, and retail POS ledger postings. |
| **Construction** | BuildCorp Infrastructure | `build.juanet.cloud` | Capital-intensive bidding, heavy field workforce logistics, progress-billing, and milestone invoices. |
| **Tech Company** | ByteStream Systems | `tech.juanet.cloud` | Multi-disciplinary software delivery, timesheet analysis, and continuous delivery support queues. |

---

## 👥 2. Realistic User Personas

Each demo organization includes standardized roles to test role-based access controls (RBAC):

```
                                [ Admin SuperUser ]
                       (Unrestricted Access & Configuration)
                                         │
                                         ▼
                                [ Project Manager ]
                       (Manages Milestones, Assigns Staff)
                                         │
                                         ▼
                     ┌───────────────────┴───────────────────┐
                     ▼                                       ▼
             [ Staff Employee ]                      [ External Client ]
         (Timecards, Support Tickets)              (Invoice Approvals, Signatures)
```

### Sandbox Credentials
All sandbox environments utilize a simple password structure for rapid login testing:
*   **Default Password**: `password123` (argon2id hashed in DB)
*   **Super Admin User**: `superadmin@juanet.cloud`

---

## 📂 3. Seeder Blueprint Specifications

The demo seeder system is organized into decoupled modules that must be executed in sequence to respect foreign-key constraints:

### I. `DemoSuperAdminSeeder`
*   **Action**: Generates global platform managers, system maintenance users, and audit roles.

### II. `DemoOrganizationSeeder`
*   **Action**: Provisions the seven distinct organization records with isolated database tenant identifiers and domain names (e.g. `apex.juanet.cloud`).

### III. `DemoCRMSeeder`
*   **Action**: Generates high-fidelity CRM pipeline states including target leads, estimated deal sizes, historical email interactions, and source metrics.

### IV. `DemoWorkforceSeeder`
*   **Action**: Assigns realistic employee profiles to respective organizations, complete with role titles, billable hourly rates, direct manager trees, and pre-logged leave history.

### V. `DemoProjectSeeder`
*   **Action**: Configures multi-phase projects populated with real-world tasks, milestones, and workforce timesheets mapping to billable hours.

### VI. `DemoFinanceSeeder`
*   **Action**: Writes immutable general ledger entries mapping historic double-entry transactions (debits and credits), custom invoice templates, and tax codes.

---

## 🛠️ 4. Running the Demo Environment

To clear existing records and seed a clean development environment, run the master artisan command from your terminal:

```bash
php artisan migrate:fresh --seed --seeder=MasterDemoSeeder
```

For specific context testing, individual seeders can be called independently:

```bash
php artisan db:seed --class=DemoCRMSeeder
```
