<?php

namespace App\Repositories\Eloquent;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    protected \App\Services\TenantContext $tenantContext;

    public function __construct(\App\Services\TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Notification
    {
        return Notification::find($id);
    }

    public function create(array $data): Notification
    {
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $this->tenantContext->getTenantId();
        }
        return Notification::create($data);
    }

    public function update(string $id, array $data): ?Notification
    {
        $notification = $this->find($id);
        if ($notification) {
            $notification->update($data);
        }
        return $notification;
    }

    public function delete(string $id): bool
    {
        $notification = $this->find($id);
        if ($notification) {
            return $notification->delete();
        }
        return false;
    }

    public function getByUser(string $userId, ?string $organizationId = null, bool $unreadOnly = false): Collection
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        $query = Notification::where('user_id', $userId);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function markAsRead(string $id): bool
    {
        $notification = $this->find($id);
        if ($notification) {
            return $notification->update(['is_read' => true]);
        }
        return false;
    }

    public function markAllAsRead(string $userId, ?string $organizationId = null): bool
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        $query = Notification::where('user_id', $userId)->where('is_read', false);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query->update(['is_read' => true]) > 0;
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
                    'database' => true,
                    'email' => true,
                    'toast' => true,
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
                'channels' => array_merge($pref->channels, $channels),
                'categories' => array_merge($pref->categories, $categories),
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
