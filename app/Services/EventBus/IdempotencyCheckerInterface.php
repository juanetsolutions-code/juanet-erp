<?php

namespace App\Services\EventBus;

interface IdempotencyCheckerInterface
{
    /**
     * Determine if a given key has already been successfully processed.
     */
    public function isDuplicate(string $key): bool;

    /**
     * Atomically register a key as 'processing'.
     * Returns true if successful (first time seeing the key), or false if it is already processing or completed.
     */
    public function claimProcessing(string $key, int $ttlSeconds = 3600): bool;

    /**
     * Mark an idempotency key as successfully processed, caching the final result payload.
     */
    public function markCompleted(string $key, array $result = []): void;

    /**
     * Mark an idempotency key as failed so it can be retried/processed again on next dispatch.
     */
    public function markFailed(string $key): void;

    /**
     * Retrieve the cached result associated with a completed idempotency key.
     */
    public function getResult(string $key): ?array;
}
