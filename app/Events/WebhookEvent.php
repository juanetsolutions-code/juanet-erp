<?php

namespace App\Events;

class WebhookEvent extends DomainEvent
{
    public function __construct(
        string $eventName,
        string $webhookUrl,
        array $payload = [],
        ?string $organizationId = null,
        ?string $idempotencyKey = null
    ) {
        parent::__construct(
            eventName: $eventName,
            eventType: 'webhook',
            payload: $payload,
            organizationId: $organizationId,
            idempotencyKey: $idempotencyKey,
            webhookUrl: $webhookUrl
        );
    }
}
