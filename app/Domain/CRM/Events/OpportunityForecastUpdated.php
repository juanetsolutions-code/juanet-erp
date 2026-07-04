<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityForecastUpdated extends CrmDomainEvent
{
    public function __construct(
        Opportunity $opportunity,
        string $newForecastCategory,
        ?string $oldForecastCategory = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.opportunity.forecast_updated',
            eventType: 'queued',
            organizationId: $opportunity->organization_id,
            aggregateType: 'Opportunity',
            aggregateId: (string) $opportunity->id,
            aggregateVersion: $opportunity->lock_version ?? 1,
            payload: [
                'id' => $opportunity->id,
                'name' => $opportunity->name,
                'old_forecast_category' => $oldForecastCategory,
                'new_forecast_category' => $newForecastCategory,
                'updated_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
