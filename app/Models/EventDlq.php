<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDlq extends Model
{
    use HasUuidV7;

    protected $table = 'event_dlqs';

    protected $fillable = [
        'organization_id',
        'original_outbox_id',
        'event_name',
        'event_type',
        'payload',
        'failure_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization associated with this dead letter.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
