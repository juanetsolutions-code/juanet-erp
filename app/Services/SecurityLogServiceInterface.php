<?php

namespace App\Services;

use App\Models\SecurityLog;

interface SecurityLogServiceInterface
{
    /**
     * Log a security event.
     */
    public function log(
        string $eventType,
        string $severity = 'info',
        ?string $description = null,
        ?string $userId = null,
        ?string $organizationId = null
    ): SecurityLog;
}
