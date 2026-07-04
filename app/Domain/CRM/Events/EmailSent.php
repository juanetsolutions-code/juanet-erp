<?php

namespace App\Domain\CRM\Events;

class EmailSent extends CrmDomainEvent
{
    public function __construct(
        array $emailData,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.email.sent',
            eventType: 'queued',
            organizationId: $emailData['organization_id'] ?? null,
            aggregateType: 'Email',
            aggregateId: (string) ($emailData['id'] ?? 'unknown'),
            aggregateVersion: 1,
            payload: $emailData,
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
