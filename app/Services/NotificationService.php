<?php

namespace App\Services;

use App\Events\NotificationSentEvent;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\EnterpriseNotification;
use App\Repositories\NotificationRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class NotificationService implements NotificationServiceInterface
{
    protected NotificationRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        NotificationRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

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
    ): ?Notification {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $orgId = $organizationId ?? $this->tenantContext->getTenantId();

        // 1. Fetch preferences
        $prefs = $this->repository->getPreferences($userId, $orgId);

        // Check if category is enabled
        $categoryEnabled = $prefs->categories[$category] ?? true;
        if (!$categoryEnabled) {
            return null;
        }

        $notification = null;

        // 2. Save to database if channel is enabled
        $dbEnabled = $prefs->channels['database'] ?? true;
        if ($dbEnabled) {
            $notification = $this->repository->create([
                'organization_id' => $orgId,
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'category' => $category,
                'priority' => $priority,
                'is_read' => false,
                'data' => $extraData,
            ]);

            // 3. Fire real-time broadcast event if toast/real-time is enabled
            $toastEnabled = $prefs->channels['toast'] ?? true;
            if ($toastEnabled) {
                event(new NotificationSentEvent($notification));
            }
        }

        // 4. Send email if channel is enabled
        $emailEnabled = $prefs->channels['email'] ?? true;
        if ($emailEnabled) {
            // Instantiate standard Laravel notification and dispatch to mail only
            // We use a clean class or pass a specific configuration
            $laravelNotification = new EnterpriseNotification(
                $title,
                $body,
                $type,
                $category,
                $priority,
                $orgId,
                $extraData
            );

            // We can dispatch standard Laravel notification
            $user->notify($laravelNotification);
        }

        return $notification;
    }

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
    ): void {
        foreach ($userIds as $userId) {
            $this->send($userId, $title, $body, $type, $category, $priority, $organizationId, $extraData);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $notificationId): bool
    {
        return $this->repository->markAsRead($notificationId);
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(string $userId, ?string $organizationId = null): bool
    {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();
        return $this->repository->markAllAsRead($userId, $orgId);
    }

    /**
     * Get notifications for a user.
     */
    public function getUserNotifications(string $userId, ?string $organizationId = null, bool $unreadOnly = false): Collection
    {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();
        return $this->repository->getByUser($userId, $orgId, $unreadOnly);
    }

    /**
     * Get user notification preferences.
     */
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference
    {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();
        return $this->repository->getPreferences($userId, $orgId);
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference
    {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();
        return $this->repository->updatePreferences($userId, $channels, $categories, $orgId);
    }
}
