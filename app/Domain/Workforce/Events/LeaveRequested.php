<?php

namespace App\Domain\Workforce\Events;

class LeaveRequested extends WorkforceDomainEvent
{
    public function __construct(array $leaveData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'workforce.leave.requested',
            payload: $leaveData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
