<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    protected $fillable = ['order_item_id', 'license_id', 'product_id', 'user_id', 'license_type', 'signature', 'expires_at'];
}
