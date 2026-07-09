<?php

namespace App\Domain\Notification\Repositories;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationDelivery;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function find(string $id): ?Notification;
    public function create(array $data): Notification;
    public function update(string $id, array $data): ?Notification;
    public function delete(string $id): bool;
    public function getByUser(string $userId, ?string $organizationId = null, bool $unreadOnly = false): Collection;
    public function markAsRead(string $id): bool;
    public function markAllAsRead(string $userId, ?string $organizationId = null): bool;
    
    // Delivery Tracking
    public function createDelivery(array $data): NotificationDelivery;
    public function updateDelivery(string $id, array $data): ?NotificationDelivery;
    public function getDeliveriesByNotification(string $notificationId): Collection;
}
