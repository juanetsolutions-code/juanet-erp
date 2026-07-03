<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Services\LeadService;

class UpdateLead
{
    protected LeadService $service;

    public function __construct(LeadService $service)
    {
        $this->service = $service;
    }

    public function execute(string $id, array $data): ?Lead
    {
        return $this->service->updateLead($id, $data);
    }
}
