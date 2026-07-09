<?php

namespace App\Domain\Proposal\Events;

class ProjectCreated extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('project.created', $payload, $organizationId);
    }
}
