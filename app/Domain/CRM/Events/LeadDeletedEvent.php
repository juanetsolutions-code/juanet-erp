<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Lead;

class LeadDeletedEvent extends CrmDomainEvent
{
    public function __construct(Lead $lead)
    {
        parent::__construct(
            'crm.lead.deleted',
            'queued',
            [
                'id' => $lead->id,
                'organization_id' => $lead->organization_id,
            ],
            $lead->organization_id,
            'idemp_lead_deleted_' . $lead->id
        );
    }
}
