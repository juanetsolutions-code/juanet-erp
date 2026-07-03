<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Company;

class CompanyCreatedEvent extends CrmDomainEvent
{
    public function __construct(Company $company)
    {
        parent::__construct(
            'crm.company.created',
            'queued',
            [
                'id' => $company->id,
                'organization_id' => $company->organization_id,
                'name' => $company->name,
                'domain' => $company->domain,
            ],
            $company->organization_id,
            'idemp_company_created_' . $company->id
        );
    }
}
