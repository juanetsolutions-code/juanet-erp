<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityClosedLostEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity)
    {
        parent::__construct(
            'crm.opportunity.closed.lost',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'lost_reason' => $opportunity->lost_reason,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_lost_' . $opportunity->id . '_' . time()
        );
    }
}
