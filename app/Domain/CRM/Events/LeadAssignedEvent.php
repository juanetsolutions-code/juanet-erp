<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadAssignedEvent extends CrmDomainEvent
{
    public function __construct(Lead $lead, ?string $fromUserId, ?string $toUserId, ?string $assignedBy = null, string $method = 'manual')
    {
        parent::__construct(
            'crm.lead.assigned',
            'queued',
            [
                'id' => $lead->id,
                'organization_id' => $lead->organization_id,
                'name' => $lead->name,
                'email' => $lead->email,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'assigned_by' => $assignedBy,
                'method' => $method,
            ],
            $lead->organization_id,
            'idemp_lead_assigned_' . $lead->id . '_' . now()->getTimestamp()
        );
    }
}
