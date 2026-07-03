<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function find(string $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getAllActive(): Collection
    {
        return User::active()->get();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(string $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function delete(string $id): bool
    {
        $user = User::find($id);
        if ($user) {
            return $user->delete();
        }
        return false;
    }

    public function assignRole(User $user, string $roleId, string $organizationId): void
    {
        // Many-to-many attach on role_user table with custom organization_id
        $user->roles()->attach($roleId, ['organization_id' => $organizationId]);
    }
}
