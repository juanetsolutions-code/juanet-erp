<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;

class NotificationPreferenceService
{
    protected NotificationPreferenceRepositoryInterface $repository;

    public function __construct(NotificationPreferenceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get user notification preferences.
     */
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference
    {
        return $this->repository->getPreferences($userId, $organizationId);
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference
    {
        return $this->repository->updatePreferences($userId, $channels, $categories, $organizationId);
    }

    /**
     * Check if a specific channel is enabled for a category/type.
     */
    public function isChannelEnabled(string $userId, string $channel, string $category, ?string $organizationId = null): bool
    {
        $prefs = $this->getPreferences($userId, $organizationId);
        
        $categoryEnabled = $prefs->categories[$category] ?? true;
        if (!$categoryEnabled) {
            return false;
        }

        return $prefs->channels[$channel] ?? true;
    }
}
