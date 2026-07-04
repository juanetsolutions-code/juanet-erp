<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\Models\ActivityNote;
use App\Domain\CRM\Activities\Models\ActivityReminder;
use App\Domain\CRM\Activities\Services\ActivityService;
use App\Domain\CRM\Activities\Services\TimelineEngine;
use App\Domain\CRM\Activities\DTO\ActivityData;
use App\Domain\CRM\Activities\Resources\ActivityResource;
use App\Domain\CRM\Activities\Resources\TimelineResource;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ActivityApiController extends Controller
{
    public function __construct(
        protected ActivityService $service,
        protected TimelineEngine $timelineEngine,
        protected TenantContext $tenantContext
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Activity::class);

        $orgId = $this->tenantContext->getTenantId();
        $query = Activity::where('organization_id', $orgId);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('is_completed')) {
            $query->where('is_completed', (bool)$request->input('is_completed'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('loggable_type') && $request->has('loggable_id')) {
            $query->where('loggable_type', $request->input('loggable_type'))
                  ->where('loggable_id', $request->input('loggable_id'));
        }

        if ($request->has('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $perPage = $request->input('per_page', 15);
        $activities = $query->with(['assignee', 'attachments.file'])->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'loggable_type' => 'nullable|string',
            'loggable_id' => 'nullable|string',
            'user_id' => 'nullable|string|exists:users,id',
            'due_at' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'is_recurring' => 'nullable|boolean',
            'recurring_rules' => 'nullable|array',
            'properties' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orgId = $this->tenantContext->getTenantId();
        $data = ActivityData::fromArray($request->all(), $orgId);

        $activity = $this->service->createActivity($data);

        return response()->json([
            'message' => 'Activity created successfully.',
            'data' => new ActivityResource($activity->load(['assignee', 'attachments.file']))
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        Gate::authorize('view', $activity);

        return response()->json([
            'data' => new ActivityResource($activity->load(['assignee', 'attachments.file', 'notes.user']))
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        Gate::authorize('update', $activity);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'loggable_type' => 'nullable|string',
            'loggable_id' => 'nullable|string',
            'user_id' => 'nullable|string|exists:users,id',
            'due_at' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'is_recurring' => 'nullable|boolean',
            'recurring_rules' => 'nullable|array',
            'properties' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = ActivityData::fromArray($request->all(), $orgId);
        $activity = $this->service->updateActivity($activity, $data);

        return response()->json([
            'message' => 'Activity updated successfully.',
            'data' => new ActivityResource($activity->load(['assignee', 'attachments.file']))
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        Gate::authorize('delete', $activity);

        $activity->delete();

        return response()->json(['message' => 'Activity deleted successfully.']);
    }

    public function complete(string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        Gate::authorize('update', $activity);

        $activity = $this->service->completeActivity($activity);

        return response()->json([
            'message' => 'Activity marked as completed successfully.',
            'data' => new ActivityResource($activity->load(['assignee', 'attachments.file']))
        ]);
    }

    /**
     * Unified Timeline Feed API.
     */
    public function timeline(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string',
            'entity_id' => 'required|string',
            'type' => 'nullable|string',
            'user_id' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orgId = $this->tenantContext->getTenantId();
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');

        $filters = $request->only(['type', 'user_id', 'start_date', 'end_date', 'search']);
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $paginator = $this->timelineEngine->getTimeline($entityType, $entityId, $orgId, $filters, $perPage, $page);

        return response()->json([
            'data' => TimelineResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * Bulk updates.
     */
    public function bulkComplete(Request $request): JsonResponse
    {
        Gate::authorize('create', Activity::class); // using create role as batch operation permission

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orgId = $this->tenantContext->getTenantId();
        $updated = $this->service->completeActivity(Activity::class); // wait, we have repository bulkComplete!
        $count = Activity::whereIn('id', $request->input('ids'))
            ->where('organization_id', $orgId)
            ->update([
                'is_completed' => true,
                'completed_at' => Carbon::now()
            ]);

        return response()->json([
            'message' => "Successfully completed {$count} activities.",
            'count' => $count
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|string',
            'attributes' => 'required|array',
            'attributes.priority' => 'nullable|string|in:low,medium,high',
            'attributes.user_id' => 'nullable|string|exists:users,id',
            'attributes.is_completed' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orgId = $this->tenantContext->getTenantId();
        $attrs = $request->input('attributes');
        if (isset($attrs['is_completed'])) {
            $attrs['completed_at'] = $attrs['is_completed'] ? Carbon::now() : null;
        }

        $count = Activity::whereIn('id', $request->input('ids'))
            ->where('organization_id', $orgId)
            ->update($attrs);

        return response()->json([
            'message' => "Successfully updated {$count} activities.",
            'count' => $count
        ]);
    }

    /**
     * Rich Standalone Notes API.
     */
    public function storeNote(Request $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $validator = Validator::make($request->all(), [
            'notable_type' => 'required|string',
            'notable_id' => 'required|string',
            'content' => 'required|string',
            'parent_id' => 'nullable|string|exists:crm_activity_notes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orgId = $this->tenantContext->getTenantId();
        $note = $this->service->addNote(
            $request->input('notable_type'),
            $request->input('notable_id'),
            $request->input('content'),
            $orgId,
            auth()->id(),
            $request->input('parent_id')
        );

        return response()->json([
            'message' => 'Note added successfully.',
            'data' => $note->load('user')
        ], 201);
    }

    public function updateNote(Request $request, string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $note = ActivityNote::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$note) {
            return response()->json(['message' => 'Note not found.'], 404);
        }

        // Gate auth
        if ($note->user_id !== auth()->id() && !auth()->user()->hasPermission('update_activities', $orgId)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updatedNote = $this->service->updateNote($note, $request->input('content'), auth()->id());

        return response()->json([
            'message' => 'Note updated successfully.',
            'data' => $updatedNote->load('user')
        ]);
    }

    public function destroyNote(string $id): JsonResponse
    {
        $orgId = $this->tenantContext->getTenantId();
        $note = ActivityNote::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$note) {
            return response()->json(['message' => 'Note not found.'], 404);
        }

        // Gate auth
        if ($note->user_id !== auth()->id() && !auth()->user()->hasPermission('delete_activities', $orgId)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->service->deleteNote($note);

        return response()->json(['message' => 'Note deleted successfully.']);
    }

    /**
     * File Attachments API.
     */
    public function storeAttachment(Request $request, string $activityId): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $activityId)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'stored_file_id' => 'required|string|exists:stored_files,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attachment = $this->service->addAttachment($activity, $request->input('stored_file_id'), auth()->id());

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment->load('file')
        ], 201);
    }

    /**
     * Reminders API.
     */
    public function storeReminder(Request $request, string $activityId): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $orgId = $this->tenantContext->getTenantId();
        $activity = Activity::where('id', $activityId)->where('organization_id', $orgId)->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'remind_at' => 'required|date',
            'method' => 'nullable|string|in:in_app,email,sms',
            'recurring_rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reminder = $this->service->addReminder(
            $activity,
            $request->input('title'),
            Carbon::parse($request->input('remind_at')),
            $request->input('method', 'in_app'),
            $request->input('description'),
            $request->input('recurring_rules'),
            auth()->id()
        );

        return response()->json([
            'message' => 'Reminder scheduled successfully.',
            'data' => $reminder
        ], 201);
    }
}
