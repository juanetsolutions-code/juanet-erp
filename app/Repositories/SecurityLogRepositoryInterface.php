<?php

namespace App\Repositories;

use App\Models\SecurityLog;
use Illuminate\Support\Collection;

interface SecurityLogRepositoryInterface
{
    public function find(string $id): ?SecurityLog;
    public function create(array $data): SecurityLog;
    public function getByOrganization(string $organizationId): Collection;
    public function getBySeverity(string $severity): Collection;
}
