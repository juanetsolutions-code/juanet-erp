<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorSession;

class SessionStarted extends CrmDomainEvent
{
    public function __construct(
        VisitorSession $session,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.session.started',
            eventType: 'queued',
            organizationId: $session->organization_id,
            aggregateType: 'VisitorSession',
            aggregateId: (string) $session->id,
            aggregateVersion: 1,
            payload: [
                'id' => $session->id,
                'visitor_id' => $session->visitor_id,
                'start_time' => $session->start_time->toIso8601String(),
                'referrer' => $session->referrer,
                'landing_page' => $session->landing_page,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
