<?php

namespace App\Services\Cache;

use Closure;
use App\Services\TenantContext;

class TenantCacheManager implements TenantCacheManagerInterface
{
    protected CacheService $cache;
    protected ?string $tenantId = null;

    public function __construct(CacheService $cache, TenantContext $tenantContext)
    {
        $this->cache = $cache;
        $this->tenantId = $tenantContext->getTenantId();
    }

    /**
     * Set the current tenant (organization) ID context dynamically.
     */
    public function setTenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Get a tenant-scoped cache key.
     */
    public function getTenantKey(string $segment, string $key): string
    {
        $tenantPrefix = $this->tenantId ? "tenant:{$this->tenantId}:" : "global:";
        return "{$tenantPrefix}{$segment}:{$key}";
    }

    /**
     * Accessor for Dashboard Cache.
     */
    public function rememberDashboard(string $userId, Closure $callback): mixed
    {
        $key = $this->getTenantKey('dashboard', $userId);
        $tags = $this->getTags(['dashboard', "user:{$userId}"]);

        return $this->cache->tags($tags)->remember($key, 300, $callback);
    }

    /**
     * Accessor for Search Cache.
     */
    public function rememberSearch(string $query, array $modules, Closure $callback): mixed
    {
        $modulesStr = implode(',', $modules);
        $cleanQuery = md5(strtolower(trim($query)));
        $key = $this->getTenantKey('search', "{$cleanQuery}:{$modulesStr}");
        $tags = $this->getTags(['search']);

        return $this->cache->tags($tags)->remember($key, 600, $callback);
    }

    /**
     * Accessor for Organization Cache.
     */
    public function rememberOrganization(string $orgId, Closure $callback): mixed
    {
        $key = "global:organization:{$orgId}";
        $tags = ['organization', "org:{$orgId}"];

        return $this->cache->tags($tags)->remember($key, 3600, $callback);
    }

    /**
     * Accessor for Permission Cache.
     */
    public function rememberPermissions(string $userId, Closure $callback): mixed
    {
        $key = $this->getTenantKey('permissions', $userId);
        $tags = $this->getTags(['permissions', "user:{$userId}"]);

        return $this->cache->tags($tags)->remember($key, 1800, $callback);
    }

    /**
     * Accessor for Configuration Cache.
     */
    public function rememberConfig(string $configKey, Closure $callback): mixed
    {
        $key = $this->getTenantKey('config', $configKey);
        $tags = $this->getTags(['config']);

        return $this->cache->tags($tags)->remember($key, 86400, $callback);
    }

    /**
     * Accessor for Feature Cache.
     */
    public function rememberFeature(string $featureKey, Closure $callback): mixed
    {
        $key = $this->getTenantKey('feature', $featureKey);
        $tags = $this->getTags(['feature']);

        return $this->cache->tags($tags)->remember($key, 3600, $callback);
    }

    /**
     * Invalidates cache by tags.
     */
    public function invalidateDashboard(string $userId): void
    {
        $tags = $this->getTags(['dashboard', "user:{$userId}"]);
        $this->cache->flushTags($tags);
    }

    public function invalidateSearch(): void
    {
        $tags = $this->getTags(['search']);
        $this->cache->flushTags($tags);
    }

    public function invalidateOrganization(string $orgId): void
    {
        $tags = ['organization', "org:{$orgId}"];
        $this->cache->flushTags($tags);
    }

    public function invalidatePermissions(string $userId): void
    {
        $tags = $this->getTags(['permissions', "user:{$userId}"]);
        $this->cache->flushTags($tags);
    }

    public function invalidateConfig(string $configKey): void
    {
        $tags = $this->getTags(['config']);
        $this->cache->flushTags($tags);
    }

    public function invalidateFeature(string $featureKey): void
    {
        $tags = $this->getTags(['feature']);
        $this->cache->flushTags($tags);
    }

    /**
     * Automatically prefix tags with tenant context if available to prevent cross-tenant tag flushing.
     */
    protected function getTags(array $tags): array
    {
        if (!$this->tenantId) {
            return $tags;
        }

        return array_map(function ($tag) {
            return "tenant:{$this->tenantId}:{$tag}";
        }, $tags);
    }
}
