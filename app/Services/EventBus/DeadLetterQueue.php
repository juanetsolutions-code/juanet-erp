<?php

namespace App\Services\EventBus;

use App\Models\EventDlq;
use App\Models\EventOutbox;
use Illuminate\Support\Collection;

class DeadLetterQueue implements DeadLetterQueueInterface
{
    /**
     * Send an exhausted outbox event to the Dead Letter Queue.
     */
    public function sendToDlq(EventOutbox $outbox, string $reason): EventDlq
    {
        $dlq = EventDlq::create([
            'organization_id' => $outbox->organization_id,
            'original_outbox_id' => $outbox->id,
            'event_name' => $outbox->event_name,
            'event_type' => $outbox->event_type,
            'payload' => $outbox->payload,
            'failure_reason' => $reason,
        ]);

        // Delete from the standard Outbox table to prevent table bloat and infinite loops
        $outbox->delete();

        return $dlq;
    }

    /**
     * Replay a specific DLQ message by moving it back to the Outbox as a pending event.
     */
    public function replay(string $dlqId): void
    {
        $dlq = EventDlq::findOrFail($dlqId);

        EventOutbox::create([
            'organization_id' => $dlq->organization_id,
            'event_name' => $dlq->event_name,
            'event_type' => $dlq->event_type,
            'payload' => $dlq->payload,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        $dlq->delete();
    }

    /**
     * Delete/purge a message from the DLQ.
     */
    public function purge(string $dlqId): void
    {
        EventDlq::destroy($dlqId);
    }

    /**
     * Retrieve all messages currently in the DLQ.
     */
    public function getAll(int $limit = 100): Collection
    {
        return EventDlq::latest()->limit($limit)->get();
    }
}
