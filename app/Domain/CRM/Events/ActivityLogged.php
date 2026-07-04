<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\LeadActivity;

class ActivityLogged extends CrmDomainEvent
{
    public function __construct(
        LeadActivity $activity,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.activity.logged',
            eventType: 'queued',
            organizationId: $activity->organization_id,
            aggregateType: 'LeadActivity',
            aggregateId: (string) $activity->id,
            aggregateVersion: 1,
            payload: [
                'id' => $activity->id,
                'lead_id' => $activity->lead_id,
                'user_id' => $activity->user_id,
                'type' => $activity->type,
                'description' => $activity->description,
                'properties' => $activity->properties,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
