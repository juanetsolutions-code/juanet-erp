<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationService
{
    protected OrganizationRepositoryInterface $organizationRepo;

    public function __construct(OrganizationRepositoryInterface $organizationRepo)
    {
        $this->organizationRepo = $organizationRepo;
    }

    public function getOrganization(string $id): ?Organization
    {
        return $this->organizationRepo->find($id);
    }

    public function registerOrganization(array $data): Organization
    {
        // Auto-generate standard slug if not supplied
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->organizationRepo->create($data);
    }

    public function updateOrganization(string $id, array $data): Organization
    {
        return $this->organizationRepo->update($id, $data);
    }

    public function listActiveOrganizations(): Collection
    {
        return $this->organizationRepo->getAllActive();
    }

    public function deleteOrganization(string $id): bool
    {
        return $this->organizationRepo->delete($id);
    }
}
