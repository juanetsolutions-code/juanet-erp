<?php

namespace App\Listeners;

use App\Services\SearchServiceInterface;
use App\Services\SearchableInterface;
use Illuminate\Support\Facades\Log;

class UpdateSearchIndexListener
{
    protected SearchServiceInterface $searchService;

    /**
     * Create the event listener.
     */
    public function __construct(SearchServiceInterface $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $model = null;
        $action = 'save'; // save or delete

        if ($event instanceof SearchableInterface) {
            $model = $event;
        } elseif (method_exists($event, 'getSearchableModel')) {
            $model = $event->getSearchableModel();
        } elseif (isset($event->model) && $event->model instanceof SearchableInterface) {
            $model = $event->model;
        }

        if (isset($event->action)) {
            $action = $event->action;
        }

        if ($model) {
            try {
                if ($action === 'delete') {
                    $this->searchService->deindexModel($model);
                } else {
                    $this->searchService->indexModel($model);
                }
            } catch (\Throwable $e) {
                Log::error('UpdateSearchIndexListener failure: ' . $e->getMessage(), [
                    'event' => get_class($event),
                    'model' => get_class($model)
                ]);
            }
        }
    }
}
