<?php

namespace App\Domain\Notification\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    use HasUuidV7;

    protected $table = 'notification_channels';

    protected $fillable = [
        'name',
        'key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
