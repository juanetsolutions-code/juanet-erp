<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorBehaviorProfile;

class VisitorValueEstimated extends CrmDomainEvent
{
    public function __construct(
        VisitorBehaviorProfile $profile,
        array $customerValue,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor_behavior.value_estimated',
            eventType: 'queued',
            organizationId: $profile->organization_id,
            aggregateType: 'VisitorBehaviorProfile',
            aggregateId: (string) $profile->id,
            aggregateVersion: 1,
            payload: [
                'id' => $profile->id,
                'visitor_id' => $profile->visitor_id,
                'customer_value' => $customerValue,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
