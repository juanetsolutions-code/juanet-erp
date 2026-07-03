<?php

namespace App\Jobs;

use App\Services\EventBus\OutboxPublisherInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

class PublishOutboxCommandJob
{
    use Dispatchable, Queueable;

    /**
     * Execute the scheduled job.
     */
    public function handle(OutboxPublisherInterface $publisher): void
    {
        // Poll and process pending outbox events
        $publisher->publishPending();
    }
}
