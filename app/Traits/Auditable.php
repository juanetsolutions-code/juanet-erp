<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the trait and hook into saving/creating.
     */
    public static function bootAuditable(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (Auth::check() && empty($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
