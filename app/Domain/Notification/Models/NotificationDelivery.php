<?php

namespace App\Domain\Notification\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasUuidV7;

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'organization_id',
        'notification_id',
        'channel',
        'recipient',
        'status',
        'error_message',
        'retry_count',
        'version',
    ];

    protected $casts = [
        'retry_count' => 'integer',
        'version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
