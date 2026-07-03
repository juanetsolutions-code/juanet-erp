<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Collection;

interface NotificationServiceInterface
{
    /**
     * Send a notification to a specific user.
     */
    public function send(
        string $userId,
        string $title,
        string $body,
        string $type = 'info',
        string $category = 'system',
        string $priority = 'normal',
        ?string $organizationId = null,
        array $extraData = []
    ): ?Notification;

    /**
     * Send a notification to multiple users.
     */
    public function sendGroup(
        array $userIds,
        string $title,
        string $body,
        string $type = 'info',
        string $category = 'system',
        string $priority = 'normal',
        ?string $organizationId = null,
        array $extraData = []
    ): void;

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $notificationId): bool;

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(string $userId, ?string $organizationId = null): bool;

    /**
     * Get notifications for a user.
     */
    public function getUserNotifications(string $userId, ?string $organizationId = null, bool $unreadOnly = false): Collection;

    /**
     * Get user notification preferences.
     */
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference;

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference;
}
