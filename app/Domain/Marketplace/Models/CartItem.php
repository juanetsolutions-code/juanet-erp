<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'license_type', 'quantity', 'price'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
