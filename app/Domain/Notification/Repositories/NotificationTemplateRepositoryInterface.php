<?php

namespace App\Domain\Notification\Repositories;

use App\Domain\Notification\Models\NotificationTemplate;
use Illuminate\Support\Collection;

interface NotificationTemplateRepositoryInterface
{
    public function find(string $id): ?NotificationTemplate;
    public function findByName(string $name, ?string $organizationId = null): ?NotificationTemplate;
    public function create(array $data): NotificationTemplate;
    public function update(string $id, array $data): ?NotificationTemplate;
    public function delete(string $id): bool;
    public function getAll(?string $organizationId = null): Collection;
}
