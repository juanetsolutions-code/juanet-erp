<?php

namespace App\Services\EventBus;

interface RetryServiceInterface
{
    /**
     * Check if the event should be retried based on current attempts and max attempts.
     */
    public function shouldRetry(int $attempts, int $maxAttempts): bool;

    /**
     * Calculate the next retry delay in seconds, supporting exponential backoff with jitter.
     */
    public function calculateNextDelay(int $attempts, int $baseDelay = 2): int;
}
