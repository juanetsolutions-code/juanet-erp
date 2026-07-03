<?php

namespace App\Services\EventBus;

use App\Models\IdempotentKey;
use Throwable;

class IdempotencyChecker implements IdempotencyCheckerInterface
{
    /**
     * Determine if a given key has already been successfully processed.
     */
    public function isDuplicate(string $key): bool
    {
        $item = IdempotentKey::where('key', $key)->first();

        if ($item && $item->expires_at && $item->expires_at->isPast()) {
            $item->delete();
            return false;
        }

        return $item && $item->status === 'completed';
    }

    /**
     * Atomically register a key as 'processing'.
     */
    public function claimProcessing(string $key, int $ttlSeconds = 3600): bool
    {
        $existing = IdempotentKey::where('key', $key)->first();

        if ($existing) {
            // Delete if expired
            if ($existing->expires_at && $existing->expires_at->isPast()) {
                $existing->delete();
            } 
            // Allow retry if previously failed
            elseif ($existing->status === 'failed') {
                $existing->update([
                    'status' => 'processing',
                    'expires_at' => now()->addSeconds($ttlSeconds),
                ]);
                return true;
            } else {
                // Already processing or completed
                return false;
            }
        }

        try {
            IdempotentKey::create([
                'key' => $key,
                'status' => 'processing',
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);
            return true;
        } catch (Throwable $e) {
            // Catches any unique constraint race conditions
            return false;
        }
    }

    /**
     * Mark an idempotency key as successfully processed.
     */
    public function markCompleted(string $key, array $result = []): void
    {
        IdempotentKey::where('key', $key)->update([
            'status' => 'completed',
            'result' => $result,
        ]);
    }

    /**
     * Mark an idempotency key as failed so it can be retried.
     */
    public function markFailed(string $key): void
    {
        IdempotentKey::where('key', $key)->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Retrieve the cached result associated with a completed idempotency key.
     */
    public function getResult(string $key): ?array
    {
        $item = IdempotentKey::where('key', $key)->first();
        return $item ? $item->result : null;
    }
}
