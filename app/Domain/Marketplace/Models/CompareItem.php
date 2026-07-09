<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

class CompareItem extends Model
{
    protected $fillable = ['session_id', 'product_id'];
}
