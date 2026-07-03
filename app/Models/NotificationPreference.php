<?php

namespace App\Models;

use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuidV7, HasOptimisticLocking;

    protected $fillable = [
        'user_id',
        'organization_id',
        'channels',
        'categories',
        'version',
    ];

    protected $casts = [
        'channels' => 'array',
        'categories' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
