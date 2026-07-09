<?php

namespace App\Domain\Proposal\Events;

class ProposalCreated extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('proposal.created', $payload, $organizationId);
    }
}
