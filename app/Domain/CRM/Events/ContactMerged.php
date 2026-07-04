<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactMerged extends CrmDomainEvent
{
    public function __construct(
        Contact $masterContact,
        array $mergedContactIds,
        ?string $actorId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'crm.contact.merged',
            eventType: 'queued',
            organizationId: $masterContact->organization_id,
            aggregateType: 'Contact',
            aggregateId: (string) $masterContact->id,
            aggregateVersion: $masterContact->lock_version ?? 1,
            payload: [
                'master_id' => $masterContact->id,
                'merged_ids' => $mergedContactIds,
                'first_name' => $masterContact->first_name,
                'last_name' => $masterContact->last_name,
            ],
            actorId: $actorId,
            metadata: $metadata
        );
    }
}
