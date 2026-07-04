<?php

namespace App\Domain\CRM\Activities\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityNote extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    protected $table = 'crm_activity_notes';

    protected $fillable = [
        'organization_id',
        'notable_type',
        'notable_id',
        'user_id',
        'content',
        'version',
        'parent_id',
        'original_note_id',
        'lock_version',
    ];

    protected $casts = [
        'version' => 'integer',
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
        return $this->belongsTo(User::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function originalNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_note_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'original_note_id')->orderBy('version', 'desc');
    }
}
