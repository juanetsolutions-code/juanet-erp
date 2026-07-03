<?php

namespace App\Domain\CRM\Policies;

use App\Models\User;
use App\Domain\CRM\Models\Company;
use App\Services\TenantContext;

class CompanyPolicy
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
        return $user->hasPermission('view_companies', $orgId);
    }

    public function view(User $user, Company $company): bool
    {
        if (!$this->checkTenant($user, $company->organization_id)) {
            return false;
        }
        return $user->hasPermission('view_companies', $company->organization_id);
    }

    public function create(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('create_companies', $orgId);
    }

    public function update(User $user, Company $company): bool
    {
        if (!$this->checkTenant($user, $company->organization_id)) {
            return false;
        }
        return $user->hasPermission('update_companies', $company->organization_id);
    }

    public function delete(User $user, Company $company): bool
    {
        if (!$this->checkTenant($user, $company->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_companies', $company->organization_id);
    }
}
