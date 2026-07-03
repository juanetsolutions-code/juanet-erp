<?php

namespace App\Domain\CRM\Policies;

use App\Models\User;
use App\Domain\CRM\Models\Opportunity;
use App\Services\TenantContext;

class OpportunityPolicy
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
        return $user->hasPermission('view_opportunities', $orgId);
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        if (!$this->checkTenant($user, $opportunity->organization_id)) {
            return false;
        }
        return $user->hasPermission('view_opportunities', $opportunity->organization_id) || $opportunity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('create_opportunities', $orgId);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        if (!$this->checkTenant($user, $opportunity->organization_id)) {
            return false;
        }
        return $user->hasPermission('update_opportunities', $opportunity->organization_id) || $opportunity->user_id === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        if (!$this->checkTenant($user, $opportunity->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_opportunities', $opportunity->organization_id);
    }
}
