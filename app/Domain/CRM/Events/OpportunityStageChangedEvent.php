<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityStageChangedEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity, string $fromStageId, string $toStageId)
    {
        parent::__construct(
            'crm.opportunity.stage.changed',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStageId,
                'amount' => $opportunity->amount,
                'status' => $opportunity->status,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_stage_' . $opportunity->id . '_' . $fromStageId . '_' . $toStageId . '_' . time()
        );
    }
}
