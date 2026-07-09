<?php

namespace App\Domain\Notification\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasUuidV7;

    protected $table = 'notification_logs';

    protected $fillable = [
        'organization_id',
        'user_id',
        'event_name',
        'payload',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
