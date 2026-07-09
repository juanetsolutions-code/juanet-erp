<?php

namespace App\Domain\Finance\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'credit_note_number',
        'amount',
        'issue_date',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issue_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
