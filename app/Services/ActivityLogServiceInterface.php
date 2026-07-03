<?php

namespace App\Services;

use App\Models\ActivityLog;

interface ActivityLogServiceInterface
{
    /**
     * Log a user activity.
     */
    public function log(
        string $action,
        ?string $description = null,
        string $module = 'core',
        ?string $userId = null,
        ?string $organizationId = null
    ): ActivityLog;
}
