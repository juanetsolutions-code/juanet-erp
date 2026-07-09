<?php

namespace App\Domain\Proposal\Events;

class ProposalAccepted extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('proposal.accepted', $payload, $organizationId);
    }
}
