<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id', 'status', 'currency'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
