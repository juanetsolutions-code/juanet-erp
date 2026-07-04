<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\CompanyLocation;

class CompanyLocationAdjustedEvent extends CrmDomainEvent
{
    public function __construct(Company $company, CompanyLocation $location, string $action)
    {
        parent::__construct(
            'crm.company.location_adjusted',
            'queued',
            [
                'id' => $company->id,
                'organization_id' => $company->organization_id,
                'company_name' => $company->name,
                'location_id' => $location->id,
                'location_name' => $location->name,
                'location_type' => $location->type,
                'action' => $action, // created, updated, deleted
            ],
            $company->organization_id,
            'idemp_company_location_adjusted_' . $location->id . '_' . $action . '_' . time()
        );
    }
}
