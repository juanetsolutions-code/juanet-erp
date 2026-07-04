<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Company;

class CompanyUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Company $company)
    {
        parent::__construct(
            'crm.company.updated',
            'queued',
            [
                'id' => $company->id,
                'organization_id' => $company->organization_id,
                'name' => $company->name,
                'domain' => $company->domain,
                'status' => $company->status,
                'changes' => $company->getChanges(),
            ],
            $company->organization_id,
            'idemp_company_updated_' . $company->id . '_' . time()
        );
    }
}
