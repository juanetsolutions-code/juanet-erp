<?php

namespace App\Domain\CRM\Activities\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimelineUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $loggableType,
        public string $loggableId,
        public string $organizationId
    ) {}
}
