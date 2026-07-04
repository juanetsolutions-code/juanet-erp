<?php

namespace App\Domain\CRM\Activities\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use App\Models\User;
use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAttachment extends Model
{
    use HasUuidV7;

    protected $table = 'crm_activity_attachments';

    protected $fillable = [
        'organization_id',
        'activity_id',
        'stored_file_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
