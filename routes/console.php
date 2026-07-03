<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessOutboxJob;
use App\Jobs\PruneOutboxJob;
use App\Services\SearchServiceInterface;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Register Command to process pending Outbox entries
Artisan::command('outbox:process {--limit=100 : The number of events to process}', function () {
    $limit = (int) $this->option('limit');
    $this->info("Starting Transactional Outbox processing (limit: {$limit})...");
    
    try {
        $publisher = app(\App\Services\EventBus\OutboxPublisherInterface::class);
        $count = $publisher->publishPending($limit);
        $this->info("Successfully processed {$count} outbox entries.");
    } catch (\Throwable $e) {
        $this->error("Transactional Outbox processing failed: " . $e->getMessage());
    }
})->purpose('Process pending entries from the Transactional Outbox');

// Register Command to prune old published Outbox entries
Artisan::command('outbox:prune {--days=30 : The age of records in days to delete}', function () {
    $days = (int) $this->option('days');
    $this->info("Pruning published outbox entries older than {$days} days...");
    
    try {
        dispatch(new PruneOutboxJob($days));
        $this->info("Prune job dispatched successfully.");
    } catch (\Throwable $e) {
        $this->error("Failed to prune outbox entries: " . $e->getMessage());
    }
})->purpose('Prune old published outbox entries');

// Register Command to rebuild search index
Artisan::command('search:rebuild', function () {
    $this->info("Rebuilding search indexes for all searchable models...");
    
    try {
        $searchService = app(SearchServiceInterface::class);
        $count = $searchService->reindexAll();
        $this->info("Successfully rebuilt {$count} search index entries.");
    } catch (\Throwable $e) {
        $this->error("Failed to rebuild search index: " . $e->getMessage());
    }
})->purpose('Rebuild the entire search database indexes');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Schedule the process outbox job to run every minute
Schedule::job(new ProcessOutboxJob(100))->everyMinute();

// Schedule outbox pruning to run daily to maintain storage and index hygiene
Schedule::job(new PruneOutboxJob(30))->daily();
