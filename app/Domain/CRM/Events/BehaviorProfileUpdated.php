<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorBehaviorProfile;

class BehaviorProfileUpdated extends CrmDomainEvent
{
    public function __construct(
        VisitorBehaviorProfile $profile,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.visitor_behavior.profile_updated',
            eventType: 'queued',
            organizationId: $profile->organization_id,
            aggregateType: 'VisitorBehaviorProfile',
            aggregateId: (string) $profile->id,
            aggregateVersion: 1,
            payload: [
                'id' => $profile->id,
                'visitor_id' => $profile->visitor_id,
                'engagement_score' => $profile->engagement_score,
                'purchase_intent' => $profile->purchase_intent,
                'service_interests' => $profile->service_interests,
                'product_interests' => $profile->product_interests,
                'content_intelligence' => $profile->content_intelligence,
                'customer_value' => $profile->customer_value,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
