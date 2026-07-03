<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Models\NotificationPreference;
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
    
    // Preferences
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference;
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference;
}
