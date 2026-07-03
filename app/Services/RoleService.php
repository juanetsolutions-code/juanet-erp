<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleService
{
    protected RoleRepositoryInterface $roleRepo;

    public function __construct(RoleRepositoryInterface $roleRepo)
    {
        $this->roleRepo = $roleRepo;
    }

    public function createRole(array $data): Role
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->roleRepo->create($data);
    }

    public function getRole(string $id): ?Role
    {
        return $this->roleRepo->find($id);
    }

    public function listTenantRoles(string $organizationId): Collection
    {
        return $this->roleRepo->getTenantRoles($organizationId);
    }
}
