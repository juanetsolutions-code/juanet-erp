<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Services\LeadService;

class DeleteLead
{
    protected LeadService $service;

    public function __construct(LeadService $service)
    {
        $this->service = $service;
    }

    public function execute(string $id): bool
    {
        return $this->service->deleteLead($id);
    }
}
