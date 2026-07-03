<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    protected UserRepositoryInterface $userRepo;

    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function createUser(array $data): User
    {
        // Enforce secure password hashing in Service layer
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userRepo->create($data);
    }

    public function getUser(string $id): ?User
    {
        return $this->userRepo->find($id);
    }

    public function listActiveUsers(): Collection
    {
        return $this->userRepo->getAllActive();
    }

    public function assignRoleToUser(User $user, string $roleId, string $organizationId): void
    {
        $this->userRepo->assignRole($user, $roleId, $organizationId);
    }
}
