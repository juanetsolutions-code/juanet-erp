<?php

namespace App\Domain\CRM\Repositories;

use App\Domain\CRM\Contracts\PipelineRepositoryInterface;
use App\Domain\CRM\Models\Pipeline;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class PipelineRepository implements PipelineRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?Pipeline
    {
        $orgId = $this->tenantContext->getTenantId();
        return Pipeline::where('id', $id)
            ->when($orgId, function ($q) use ($orgId) {
                return $q->where('organization_id', $orgId);
            })
            ->with('stages')
            ->first();
    }

    public function create(array $data): Pipeline
    {
        $orgId = $this->tenantContext->getTenantId();
        if ($orgId && !isset($data['organization_id'])) {
            $data['organization_id'] = $orgId;
        }
        return Pipeline::create($data);
    }

    public function update(string $id, array $data): ?Pipeline
    {
        $pipeline = $this->find($id);
        if ($pipeline) {
            $pipeline->update($data);
            return $pipeline;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $pipeline = $this->find($id);
        if ($pipeline) {
            return (bool) $pipeline->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        return Pipeline::when($orgId, function ($q) use ($orgId) {
            return $q->where('organization_id', $orgId);
        })->with('stages')->get();
    }
}
