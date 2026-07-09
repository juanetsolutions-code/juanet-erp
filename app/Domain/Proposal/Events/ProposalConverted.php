<?php

namespace App\Domain\Proposal\Events;

class ProposalConverted extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('proposal.converted', $payload, $organizationId);
    }
}
