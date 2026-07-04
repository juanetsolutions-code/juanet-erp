<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactConsentChanged extends CrmDomainEvent
{
    public function __construct(
        Contact $contact,
        string $channel,
        string $status,
        string $purpose,
        ?string $actorId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'crm.contact.consent_changed',
            eventType: 'queued',
            organizationId: $contact->organization_id,
            aggregateType: 'Contact',
            aggregateId: (string) $contact->id,
            aggregateVersion: $contact->lock_version ?? 1,
            payload: [
                'id' => $contact->id,
                'channel' => $channel,
                'status' => $status,
                'purpose' => $purpose,
            ],
            actorId: $actorId,
            metadata: $metadata
        );
    }
}
