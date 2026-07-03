<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function getAllActive(): Collection;
    public function create(array $data): User;
    public function update(string $id, array $data): User;
    public function delete(string $id): bool;
    public function assignRole(User $user, string $roleId, string $organizationId): void;
}
