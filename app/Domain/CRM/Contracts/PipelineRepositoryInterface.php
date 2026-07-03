<?php

namespace App\Domain\CRM\Contracts;

use App\Domain\CRM\Models\Pipeline;
use Illuminate\Support\Collection;

interface PipelineRepositoryInterface
{
    public function find(string $id): ?Pipeline;
    public function create(array $data): Pipeline;
    public function update(string $id, array $data): ?Pipeline;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null): Collection;
}
