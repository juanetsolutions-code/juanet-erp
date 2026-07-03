<?php

namespace App\Domain\CRM\Repositories;

use App\Domain\CRM\Contracts\OpportunityRepositoryInterface;
use App\Domain\CRM\Models\Opportunity;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class OpportunityRepository implements OpportunityRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Opportunity
    {
        $orgId = $this->tenantContext->getTenantId();
        return Opportunity::where('id', $id)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->first();
    }

    public function create(array $data): Opportunity
    {
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId && !isset($data['organization_id'])) {
            $data['organization_id'] = $orgId;
        }
        return Opportunity::create($data);
    }

    public function update(string $id, array $data): ?Opportunity
    {
        $opportunity = $this->find($id);
        if ($opportunity) {
            $opportunity->update($data);
            return $opportunity;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $opportunity = $this->find($id);
        if ($opportunity) {
            return (bool) $opportunity->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Opportunity::when($orgId, function ($q) use ($orgId) {
            return $q->where('organization_id', $orgId);
        })->get();
    }
}
