<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\Tag;
use App\Domain\CRM\Services\LeadService;
use App\Domain\CRM\Requests\CreateLeadRequest;
use App\Domain\CRM\Requests\UpdateLeadRequest;
use App\Domain\CRM\Resources\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

        // 1. Filtering by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filtering by owner
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // 2. Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
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

    // ==========================================
    // EXTENDED LEAD LIFECYCLE & WORKFLOW ROUTES
    // ==========================================

    /**
     * Transition lead status.
     */
    public function transition(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('update', $lead);

        try {
            $updated = $this->service->changeLeadStatus(
                $id,
                $request->input('status'),
                $request->user()?->id,
                $request->input('reason')
            );
            return response()->json([
                'message' => 'Lead status transitioned successfully.',
                'data' => new LeadResource($updated),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Assign lead ownership.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|uuid',
            'method' => 'nullable|string', // manual, round_robin, load_balanced
            'user_ids' => 'nullable|array', // used for routing pools
        ]);

        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('update', $lead);

        $method = $request->input('method', 'manual');

        try {
            if ($method === 'round_robin') {
                $updated = $this->service->assignLeadRoundRobin(
                    $id,
                    $request->input('user_ids', []),
                    $request->user()?->id
                );
            } else if ($method === 'load_balanced') {
                $updated = $this->service->assignLeadLoadBalanced(
                    $id,
                    $request->input('user_ids', []),
                    $request->user()?->id
                );
            } else {
                $updated = $this->service->assignLead(
                    $id,
                    $request->input('user_id'),
                    $request->user()?->id,
                    'manual'
                );
            }

            return response()->json([
                'message' => 'Lead assigned successfully.',
                'data' => new LeadResource($updated),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Convert Lead to Account/Contact/Opportunity.
     */
    public function convert(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'company_name' => 'required_with:create_contact|string|max:255',
            'company_website' => 'nullable|url',
            'create_contact' => 'required|boolean',
            'create_opportunity' => 'required|boolean',
            'opportunity_name' => 'required_if:create_opportunity,true|string|max:255',
            'opportunity_amount' => 'nullable|numeric|min:0',
            'pipeline_id' => 'required_if:create_opportunity,true|uuid',
            'pipeline_stage_id' => 'required_if:create_opportunity,true|uuid',
        ]);

        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('update', $lead);

        try {
            $result = $this->service->convertLead($id, $request->all(), $request->user()?->id);
            return response()->json([
                'message' => 'Lead converted successfully.',
                'company' => $result['company'],
                'contact' => $result['contact'],
                'opportunity' => $result['opportunity'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get immutable activity timeline.
     */
    public function timeline(string $id): JsonResponse
    {
        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('view', $lead);

        $timeline = $this->service->getTimeline($id);

        return response()->json([
            'data' => $timeline,
        ]);
    }

    /**
     * Scan and discover duplicate suggestions.
     */
    public function duplicates(string $id): JsonResponse
    {
        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        Gate::authorize('view', $lead);

        $duplicates = $this->service->findDuplicates($id);

        return response()->json([
            'data' => LeadResource::collection($duplicates),
        ]);
    }

    /**
     * Merge duplicate lead into primary lead.
     */
    public function merge(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'duplicate_lead_id' => 'required|uuid',
            'override_fields' => 'nullable|array', // key: field, value: primary or duplicate
        ]);

        $lead = $this->service->getLead($id);
        if (!$lead) {
            return response()->json(['message' => 'Primary lead not found.'], 404);
        }

        Gate::authorize('update', $lead);

        try {
            $merged = $this->service->mergeLeads(
                $id,
                $request->input('duplicate_lead_id'),
                $request->input('override_fields', []),
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Leads merged successfully.',
                'data' => new LeadResource($merged),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ==========================================
    // DATA PORTABILITY (IMPORT / EXPORT) ROUTES
    // ==========================================

    /**
     * Export all organization leads to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $csv = $this->service->exportLeads();

        return response()->json([
            'csv' => $csv,
        ]);
    }

    /**
     * Import leads from CSV data.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'csv_content' => 'required|string',
            'organization_id' => 'required|uuid',
            'dry_run' => 'nullable|boolean',
        ]);

        Gate::authorize('create', Lead::class);

        $result = $this->service->importLeads(
            $request->input('csv_content'),
            $request->input('organization_id'),
            $request->user()?->id,
            $request->input('dry_run', false)
        );

        return response()->json($result);
    }

    /**
     * Rollback a complete import batch.
     */
    public function rollback(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id' => 'required|string',
            'organization_id' => 'required|uuid',
        ]);

        Gate::authorize('delete', Lead::class);

        $result = $this->service->rollbackImport(
            $request->input('batch_id'),
            $request->input('organization_id')
        );

        return response()->json($result);
    }

    // ==========================================
    // BULK CRM ACTIONS
    // ==========================================

    /**
     * Bulk update field values across multiple lead IDs.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
            'fields' => 'required|array',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');
        $fields = $request->input('fields');
        $userId = $request->user()?->id;

        $count = 0;
        foreach ($ids as $id) {
            $lead = $this->service->getLead($id);
            if ($lead && Gate::allows('update', $lead)) {
                $this->service->updateLead($id, array_merge($fields, ['updated_by' => $userId]));
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $count,
        ]);
    }

    /**
     * Bulk delete multiple lead IDs.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');

        $count = 0;
        foreach ($ids as $id) {
            $lead = $this->service->getLead($id);
            if ($lead && Gate::allows('delete', $lead)) {
                $this->service->deleteLead($id);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'deleted_count' => $count,
        ]);
    }

    /**
     * Bulk assign owner across multiple lead IDs.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
            'user_id' => 'nullable|uuid',
            'method' => 'nullable|string',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');
        $toUserId = $request->input('user_id');
        $method = $request->input('method', 'manual');
        $userId = $request->user()?->id;

        $count = 0;
        foreach ($ids as $id) {
            $lead = $this->service->getLead($id);
            if ($lead && Gate::allows('update', $lead)) {
                $this->service->assignLead($id, $toUserId, $userId, $method);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'assigned_count' => $count,
        ]);
    }

    /**
     * Bulk archive multiple lead IDs.
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');
        $userId = $request->user()?->id;

        $count = 0;
        foreach ($ids as $id) {
            $lead = $this->service->getLead($id);
            if ($lead && Gate::allows('update', $lead)) {
                $this->service->changeLeadStatus($id, 'archived', $userId, 'Bulk archived action.');
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'archived_count' => $count,
        ]);
    }

    /**
     * Bulk restore multiple lead IDs.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');
        $userId = $request->user()?->id;

        $count = 0;
        foreach ($ids as $id) {
            // Check soft-deleted leads too
            $lead = Lead::onlyTrashed()->find($id);
            if ($lead && Gate::allows('delete', $lead)) {
                $lead->restore();
                
                LeadActivity::create([
                    'organization_id' => $lead->organization_id,
                    'lead_id' => $lead->id,
                    'user_id' => $userId,
                    'type' => 'edit',
                    'description' => "Lead restored from deletion.",
                ]);

                $count++;
            } else {
                // If not soft-deleted but status is 'archived', restore status to 'new'
                $lead = $this->service->getLead($id);
                if ($lead && $lead->status === 'archived' && Gate::allows('update', $lead)) {
                    $this->service->changeLeadStatus($id, 'new', $userId, 'Bulk restored from archive.');
                    $count++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'restored_count' => $count,
        ]);
    }

    /**
     * Bulk tag multiple lead IDs.
     */
    public function bulkTag(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'uuid',
            'action' => 'required|in:attach,detach',
        ]);

        Gate::authorize('viewAny', Lead::class);

        $ids = $request->input('ids');
        $tagIds = $request->input('tag_ids');
        $action = $request->input('action');
        $userId = $request->user()?->id;

        $count = 0;
        foreach ($ids as $id) {
            $lead = $this->service->getLead($id);
            if ($lead && Gate::allows('update', $lead)) {
                if ($action === 'attach') {
                    $lead->tags()->syncWithoutDetaching($tagIds);
                    $tagNames = Tag::whereIn('id', $tagIds)->pluck('name')->toArray();
                    
                    LeadActivity::create([
                        'organization_id' => $lead->organization_id,
                        'lead_id' => $lead->id,
                        'user_id' => $userId,
                        'type' => 'edit',
                        'description' => "Attached tags: " . implode(', ', $tagNames) . ".",
                        'properties' => ['tag_ids' => $tagIds],
                    ]);
                } else {
                    $lead->tags()->detach($tagIds);
                    $tagNames = Tag::whereIn('id', $tagIds)->pluck('name')->toArray();

                    LeadActivity::create([
                        'organization_id' => $lead->organization_id,
                        'lead_id' => $lead->id,
                        'user_id' => $userId,
                        'type' => 'edit',
                        'description' => "Detached tags: " . implode(', ', $tagNames) . ".",
                        'properties' => ['tag_ids' => $tagIds],
                    ]);
                }
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'tagged_count' => $count,
        ]);
    }
}
