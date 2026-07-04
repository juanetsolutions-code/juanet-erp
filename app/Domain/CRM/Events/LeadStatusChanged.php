<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadStatusChanged extends CrmDomainEvent
{
    public function __construct(
        Lead $lead,
        string $newStatus,
        ?string $oldStatus = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.lead.status_changed',
            eventType: 'queued',
            organizationId: $lead->organization_id,
            aggregateType: 'Lead',
            aggregateId: (string) $lead->id,
            aggregateVersion: $lead->lock_version ?? 1,
            payload: [
                'id' => $lead->id,
                'name' => $lead->name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_at' => now()->format(\DateTimeInterface::ATOM),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
