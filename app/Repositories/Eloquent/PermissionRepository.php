<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function find(string $id): ?Permission
    {
        return Permission::find($id);
    }

    public function findBySlug(string $slug): ?Permission
    {
        return Permission::where('slug', $slug)->first();
    }

    public function getAll(): Collection
    {
        return Permission::all();
    }

    public function getByModule(string $module): Collection
    {
        return Permission::where('module', $module)->get();
    }

    public function create(array $data): Permission
    {
        return Permission::create($data);
    }
}
