<?php

namespace App\Domain\CRM\Activities\Services;

use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\Models\ActivityNote;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Models\LeadStatusHistory;
use App\Domain\CRM\Models\LeadAssignmentHistory;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class TimelineEngine
{
    /**
     * Generate unified chronological timeline for a specific loggable entity (Lead, Company, etc.)
     */
    public function getTimeline(string $entityType, string $entityId, string $organizationId, array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $timeline = collect();

        // 1. Fetch polymorphic activities
        $activities = Activity::where('organization_id', $organizationId)
            ->where('loggable_type', $entityType)
            ->where('loggable_id', $entityId)
            ->with(['assignee', 'attachments.file', 'notes'])
            ->get();

        foreach ($activities as $act) {
            $timeline->push([
                'id' => $act->id,
                'source' => 'activity',
                'type' => $act->type, // phone_call, meeting, follow_up_task, etc.
                'subject' => $act->subject,
                'description' => $act->description,
                'user_name' => $act->assignee?->name ?? 'System',
                'user_id' => $act->user_id,
                'properties' => $act->properties,
                'timestamp' => $act->created_at,
                'due_at' => $act->due_at,
                'completed_at' => $act->completed_at,
                'is_completed' => $act->is_completed,
                'priority' => $act->priority,
                'attachments' => $act->attachments->map(fn($att) => [
                    'id' => $att->id,
                    'file_id' => $att->stored_file_id,
                    'name' => $att->file?->name,
                    'mime_type' => $att->file?->mime_type,
                    'size' => $att->file?->size,
                ]),
                'notes_count' => $act->notes->count(),
            ]);
        }

        // 2. Fetch polymorphic standalone notes (where notable is this entity)
        $notes = ActivityNote::where('organization_id', $organizationId)
            ->where('notable_type', $entityType)
            ->where('notable_id', $entityId)
            ->with('user')
            ->get();

        foreach ($notes as $note) {
            $timeline->push([
                'id' => $note->id,
                'source' => 'note',
                'type' => 'internal_note',
                'subject' => 'Internal Note added',
                'description' => $note->content,
                'user_name' => $note->user?->name ?? 'System',
                'user_id' => $note->user_id,
                'properties' => [
                    'version' => $note->version,
                    'parent_id' => $note->parent_id,
                    'original_note_id' => $note->original_note_id
                ],
                'timestamp' => $note->created_at,
                'attachments' => [],
            ]);
        }

        // 3. For Lead entity, include legacy log streams (lead activities, status history, assignment history)
        if (str_contains($entityType, 'Lead') || str_contains($entityType, 'lead')) {
            // Lead activities (Phase 4.2)
            $leadActivities = LeadActivity::where('organization_id', $organizationId)
                ->where('lead_id', $entityId)
                ->with('user')
                ->get();

            foreach ($leadActivities as $la) {
                $timeline->push([
                    'id' => $la->id,
                    'source' => 'lead_activity',
                    'type' => $la->type,
                    'subject' => ucfirst($la->type) . ' logged',
                    'description' => $la->description,
                    'user_name' => $la->user?->name ?? 'System',
                    'user_id' => $la->user_id,
                    'properties' => $la->properties,
                    'timestamp' => $la->created_at,
                    'attachments' => [],
                ]);
            }

            // Lead status history
            $statusHistory = LeadStatusHistory::where('organization_id', $organizationId)
                ->where('lead_id', $entityId)
                ->with('changer')
                ->get();

            foreach ($statusHistory as $sh) {
                $timeline->push([
                    'id' => $sh->id,
                    'source' => 'status_history',
                    'type' => 'status_change',
                    'subject' => 'Status changed',
                    'description' => "Status changed from '{$sh->from_status}' to '{$sh->to_status}'",
                    'user_name' => $sh->changer?->name ?? 'System',
                    'user_id' => $sh->changed_by,
                    'properties' => ['from_status' => $sh->from_status, 'to_status' => $sh->to_status, 'reason' => $sh->reason],
                    'timestamp' => $sh->created_at,
                    'attachments' => [],
                ]);
            }

            // Lead assignment history
            $assignHistory = LeadAssignmentHistory::where('organization_id', $organizationId)
                ->where('lead_id', $entityId)
                ->with(['assigner', 'fromUser', 'toUser'])
                ->get();

            foreach ($assignHistory as $ah) {
                $timeline->push([
                    'id' => $ah->id,
                    'source' => 'assignment_history',
                    'type' => 'assignment_change',
                    'subject' => 'Lead reassigned',
                    'description' => "Reassigned from " . ($ah->fromUser?->name ?? 'Unassigned') . " to " . ($ah->toUser?->name ?? 'Unassigned'),
                    'user_name' => $ah->assigner?->name ?? 'System',
                    'user_id' => $ah->assigned_by,
                    'properties' => ['from_user' => $ah->fromUser?->name, 'to_user' => $ah->toUser?->name, 'method' => $ah->method],
                    'timestamp' => $ah->created_at,
                    'attachments' => [],
                ]);
            }
        }

        // Apply filters & search
        $filtered = $this->applyFiltersAndSearch($timeline, $filters);

        // Sort chronological (newest first)
        $sorted = $filtered->sortByDesc(function ($item) {
            return Carbon::parse($item['timestamp'])->timestamp;
        });

        // Paginate manual collection
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $sliced = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);
    }

    protected function applyFiltersAndSearch(Collection $timeline, array $filters): Collection
    {
        return $timeline->filter(function ($item) use ($filters) {
            // Type Filter
            if (!empty($filters['type']) && $item['type'] !== $filters['type'] && $item['source'] !== $filters['type']) {
                return false;
            }

            // User Filter
            if (!empty($filters['user_id']) && $item['user_id'] !== $filters['user_id']) {
                return false;
            }

            // Date Range
            if (!empty($filters['start_date'])) {
                $start = Carbon::parse($filters['start_date'])->startOfDay();
                if (Carbon::parse($item['timestamp'])->lt($start)) {
                    return false;
                }
            }

            if (!empty($filters['end_date'])) {
                $end = Carbon::parse($filters['end_date'])->endOfDay();
                if (Carbon::parse($item['timestamp'])->gt($end)) {
                    return false;
                }
            }

            // Full text search
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $subMatch = str_contains(strtolower($item['subject']), $search);
                $descMatch = str_contains(strtolower($item['description'] ?? ''), $search);
                return $subMatch || $descMatch;
            }

            return true;
        });
    }
}
