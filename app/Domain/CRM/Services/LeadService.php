<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\LeadRepositoryInterface;
use App\Domain\CRM\Models\Lead;
use Illuminate\Support\Collection;

class LeadService
{
    protected LeadRepositoryInterface $repo;

    public function __construct(LeadRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getLead(string $id): ?Lead
    {
        return $this->repo->find($id);
    }

    public function createLead(array $data): Lead
    {
        return $this->repo->create($data);
    }

    public function updateLead(string $id, array $data): ?Lead
    {
        return $this->repo->update($id, $data);
    }

    public function deleteLead(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listLeads(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }

    public function listLeadsByOwner(string $userId, ?string $orgId = null): Collection
    {
        return $this->repo->getByUser($userId, $orgId);
    }
}
