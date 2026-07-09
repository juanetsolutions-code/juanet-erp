<?php

namespace App\Domain\Proposal\Events;

class ChecklistCreated extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('checklist.created', $payload, $organizationId);
    }
}
