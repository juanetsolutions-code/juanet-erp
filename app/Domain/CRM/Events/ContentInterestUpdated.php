<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorBehaviorProfile;

class ContentInterestUpdated extends CrmDomainEvent
{
    public function __construct(
        VisitorBehaviorProfile $profile,
        array $contentIntelligence,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor_behavior.content_interest_updated',
            eventType: 'queued',
            organizationId: $profile->organization_id,
            aggregateType: 'VisitorBehaviorProfile',
            aggregateId: (string) $profile->id,
            aggregateVersion: 1,
            payload: [
                'id' => $profile->id,
                'visitor_id' => $profile->visitor_id,
                'content_intelligence' => $contentIntelligence,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
