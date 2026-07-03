<?php

namespace App\Services\EventBus;

use App\Models\EventOutbox;

interface OutboxPublisherInterface
{
    /**
     * Poll the transactional outbox table and publish pending entries.
     * Returns the count of processed outbox items.
     */
    public function publishPending(int $limit = 100): int;

    /**
     * Publish a single specific Outbox item.
     */
    public function publish(EventOutbox $outbox): void;
}
