<?php

namespace App\Domain\CRM\Contracts;

use App\Domain\CRM\Models\Lead;
use Illuminate\Support\Collection;

interface LeadRepositoryInterface
{
    public function find(string $id): ?Lead;
    public function create(array $data): Lead;
    public function update(string $id, array $data): ?Lead;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null): Collection;
    public function getByUser(string $userId, ?string $orgId = null): Collection;
}
