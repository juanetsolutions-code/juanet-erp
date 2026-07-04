<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;

class ContactCommunicationUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Contact $contact, array $preferences)
    {
        parent::__construct(
            'crm.contact.communication.updated',
            'queued',
            [
                'id' => $contact->id,
                'organization_id' => $contact->organization_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'communication_preferences' => $preferences,
            ],
            $contact->organization_id,
            'idemp_contact_comm_updated_' . $contact->id . '_' . time()
        );
    }
}
