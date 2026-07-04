<?php

namespace App\Domain\CRM\Events;

class CallLogged extends CrmDomainEvent
{
    public function __construct(
        array $callData,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.call.logged',
            eventType: 'queued',
            organizationId: $callData['organization_id'] ?? null,
            aggregateType: 'Call',
            aggregateId: (string) ($callData['id'] ?? 'unknown'),
            aggregateVersion: 1,
            payload: $callData,
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
