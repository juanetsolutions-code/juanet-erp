<?php

namespace App\Services;

use App\Jobs\LogActivityJob;
use App\Models\ActivityLog;
use App\Repositories\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class ActivityLogService implements ActivityLogServiceInterface
{
    protected ActivityLogRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        ActivityLogRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

    public function log(
        string $action,
        ?string $description = null,
        string $module = 'core',
        ?string $userId = null,
        ?string $organizationId = null
    ): ActivityLog {
        $data = [
            'organization_id' => $organizationId ?? $this->tenantContext->getTenantId(),
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'module' => $module,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (config('logging.enterprise.queue', false)) {
            LogActivityJob::dispatch($data);
            // Create a transient model instance for return in async flow
            return new ActivityLog($data);
        }

        return $this->repository->create($data);
    }
}
