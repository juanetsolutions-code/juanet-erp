<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadDeleted extends CrmDomainEvent
{
    public function __construct(
        Lead $lead,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.lead.deleted',
            eventType: 'queued',
            organizationId: $lead->organization_id,
            aggregateType: 'Lead',
            aggregateId: (string) $lead->id,
            aggregateVersion: $lead->lock_version ?? 1,
            payload: [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'deleted_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
