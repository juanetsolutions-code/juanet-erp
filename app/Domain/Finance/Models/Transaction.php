<?php

namespace App\Domain\Finance\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'ledgerable_type',
        'ledgerable_id',
        'type', // debit, credit
        'amount',
        'transaction_date',
        'description',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }
}
