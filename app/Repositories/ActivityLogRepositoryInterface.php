<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Support\Collection;

interface ActivityLogRepositoryInterface
{
    public function find(string $id): ?ActivityLog;
    public function create(array $data): ActivityLog;
    public function getByOrganization(string $organizationId): Collection;
    public function getByUser(string $userId): Collection;
}
