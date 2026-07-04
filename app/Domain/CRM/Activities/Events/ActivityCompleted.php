<?php

namespace App\Domain\CRM\Activities\Events;

use App\Domain\CRM\Activities\Models\Activity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Activity $activity) {}
}
