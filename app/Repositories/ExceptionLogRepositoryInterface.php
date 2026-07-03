<?php

namespace App\Repositories;

use App\Models\ExceptionLog;
use Illuminate\Support\Collection;

interface ExceptionLogRepositoryInterface
{
    public function find(string $id): ?ExceptionLog;
    public function create(array $data): ExceptionLog;
    public function getByClass(string $exceptionClass): Collection;
}
