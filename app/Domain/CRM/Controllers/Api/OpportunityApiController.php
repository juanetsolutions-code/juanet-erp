<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Services\OpportunityService;
use App\Domain\CRM\Requests\CreateOpportunityRequest;
use App\Domain\CRM\Resources\OpportunityResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OpportunityApiController extends Controller
{
    protected OpportunityService $service;

    public function __construct(OpportunityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $query = Opportunity::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $includes = explode(',', $request->input('include', ''));
        if (in_array('company', $includes)) {
            $query->with('company');
        }
        if (in_array('contact', $includes)) {
            $query->with('contact');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $opportunities = $query->paginate($perPage);

        return response()->json([
            'data' => OpportunityResource::collection($opportunities->items()),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'last_page' => $opportunities->lastPage(),
                'per_page' => $opportunities->perPage(),
                'total' => $opportunities->total(),
            ]
        ]);
    }

    public function store(CreateOpportunityRequest $request): JsonResponse
    {
        Gate::authorize('create', Opportunity::class);

        $opportunity = $this->service->createOpportunity($request->validated());

        return response()->json([
            'message' => 'Opportunity created successfully.',
            'data' => new OpportunityResource($opportunity),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('view', $opportunity);

        return response()->json([
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('update', $opportunity);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'contact_id' => 'nullable|uuid|exists:crm_contacts,id',
            'pipeline_id' => 'sometimes|required|uuid|exists:crm_pipelines,id',
            'pipeline_stage_id' => 'sometimes|required|uuid|exists:crm_pipeline_stages,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'amount' => 'nullable|numeric|min:0',
            'close_date' => 'nullable|date',
            'status' => 'nullable|string|in:open,won,lost',
            'custom_fields' => 'nullable|array',
        ]);

        $updated = $this->service->updateOpportunity($id, $validated);

        return response()->json([
            'message' => 'Opportunity updated successfully.',
            'data' => new OpportunityResource($updated),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('delete', $opportunity);

        $this->service->deleteOpportunity($id);

        return response()->json([
            'message' => 'Opportunity deleted successfully.',
        ]);
    }
}
