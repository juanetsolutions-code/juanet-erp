<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedisRepository implements RedisRepositoryInterface
{
    // Local in-memory store fallback if Redis connection is not configured or fails
    protected array $fallbackStore = [];

    /**
     * Set a value in a hash.
     */
    public function hSet(string $key, string $field, string $value): int|bool
    {
        try {
            return Redis::hset($key, $field, $value);
        } catch (Throwable $e) {
            Log::debug("Redis hSet fallback triggered: " . $e->getMessage());
            $this->fallbackStore[$key]['hash'][$field] = $value;
            return true;
        }
    }

    /**
     * Get a value from a hash.
     */
    public function hGet(string $key, string $field): ?string
    {
        try {
            return Redis::hget($key, $field);
        } catch (Throwable $e) {
            Log::debug("Redis hGet fallback triggered: " . $e->getMessage());
            return $this->fallbackStore[$key]['hash'][$field] ?? null;
        }
    }

    /**
     * Get all fields and values of a hash.
     */
    public function hGetAll(string $key): array
    {
        try {
            return Redis::hgetall($key);
        } catch (Throwable $e) {
            Log::debug("Redis hGetAll fallback triggered: " . $e->getMessage());
            return $this->fallbackStore[$key]['hash'] ?? [];
        }
    }

    /**
     * Delete one or more fields from a hash.
     */
    public function hDel(string $key, string ...$fields): int
    {
        try {
            return Redis::hdel($key, ...$fields);
        } catch (Throwable $e) {
            Log::debug("Redis hDel fallback triggered: " . $e->getMessage());
            $count = 0;
            foreach ($fields as $field) {
                if (isset($this->fallbackStore[$key]['hash'][$field])) {
                    unset($this->fallbackStore[$key]['hash'][$field]);
                    $count++;
                }
            }
            return $count;
        }
    }

    /**
     * Add one or more members to a set.
     */
    public function sAdd(string $key, string ...$members): int
    {
        try {
            return Redis::sadd($key, ...$members);
        } catch (Throwable $e) {
            Log::debug("Redis sAdd fallback triggered: " . $e->getMessage());
            if (!isset($this->fallbackStore[$key]['set'])) {
                $this->fallbackStore[$key]['set'] = [];
            }
            $added = 0;
            foreach ($members as $member) {
                if (!in_array($member, $this->fallbackStore[$key]['set'])) {
                    $this->fallbackStore[$key]['set'][] = $member;
                    $added++;
                }
            }
            return $added;
        }
    }

    /**
     * Check if a member belongs to a set.
     */
    public function sIsMember(string $key, string $member): bool
    {
        try {
            return (bool) Redis::sismember($key, $member);
        } catch (Throwable $e) {
            Log::debug("Redis sIsMember fallback triggered: " . $e->getMessage());
            return in_array($member, $this->fallbackStore[$key]['set'] ?? []);
        }
    }

    /**
     * Remove one or more members from a set.
     */
    public function sRem(string $key, string ...$members): int
    {
        try {
            return Redis::srem($key, ...$members);
        } catch (Throwable $e) {
            Log::debug("Redis sRem fallback triggered: " . $e->getMessage());
            $count = 0;
            if (isset($this->fallbackStore[$key]['set'])) {
                foreach ($members as $member) {
                    $idx = array_search($member, $this->fallbackStore[$key]['set']);
                    if ($idx !== false) {
                        array_splice($this->fallbackStore[$key]['set'], $idx, 1);
                        $count++;
                    }
                }
            }
            return $count;
        }
    }

    /**
     * Add a member to a sorted set with a score.
     */
    public function zAdd(string $key, float $score, string $member): int|bool
    {
        try {
            return Redis::zadd($key, $score, $member);
        } catch (Throwable $e) {
            Log::debug("Redis zAdd fallback triggered: " . $e->getMessage());
            if (!isset($this->fallbackStore[$key]['zset'])) {
                $this->fallbackStore[$key]['zset'] = [];
            }
            $this->fallbackStore[$key]['zset'][$member] = $score;
            asort($this->fallbackStore[$key]['zset']);
            return true;
        }
    }

    /**
     * Get range of members in a sorted set.
     */
    public function zRange(string $key, int $start, int $end, bool $withScores = false): array
    {
        try {
            if ($withScores) {
                return Redis::zrange($key, $start, $end, 'WITHSCORES');
            }
            return Redis::zrange($key, $start, $end);
        } catch (Throwable $e) {
            Log::debug("Redis zRange fallback triggered: " . $e->getMessage());
            $items = array_keys($this->fallbackStore[$key]['zset'] ?? []);
            $slice = array_slice($items, $start, $end === -1 ? null : ($end - $start + 1));
            
            if ($withScores) {
                $result = [];
                foreach ($slice as $item) {
                    $result[$item] = $this->fallbackStore[$key]['zset'][$item];
                }
                return $result;
            }
            return $slice;
        }
    }

    /**
     * Remove one or more members from a sorted set.
     */
    public function zRem(string $key, string ...$members): int
    {
        try {
            return Redis::zrem($key, ...$members);
        } catch (Throwable $e) {
            Log::debug("Redis zRem fallback triggered: " . $e->getMessage());
            $count = 0;
            if (isset($this->fallbackStore[$key]['zset'])) {
                foreach ($members as $member) {
                    if (isset($this->fallbackStore[$key]['zset'][$member])) {
                        unset($this->fallbackStore[$key]['zset'][$member]);
                        $count++;
                    }
                }
            }
            return $count;
        }
    }

    /**
     * Set expire time for a key.
     */
    public function expire(string $key, int $seconds): bool
    {
        try {
            return (bool) Redis::expire($key, $seconds);
        } catch (Throwable $e) {
            Log::debug("Redis expire fallback triggered: " . $e->getMessage());
            return true;
        }
    }
}
