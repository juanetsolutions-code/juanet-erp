<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\CompanyRepositoryInterface;
use App\Domain\CRM\Models\Company;
use Illuminate\Support\Collection;

class CompanyService
{
    protected CompanyRepositoryInterface $repo;

    public function __construct(CompanyRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getCompany(string $id): ?Company
    {
        return $this->repo->find($id);
    }

    public function createCompany(array $data): Company
    {
        return $this->repo->create($data);
    }

    public function updateCompany(string $id, array $data): ?Company
    {
        return $this->repo->update($id, $data);
    }

    public function deleteCompany(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listCompanies(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }
}
