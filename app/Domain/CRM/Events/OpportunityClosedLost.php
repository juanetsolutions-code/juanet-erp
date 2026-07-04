<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityClosedLost extends CrmDomainEvent
{
    public function __construct(
        Opportunity $opportunity,
        ?string $lostReason = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.opportunity.closed_lost',
            eventType: 'queued',
            organizationId: $opportunity->organization_id,
            aggregateType: 'Opportunity',
            aggregateId: (string) $opportunity->id,
            aggregateVersion: $opportunity->lock_version ?? 1,
            payload: [
                'id' => $opportunity->id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'lost_reason' => $lostReason ?? $opportunity->lost_reason,
                'closed_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
