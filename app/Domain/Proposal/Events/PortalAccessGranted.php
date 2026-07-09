<?php

namespace App\Domain\Proposal\Events;

class PortalAccessGranted extends ProposalDomainEvent
{
    public function __construct(array $payload, ?string $organizationId = null)
    {
        parent::__construct('portal.access_granted', $payload, $organizationId);
    }
}
