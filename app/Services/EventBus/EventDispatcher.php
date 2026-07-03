<?php

namespace App\Services\EventBus;

use App\Events\Interfaces\DomainEventInterface;
use App\Jobs\DispatchEventJob;

class EventDispatcher implements EventDispatcherInterface
{
    protected TransactionalOutboxInterface $outbox;

    public function __construct(TransactionalOutboxInterface $outbox)
    {
        $this->outbox = $outbox;
    }

    /**
     * Dispatch an event. If the event is non-immediate (queued, scheduled, webhook), 
     * it is saved in the outbox first for transactional safety.
     */
    public function dispatch(DomainEventInterface $event): void
    {
        if ($event->getEventType() === 'immediate') {
            $this->dispatchImmediately($event);
            return;
        }

        $this->outbox->store($event);
    }

    /**
     * Dispatch the event immediately and synchronously to local listeners.
     */
    public function dispatchImmediately(DomainEventInterface $event): void
    {
        event($event);
    }

    /**
     * Dispatch the event using standard Laravel queue systems.
     */
    public function dispatchToQueue(DomainEventInterface $event): void
    {
        dispatch(new DispatchEventJob($event));
    }
}
