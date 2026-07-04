<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactUpdated extends CrmDomainEvent
{
    public function __construct(
        Contact $contact,
        array $dirtyAttributes = [],
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.contact.updated',
            eventType: 'queued',
            organizationId: $contact->organization_id,
            aggregateType: 'Contact',
            aggregateId: (string) $contact->id,
            aggregateVersion: $contact->lock_version ?? 1,
            payload: [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'dirty' => $dirtyAttributes ?: $contact->getChanges(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
