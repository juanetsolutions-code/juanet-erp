<?php

namespace App\Domain\CRM\Activities\Events;

use App\Domain\CRM\Activities\Models\ActivityReminder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReminderTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivityReminder $reminder) {}
}
