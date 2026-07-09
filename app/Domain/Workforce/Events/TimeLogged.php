<?php

namespace App\Domain\Workforce\Events;

class TimeLogged extends WorkforceDomainEvent
{
    public function __construct(array $timeEntryData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'workforce.time.logged',
            payload: $timeEntryData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
