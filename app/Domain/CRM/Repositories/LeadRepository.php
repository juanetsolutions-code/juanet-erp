<?php

namespace App\Domain\CRM\Repositories;

use App\Domain\CRM\Contracts\LeadRepositoryInterface;
use App\Domain\CRM\Models\Lead;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class LeadRepository implements LeadRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Lead
    {
        $orgId = $this->tenantContext->getTenantId();
        return Lead::where('id', $id)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->first();
    }

    public function create(array $data): Lead
    {
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId && !isset($data['organization_id'])) {
            $data['organization_id'] = $orgId;
        }
        return Lead::create($data);
    }

    public function update(string $id, array $data): ?Lead
    {
        $lead = $this->find($id);
        if ($lead) {
            $lead->update($data);
            return $lead;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $lead = $this->find($id);
        if ($lead) {
            return (bool) $lead->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Lead::when($orgId, function ($q) use ($orgId) {
            return $q->where('organization_id', $orgId);
        })->get();
    }

    public function getByUser(string $userId, ?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Lead::where('user_id', $userId)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->get();
    }
}
