<?php

namespace App\Services\Configuration;

use App\Repositories\SettingsRepositoryInterface;
use App\Services\Cache\TenantCacheManagerInterface;
use App\Services\Cache\CacheInvalidator;
use App\Models\Setting;
use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Log;
use Closure;

class ConfigurationService implements ConfigurationServiceInterface
{
    protected SettingsRepositoryInterface $repository;
    protected TenantCacheManagerInterface $cacheManager;
    protected CacheInvalidator $cacheInvalidator;

    public function __construct(
        SettingsRepositoryInterface $repository,
        TenantCacheManagerInterface $cacheManager,
        CacheInvalidator $cacheInvalidator
    ) {
        $this->repository = $repository;
        $this->cacheManager = $cacheManager;
        $this->cacheInvalidator = $cacheInvalidator;
    }

    /**
     * Retrieve a configuration setting value. Resolves inheritance chain:
     * Environment Override -> User Override -> Organization Override -> Platform Default -> App/Fallback Default.
     */
    public function get(string $key, mixed $default = null, ?string $orgId = null, ?string $userId = null): mixed
    {
        // 1. Environment Override Check
        // Matches env variable SETTING_SOME_KEY for a setting named 'some.key'
        $envKey = 'SETTING_' . strtoupper(str_replace(['.', '-'], '_', $key));
        if (($envValue = env($envKey)) !== null) {
            return $this->castRawValue($envValue);
        }

        // 2. User-specific Settings Override
        if ($userId) {
            $userVal = $this->cacheManager->setTenantId($orgId)->rememberConfig("user:{$userId}:{$key}", function () use ($userId, $key) {
                $setting = $this->repository->get('user', $userId, $key);
                return $setting ? $setting->getCastValue() : '__NOT_SET__';
            });

            if ($userVal !== '__NOT_SET__') {
                return $userVal;
            }
        }

        // 3. Organization-specific Settings Override
        if ($orgId) {
            $orgVal = $this->cacheManager->setTenantId($orgId)->rememberConfig("org:{$key}", function () use ($orgId, $key) {
                $setting = $this->repository->get('organization', $orgId, $key);
                return $setting ? $setting->getCastValue() : '__NOT_SET__';
            });

            if ($orgVal !== '__NOT_SET__') {
                return $orgVal;
            }
        }

        // 4. Platform-wide Settings Default
        $platformVal = $this->cacheManager->setTenantId(null)->rememberConfig("platform:{$key}", function () use ($key) {
            $setting = $this->repository->get('platform', null, $key);
            return $setting ? $setting->getCastValue() : '__NOT_SET__';
        });

        if ($platformVal !== '__NOT_SET__') {
            return $platformVal;
        }

        return $default;
    }

    /**
     * Set a configuration setting value, with type casting and encryption options.
     */
    public function set(string $group, ?string $ownerId, string $key, mixed $value, string $type = 'string', bool $encrypt = false): void
    {
        $this->repository->set($group, $ownerId, $key, $value, $type, $encrypt);

        // Invalidate appropriate cache based on setting group
        if ($group === 'user') {
            $this->cacheInvalidator->invalidateConfig("user:{$ownerId}:{$key}", null);
        } elseif ($group === 'organization') {
            $this->cacheInvalidator->invalidateConfig("org:{$key}", $ownerId);
        } else {
            $this->cacheInvalidator->invalidateConfig("platform:{$key}", null);
        }
    }

    /**
     * Delete a setting from the database and cache.
     */
    public function delete(string $group, ?string $ownerId, string $key): void
    {
        $this->repository->delete($group, $ownerId, $key);

        // Invalidate appropriate cache based on setting group
        if ($group === 'user') {
            $this->cacheInvalidator->invalidateConfig("user:{$ownerId}:{$key}", null);
        } elseif ($group === 'organization') {
            $this->cacheInvalidator->invalidateConfig("org:{$key}", $ownerId);
        } else {
            $this->cacheInvalidator->invalidateConfig("platform:{$key}", null);
        }
    }

    /**
     * Evaluate whether a feature flag is enabled for the current context.
     * Processes global toggles, beta gating, and targeting rules.
     */
    public function isEnabled(string $featureKey, ?string $orgId = null, ?string $userId = null): bool
    {
        // Cache feature flag metadata lookup
        $flag = $this->cacheManager->rememberFeature($featureKey, function () use ($featureKey) {
            return $this->repository->getFeatureFlag($featureKey);
        });

        if (!$flag) {
            return false;
        }

        // 1. If global toggle is off, completely off unless overrides/beta allow it? 
        // Standard feature flag design: if globally disabled, it is OFF. Let's respect this unless rules say otherwise.
        if (!$flag->is_enabled) {
            return false;
        }

        // 2. Beta Feature Gating Check
        if ($flag->is_beta) {
            // Must be enrolled in beta to access
            if (!$this->repository->isEnrolledInBeta($featureKey, $orgId, $userId)) {
                return false;
            }
        }

        // 3. Evaluate Targeting Rules
        if (!empty($flag->rules)) {
            return $this->evaluateRules($flag->rules, $orgId, $userId);
        }

        return true;
    }

    /**
     * Set or update a feature flag.
     */
    public function setFeatureFlag(string $key, bool $isEnabled, array $rules = [], bool $isBeta = false, ?string $description = null): void
    {
        $this->repository->setFeatureFlag($key, $isEnabled, $rules, $isBeta, $description);
        $this->cacheInvalidator->invalidateFeature($key, null);
    }

    /**
     * Enroll a tenant/user in a beta feature flag.
     */
    public function enrollInBeta(string $featureKey, ?string $orgId, ?string $userId): void
    {
        $this->repository->enrollInBeta($featureKey, $orgId, $userId);
        $this->cacheInvalidator->invalidateFeature($featureKey, null);
    }

    /**
     * Unenroll a tenant/user from a beta feature flag.
     */
    public function unenrollFromBeta(string $featureKey, ?string $orgId, ?string $userId): void
    {
        $this->repository->unenrollFromBeta($featureKey, $orgId, $userId);
        $this->cacheInvalidator->invalidateFeature($featureKey, null);
    }

    /**
     * Evaluates complex targeting rules for feature flags.
     */
    protected function evaluateRules(array $rules, ?string $orgId, ?string $userId): bool
    {
        // User target list rule
        if (isset($rules['users']) && is_array($rules['users'])) {
            if (!$userId || !in_array($userId, $rules['users'])) {
                return false;
            }
        }

        // Organization/Tenant target list rule
        if (isset($rules['organizations']) && is_array($rules['organizations'])) {
            if (!$orgId || !in_array($orgId, $rules['organizations'])) {
                return false;
            }
        }

        // Rollout percentage rule (deterministic bucket mapping via CRC32)
        if (isset($rules['rollout'])) {
            $rolloutPercent = (int) $rules['rollout'];
            if ($rolloutPercent <= 0) {
                return false;
            }
            if ($rolloutPercent < 100) {
                $seedId = $userId ?? $orgId;
                if (!$seedId) {
                    return false; // Can't evaluate rollout deterministically without an ID
                }
                $bucket = crc32($featureKey . ':' . $seedId) % 100;
                if (abs($bucket) >= $rolloutPercent) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Cast string environment values back to native primitives where appropriate.
     */
    protected function castRawValue(string $value): mixed
    {
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        
        // If JSON formatted
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
