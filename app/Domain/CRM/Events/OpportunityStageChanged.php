<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityStageChanged extends CrmDomainEvent
{
    public function __construct(
        Opportunity $opportunity,
        string $newStageId,
        ?string $oldStageId = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.opportunity.stage_changed',
            eventType: 'queued',
            organizationId: $opportunity->organization_id,
            aggregateType: 'Opportunity',
            aggregateId: (string) $opportunity->id,
            aggregateVersion: $opportunity->lock_version ?? 1,
            payload: [
                'id' => $opportunity->id,
                'name' => $opportunity->name,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $newStageId,
                'changed_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
