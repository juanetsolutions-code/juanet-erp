<?php

namespace App\Repositories;

use App\Models\Organization;
use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    public function find(string $id): ?Organization;
    public function findBySlug(string $slug): ?Organization;
    public function findByDomain(string $domain): ?Organization;
    public function getAllActive(): Collection;
    public function create(array $data): Organization;
    public function update(string $id, array $data): Organization;
    public function delete(string $id): bool;
}
