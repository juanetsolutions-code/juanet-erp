<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Models\NotificationLog;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Notification\Services\NotificationTemplateService;
use App\Domain\Notification\Services\NotificationPreferenceService;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    protected NotificationRepositoryInterface $repository;
    protected NotificationTemplateService $templateService;
    protected NotificationPreferenceService $preferenceService;
    protected NotificationDispatcher $dispatcher;

    public function __construct(
        NotificationRepositoryInterface $repository,
        NotificationTemplateService $templateService,
        NotificationPreferenceService $preferenceService,
        NotificationDispatcher $dispatcher
    ) {
        $this->repository = $repository;
        $this->templateService = $templateService;
        $this->preferenceService = $preferenceService;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Centralized send function that uses templates, preference checks, and async dispatching.
     */
    public function send(
        string $userId,
        string $titleOrTemplate,
        string $bodyOrEventName,
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

        // 1. Check if category is enabled in user preferences
        if (!$this->preferenceService->isChannelEnabled($userId, 'in_app', $category, $organizationId)) {
            // Also log skip status
            NotificationLog::create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'event_name' => $bodyOrEventName,
                'payload' => $extraData,
                'status' => 'skipped_by_preferences',
            ]);
            return null;
        }

        // 2. Render Template or use Title/Body directly
        // If $bodyOrEventName corresponds to a registered template or has placeholders, we compile it.
        $rendered = $this->templateService->render($bodyOrEventName, array_merge([
            'title' => $titleOrTemplate,
            'user_name' => $user->name,
            'email' => $user->email,
        ], $extraData), $organizationId);

        $finalTitle = $rendered['subject'] ?? $titleOrTemplate;
        $finalBody = $rendered['markdown'] ?? $bodyOrEventName;

        // 3. Create the Database Notification (In-App)
        $notification = $this->repository->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'title' => $finalTitle,
            'body' => $finalBody,
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'is_read' => false,
            'data' => $extraData,
        ]);

        // 4. Log the Dispatch Action
        NotificationLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'event_name' => $bodyOrEventName,
            'payload' => $extraData,
            'status' => 'processed',
        ]);

        // 5. Centralized dispatching across all preferred channels
        $this->dispatcher->dispatch($notification, $rendered);

        return $notification;
    }

    /**
     * Send notification to multiple users.
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
        return $this->repository->markAllAsRead($userId, $organizationId);
    }

    /**
     * Get notifications for a user.
     */
    public function getUserNotifications(string $userId, ?string $organizationId = null, bool $unreadOnly = false): Collection
    {
        return $this->repository->getByUser($userId, $organizationId, $unreadOnly);
    }

    /**
     * Get user notification preferences.
     */
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference
    {
        return $this->preferenceService->getPreferences($userId, $organizationId);
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference
    {
        return $this->preferenceService->updatePreferences($userId, $channels, $categories, $organizationId);
    }
}
