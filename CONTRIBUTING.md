# Contributing to JUANET Platform

We welcome developers to contribute to the **JUANET Enterprise SaaS Platform**. To maintain high code quality, system performance, and financial auditing standards, all contributions must strictly adhere to the following rules and standards.

---

## 🪵 Branching Strategy & Workflow

We use a structured branching model to manage development and deployments safely.

### 1. Main Branches
*   `main` — Represents production-ready code. Only hotfixes and fully verified feature branches may be merged here.
*   `develop` — The primary integration branch. All active feature development begins and ends here.

### 2. Supporting Branches
*   **Features**: `feature/your-feature-name` (must branch off and merge back into `develop`).
*   **Fixes**: `bugfix/your-issue-name` (branches off `develop`).
*   **Hotfixes**: `hotfix/critical-issue` (branches off and merges back into both `main` and `develop` to resolve production issues immediately).

### 3. Step-by-Step Branch Workflow
1.  Pull the latest updates from the main integration branch:
    ```bash
    git checkout develop
    git pull origin develop
    ```
2.  Create your feature branch using lowercase characters and hyphens:
    ```bash
    git checkout -b feature/vendor-ledger-finance
    ```
3.  Write your code, commit incrementally with descriptive commit messages, and push your changes:
    ```bash
    git commit -m "feat(finance): add immutable general ledger vendor tables"
    git push origin feature/vendor-ledger-finance
    ```
4.  Submit a Pull Request (PR) targeting the `develop` branch.

---

## 🎨 Code Style & Quality Standards

To maintain a clean and consistent codebase, your code must adhere to these language-specific style guides:

### 1. PHP Style Standards (Laravel)
*   **Standard Code Format**: Follow PSR-12 and standard Laravel design principles.
*   **Type Declarations**: All class methods must define strict, explicit return types and parameter type-hints.
*   **Format Verification**: Run Laravel Pint to automatically clean code formatting:
    ```bash
    ./vendor/bin/pint
    ```

### 2. TypeScript & React Frontend Standards
*   **No Explicit any**: Do not use the `any` type. Define clear type contracts, interfaces, or standard union declarations instead.
*   **Icons Standard**: All icons must be imported from the `lucide-react` library. Custom SVGs or raw inline HTML elements are not allowed.
*   **React State Safety**: Never update state directly in a React component's render body.
*   **TypeScript Validation**: Run the typescript compiler to check for static type errors before proposing changes:
    ```bash
    npm run lint
    ```

---

## 🧪 Testing & Code Quality Assurance

You must verify that your changes do not break existing business processes.

### Run All Platform Tests
```bash
php artisan test
```

### Write New Feature Tests
If you introduce a new feature or domain logic, you **MUST** write accompanying test coverage inside `tests/Feature/`. Ensure your tests verify:
1.  **Successful Flow**: The system behaves as expected under normal operations.
2.  **Unhappy Paths**: The system handles bad inputs, invalid requests, and missing fields gracefully without crashing.
3.  **Tenancy Separation**: Verify that your feature strictly isolates tenant data (i.e. Organization A cannot access resources owned by Organization B).

---

## 📝 Pull Request & Code Review Checklist

Before submitting a Pull Request for review, make sure you have completed the following steps:

- [ ] All code formatting conforms to project standards (run `./vendor/bin/pint`).
- [ ] TypeScript code compiles without errors (run `npm run lint`).
- [ ] All automated tests pass successfully (run `php artisan test`).
- [ ] The feature is completely isolated by tenant (`organization_id`).
- [ ] New features include appropriate test coverage.
- [ ] The documentation has been updated to reflect your changes.
