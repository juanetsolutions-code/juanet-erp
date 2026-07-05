<?php

namespace App\Domain\Marketplace\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'marketplace_categories';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'icon',
        'cover_image',
        'product_count',
    ];

    protected $casts = [
        'product_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }
}
