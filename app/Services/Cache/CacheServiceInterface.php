<?php

namespace App\Services\Cache;

use Closure;

interface CacheServiceInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache for a given duration (in seconds).
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Retrieve an item from the cache, or store the default value if it doesn't exist.
     */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed;

    /**
     * Remove an item from the cache by key.
     */
    public function forget(string $key): bool;

    /**
     * Clear all cached items.
     */
    public function clear(): bool;

    /**
     * Begin a tagged cache operation if supported by the driver,
     * or fallback to namespace/key tracking.
     */
    public function tags(array $tags): self;
}
