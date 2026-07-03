<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    protected \App\Services\TenantContext $tenantContext;

    public function __construct(\App\Services\TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Role
    {
        return Role::find($id);
    }

    public function findBySlug(string $slug, ?string $organizationId = null): ?Role
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        return Role::where('slug', $slug)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function getGlobalRoles(): Collection
    {
        return Role::global()->get();
    }

    public function getTenantRoles(?string $organizationId = null): Collection
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        return Role::forTenant($organizationId)->get();
    }

    public function create(array $data): Role
    {
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $this->tenantContext->getTenantId();
        }
        return Role::create($data);
    }

    public function update(string $id, array $data): Role
    {
        $role = Role::findOrFail($id);
        $role->update($data);
        return $role;
    }

    public function delete(string $id): bool
    {
        $role = Role::find($id);
        if ($role) {
            return $role->delete();
        }
        return false;
    }
}
