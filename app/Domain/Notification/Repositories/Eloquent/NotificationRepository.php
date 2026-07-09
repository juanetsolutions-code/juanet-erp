<?php

namespace App\Domain\Notification\Repositories\Eloquent;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationDelivery;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
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

    // Delivery Tracking
    public function createDelivery(array $data): NotificationDelivery
    {
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $this->tenantContext->getTenantId();
        }
        return NotificationDelivery::create($data);
    }

    public function updateDelivery(string $id, array $data): ?NotificationDelivery
    {
        $delivery = NotificationDelivery::find($id);
        if ($delivery) {
            $delivery->update($data);
        }
        return $delivery;
    }

    public function getDeliveriesByNotification(string $notificationId): Collection
    {
        return NotificationDelivery::where('notification_id', $notificationId)->get();
    }
}
