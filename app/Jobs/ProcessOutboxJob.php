<?php

namespace App\Jobs;

use App\Services\EventBus\OutboxPublisherInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $limit;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 100)
    {
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle(OutboxPublisherInterface $publisher): void
    {
        $publisher->publishPending($this->limit);
    }
}
