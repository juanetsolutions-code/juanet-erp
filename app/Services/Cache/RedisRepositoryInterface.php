<?php

namespace App\Services\Cache;

interface RedisRepositoryInterface
{
    /**
     * Set a value in a hash.
     */
    public function hSet(string $key, string $field, string $value): int|bool;

    /**
     * Get a value from a hash.
     */
    public function hGet(string $key, string $field): ?string;

    /**
     * Get all fields and values of a hash.
     */
    public function hGetAll(string $key): array;

    /**
     * Delete one or more fields from a hash.
     */
    public function hDel(string $key, string ...$fields): int;

    /**
     * Add one or more members to a set.
     */
    public function sAdd(string $key, string ...$members): int;

    /**
     * Check if a member belongs to a set.
     */
    public function sIsMember(string $key, string $member): bool;

    /**
     * Remove one or more members from a set.
     */
    public function sRem(string $key, string ...$members): int;

    /**
     * Add a member to a sorted set with a score.
     */
    public function zAdd(string $key, float $score, string $member): int|bool;

    /**
     * Get range of members in a sorted set.
     */
    public function zRange(string $key, int $start, int $end, bool $withScores = false): array;

    /**
     * Remove one or more members from a sorted set.
     */
    public function zRem(string $key, string ...$members): int;

    /**
     * Set expire time for a key.
     */
    public function expire(string $key, int $seconds): bool;
}
