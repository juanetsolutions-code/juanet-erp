<?php

namespace App\Repositories\Eloquent;

use App\Models\SecurityLog;
use App\Repositories\SecurityLogRepositoryInterface;
use Illuminate\Support\Collection;

class SecurityLogRepository implements SecurityLogRepositoryInterface
{
    public function find(string $id): ?SecurityLog
    {
        return SecurityLog::find($id);
    }

    public function create(array $data): SecurityLog
    {
        return SecurityLog::create($data);
    }

    public function getByOrganization(string $organizationId): Collection
    {
        return SecurityLog::where('organization_id', $organizationId)->get();
    }

    public function getBySeverity(string $severity): Collection
    {
        return SecurityLog::where('severity', $severity)->get();
    }
}
