<?php

namespace App\Domain\Proposal\Events;

class TimelineInitialized extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('timeline.initialized', $payload, $organizationId);
    }
}
