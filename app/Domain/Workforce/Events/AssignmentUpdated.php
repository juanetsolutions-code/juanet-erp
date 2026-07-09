<?php

namespace App\Domain\Workforce\Events;

class AssignmentUpdated extends WorkforceDomainEvent
{
    public function __construct(array $assignmentData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'workforce.assignment.updated',
            payload: $assignmentData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
