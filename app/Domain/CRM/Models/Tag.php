<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

class Tag extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable;

    protected $table = 'crm_tags';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'color',
        'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function leads(): MorphedByMany
    {
        return $this->morphedByMany(Lead::class, 'taggable', 'crm_taggables');
    }

    public function contacts(): MorphedByMany
    {
        return $this->morphedByMany(Contact::class, 'taggable', 'crm_taggables');
    }

    public function companies(): MorphedByMany
    {
        return $this->morphedByMany(Company::class, 'taggable', 'crm_taggables');
    }

    public function opportunities(): MorphedByMany
    {
        return $this->morphedByMany(Opportunity::class, 'taggable', 'crm_taggables');
    }
}
