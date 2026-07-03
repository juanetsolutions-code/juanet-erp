<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\ContactRepositoryInterface;
use App\Domain\CRM\Models\Contact;
use Illuminate\Support\Collection;

class ContactService
{
    protected ContactRepositoryInterface $repo;

    public function __construct(ContactRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getContact(string $id): ?Contact
    {
        return $this->repo->find($id);
    }

    public function createContact(array $data): Contact
    {
        return $this->repo->create($data);
    }

    public function updateContact(string $id, array $data): ?Contact
    {
        return $this->repo->update($id, $data);
    }

    public function deleteContact(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listContacts(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }
}
