<?php

namespace App\Domain\CRM\Events;

class LeadRejected extends CrmDomainEvent
{
    public function __construct(
        string $email,
        string $name,
        string $reason,
        int $spamScore,
        ?string $organizationId = null,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.lead.rejected',
            eventType: 'queued',
            organizationId: $organizationId,
            aggregateType: 'LeadSubmission',
            aggregateId: 'rejected_' . md5($email . '_' . microtime(true)),
            aggregateVersion: 1,
            payload: [
                'email' => $email,
                'name' => $name,
                'reason' => $reason,
                'spam_score' => $spamScore,
                'rejected_at' => now()->toIso8601String(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
