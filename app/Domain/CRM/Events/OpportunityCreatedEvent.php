<?php

namespace App\Domain\CRM\Models; // wait, correct namespace: App\Domain\CRM\Events

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityCreatedEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity)
    {
        parent::__construct(
            'crm.opportunity.created',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'amount' => $opportunity->amount,
                'status' => $opportunity->status,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_created_' . $opportunity->id
        );
    }
}
