<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Visitor;

class VisitorReturned extends CrmDomainEvent
{
    public function __construct(
        Visitor $visitor,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor.returned',
            eventType: 'queued',
            organizationId: $visitor->organization_id,
            aggregateType: 'Visitor',
            aggregateId: (string) $visitor->id,
            aggregateVersion: 1,
            payload: [
                'id' => $visitor->id,
                'total_sessions' => $visitor->total_sessions,
                'last_seen_at' => $visitor->last_seen_at->toIso8601String(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
