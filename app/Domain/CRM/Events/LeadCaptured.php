<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadCaptured extends CrmDomainEvent
{
    public function __construct(
        Lead $lead,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.lead.captured',
            eventType: 'queued',
            organizationId: $lead->organization_id,
            aggregateType: 'Lead',
            aggregateId: (string) $lead->id,
            aggregateVersion: $lead->lock_version ?? 1,
            payload: [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => $lead->status,
                'score' => $lead->score,
                'source' => $lead->custom_fields['source_page'] ?? 'public',
                'priority' => $lead->custom_fields['priority'] ?? 'medium',
                'estimated_deal_size' => $lead->crm_lead_metadata['estimated_deal_size'] ?? 0,
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
