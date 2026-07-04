<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadConverted extends CrmDomainEvent
{
    public function __construct(
        Lead $lead,
        string $companyId,
        string $contactId,
        ?string $opportunityId = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.lead.converted',
            eventType: 'queued',
            organizationId: $lead->organization_id,
            aggregateType: 'Lead',
            aggregateId: (string) $lead->id,
            aggregateVersion: $lead->lock_version ?? 1,
            payload: [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'company_id' => $companyId,
                'contact_id' => $contactId,
                'opportunity_id' => $opportunityId,
                'converted_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
