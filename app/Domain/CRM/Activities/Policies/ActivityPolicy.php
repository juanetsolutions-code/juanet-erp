<?php

namespace App\Domain\CRM\Activities\Policies;

use App\Models\User;
use App\Domain\CRM\Activities\Models\Activity;
use App\Services\TenantContext;

class ActivityPolicy
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
        return $user->hasPermission('view_activities', $orgId);
    }

    public function view(User $user, Activity $activity): bool
    {
        if (!$this->checkTenant($user, $activity->organization_id)) {
            return false;
        }
        return $user->hasPermission('view_activities', $activity->organization_id) || $activity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        $orgId = $this->tenantContext->getTenantId();
        return $user->hasPermission('create_activities', $orgId);
    }

    public function update(User $user, Activity $activity): bool
    {
        if (!$this->checkTenant($user, $activity->organization_id)) {
            return false;
        }
        return $user->hasPermission('update_activities', $activity->organization_id) || $activity->user_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        if (!$this->checkTenant($user, $activity->organization_id)) {
            return false;
        }
        return $user->hasPermission('delete_activities', $activity->organization_id);
    }
}
