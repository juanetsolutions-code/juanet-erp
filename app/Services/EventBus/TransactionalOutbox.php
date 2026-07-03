<?php

namespace App\Services\EventBus;

use App\Events\Interfaces\DomainEventInterface;
use App\Models\EventOutbox;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionalOutbox implements TransactionalOutboxInterface
{
    /**
     * Persist a DomainEvent into the Outbox database table.
     */
    public function store(DomainEventInterface $event): EventOutbox
    {
        return EventOutbox::create([
            'organization_id' => $event->getOrganizationId(),
            'event_name' => $event->getEventName(),
            'event_type' => $event->getEventType(),
            'payload' => $event->getPayload(),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
            'scheduled_at' => $event->getScheduledAt(),
            'idempotency_key' => $event->getIdempotencyKey(),
            'webhook_url' => $event->getWebhookUrl(),
        ]);
    }

    /**
     * Execute a callback inside a database transaction and store the event.
     */
    public function storeInTransaction(Closure $callback, DomainEventInterface $event): mixed
    {
        return DB::transaction(function () use ($callback, $event) {
            $result = $callback();
            $this->store($event);
            return $result;
        });
    }

    /**
     * Retrieve all outbox records that are ready to be dispatched/retried.
     */
    public function getPending(int $limit = 100): Collection
    {
        return EventOutbox::whereIn('status', ['pending', 'failed'])
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->whereRaw('attempts < max_attempts')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark an outbox item as successfully published.
     */
    public function markPublished(string $id): void
    {
        EventOutbox::where('id', $id)->update([
            'status' => 'published',
            'error_message' => null,
        ]);
    }

    /**
     * Mark an outbox item as failed, specifying the error message.
     */
    public function markFailed(string $id, string $errorMessage): void
    {
        EventOutbox::where('id', $id)->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Lock an outbox record for processing to avoid race conditions.
     */
    public function lockForProcessing(string $id): bool
    {
        // Atomically lock the record by updating status to 'processing'
        $affected = EventOutbox::where('id', $id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'attempts' => DB::raw('attempts + 1'),
            ]);

        return $affected > 0;
    }
}
