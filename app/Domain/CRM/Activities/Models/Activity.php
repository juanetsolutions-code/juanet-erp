<?php

namespace App\Domain\CRM\Activities\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Traits\Searchable;
use App\Services\SearchableInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activity extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Auditable, Searchable, SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'organization_id',
        'loggable_type',
        'loggable_id',
        'user_id',
        'type',
        'subject',
        'description',
        'properties',
        'due_at',
        'completed_at',
        'is_completed',
        'priority',
        'is_recurring',
        'recurring_rules',
        'last_reminder_sent_at',
        'lock_version',
    ];

    protected $casts = [
        'properties' => 'array',
        'recurring_rules' => 'array',
        'is_completed' => 'boolean',
        'is_recurring' => 'boolean',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ActivityNote::class, 'notable_id')
            ->where('notable_type', self::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ActivityReminder::class, 'activity_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ActivityAttachment::class, 'activity_id');
    }

    // SearchableInterface implementation
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->subject,
            'description' => "CRM Activity ({$this->type}): {$this->subject}. Priority: {$this->priority}. Status: " . ($this->is_completed ? 'Completed' : 'Pending'),
            'content' => "Subject: {$this->subject}. Description: {$this->description}. Type: {$this->type}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'crm';
    }

    public function getSearchPermission(): ?string
    {
        return 'view_activities';
    }

    public function getSearchUrl(): ?string
    {
        return "/crm/activities/{$this->id}";
    }
}
