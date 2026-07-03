<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOutbox extends Model
{
    use HasUuidV7;

    protected $table = 'event_outboxes';

    protected $fillable = [
        'organization_id',
        'event_name',
        'event_type',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'scheduled_at',
        'idempotency_key',
        'webhook_url',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization associated with this outbox event.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
