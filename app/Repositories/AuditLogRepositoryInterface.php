<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function find(string $id): ?AuditLog;
    public function create(array $data): AuditLog;
    public function getByOrganization(string $organizationId): Collection;
    public function getByAuditable(string $type, string $id): Collection;
}
