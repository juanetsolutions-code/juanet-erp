<?php

namespace App\Models;

use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasUuidV7, HasOptimisticLocking;

    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'description',
        'module',
        'ip_address',
        'user_agent',
        'version',
    ];

    protected $casts = [
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
