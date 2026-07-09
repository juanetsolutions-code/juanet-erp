<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'total', 'status', 'currency', 'payment_status', 'billing_details'];
    protected $casts = ['billing_details' => 'array'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
