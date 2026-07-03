<?php

namespace App\Services;

use App\Models\ExceptionLog;
use Throwable;

interface ExceptionLogServiceInterface
{
    /**
     * Log an exception.
     */
    public function log(
        Throwable $exception,
        ?string $userId = null,
        ?string $organizationId = null
    ): ExceptionLog;
}
