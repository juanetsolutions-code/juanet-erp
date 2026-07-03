<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Services\LeadService;

class AssignLead
{
    protected LeadService $service;

    public function __construct(LeadService $service)
    {
        $this->service = $service;
    }

    public function execute(string $leadId, string $userId): ?Lead
    {
        return $this->service->updateLead($leadId, [
            'user_id' => $userId,
        ]);
    }
}
