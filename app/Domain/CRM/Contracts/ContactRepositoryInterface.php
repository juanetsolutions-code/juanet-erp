<?php

namespace App\Domain\CRM\Contracts;

use App\Domain\CRM\Models\Contact;
use Illuminate\Support\Collection;

interface ContactRepositoryInterface
{
    public function find(string $id): ?Contact;
    public function create(array $data): Contact;
    public function update(string $id, array $data): ?Contact;
    public function delete(string $id): bool;
    public function getByOrganization(?string $orgId = null): Collection;
}
