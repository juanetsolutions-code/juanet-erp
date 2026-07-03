<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\OpportunityRepositoryInterface;
use App\Domain\CRM\Models\Opportunity;
use Illuminate\Support\Collection;

class OpportunityService
{
    protected OpportunityRepositoryInterface $repo;

    public function __construct(OpportunityRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getOpportunity(string $id): ?Opportunity
    {
        return $this->repo->find($id);
    }

    public function createOpportunity(array $data): Opportunity
    {
        return $this->repo->create($data);
    }

    public function updateOpportunity(string $id, array $data): ?Opportunity
    {
        return $this->repo->update($id, $data);
    }

    public function deleteOpportunity(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listOpportunities(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }
}
