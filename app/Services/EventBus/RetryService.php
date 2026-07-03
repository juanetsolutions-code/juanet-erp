<?php

namespace App\Services\EventBus;

class RetryService implements RetryServiceInterface
{
    /**
     * Check if the event should be retried.
     */
    public function shouldRetry(int $attempts, int $maxAttempts): bool
    {
        return $attempts < $maxAttempts;
    }

    /**
     * Calculate the next retry delay in seconds.
     */
    public function calculateNextDelay(int $attempts, int $baseDelay = 2): int
    {
        // Exponential backoff: base * 2 ^ (attempts - 1)
        // e.g., attempt 1 = 2s, attempt 2 = 4s, attempt 3 = 8s
        $delay = (int) ($baseDelay * (2 ** max(0, $attempts - 1)));

        // Add small randomized jitter (+/- 20% or up to 3 seconds) to prevent thundering herd
        $jitter = rand(0, 3);
        
        // Cap retry at 3600 seconds (1 hour)
        return min($delay + $jitter, 3600);
    }
}
