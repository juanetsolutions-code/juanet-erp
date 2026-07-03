<?php

namespace App\Repositories\Eloquent;

use App\Models\Organization;
use App\Repositories\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function find(string $id): ?Organization
    {
        return Organization::find($id);
    }

    public function findBySlug(string $slug): ?Organization
    {
        return Organization::where('slug', $slug)->first();
    }

    public function findByDomain(string $domain): ?Organization
    {
        return Organization::where('domain', $domain)->first();
    }

    public function getAllActive(): Collection
    {
        return Organization::active()->get();
    }

    public function create(array $data): Organization
    {
        return Organization::create($data);
    }

    public function update(string $id, array $data): Organization
    {
        $organization = Organization::findOrFail($id);
        $organization->update($data);
        return $organization;
    }

    public function delete(string $id): bool
    {
        $organization = Organization::find($id);
        if ($organization) {
            return $organization->delete();
        }
        return false;
    }
}
