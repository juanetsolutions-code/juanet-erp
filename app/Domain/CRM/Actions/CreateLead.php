<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Services\LeadService;

class CreateLead
{
    protected LeadService $service;

    public function __construct(LeadService $service)
    {
        $this->service = $service;
    }

    public function execute(array $data): Lead
    {
        return $this->service->createLead($data);
    }
}
