<?php

namespace App\Repositories\Eloquent;

use App\Models\ExceptionLog;
use App\Repositories\ExceptionLogRepositoryInterface;
use Illuminate\Support\Collection;

class ExceptionLogRepository implements ExceptionLogRepositoryInterface
{
    public function find(string $id): ?ExceptionLog
    {
        return ExceptionLog::find($id);
    }

    public function create(array $data): ExceptionLog
    {
        return ExceptionLog::create($data);
    }

    public function getByClass(string $exceptionClass): Collection
    {
        return ExceptionLog::where('exception_class', $exceptionClass)->get();
    }
}
