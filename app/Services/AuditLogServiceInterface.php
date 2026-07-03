<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

interface AuditLogServiceInterface
{
    /**
     * Log an audit event.
     */
    public function log(
        Model $model,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $organizationId = null
    ): AuditLog;
}
