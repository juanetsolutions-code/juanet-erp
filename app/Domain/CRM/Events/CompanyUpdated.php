<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Company;

class CompanyUpdated extends CrmDomainEvent
{
    public function __construct(
        Company $company,
        array $dirtyAttributes = [],
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.company.updated',
            eventType: 'queued',
            organizationId: $company->organization_id,
            aggregateType: 'Company',
            aggregateId: (string) $company->id,
            aggregateVersion: $company->lock_version ?? 1,
            payload: [
                'id' => $company->id,
                'name' => $company->name,
                'dirty' => $dirtyAttributes ?: $company->getChanges(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
