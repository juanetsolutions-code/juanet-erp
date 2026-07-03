<?php

namespace App\Models;

use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExceptionLog extends Model
{
    use HasUuidV7, HasOptimisticLocking;

    protected $fillable = [
        'organization_id',
        'user_id',
        'exception_class',
        'message',
        'trace',
        'file',
        'line',
        'url',
        'ip_address',
        'user_agent',
        'version',
    ];

    protected $casts = [
        'line' => 'integer',
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
