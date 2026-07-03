<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Services\LeadService;
use App\Domain\CRM\Requests\CreateLeadRequest;
use App\Domain\CRM\Requests\UpdateLeadRequest;
use App\Domain\CRM\Resources\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LeadApiController extends Controller
{
    protected LeadService $service;

    public function __construct(LeadService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query();

        // 1. Filtering
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 2. Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 3. Includes
        $includes = explode(',', $request->input('include', ''));
        if (in_array('company', $includes)) {
            $query->with('company');
        }
        if (in_array('contact', $includes)) {
            $query->with('contact');
        }

        // 4. Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // 5. Pagination
        $perPage = $request->input('per_page', 15);
        $leads = $query->paginate($perPage);

        return response()->json([
            'data' => LeadResource::collection($leads->items()),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ]
        ]);
    }

    public function store(CreateLeadRequest $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $lead = $this->service->createLead($request->validated());

        return response()->json([
            'message' => 'Lead created successfully.',
            'data' => new LeadResource($lead),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('view', $lead);

        return response()->json([
            'data' => new LeadResource($lead),
        ]);
    }

    public function update(UpdateLeadRequest $request, string $id): JsonResponse
    {
        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('update', $lead);

        $updated = $this->service->updateLead($id, $request->validated());

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data' => new LeadResource($updated),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('delete', $lead);

        $this->service->deleteLead($id);

        return response()->json([
            'message' => 'Lead deleted successfully.',
        ]);
    }
}
