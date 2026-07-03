<?php

namespace App\Jobs;

use App\Events\Interfaces\DomainEventInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DomainEventInterface $event;

    /**
     * Create a new job instance.
     */
    public function __construct(DomainEventInterface $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Dispatch the native event to all local registered listeners
        event($this->event);
    }
}
