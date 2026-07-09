<?php

namespace App\Domain\Finance\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_name',
        'client_email',
        'billing_cycle',
        'start_date',
        'end_date',
        'last_generated_at',
        'status',
        'template_data',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_generated_at' => 'datetime',
        'template_data' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
