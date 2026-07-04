<?php

namespace App\Domain\CRM\Activities\Enums;

enum ActivityPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
