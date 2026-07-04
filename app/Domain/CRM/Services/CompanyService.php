<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\CompanyRepositoryInterface;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\CompanyLocation;
use Illuminate\Support\Collection;
use App\Services\TenantContext;
use App\Contracts\EventBus;
use App\Domain\CRM\Events\CompanyLocationAdjustedEvent;

class CompanyService
{
    protected CompanyRepositoryInterface $repo;
    protected TenantContext $tenantContext;
    protected EventBus $eventBus;

    public function __construct(CompanyRepositoryInterface $repo, TenantContext $tenantContext, EventBus $eventBus)
    {
        $this->repo = $repo;
        $this->tenantContext = $tenantContext;
        $this->eventBus = $eventBus;
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

    // New nested location methods
    public function getLocations(string $companyId): Collection
    {
        $company = $this->getCompany($companyId);
        return $company ? $company->locations : collect();
    }

    public function createLocation(string $companyId, array $data): ?CompanyLocation
    {
        $company = $this->getCompany($companyId);
        if (!$company) return null;

        $orgId = $this->tenantContext->getTenantId();
        $data['company_id'] = $companyId;
        $data['organization_id'] = $orgId;

        $location = CompanyLocation::create($data);
        if ($location) {
            $this->eventBus->dispatch(new CompanyLocationAdjustedEvent($company, $location, 'created'));
        }
        return $location;
    }

    public function updateLocation(string $companyId, string $locationId, array $data): ?CompanyLocation
    {
        $company = $this->getCompany($companyId);
        if (!$company) return null;

        $location = $company->locations()->where('id', $locationId)->first();
        if ($location) {
            $location->update($data);
            $this->eventBus->dispatch(new CompanyLocationAdjustedEvent($company, $location, 'updated'));
            return $location;
        }
        return null;
    }

    public function deleteLocation(string $companyId, string $locationId): bool
    {
        $company = $this->getCompany($companyId);
        if (!$company) return false;

        $location = $company->locations()->where('id', $locationId)->first();
        if ($location) {
            $this->eventBus->dispatch(new CompanyLocationAdjustedEvent($company, $location, 'deleted'));
            return (bool) $location->delete();
        }
        return false;
    }

    // Hierarchy traversal
    public function getHierarchy(string $companyId): array
    {
        $company = $this->getCompany($companyId);
        if (!$company) return [];

        // Traversal up
        $ancestors = [];
        $current = $company;
        $visited = []; // Prevent infinite loops
        while ($current && $current->parent_id && !isset($visited[$current->parent_id])) {
            $visited[$current->parent_id] = true;
            $parent = Company::find($current->parent_id);
            if ($parent) {
                $ancestors[] = [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'status' => $parent->status,
                ];
                $current = $parent;
            } else {
                break;
            }
        }

        // Traversal down (direct child company level)
        $subsidiaries = $company->subsidiaries()->get()->map(function ($child) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'status' => $child->status,
            ];
        })->toArray();

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
            ],
            'ancestors' => array_reverse($ancestors),
            'subsidiaries' => $subsidiaries,
        ];
    }
}
