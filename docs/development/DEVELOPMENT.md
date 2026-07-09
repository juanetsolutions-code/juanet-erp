# Developer Workflow & Contribution Guidelines

This guide details the development standards, file creation processes, and workflow patterns for adding features to the **JUANET Enterprise SaaS Platform**.

---

## 🎨 Architectural Foundations & Coding Principles

JUANET uses a **Domain-Driven Design (DDD)** approach to modularize code into bounded business contexts. Business logic is separated from infrastructure interfaces like routes, controllers, or database models.

### Key Rules
1.  **Isolate Core Logic**: Domain models, events, services, and interface boundaries go in `/app/Domain/{DomainName}`.
2.  **No Direct Database Writing in Controllers**: Database operations must go through repositories or dedicated service classes.
3.  **Strict Typing**: Every method parameter and return type must be explicitly typed. Avoid using `any` or loose types.
4.  **Database Integrity**: Always use foreign keys, database indexes, and database transactions for multi-record operations.

---

## 🚀 Step-by-Step Feature Implementation Guide

To implement a new feature (e.g., adding a "Vendor Ledger" to the Finance domain), follow this standard development workflow:

### Step 1: Create Database Migration
Generate a new database migration file using the artisan command:
```bash
php artisan make:migration create_finance_vendor_ledgers_table
```

Inside your migration file, write the table schema using UUID primary keys and standard tenant-isolation fields:
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_vendor_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index(); // Tenant isolation
            $table->string('vendor_name');
            $table->decimal('outstanding_balance', 15, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes(); // Soft delete protection

            // Enforce relational database consistency
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_vendor_ledgers');
    }
};
```
Apply the database schema changes:
```bash
php artisan migrate
```

---

### Step 2: Define the Eloquent Model
Create your Eloquent model inside the domain's subfolder: `/app/Domain/Finance/Models/VendorLedger.php`.
```php
namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class VendorLedger extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'finance_vendor_ledgers';

    protected $fillable = [
        'organization_id',
        'vendor_name',
        'outstanding_balance',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
    ];
}
```

---

### Step 3: Implement the Repository Boundary
First, define the contract interface inside `/app/Domain/Finance/Contracts/VendorLedgerRepositoryInterface.php`:
```php
namespace App\Domain\Finance\Contracts;

use App\Domain\Finance\Models\VendorLedger;
use Illuminate\Support\Collection;

interface VendorLedgerRepositoryInterface
{
    public function getByOrganization(string $orgId): Collection;
    public function create(array $data): VendorLedger;
}
```

Next, implement this interface in the repository layer: `/app/Repositories/Finance/VendorLedgerRepository.php`:
```php
namespace App\Repositories\Finance;

use App\Domain\Finance\Contracts\VendorLedgerRepositoryInterface;
use App\Domain\Finance\Models\VendorLedger;
use Illuminate\Support\Collection;

class VendorLedgerRepository implements VendorLedgerRepositoryInterface
{
    public function getByOrganization(string $orgId): Collection
    {
        return VendorLedger::where('organization_id', $orgId)->get();
    }

    public function create(array $data): VendorLedger
    {
        return VendorLedger::create($data);
    }
}
```

Finally, register your interface and class binding in `/app/Providers/AppServiceProvider.php`:
```php
$this->app->bind(
    \App\Domain\Finance\Contracts\VendorLedgerRepositoryInterface::class,
    \App\Repositories\Finance\VendorLedgerRepository::class
);
```

---

### Step 4: Write Domain Service Logic
Write any core business logic inside the service class: `/app/Domain/Finance/Services/VendorLedgerService.php`.
```php
namespace App\Domain\Finance\Services;

use App\Domain\Finance\Contracts\VendorLedgerRepositoryInterface;
use App\Domain\Finance\Models\VendorLedger;
use Illuminate\Support\Collection;

class VendorLedgerService
{
    protected VendorLedgerRepositoryInterface $repository;

    public function __construct(VendorLedgerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getVendorsForOrg(string $orgId): Collection
    {
        return $this->repository->getByOrganization($orgId);
    }

    public function registerVendor(string $orgId, array $data): VendorLedger
    {
        $data['organization_id'] = $orgId;
        $data['outstanding_balance'] = 0.00;

        return $this->repository->create($data);
    }
}
```

---

### Step 5: Implement API Routing & Controller
Create your controller class inside the domain workspace: `/app/Domain/Finance/Controllers/Api/VendorLedgerController.php`.
```php
namespace App\Domain\Finance\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\VendorLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VendorLedgerController extends Controller
{
    protected VendorLedgerService $service;

    public function __construct(VendorLedgerService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->header('X-Organization-ID') ?? 'org-001';
        $vendors = $this->service->getVendorsForOrg($orgId);

        return response()->json(['data' => $vendors]);
    }
}
```

Register this new route inside `/routes/api.php` under the designated domain prefix:
```php
Route::prefix('finance')->group(function () {
    Route::get('/vendors', [\App\Domain\Finance\Controllers\Api\VendorLedgerController::class, 'index']);
});
```

---

### Step 6: Create the Frontend React Component View
Add the frontend UI code within `/src/components/VendorLedgerList.tsx`:
```tsx
import React, { useEffect, useState } from "react";
import axios from "axios";

interface Vendor {
  id: string;
  vendor_name: string;
  outstanding_balance: string;
}

export default function VendorLedgerList() {
  const [vendors, setVendors] = useState<Vendor[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios.get("/api/finance/vendors", {
      headers: { "X-Organization-ID": "org-001" }
    })
    .then((response) => {
      setVendors(response.data.data);
      setLoading(false);
    })
    .catch((error) => console.error("Error loading vendors:", error));
  }, []);

  if (loading) return <div className="text-muted text-xs font-mono">Loading records...</div>;

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-lg p-6">
      <h3 className="text-white text-lg font-sans font-medium mb-4">Vendor General Ledgers</h3>
      <div className="space-y-3">
        {vendors.map((vendor) => (
          <div key={vendor.id} className="flex justify-between border-b border-slate-800 py-2">
            <span className="text-slate-300 text-sm font-sans">{vendor.vendor_name}</span>
            <span className="text-cyan-400 text-sm font-mono font-medium">
              KES {parseFloat(vendor.outstanding_balance).toLocaleString()}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
```

---

## ⚡ Development Validation & Verification

Before submitting code, always run the linter and test suite to ensure system integrity:

```bash
# Check TypeScript code compilation for static type errors
npm run lint

# Run all PHP Unit/Feature tests
php artisan test
```
**Strict Code Quality Guideline**: No code can be merged into production branches with syntax warnings, missing parameters, or failing unit tests.
