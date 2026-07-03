<?php

namespace App\Services\EventBus;

use App\Models\EventOutbox;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboxPublisher implements OutboxPublisherInterface
{
    protected TransactionalOutboxInterface $outbox;
    protected OutboxConsumerInterface $consumer;
    protected RetryServiceInterface $retryService;
    protected DeadLetterQueueInterface $dlq;

    public function __construct(
        TransactionalOutboxInterface $outbox,
        OutboxConsumerInterface $consumer,
        RetryServiceInterface $retryService,
        DeadLetterQueueInterface $dlq
    ) {
        $this->outbox = $outbox;
        $this->consumer = $consumer;
        $this->retryService = $retryService;
        $this->dlq = $dlq;
    }

    /**
     * Poll the transactional outbox table and publish pending entries.
     */
    public function publishPending(int $limit = 100): int
    {
        $pending = $this->outbox->getPending($limit);
        $count = 0;

        foreach ($pending as $item) {
            // Lock the record atomically for processing
            if ($this->outbox->lockForProcessing($item->id)) {
                // Refresh item state to get updated attempt counter
                $item->refresh();
                try {
                    $this->publish($item);
                    $count++;
                } catch (Throwable $e) {
                    // Failures are already handled internally in publish(), just log here
                    Log::error("Outbox execution error on event {$item->id}: " . $e->getMessage());
                }
            }
        }

        return $count;
    }

    /**
     * Publish a single specific Outbox item.
     */
    public function publish(EventOutbox $outbox): void
    {
        try {
            $this->consumer->consume($outbox);
            $this->outbox->markPublished($outbox->id);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage() . "\n" . $e->getTraceAsString();

            if ($this->retryService->shouldRetry($outbox->attempts, $outbox->max_attempts)) {
                $delay = $this->retryService->calculateNextDelay($outbox->attempts);

                $outbox->update([
                    'status' => 'failed',
                    'scheduled_at' => now()->addSeconds($delay),
                    'error_message' => $e->getMessage(),
                ]);

                Log::warning("Outbox event {$outbox->id} failed. Scheduled retry in {$delay} seconds (Attempt {$outbox->attempts}/{$outbox->max_attempts}).");
            } else {
                // Retries exhausted, forward to Dead Letter Queue
                $this->dlq->sendToDlq($outbox, "Exhausted all {$outbox->max_attempts} retry attempts. Error: " . $e->getMessage());
                Log::error("Outbox event {$outbox->id} failed definitively. Moved to Dead Letter Queue (DLQ).");
            }

            throw $e;
        }
    }
}
