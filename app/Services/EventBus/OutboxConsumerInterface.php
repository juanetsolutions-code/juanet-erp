<?php

namespace App\Services\EventBus;

use App\Models\EventOutbox;

interface OutboxConsumerInterface
{
    /**
     * Consume/process an Outbox item. Invokes local listeners or executes specific handlers based on the event type.
     */
    public function consume(EventOutbox $outbox): void;
}
