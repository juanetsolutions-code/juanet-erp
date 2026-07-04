<?php

namespace App\Domain\CRM\Activities\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReminder extends Model
{
    use HasUuidV7;

    protected $table = 'crm_activity_reminders';

    protected $fillable = [
        'organization_id',
        'activity_id',
        'user_id',
        'title',
        'description',
        'remind_at',
        'method',
        'is_sent',
        'sent_at',
        'recurring_rules',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'recurring_rules' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
