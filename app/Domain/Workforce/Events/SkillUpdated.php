<?php

namespace App\Domain\Workforce\Events;

class SkillUpdated extends WorkforceDomainEvent
{
    public function __construct(array $skillData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'workforce.skill.updated',
            payload: $skillData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
