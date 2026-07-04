<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\ContactRelationship;

class ContactRelationshipUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Contact $contact, ContactRelationship $relationship, string $action)
    {
        parent::__construct(
            'crm.contact.relationship.updated',
            'queued',
            [
                'id' => $contact->id,
                'organization_id' => $contact->organization_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'relationship_id' => $relationship->id,
                'related_contact_id' => $relationship->related_contact_id,
                'relationship_type' => $relationship->type,
                'action' => $action, // created, updated, deleted
            ],
            $contact->organization_id,
            'idemp_contact_rel_updated_' . $relationship->id . '_' . $action . '_' . time()
        );
    }
}
