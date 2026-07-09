<?php

namespace App\Domain\Proposal\Events;

class ProposalStatusChanged extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('proposal.status_changed', $payload, $organizationId);
    }
}
