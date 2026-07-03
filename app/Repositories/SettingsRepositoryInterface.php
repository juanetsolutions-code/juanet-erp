<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\FeatureFlag;
use App\Models\BetaEnrollment;
use Illuminate\Support\Collection;

interface SettingsRepositoryInterface
{
    /**
     * Retrieve a setting by its group, owner ID, and key.
     */
    public function get(string $group, ?string $ownerId, string $key): ?Setting;

    /**
     * Create or update a setting.
     */
    public function set(string $group, ?string $ownerId, string $key, mixed $value, string $type = 'string', bool $encrypt = false): Setting;

    /**
     * Delete a setting.
     */
    public function delete(string $group, ?string $ownerId, string $key): bool;

    /**
     * List settings for a given group and owner.
     */
    public function listByGroup(string $group, ?string $ownerId): Collection;

    /**
     * Retrieve a feature flag by key.
     */
    public function getFeatureFlag(string $key): ?FeatureFlag;

    /**
     * Create or update a feature flag.
     */
    public function setFeatureFlag(string $key, bool $isEnabled, array $rules = [], bool $isBeta = false, ?string $description = null): FeatureFlag;

    /**
     * Delete a feature flag.
     */
    public function deleteFeatureFlag(string $key): bool;

    /**
     * List all feature flags.
     */
    public function listFeatureFlags(): Collection;

    /**
     * Enroll a tenant/user into a beta feature flag.
     */
    public function enrollInBeta(string $featureKey, ?string $orgId, ?string $userId): BetaEnrollment;

    /**
     * Unenroll a tenant/user from a beta feature flag.
     */
    public function unenrollFromBeta(string $featureKey, ?string $orgId, ?string $userId): bool;

    /**
     * Check if enrollment exists.
     */
    public function isEnrolledInBeta(string $featureKey, ?string $orgId, ?string $userId): bool;
}
