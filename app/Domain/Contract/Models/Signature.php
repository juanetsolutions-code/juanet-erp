<?php

namespace App\Domain\Contract\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Signature extends Model
{
    protected $table = 'signatures';

    protected $fillable = [
        'contract_id',
        'signer_id',
        'ip_address',
        'signature_hash',
        'signed_at',
        'signature_type', // mouse, typed, touch
        'signature_data'  // JSON or string signature representation
    ];

    protected $casts = [
        'signed_at' => 'datetime'
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
