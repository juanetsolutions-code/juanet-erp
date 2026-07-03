<?php

namespace App\Services;

use App\Jobs\LogExceptionJob;
use App\Models\ExceptionLog;
use App\Repositories\ExceptionLogRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExceptionLogService implements ExceptionLogServiceInterface
{
    protected ExceptionLogRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        ExceptionLogRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

    public function log(
        Throwable $exception,
        ?string $userId = null,
        ?string $organizationId = null
    ): ExceptionLog {
        $data = [
            'organization_id' => $organizationId ?? $this->tenantContext->getTenantId(),
            'user_id' => $userId ?? Auth::id(),
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (config('logging.enterprise.queue', false)) {
            LogExceptionJob::dispatch($data);
            return new ExceptionLog($data);
        }

        return $this->repository->create($data);
    }
}
