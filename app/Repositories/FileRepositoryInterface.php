<?php

namespace App\Repositories;

use App\Models\StoredFile;
use Illuminate\Support\Collection;

interface FileRepositoryInterface
{
    public function find(string $id): ?StoredFile;
    public function create(array $data): StoredFile;
    public function update(string $id, array $data): ?StoredFile;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null, ?string $userId = null, ?string $category = null): Collection;
    public function getByUser(string $userId, ?string $orgId = null, ?string $category = null): Collection;
    public function getExpiredTemporaryFiles(): Collection;
    public function search(string $query, ?string $orgId = null, ?string $userId = null): Collection;
}
