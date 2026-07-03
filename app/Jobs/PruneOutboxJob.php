<?php

namespace App\Jobs;

use App\Models\EventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

class PruneOutboxJob
{
    use Dispatchable, Queueable;

    protected int $days;

    /**
     * Create a new job instance.
     */
    public function __construct(int $days = 30)
    {
        $this->days = $days;
    }

    /**
     * Execute the scheduled job.
     */
    public function handle(): void
    {
        // Delete records older than the threshold to save storage and optimize indexes
        EventOutbox::where('status', 'published')
            ->where('updated_at', '<', now()->subDays($this->days))
            ->delete();
    }
}
