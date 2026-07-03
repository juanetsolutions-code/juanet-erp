<?php

namespace App\Domain\CRM\Contracts;

use App\Domain\CRM\Models\Company;
use Illuminate\Support\Collection;

interface CompanyRepositoryInterface
{
    public function find(string $id): ?Company;
    public function create(array $data): Company;
    public function update(string $id, array $data): ?Company;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null): Collection;
}
