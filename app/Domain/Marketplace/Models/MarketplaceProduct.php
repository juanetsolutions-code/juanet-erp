<?php

namespace App\Domain\Marketplace\Models;

use App\Traits\HasUuidV7;
use App\Contracts\MarketplaceListingInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProduct extends Model implements MarketplaceListingInterface
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'marketplace_products';

    protected $fillable = [
        'organization_id',
        'category_id',
        'title',
        'slug',
        'short_description',
        'description',
        'technology',
        'rating',
        'review_count',
        'price',
        'previous_price',
        'is_new',
        'is_best_seller',
        'is_featured',
        'thumbnail',
        'gallery',
        'features',
        'screenshots',
    ];

    protected $casts = [
        'technology' => 'array',
        'gallery' => 'array',
        'features' => 'array',
        'screenshots' => 'array',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'is_new' => 'boolean',
        'is_best_seller' => 'boolean',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    /**
     * Map model attributes to search index format.
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->short_description,
            'content' => $this->description . ' ' . implode(' ', $this->technology ?? []) . ' ' . implode(' ', $this->features ?? []),
            'embedding' => null,
        ];
    }

    /**
     * Return the module name representing this record.
     */
    public function getSearchableModule(): string
    {
        return 'marketplace';
    }

    /**
     * Return the permission name required to view this search result.
     * Public page, so return null.
     */
    public function getSearchPermission(): ?string
    {
        return null;
    }

    /**
     * Return the view deep-link URL for this record.
     */
    public function getSearchUrl(): ?string
    {
        return '/marketplace?product=' . $this->slug;
    }

    /**
     * Get the tenant organization ID if applicable.
     */
    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }
}
