<?php

namespace App\Domain\CRM\Activities\Events;

use App\Domain\CRM\Activities\Models\ActivityAttachment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttachmentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivityAttachment $attachment) {}
}
