<?php

namespace App\Domain\CRM\Contracts;

use App\Domain\CRM\Models\Opportunity;
use Illuminate\Support\Collection;

interface OpportunityRepositoryInterface
{
    public function find(string $id): ?Opportunity;
    public function create(array $data): Opportunity;
    public function update(string $id, array $data): ?Opportunity;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null): Collection;
}
