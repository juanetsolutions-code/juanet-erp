<?php

namespace App\Domain\CRM\Policies;

use App\Models\User;
use App\Domain\CRM\Models\Contact;
use App\Services\TenantContext;

class ContactPolicy
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    protected function checkTenant(User $user, ?string $orgId): bool
    {
        $currentOrg = $this->tenantContext->getTenantId();
        if (!$currentOrg || !$orgId) {
            return false;
        }
        return $currentOrg === $orgId;
    }

    public function viewAny(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('view_contacts', $orgId);
    }

    public function view(User $user, Contact $contact): bool
    {
        if (!$this->checkTenant($user, $contact->organization_id)) {
            return false;
        }
        return $user->hasPermission('view_contacts', $contact->organization_id);
    }

    public function create(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('create_contacts', $orgId);
    }

    public function update(User $user, Contact $contact): bool
    {
        if (!$this->checkTenant($user, $contact->organization_id)) {
            return false;
        }
        return $user->hasPermission('update_contacts', $contact->organization_id);
    }

    public function delete(User $user, Contact $contact): bool
    {
        if (!$this->checkTenant($user, $contact->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_contacts', $contact->organization_id);
    }
}
