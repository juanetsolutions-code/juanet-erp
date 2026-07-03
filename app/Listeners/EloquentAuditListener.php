<?php

namespace App\Listeners;

use App\Services\AuditLogServiceInterface;
use App\Services\SecurityLogServiceInterface;
use App\Services\ActivityLogServiceInterface;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;

class EloquentAuditListener
{
    protected AuditLogServiceInterface $auditLogService;
    protected SecurityLogServiceInterface $securityLogService;
    protected ActivityLogServiceInterface $activityLogService;
    protected \App\Services\NotificationServiceInterface $notificationService;

    public function __construct(
        AuditLogServiceInterface $auditLogService,
        SecurityLogServiceInterface $securityLogService,
        ActivityLogServiceInterface $activityLogService,
        \App\Services\NotificationServiceInterface $notificationService
    ) {
        $this->auditLogService = $auditLogService;
        $this->securityLogService = $securityLogService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle Eloquent events.
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;

        if (!$model) {
            return;
        }

        // Prevent infinite loops on logging models
        if ($model instanceof \App\Models\ActivityLog ||
            $model instanceof \App\Models\AuditLog ||
            $model instanceof \App\Models\SecurityLog ||
            $model instanceof \App\Models\ExceptionLog) {
            return;
        }

        // Determine event type from string (e.g., eloquent.created: App\Models\User -> created)
        $eventType = 'updated';
        if (str_contains($event, 'created')) {
            $eventType = 'created';
        } elseif (str_contains($event, 'deleted')) {
            $eventType = 'deleted';
        }

        $oldValues = [];
        $newValues = [];

        if ($eventType === 'created') {
            $newValues = $model->getAttributes();
            // Remove sensitive fields
            unset($newValues['password'], $newValues['remember_token']);
        } elseif ($eventType === 'deleted') {
            $oldValues = $model->getAttributes();
            unset($oldValues['password'], $oldValues['remember_token']);
        } elseif ($eventType === 'updated') {
            $dirty = $model->getDirty();
            foreach ($dirty as $key => $value) {
                if ($key === 'updated_at' || $key === 'version') {
                    continue;
                }
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $value;
            }

            // Remove sensitive fields from dirty values
            unset($oldValues['password'], $oldValues['remember_token']);
            unset($newValues['password'], $newValues['remember_token']);
        }

        // 1. Write the standard Audit Log
        $this->auditLogService->log(
            $model,
            $eventType,
            empty($oldValues) ? null : $oldValues,
            empty($newValues) ? null : $newValues
        );

        // 2. Automatically log special events: Password Changes
        if ($model instanceof User && $eventType === 'updated' && $model->isDirty('password')) {
            $this->securityLogService->log(
                'password_change',
                'warning',
                "Password changed for user: {$model->email}",
                $model->id
            );

            $this->notificationService->send(
                $model->id,
                'Security Warning: Password Changed',
                'The password for your account was updated. If you did not initiate this, please contact support immediately.',
                'warning',
                'security',
                'high'
            );
        }

        // 3. Automatically log special events: Organization Changes
        if ($model instanceof Organization) {
            $this->activityLogService->log(
                "organization_{$eventType}",
                "Organization '{$model->name}' was {$eventType}.",
                'organization',
                null,
                $model->id
            );
        }

        if ($model instanceof OrganizationMember) {
            $this->activityLogService->log(
                "membership_{$eventType}",
                "Membership for user {$model->user_id} was {$eventType}.",
                'organization',
                $model->user_id,
                $model->organization_id
            );

            // Notify user about membership changes
            $orgName = $model->organization ? $model->organization->name : 'Workspace';
            $this->notificationService->send(
                $model->user_id,
                "Workspace Membership Updated",
                "Your membership in {$orgName} was {$eventType}.",
                'info',
                'system',
                'normal',
                $model->organization_id
            );
        }

        // 4. Automatically log special events: Permission Changes
        if ($model instanceof Role) {
            $this->activityLogService->log(
                "role_{$eventType}",
                "Role '{$model->name}' was {$eventType}.",
                'auth',
                null,
                $model->organization_id
            );
        }

        if ($model instanceof Permission) {
            $this->activityLogService->log(
                "permission_{$eventType}",
                "Permission '{$model->name}' was {$eventType}.",
                'auth'
            );
        }
    }
}
