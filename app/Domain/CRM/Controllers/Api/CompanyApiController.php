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
            'trading_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'industry_id' => 'nullable|uuid|exists:crm_industries,id',
            'industry_classification' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:255',
            'annual_revenue' => 'nullable|numeric|min:0',
            'employees_count' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|uuid|exists:crm_companies,id',
            'status' => 'nullable|string|in:Prospect,Customer,Partner,Vendor,Inactive',
            'user_id' => 'nullable|uuid|exists:users,id',
            'territory' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'domain' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'social_media_profiles' => 'nullable|array',
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

    public function recalculateHealth(string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('update', $company);

        $score = $company->recalculateHealthScore();

        return response()->json([
            'message' => 'Company health recalculated successfully.',
            'health_score' => $score,
            'health_status' => $company->health_status,
            'health_breakdown' => $company->health_breakdown,
        ]);
    }

    public function hierarchy(string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('view', $company);

        $hierarchy = $this->service->getHierarchy($id);

        return response()->json([
            'data' => $hierarchy,
        ]);
    }

    public function getLocations(string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('view', $company);

        $locations = $this->service->getLocations($id);

        return response()->json([
            'data' => $locations,
        ]);
    }

    public function storeLocation(Request $request, string $id): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('update', $company);

        $validated = $request->validate([
            'type' => 'required|string|in:headquarters,branch,warehouse,billing,shipping',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'gps_coordinates' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
        ]);

        $location = $this->service->createLocation($id, $validated);

        return response()->json([
            'message' => 'Location created successfully.',
            'data' => $location,
        ], 201);
    }

    public function updateLocation(Request $request, string $id, string $locationId): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('update', $company);

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:headquarters,branch,warehouse,billing,shipping',
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'gps_coordinates' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
        ]);

        $location = $this->service->updateLocation($id, $locationId, $validated);

        if (!$location) {
            return response()->json(['message' => 'Location not found.'], 404);
        }

        return response()->json([
            'message' => 'Location updated successfully.',
            'data' => $location,
        ]);
    }

    public function destroyLocation(string $id, string $locationId): JsonResponse
    {
        $company = $this->service->getCompany($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        Gate::authorize('update', $company);

        $deleted = $this->service->deleteLocation($id, $locationId);

        if (!$deleted) {
            return response()->json(['message' => 'Location not found.'], 404);
        }

        return response()->json([
            'message' => 'Location deleted successfully.',
        ]);
    }
}
