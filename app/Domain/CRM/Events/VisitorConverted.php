<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\Lead;

class VisitorConverted extends CrmDomainEvent
{
    public function __construct(
        Visitor $visitor,
        Lead $lead,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor.converted',
            eventType: 'queued',
            organizationId: $visitor->organization_id,
            aggregateType: 'Visitor',
            aggregateId: (string) $visitor->id,
            aggregateVersion: 1,
            payload: [
                'visitor_id' => $visitor->id,
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'name' => $lead->name,
                'converted_at' => now()->toIso8601String(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
