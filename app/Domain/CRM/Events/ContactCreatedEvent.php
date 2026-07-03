<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactCreatedEvent extends CrmDomainEvent
{
    public function __construct(Contact $contact)
    {
        parent::__construct(
            'crm.contact.created',
            'queued',
            [
                'id' => $contact->id,
                'organization_id' => $contact->organization_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
            ],
            $contact->organization_id,
            'idemp_contact_created_' . $contact->id
        );
    }
}
