<?php

namespace App\Events;

use DateTimeInterface;

class ScheduledEvent extends DomainEvent
{
    public function __construct(
        string $eventName,
        DateTimeInterface $scheduledAt,
        array $payload = [],
        ?string $organizationId = null,
        ?string $idempotencyKey = null
    ) {
        parent::__construct(
            eventName: $eventName,
            eventType: 'scheduled',
            payload: $payload,
            organizationId: $organizationId,
            idempotencyKey: $idempotencyKey,
            scheduledAt: $scheduledAt
        );
    }
}
