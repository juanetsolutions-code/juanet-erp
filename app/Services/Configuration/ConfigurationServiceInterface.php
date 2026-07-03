<?php

namespace App\Services\Configuration;

interface ConfigurationServiceInterface
{
    /**
     * Retrieve a configuration setting value. Resolves inheritance chain:
     * Environment Override -> User Override -> Organization Override -> Platform Default -> App/Fallback Default.
     */
    public function get(string $key, mixed $default = null, ?string $orgId = null, ?string $userId = null): mixed;

    /**
     * Set a configuration setting value, with type casting and encryption options.
     */
    public function set(string $group, ?string $ownerId, string $key, mixed $value, string $type = 'string', bool $encrypt = false): void;

    /**
     * Delete a setting from the database and cache.
     */
    public function delete(string $group, ?string $ownerId, string $key): void;

    /**
     * Evaluate whether a feature flag is enabled for the current context.
     * Processes global toggles, beta gating, and targeting rules (e.g. user lists, tenant lists, rollout %).
     */
    public function isEnabled(string $featureKey, ?string $orgId = null, ?string $userId = null): bool;

    /**
     * Set or update a feature flag.
     */
    public function setFeatureFlag(string $key, bool $isEnabled, array $rules = [], bool $isBeta = false, ?string $description = null): void;

    /**
     * Enroll a tenant/user in a beta feature flag.
     */
    public function enrollInBeta(string $featureKey, ?string $orgId, ?string $userId): void;

    /**
     * Unenroll a tenant/user from a beta feature flag.
     */
    public function unenrollFromBeta(string $featureKey, ?string $orgId, ?string $userId): void;
}
