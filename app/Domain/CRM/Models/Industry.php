<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    protected $table = 'crm_industries';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'industry_id');
    }
}
