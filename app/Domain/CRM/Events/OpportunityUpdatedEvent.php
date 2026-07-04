<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity)
    {
        parent::__construct(
            'crm.opportunity.updated',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'status' => $opportunity->status,
                'changes' => $opportunity->getChanges(),
            ],
            $opportunity->organization_id,
            'idemp_opportunity_updated_' . $opportunity->id . '_' . time()
        );
    }
}
