<?php

namespace App\Domain\CRM\Activities\Contracts;

use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\DTO\ActivityData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ActivityRepositoryInterface
{
    public function create(ActivityData $data): Activity;

    public function update(Activity $activity, ActivityData $data): Activity;

    public function delete(Activity $activity): bool;

    public function findById(string $id, string $organizationId): ?Activity;

    public function getForEntity(string $loggableType, string $loggableId, string $organizationId, array $filters = []): Collection;

    public function paginateForEntity(string $loggableType, string $loggableId, string $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getOverdueTasks(string $organizationId): Collection;

    public function bulkComplete(array $ids, string $organizationId): int;

    public function bulkUpdate(array $ids, array $attributes, string $organizationId): int;
}
