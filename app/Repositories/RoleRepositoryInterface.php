<?php

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function find(string $id): ?Role;
    public function findBySlug(string $slug, ?string $organizationId = null): ?Role;
    public function getGlobalRoles(): Collection;
    public function getTenantRoles(?string $organizationId = null): Collection;
    public function create(array $data): Role;
    public function update(string $id, array $data): Role;
    public function delete(string $id): bool;
}
