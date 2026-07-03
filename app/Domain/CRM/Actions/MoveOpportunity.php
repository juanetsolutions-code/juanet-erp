<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Services\OpportunityService;

class MoveOpportunity
{
    protected OpportunityService $service;

    public function __construct(OpportunityService $service)
    {
        $this->service = $service;
    }

    public function execute(string $opportunityId, string $stageId): ?Opportunity
    {
        return $this->service->updateOpportunity($opportunityId, [
            'pipeline_stage_id' => $stageId,
        ]);
    }
}
