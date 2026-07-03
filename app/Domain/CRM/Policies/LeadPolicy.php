<?php

namespace App\Domain\CRM\Policies;

use App\Models\User;
use App\Domain\CRM\Models\Lead;
use App\Services\TenantContext;

class LeadPolicy
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
        return $user->hasPermission('view_leads', $orgId);
    }

    public function view(User $user, Lead $lead): bool
    {
        if (!$this->checkTenant($user, $lead->organization_id)) {
            return false;
        }
        return $user->hasPermission('view_leads', $lead->organization_id) || $lead->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('create_leads', $orgId);
    }

    public function update(User $user, Lead $lead): bool
    {
        if (!$this->checkTenant($user, $lead->organization_id)) {
            return false;
        }
        return $user->hasPermission('update_leads', $lead->organization_id) || $lead->user_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        if (!$this->checkTenant($user, $lead->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_leads', $lead->organization_id);
    }

    public function restore(User $user, Lead $lead): bool
    {
        if (!$this->checkTenant($user, $lead->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_leads', $lead->organization_id);
    }
}
