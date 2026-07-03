<?php

namespace App\Traits;

use App\Services\SearchableInterface;
use App\Services\SearchServiceInterface;
use Illuminate\Support\Facades\Log;

trait Searchable
{
    /**
     * Automatic boot hook for Eloquent models using this trait.
     */
    public static function bootSearchable(): void
    {
        static::saved(function ($model) {
            if ($model instanceof SearchableInterface) {
                try {
                    // Let's index using SearchService
                    $searchService = app(SearchServiceInterface::class);
                    $searchService->indexModel($model);
                } catch (\Throwable $e) {
                    Log::error('Automated search index synchronization failed: ' . $e->getMessage(), [
                        'model' => get_class($model),
                        'id' => $model->id ?? 'unknown'
                    ]);
                }
            }
        });

        static::deleted(function ($model) {
            if ($model instanceof SearchableInterface) {
                try {
                    $searchService = app(SearchServiceInterface::class);
                    $searchService->deindexModel($model);
                } catch (\Throwable $e) {
                    Log::error('Automated search index deindexing failed: ' . $e->getMessage(), [
                        'model' => get_class($model),
                        'id' => $model->id ?? 'unknown'
                    ]);
                }
            }
        });
    }
}
