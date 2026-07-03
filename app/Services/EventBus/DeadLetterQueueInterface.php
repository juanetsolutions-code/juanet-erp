<?php

namespace App\Services\EventBus;

use App\Models\EventDlq;
use App\Models\EventOutbox;
use Illuminate\Support\Collection;

interface DeadLetterQueueInterface
{
    /**
     * Send an exhausted outbox event to the Dead Letter Queue database table.
     */
    public function sendToDlq(EventOutbox $outbox, string $reason): EventDlq;

    /**
     * Replay a specific DLQ message by moving it back to the Outbox as a pending event.
     */
    public function replay(string $dlqId): void;

    /**
     * Delete/purge a message from the DLQ.
     */
    public function purge(string $dlqId): void;

    /**
     * Retrieve all messages currently in the DLQ.
     */
    public function getAll(int $limit = 100): Collection;
}
