<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorSession;

class SessionEnded extends CrmDomainEvent
{
    public function __construct(
        VisitorSession $session,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.session.ended',
            eventType: 'queued',
            organizationId: $session->organization_id,
            aggregateType: 'VisitorSession',
            aggregateId: (string) $session->id,
            aggregateVersion: 1,
            payload: [
                'id' => $session->id,
                'visitor_id' => $session->visitor_id,
                'duration' => $session->duration,
                'pages_visited' => $session->pages_visited,
                'bounce' => $session->bounce,
                'end_time' => $session->end_time ? $session->end_time->toIso8601String() : null,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
