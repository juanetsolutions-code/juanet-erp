<?php

namespace App\Services\EventBus;

use App\Events\Interfaces\DomainEventInterface;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event. If the event is non-immediate, it may go through the outbox.
     */
    public function dispatch(DomainEventInterface $event): void;

    /**
     * Dispatch the event immediately and synchronously to local listeners.
     */
    public function dispatchImmediately(DomainEventInterface $event): void;

    /**
     * Dispatch the event using standard Laravel queue systems.
     */
    public function dispatchToQueue(DomainEventInterface $event): void;
}
