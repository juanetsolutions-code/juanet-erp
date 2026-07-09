<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'license_type', 'price'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
