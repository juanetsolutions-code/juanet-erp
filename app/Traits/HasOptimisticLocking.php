<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

trait HasOptimisticLocking
{
    /**
     * Boot the trait to set initial version.
     */
    public static function bootHasOptimisticLocking(): void
    {
        static::creating(function ($model) {
            if (!isset($model->version)) {
                $model->version = 1;
            }
        });

        static::updating(function ($model) {
            $originalVersion = $model->getOriginal('version') ?? 1;
            $model->version = $originalVersion + 1;
        });
    }

    /**
     * Override performUpdate to enforce the version condition.
     */
    protected function performUpdate(Builder $query)
    {
        if ($this->isDirty('version')) {
            $originalVersion = $this->getOriginal('version') ?? 1;
            $query->where('version', $originalVersion);
        }

        $result = parent::performUpdate($query);

        if ($result === 0) {
            throw new RuntimeException("Optimistic locking conflict: Model " . get_class($this) . " [{$this->getKey()}] has been updated by another process.");
        }

        return $result;
    }
}
