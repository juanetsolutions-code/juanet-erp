<?php

namespace App\Services\Cache;

use App\Events\CacheInvalidatedEvent;
use Illuminate\Support\Facades\Log;

class CacheInvalidator
{
    protected TenantCacheManagerInterface $cacheManager;

    public function __construct(TenantCacheManagerInterface $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Invalidate dashboard caches for a specific user within a tenant.
     */
    public function invalidateUserDashboard(string $userId, ?string $tenantId = null): void
    {
        try {
            $this->cacheManager->setTenantId($tenantId)->invalidateDashboard($userId);
            event(new CacheInvalidatedEvent("user:{$userId}:dashboard", $tenantId, 'dashboard'));
            Log::info("Dashboard cache invalidated for user [{$userId}] in tenant [{$tenantId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate dashboard cache: " . $e->getMessage());
        }
    }

    /**
     * Invalidate permission caches for a specific user.
     */
    public function invalidateUserPermissions(string $userId, ?string $tenantId = null): void
    {
        try {
            $this->cacheManager->setTenantId($tenantId)->invalidatePermissions($userId);
            event(new CacheInvalidatedEvent("user:{$userId}:permissions", $tenantId, 'permissions'));
            Log::info("Permission cache invalidated for user [{$userId}] in tenant [{$tenantId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate permission cache: " . $e->getMessage());
        }
    }

    /**
     * Invalidate organization metadata cache.
     */
    public function invalidateOrganization(string $orgId): void
    {
        try {
            $this->cacheManager->invalidateOrganization($orgId);
            event(new CacheInvalidatedEvent("org:{$orgId}", $orgId, 'organization'));
            Log::info("Organization cache invalidated for org [{$orgId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate organization cache: " . $e->getMessage());
        }
    }

    /**
     * Invalidate search queries cache within a tenant.
     */
    public function invalidateSearch(?string $tenantId = null): void
    {
        try {
            $this->cacheManager->setTenantId($tenantId)->invalidateSearch();
            event(new CacheInvalidatedEvent("search_index", $tenantId, 'search'));
            Log::info("Search cache flushed for tenant [{$tenantId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate search cache: " . $e->getMessage());
        }
    }

    /**
     * Invalidate configuration cache.
     */
    public function invalidateConfig(string $configKey, ?string $tenantId = null): void
    {
        try {
            $this->cacheManager->setTenantId($tenantId)->invalidateConfig($configKey);
            event(new CacheInvalidatedEvent("config:{$configKey}", $tenantId, 'config'));
            Log::info("Configuration cache [{$configKey}] invalidated for tenant [{$tenantId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate configuration cache: " . $e->getMessage());
        }
    }

    /**
     * Invalidate feature flag cache.
     */
    public function invalidateFeature(string $featureKey, ?string $tenantId = null): void
    {
        try {
            $this->cacheManager->setTenantId($tenantId)->invalidateFeature($featureKey);
            event(new CacheInvalidatedEvent("feature:{$featureKey}", $tenantId, 'feature'));
            Log::info("Feature flag cache [{$featureKey}] invalidated for tenant [{$tenantId}].");
        } catch (\Throwable $e) {
            Log::error("Failed to invalidate feature flag cache: " . $e->getMessage());
        }
    }
}
