<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Services\CompanyService;

class CreateCompany
{
    protected CompanyService $service;

    public function __construct(CompanyService $service)
    {
        $this->service = $service;
    }

    public function execute(array $data): Company
    {
        return $this->service->createCompany($data);
    }
}
