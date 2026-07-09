<?php

namespace App\Domain\Proposal\Events;

class ProposalUpdated extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('proposal.updated', $payload, $organizationId);
    }
}
