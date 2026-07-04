<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Company;

class CompanyHealthDeterioratedEvent extends CrmDomainEvent
{
    public function __construct(Company $company, int $oldScore, int $newScore)
    {
        parent::__construct(
            'crm.company.health_deteriorated',
            'queued',
            [
                'id' => $company->id,
                'organization_id' => $company->organization_id,
                'name' => $company->name,
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'health_status' => $company->health_status,
            ],
            $company->organization_id,
            'idemp_company_health_deteriorated_' . $company->id . '_' . $newScore
        );
    }
}
