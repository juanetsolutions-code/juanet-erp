<?php

namespace App\Services\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CacheService implements CacheServiceInterface
{
    protected array $activeTags = [];

    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (!empty($this->activeTags) && $this->supportsTags()) {
                $value = Cache::tags($this->activeTags)->get($key, $default);
                $this->activeTags = []; // Reset tags
                return $value;
            }

            return Cache::get($key, $default);
        } catch (Throwable $e) {
            Log::error("Cache read failed for key [{$key}]: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Store an item in the cache for a given duration (in seconds).
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $ttlDuration = $ttl ?? 3600; // default 1 hour

            if (!empty($this->activeTags) && $this->supportsTags()) {
                $status = Cache::tags($this->activeTags)->put($key, $value, $ttlDuration);
                $this->activeTags = []; // Reset tags
                return $status;
            }

            // If tag fallback is needed, track key relation
            if (!empty($this->activeTags)) {
                $this->trackFallbackTags($key, $this->activeTags, $ttlDuration);
            }

            return Cache::put($key, $value, $ttlDuration);
        } catch (Throwable $e) {
            Log::error("Cache write failed for key [{$key}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve an item from the cache, or store the default value if it doesn't exist.
     */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        try {
            $ttlDuration = $ttl ?? 3600;

            if (!empty($this->activeTags) && $this->supportsTags()) {
                $tags = $this->activeTags;
                $this->activeTags = []; // Reset tags
                return Cache::tags($tags)->remember($key, $ttlDuration, $callback);
            }

            if (!empty($this->activeTags)) {
                $tags = $this->activeTags;
                $this->activeTags = []; // Reset tags
                
                if (Cache::has($key)) {
                    return Cache::get($key);
                }

                $value = $callback();
                $this->trackFallbackTags($key, $tags, $ttlDuration);
                Cache::put($key, $value, $ttlDuration);
                return $value;
            }

            return Cache::remember($key, $ttlDuration, $callback);
        } catch (Throwable $e) {
            Log::warning("Cache remember block failed, executing fallback callback directly. Key: [{$key}]. Error: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Remove an item from the cache by key.
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (Throwable $e) {
            Log::error("Cache deletion failed for key [{$key}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cached items.
     */
    public function clear(): bool
    {
        try {
            return Cache::clear();
        } catch (Throwable $e) {
            Log::error("Cache clear execution failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin a tagged cache operation.
     */
    public function tags(array $tags): self
    {
        $this->activeTags = $tags;
        return $this;
    }

    /**
     * Check if the active cache driver supports tags.
     */
    protected function supportsTags(): bool
    {
        $driver = config('cache.default', 'file');
        return in_array($driver, ['redis', 'memcached', 'array']);
    }

    /**
     * Fallback tag tracking system for drivers that don't natively support tags (e.g. file, database).
     */
    protected function trackFallbackTags(string $key, array $tags, int $ttl): void
    {
        foreach ($tags as $tag) {
            $trackerKey = "tag_tracker:{$tag}";
            $keys = Cache::get($trackerKey, []);
            if (!in_array($key, $keys)) {
                $keys[] = $key;
                Cache::put($trackerKey, $keys, $ttl);
            }
        }
    }

    /**
     * Invalidate fallbacked tag systems.
     */
    public function flushTags(array $tags): void
    {
        if ($this->supportsTags()) {
            Cache::tags($tags)->flush();
            return;
        }

        foreach ($tags as $tag) {
            $trackerKey = "tag_tracker:{$tag}";
            $keys = Cache::get($trackerKey, []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget($trackerKey);
        }
    }
}
