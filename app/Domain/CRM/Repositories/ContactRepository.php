<?php

namespace App\Domain\CRM\Repositories;

use App\Domain\CRM\Contracts\ContactRepositoryInterface;
use App\Domain\CRM\Models\Contact;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class ContactRepository implements ContactRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Contact
    {
        $orgId = $this->tenantContext->getTenantId();
        return Contact::where('id', $id)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->first();
    }

    public function create(array $data): Contact
    {
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId && !isset($data['organization_id'])) {
            $data['organization_id'] = $orgId;
        }
        return Contact::create($data);
    }

    public function update(string $id, array $data): ?Contact
    {
        $contact = $this->find($id);
        if ($contact) {
            $contact->update($data);
            return $contact;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $contact = $this->find($id);
        if ($contact) {
            return (bool) $contact->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Contact::when($orgId, function ($q) use ($orgId) {
            return $q->where('organization_id', $orgId);
        })->get();
    }
}
