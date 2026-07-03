<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Repositories\ActivityLogRepositoryInterface;
use Illuminate\Support\Collection;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function find(string $id): ?ActivityLog
    {
        return ActivityLog::find($id);
    }

    public function create(array $data): ActivityLog
    {
        return ActivityLog::create($data);
    }

    public function getByOrganization(string $organizationId): Collection
    {
        return ActivityLog::where('organization_id', $organizationId)->get();
    }

    public function getByUser(string $userId): Collection
    {
        return ActivityLog::where('user_id', $userId)->get();
    }
}
