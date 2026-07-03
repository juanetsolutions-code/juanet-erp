<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SearchIndex extends Model
{
    use HasUuidV7;

    protected $table = 'search_indexes';

    protected $fillable = [
        'organization_id',
        'searchable_type',
        'searchable_id',
        'module',
        'title',
        'description',
        'content',
        'url',
        'permission_required',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Parent organization for tenant-isolated search results.
     */
    public function organization(): BelongsTo
     {
         return $this->belongsTo(Organization::class);
     }

    /**
     * Polymorphic relation to the original model.
     */
    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }
}
