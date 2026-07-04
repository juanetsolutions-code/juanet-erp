<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactHealthChanged extends CrmDomainEvent
{
    public function __construct(
        Contact $contact,
        int $score,
        string $status,
        int $oldScore,
        string $oldStatus,
        ?string $actorId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'crm.contact.health_changed',
            eventType: 'queued',
            organizationId: $contact->organization_id,
            aggregateType: 'Contact',
            aggregateId: (string) $contact->id,
            aggregateVersion: $contact->lock_version ?? 1,
            payload: [
                'id' => $contact->id,
                'health_score' => $score,
                'health_status' => $status,
                'old_health_score' => $oldScore,
                'old_health_status' => $oldStatus,
            ],
            actorId: $actorId,
            metadata: $metadata
        );
    }
}
