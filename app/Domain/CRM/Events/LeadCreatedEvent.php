<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadCreatedEvent extends CrmDomainEvent
{
    public function __construct(Lead $lead)
    {
        parent::__construct(
            'crm.lead.created',
            'queued',
            [
                'id' => $lead->id,
                'organization_id' => $lead->organization_id,
                'name' => $lead->name,
                'email' => $lead->email,
                'status' => $lead->status,
            ],
            $lead->organization_id,
            'idemp_lead_created_' . $lead->id
        );
    }
}
