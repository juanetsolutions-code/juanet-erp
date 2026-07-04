<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\OpportunityProduct;
use App\Domain\CRM\Models\PipelineStage;
use App\Domain\CRM\Services\OpportunityService;
use App\Domain\CRM\Services\PipelineStateMachine;
use App\Domain\CRM\Requests\CreateOpportunityRequest;
use App\Domain\CRM\Resources\OpportunityResource;
use App\Domain\CRM\Events\OpportunityProductAddedEvent;
use App\Domain\CRM\Events\OpportunityProductRemovedEvent;
use App\Contracts\EventBus;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class OpportunityApiController extends Controller
{
    protected OpportunityService $service;
    protected PipelineStateMachine $stateMachine;
    protected TenantContext $tenantContext;
    protected EventBus $eventBus;

    public function __construct(
        OpportunityService $service,
        PipelineStateMachine $stateMachine,
        TenantContext $tenantContext,
        EventBus $eventBus
    ) {
        $this->service = $service;
        $this->stateMachine = $stateMachine;
        $this->tenantContext = $tenantContext;
        $this->eventBus = $eventBus;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $query = Opportunity::query();

        // Tenant isolation
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('pipeline_id')) {
            $query->where('pipeline_id', $request->input('pipeline_id'));
        }

        if ($request->has('forecast_category')) {
            $query->where('forecast_category', $request->input('forecast_category'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('opportunity_number', 'like', "%{$search}%")
                  ->orWhere('competitor', 'like', "%{$search}%");
            });
        }

        $includes = explode(',', $request->input('include', ''));
        if (in_array('company', $includes)) {
            $query->with('company');
        }
        if (in_array('contact', $includes)) {
            $query->with('contact');
        }
        if (in_array('products', $includes)) {
            $query->with('products');
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

        $data = $request->validated();
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId) {
            $data['organization_id'] = $orgId;
        }

        $opportunity = $this->service->createOpportunity($data);

        // If stage is provided, transition using State Machine
        if (!empty($data['pipeline_stage_id'])) {
            $stage = PipelineStage::where('id', $data['pipeline_stage_id'])
                ->when($orgId, function ($q) use ($orgId) {
                    return $q->where('organization_id', $orgId);
                })->first();
            if ($stage) {
                $this->stateMachine->transition($opportunity, $stage, auth()->id() ?? $opportunity->user_id);
            }
        }

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

            // Extended Fields
            'description' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'expected_close_date' => 'nullable|date',
            'actual_close_date' => 'nullable|date',
            'estimated_revenue' => 'nullable|numeric|min:0',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'currency' => 'nullable|string|size:3',
            'forecast_category' => 'nullable|string|in:commit,best_case,pipeline,omitted',
            'competitor' => 'nullable|string|max:255',
            'lost_reason' => 'nullable|string',
            'won_reason' => 'nullable|string',
            'sales_team' => 'nullable|string|max:255',
            
            // AI Fields
            'ai_confidence' => 'nullable|numeric',
            'ai_win_probability_prediction' => 'nullable|numeric',
            'ai_next_best_action' => 'nullable|string',
            'ai_deal_health' => 'nullable|string|max:50',
            'ai_risk_detection' => 'nullable|string',
            'ai_upsell_recommendations' => 'nullable|array',
        ]);

        $orgId = $this->tenantContext->getTenantId();

        // Handle Pipeline Stage Change via State Machine
        if (isset($validated['pipeline_stage_id']) && $validated['pipeline_stage_id'] !== $opportunity->pipeline_stage_id) {
            $toStage = PipelineStage::where('id', $validated['pipeline_stage_id'])
                ->when($orgId, function ($q) use ($orgId) {
                    return $q->where('organization_id', $orgId);
                })->first();
            
            if ($toStage) {
                $reason = $validated['won_reason'] ?? $validated['lost_reason'] ?? null;
                $this->stateMachine->transition($opportunity, $toStage, auth()->id() ?? $opportunity->user_id, $reason);
            }
            unset($validated['pipeline_stage_id']);
        }

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

    // --- Opportunity Line Items (Products) CRUD ---

    public function indexProducts(string $id): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('view', $opportunity);

        return response()->json([
            'data' => $opportunity->products,
        ]);
    }

    public function storeProduct(Request $request, string $id): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('update', $opportunity);

        $validated = $request->validate([
            'product_id' => 'nullable|uuid',
            'product_name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'recurring_billing_flag' => 'nullable|boolean',
            'subscription_interval' => 'nullable|string|max:50',
            'manual_pricing_override' => 'nullable|boolean',
            'price_snapshot' => 'nullable|numeric|min:0',
        ]);

        $orgId = $this->tenantContext->getTenantId() ?? $opportunity->organization_id;
        $validated['organization_id'] = $orgId;
        $validated['opportunity_id'] = $opportunity->id;

        if (empty($validated['price_snapshot'])) {
            $validated['price_snapshot'] = $validated['unit_price'];
        }

        $product = $opportunity->products()->create($validated);
        
        // Recalculate Opportunity Totals
        $opportunity->recalculateTotals();

        $this->eventBus->dispatch(new OpportunityProductAddedEvent($opportunity, $product));

        return response()->json([
            'message' => 'Opportunity product added successfully.',
            'data' => $product,
            'opportunity' => new OpportunityResource($opportunity->fresh()),
        ], 201);
    }

    public function updateProduct(Request $request, string $id, string $productId): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('update', $opportunity);

        $product = $opportunity->products()->where('id', $productId)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found on this opportunity.'], 404);
        }

        $validated = $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'quantity' => 'sometimes|required|integer|min:1',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'recurring_billing_flag' => 'nullable|boolean',
            'subscription_interval' => 'nullable|string|max:50',
            'manual_pricing_override' => 'nullable|boolean',
            'price_snapshot' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);
        
        // Recalculate Opportunity Totals
        $opportunity->recalculateTotals();

        return response()->json([
            'message' => 'Opportunity product updated successfully.',
            'data' => $product,
            'opportunity' => new OpportunityResource($opportunity->fresh()),
        ]);
    }

    public function destroyProduct(string $id, string $productId): JsonResponse
    {
        $opportunity = $this->service->getOpportunity($id);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('update', $opportunity);

        $product = $opportunity->products()->where('id', $productId)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found on this opportunity.'], 404);
        }

        $product->delete();

        // Recalculate Opportunity Totals
        $opportunity->recalculateTotals();

        $this->eventBus->dispatch(new OpportunityProductRemovedEvent($opportunity, $product));

        return response()->json([
            'message' => 'Opportunity product removed successfully.',
            'opportunity' => new OpportunityResource($opportunity->fresh()),
        ]);
    }

    // --- Bulk Updates / Assignment / Stage Movement ---

    public function bulkUpdate(Request $request): JsonResponse
    {
        Gate::authorize('update', Opportunity::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:crm_opportunities,id',
            'data' => 'required|array',
        ]);

        $orgId = $this->tenantContext->getTenantId();

        $count = DB::transaction(function () use ($validated, $orgId) {
            $updatedCount = 0;
            foreach ($validated['ids'] as $id) {
                $opp = Opportunity::where('id', $id)
                    ->when($orgId, function ($q) use ($orgId) {
                        return $q->where('organization_id', $orgId);
                    })->first();

                if ($opp) {
                    $opp->update($validated['data']);
                    $opp->recalculateTotals();
                    $updatedCount++;
                }
            }
            return $updatedCount;
        });

        return response()->json([
            'message' => "Successfully updated {$count} opportunities.",
        ]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        Gate::authorize('update', Opportunity::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:crm_opportunities,id',
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $orgId = $this->tenantContext->getTenantId();

        $count = DB::transaction(function () use ($validated, $orgId) {
            $updatedCount = 0;
            foreach ($validated['ids'] as $id) {
                $opp = Opportunity::where('id', $id)
                    ->when($orgId, function ($q) use ($orgId) {
                        return $q->where('organization_id', $orgId);
                    })->first();

                if ($opp) {
                    $opp->update(['user_id' => $validated['user_id']]);
                    $updatedCount++;
                }
            }
            return $updatedCount;
        });

        return response()->json([
            'message' => "Successfully assigned {$count} opportunities to user.",
        ]);
    }

    public function bulkMoveStage(Request $request): JsonResponse
    {
        Gate::authorize('update', Opportunity::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:crm_opportunities,id',
            'pipeline_stage_id' => 'required|uuid|exists:crm_pipeline_stages,id',
        ]);

        $orgId = $this->tenantContext->getTenantId();
        $stage = PipelineStage::where('id', $validated['pipeline_stage_id'])
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })->firstOrFail();

        $count = DB::transaction(function () use ($validated, $stage) {
            $movedCount = 0;
            foreach ($validated['ids'] as $id) {
                $opp = Opportunity::where('id', $id)->first();
                if ($opp) {
                    try {
                        $this->stateMachine->transition($opp, $stage, auth()->id());
                        $movedCount++;
                    } catch (\Throwable $e) {
                        // Skip if transition is invalid or errors out
                    }
                }
            }
            return $movedCount;
        });

        return response()->json([
            'message' => "Successfully moved {$count} opportunities to stage '{$stage->name}'.",
        ]);
    }
}
