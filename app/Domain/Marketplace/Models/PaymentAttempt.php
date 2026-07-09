<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $fillable = ['order_id', 'provider', 'transaction_id', 'amount', 'status', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}
