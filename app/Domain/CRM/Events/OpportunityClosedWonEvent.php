<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityClosedWonEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity)
    {
        parent::__construct(
            'crm.opportunity.closed.won',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'won_reason' => $opportunity->won_reason,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_won_' . $opportunity->id . '_' . time()
        );
    }
}
