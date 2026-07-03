<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomField extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable;

    protected $table = 'crm_custom_fields';

    protected $fillable = [
        'organization_id',
        'model_type',
        'name',
        'field_type',
        'options',
        'is_required',
        'lock_version',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
