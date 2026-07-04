<?php

namespace App\Domain\CRM\Activities\Repositories;

use App\Domain\CRM\Activities\Contracts\ActivityRepositoryInterface;
use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\DTO\ActivityData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ActivityRepository implements ActivityRepositoryInterface
{
    public function create(ActivityData $data): Activity
    {
        return Activity::create($data->toArray());
    }

    public function update(Activity $activity, ActivityData $data): Activity
    {
        $activity->update($data->toArray());
        return $activity;
    }

    public function delete(Activity $activity): bool
    {
        return (bool) $activity->delete();
    }

    public function findById(string $id, string $organizationId): ?Activity
    {
        return Activity::where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function getForEntity(string $loggableType, string $loggableId, string $organizationId, array $filters = []): Collection
    {
        return $this->buildQuery($loggableType, $loggableId, $organizationId, $filters)->get();
    }

    public function paginateForEntity(string $loggableType, string $loggableId, string $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildQuery($loggableType, $loggableId, $organizationId, $filters)
            ->paginate($perPage);
    }

    public function getOverdueTasks(string $organizationId): Collection
    {
        return Activity::where('organization_id', $organizationId)
            ->where('type', 'follow_up_task')
            ->where('is_completed', false)
            ->where('due_at', '<', Carbon::now())
            ->get();
    }

    public function bulkComplete(array $ids, string $organizationId): int
    {
        return Activity::whereIn('id', $ids)
            ->where('organization_id', $organizationId)
            ->update([
                'is_completed' => true,
                'completed_at' => Carbon::now()
            ]);
    }

    public function bulkUpdate(array $ids, array $attributes, string $organizationId): int
    {
        // Enforce safe attributes for mass updates
        $allowed = ['priority', 'user_id', 'is_completed'];
        $filtered = array_intersect_key($attributes, array_flip($allowed));

        if (empty($filtered)) {
            return 0;
        }

        if (isset($filtered['is_completed']) && $filtered['is_completed']) {
            $filtered['completed_at'] = Carbon::now();
        }

        return Activity::whereIn('id', $ids)
            ->where('organization_id', $organizationId)
            ->update($filtered);
    }

    protected function buildQuery(string $loggableType, string $loggableId, string $organizationId, array $filters = [])
    {
        $query = Activity::where('organization_id', $organizationId)
            ->where('loggable_type', $loggableType)
            ->where('loggable_id', $loggableId)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_completed'])) {
            $query->where('is_completed', (bool)$filters['is_completed']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        return $query;
    }
}
