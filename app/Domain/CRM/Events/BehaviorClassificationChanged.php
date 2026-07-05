<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorBehaviorProfile;

class BehaviorClassificationChanged extends CrmDomainEvent
{
    public function __construct(
        VisitorBehaviorProfile $profile,
        string $previousIntent,
        string $newIntent,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor_behavior.classification_changed',
            eventType: 'queued',
            organizationId: $profile->organization_id,
            aggregateType: 'VisitorBehaviorProfile',
            aggregateId: (string) $profile->id,
            aggregateVersion: 1,
            payload: [
                'id' => $profile->id,
                'visitor_id' => $profile->visitor_id,
                'previous_classification' => $previousIntent,
                'new_classification' => $newIntent,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
