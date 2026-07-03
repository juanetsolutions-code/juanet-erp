<?php

namespace App\Domain\CRM\Repositories;

use App\Domain\CRM\Contracts\CompanyRepositoryInterface;
use App\Domain\CRM\Models\Company;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class CompanyRepository implements CompanyRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Company
    {
        $orgId = $this->tenantContext->getTenantId();
        return Company::where('id', $id)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->first();
    }

    public function create(array $data): Company
    {
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId && !isset($data['organization_id'])) {
            $data['organization_id'] = $orgId;
        }
        return Company::create($data);
    }

    public function update(string $id, array $data): ?Company
    {
        $company = $this->find($id);
        if ($company) {
            $company->update($data);
            return $company;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $company = $this->find($id);
        if ($company) {
            return (bool) $company->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Company::when($orgId, function ($q) use ($orgId) {
            return $q->where('organization_id', $orgId);
        })->get();
    }
}
