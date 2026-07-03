<?php

namespace App\Services\Cache;

use Closure;

interface TenantCacheManagerInterface
{
    /**
     * Set the current tenant (organization) ID context.
     */
    public function setTenantId(?string $tenantId): self;

    /**
     * Get a tenant-scoped cache key.
     */
    public function getTenantKey(string $segment, string $key): string;

    /**
     * Accessor for Dashboard Cache.
     */
    public function rememberDashboard(string $userId, Closure $callback): mixed;

    /**
     * Accessor for Search Cache.
     */
    public function rememberSearch(string $query, array $modules, Closure $callback): mixed;

    /**
     * Accessor for Organization Cache.
     */
    public function rememberOrganization(string $orgId, Closure $callback): mixed;

    /**
     * Accessor for Permission Cache.
     */
    public function rememberPermissions(string $userId, Closure $callback): mixed;

    /**
     * Accessor for Configuration Cache.
     */
    public function rememberConfig(string $configKey, Closure $callback): mixed;

    /**
     * Accessor for Feature Cache.
     */
    public function rememberFeature(string $featureKey, Closure $callback): mixed;

    /**
     * Invalidates cache by tags or key prefixes.
     */
    public function invalidateDashboard(string $userId): void;

    public function invalidateSearch(): void;

    public function invalidateOrganization(string $orgId): void;

    public function invalidatePermissions(string $userId): void;

    public function invalidateConfig(string $configKey): void;

    public function invalidateFeature(string $featureKey): void;
}
