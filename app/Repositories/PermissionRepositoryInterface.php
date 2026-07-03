<?php

namespace App\Repositories;

use App\Models\Permission;
use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    public function find(string $id): ?Permission;
    public function findBySlug(string $slug): ?Permission;
    public function getAll(): Collection;
    public function getByModule(string $module): Collection;
    public function create(array $data): Permission;
}
