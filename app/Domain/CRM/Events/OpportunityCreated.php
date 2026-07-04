<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityCreated extends CrmDomainEvent
{
    public function __construct(
        Opportunity $opportunity,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.opportunity.created',
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
                'company_id' => $opportunity->company_id,
                'contact_id' => $opportunity->contact_id,
                'pipeline_id' => $opportunity->pipeline_id,
                'pipeline_stage_id' => $opportunity->pipeline_stage_id,
                'user_id' => $opportunity->user_id,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
