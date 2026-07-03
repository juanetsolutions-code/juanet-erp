<?php

namespace App\Services;

use App\Jobs\LogAuditJob;
use App\Models\AuditLog;
use App\Repositories\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService implements AuditLogServiceInterface
{
    protected AuditLogRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        AuditLogRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

    public function log(
        Model $model,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $organizationId = null
    ): AuditLog {
        $data = [
            'organization_id' => $organizationId ?? $this->tenantContext->getTenantId(),
            'user_id' => $userId ?? Auth::id(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (config('logging.enterprise.queue', false)) {
            LogAuditJob::dispatch($data);
            return new AuditLog($data);
        }

        return $this->repository->create($data);
    }
}
