<?php

namespace App\Domain\Notification\Repositories;

use App\Domain\Notification\Models\NotificationPreference;

interface NotificationPreferenceRepositoryInterface
{
    public function getPreferences(string $userId, ?string $organizationId = null): NotificationPreference;
    public function updatePreferences(string $userId, array $channels, array $categories, ?string $organizationId = null): NotificationPreference;
}
