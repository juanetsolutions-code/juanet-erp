<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Contact $contact)
    {
        parent::__construct(
            'crm.contact.updated',
            'queued',
            [
                'id' => $contact->id,
                'organization_id' => $contact->organization_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'changes' => $contact->getChanges(),
            ],
            $contact->organization_id,
            'idemp_contact_updated_' . $contact->id . '_' . time()
        );
    }
}
