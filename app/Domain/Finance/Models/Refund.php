<?php

namespace App\Domain\Finance\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'payment_id',
        'refund_number',
        'amount',
        'refund_date',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
