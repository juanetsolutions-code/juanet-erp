<?php

namespace App\Services;

use App\Jobs\LogSecurityJob;
use App\Models\SecurityLog;
use App\Repositories\SecurityLogRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class SecurityLogService implements SecurityLogServiceInterface
{
    protected SecurityLogRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        SecurityLogRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

    public function log(
        string $eventType,
        string $severity = 'info',
        ?string $description = null,
        ?string $userId = null,
        ?string $organizationId = null
    ): SecurityLog {
        $data = [
            'organization_id' => $organizationId ?? $this->tenantContext->getTenantId(),
            'user_id' => $userId ?? Auth::id(),
            'event_type' => $eventType,
            'severity' => $severity,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (config('logging.enterprise.queue', false)) {
            LogSecurityJob::dispatch($data);
            return new SecurityLog($data);
        }

        return $this->repository->create($data);
    }
}
