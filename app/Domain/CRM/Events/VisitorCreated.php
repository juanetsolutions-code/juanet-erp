<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Visitor;

class VisitorCreated extends CrmDomainEvent
{
    public function __construct(
        Visitor $visitor,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor.created',
            eventType: 'queued',
            organizationId: $visitor->organization_id,
            aggregateType: 'Visitor',
            aggregateId: (string) $visitor->id,
            aggregateVersion: 1,
            payload: [
                'id' => $visitor->id,
                'first_seen_at' => $visitor->first_seen_at->toIso8601String(),
                'country' => $visitor->country,
                'city' => $visitor->city,
                'browser' => $visitor->browser,
                'operating_system' => $visitor->operating_system,
                'device_type' => $visitor->device_type,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
