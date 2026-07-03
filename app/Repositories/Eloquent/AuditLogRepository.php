<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\AuditLogRepositoryInterface;
use Illuminate\Support\Collection;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function find(string $id): ?AuditLog
    {
        return AuditLog::find($id);
    }

    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    public function getByOrganization(string $organizationId): Collection
    {
        return AuditLog::where('organization_id', $organizationId)->get();
    }

    public function getByAuditable(string $type, string $id): Collection
    {
        return AuditLog::where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->get();
    }
}
