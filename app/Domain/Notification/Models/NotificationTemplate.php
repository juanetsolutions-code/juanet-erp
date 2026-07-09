<?php

namespace App\Domain\Notification\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use HasUuidV7;

    protected $table = 'notification_templates';

    protected $fillable = [
        'organization_id',
        'name',
        'subject',
        'body_markdown',
        'body_html',
        'channels',
        'version',
    ];

    protected $casts = [
        'channels' => 'array',
        'version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
