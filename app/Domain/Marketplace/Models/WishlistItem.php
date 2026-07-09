<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    protected $fillable = ['user_id', 'session_id', 'product_id'];
}
