<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityUpdated extends CrmDomainEvent
{
    public function __construct(
        Opportunity $opportunity,
        array $dirtyAttributes = [],
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.opportunity.updated',
            eventType: 'queued',
            organizationId: $opportunity->organization_id,
            aggregateType: 'Opportunity',
            aggregateId: (string) $opportunity->id,
            aggregateVersion: $opportunity->lock_version ?? 1,
            payload: [
                'id' => $opportunity->id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'status' => $opportunity->status,
                'dirty' => $dirtyAttributes ?: $opportunity->getChanges(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
