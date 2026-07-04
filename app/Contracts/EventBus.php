<?php

namespace App\Contracts;

use App\Events\Interfaces\DomainEventInterface;

interface EventBus
{
    /**
     * Dispatch an event immediately or queue it according to its type.
     */
    public function dispatch(DomainEventInterface $event): void;

    /**
     * Dispatch an event after the current database transaction commits successfully.
     */
    public function dispatchAfterCommit(DomainEventInterface $event): void;

    /**
     * Dispatch multiple events.
     *
     * @param array<DomainEventInterface> $events
     */
    public function dispatchMany(array $events): void;
}
