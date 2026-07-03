<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuidV7
{
    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Get whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Boot the trait and generate UUIDv7 on creating.
     */
    protected static function bootHasUuidV7(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                // Laravel 11/12 Str::uuid7() generates UUIDv7
                $model->{$model->getKeyName()} = (string) Str::uuid7();
            }
        });
    }
}
