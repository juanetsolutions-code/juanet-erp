<?php

namespace App\Events;

class QueuedEvent extends DomainEvent
{
    public function __construct(
        string $eventName,
        array $payload = [],
        ?string $organizationId = null,
        ?string $idempotencyKey = null
    ) {
        parent::__construct(
            eventName: $eventName,
            eventType: 'queued',
            payload: $payload,
            organizationId: $organizationId,
            idempotencyKey: $idempotencyKey
        );
    }
}
