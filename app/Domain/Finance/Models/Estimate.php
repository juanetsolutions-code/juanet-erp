<?php

namespace App\Domain\Finance\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estimate extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'estimate_number',
        'client_id',
        'client_name',
        'client_email',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'estimate_date',
        'expiry_date',
        'terms_conditions',
        'notes',
        'revision_history',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'estimate_date' => 'date',
        'expiry_date' => 'date',
        'revision_history' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }
}
