<?php

namespace App\Domain\Notification\Repositories\Eloquent;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;
use App\Services\TenantContext;

class NotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        $pref = NotificationPreference::where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$pref) {
            // Default preferences
            $pref = NotificationPreference::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'channels' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                    'push' => true,
                    'webhook' => false,
                ],
                'categories' => [
                    'system' => true,
                    'billing' => true,
                    'crm' => true,
                    'security' => true,
                ],
            ]);
        }

        return $pref;
    }

    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        $pref = NotificationPreference::where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($pref) {
            $pref->update([
                'channels' => array_merge($pref->channels ?? [], $channels),
                'categories' => array_merge($pref->categories ?? [], $categories),
            ]);
        } else {
            $pref = NotificationPreference::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'channels' => $channels,
                'categories' => $categories,
            ]);
        }

        return $pref;
    }
}
