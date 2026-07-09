<?php

namespace App\Domain\Proposal\Events;

class MilestoneCreated extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('milestone.created', $payload, $organizationId);
    }
}
