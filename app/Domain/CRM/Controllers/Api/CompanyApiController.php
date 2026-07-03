<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Services\CompanyService;
use App\Domain\CRM\Requests\CreateCompanyRequest;
use App\Domain\CRM\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CompanyApiController extends Controller
{
    protected CompanyService $service;

    public function __construct(CompanyService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Company::class);

        $query = Company::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $companies = $query->paginate($perPage);

        return response()->json([
            'data' => CompanyResource::collection($companies->items()),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ]
        ]);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        Gate::authorize('create', Company::class);

        $company = $this->service->createCompany($request->validated());

        return response()->json([
            'message' => 'Company created successfully.',
            'data' => new CompanyResource($company),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('view', $company);

        return response()->json([
            'data' => new CompanyResource($company),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('update', $company);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'industry_id' => 'nullable|uuid|exists:crm_industries,id',
            'domain' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ]);

        $updated = $this->service->updateCompany($id, $validated);

        return response()->json([
            'message' => 'Company updated successfully.',
            'data' => new CompanyResource($updated),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('delete', $company);

        $this->service->deleteCompany($id);

        return response()->json([
            'message' => 'Company deleted successfully.',
        ]);
    }
}
