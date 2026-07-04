<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadStatusChangedEvent extends CrmDomainEvent
{
    public function __construct(Lead $lead, string $fromStatus, string $toStatus, ?string $changedBy = null, ?string $reason = null)
    {
        parent::__construct(
            'crm.lead.status_changed',
            'queued',
            [
                'id' => $lead->id,
                'organization_id' => $lead->organization_id,
                'name' => $lead->name,
                'email' => $lead->email,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ],
            $lead->organization_id,
            'idemp_lead_status_' . $lead->id . '_' . now()->getTimestamp()
        );
    }
}
