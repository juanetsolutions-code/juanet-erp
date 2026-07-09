<?php

namespace App\Domain\Proposal\Events;

class ClientInvited extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('client.invited', $payload, $organizationId);
    }
}
