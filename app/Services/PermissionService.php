<?php

namespace App\Services;

use App\Models\Permission;
use App\Repositories\PermissionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PermissionService
{
    protected PermissionRepositoryInterface $permissionRepo;

    public function __construct(PermissionRepositoryInterface $permissionRepo)
    {
        $this->permissionRepo = $permissionRepo;
    }

    public function createPermission(array $data): Permission
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->permissionRepo->create($data);
    }

    public function listAllPermissions(): Collection
    {
        return $this->permissionRepo->getAll();
    }
}
