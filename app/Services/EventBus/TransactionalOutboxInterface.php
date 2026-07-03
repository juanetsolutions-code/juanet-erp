<?php

namespace App\Services\EventBus;

use App\Events\Interfaces\DomainEventInterface;
use App\Models\EventOutbox;
use Closure;
use Illuminate\Support\Collection;

interface TransactionalOutboxInterface
{
    /**
     * Persist a DomainEvent into the Outbox database table.
     */
    public function store(DomainEventInterface $event): EventOutbox;

    /**
     * Execute a callback inside a database transaction and store the event.
     * Ensures perfect transactional consistency between state changes and event dispatch.
     */
    public function storeInTransaction(Closure $callback, DomainEventInterface $event): mixed;

    /**
     * Retrieve all outbox records that are ready to be dispatched/retried.
     */
    public function getPending(int $limit = 100): Collection;

    /**
     * Mark an outbox item as successfully published.
     */
    public function markPublished(string $id): void;

    /**
     * Mark an outbox item as failed, specifying the error message.
     */
    public function markFailed(string $id, string $errorMessage): void;

    /**
     * Lock an outbox record for processing to avoid race conditions.
     */
    public function lockForProcessing(string $id): bool;
}
