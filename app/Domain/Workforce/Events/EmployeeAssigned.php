<?php

namespace App\Domain\Workforce\Events;

class EmployeeAssigned extends WorkforceDomainEvent
{
    public function __construct(array $assignmentData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'workforce.employee.assigned',
            payload: $assignmentData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
