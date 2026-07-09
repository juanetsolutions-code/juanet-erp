<?php

namespace App\Domain\Proposal\Events;

class ProjectInitialized extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('project.initialized', $payload, $organizationId);
    }
}
