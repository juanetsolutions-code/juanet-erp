<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityProduct extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    protected $table = 'crm_opportunity_products';

    protected $fillable = [
        'organization_id',
        'opportunity_id',
        'product_id',
        'product_name',
        'sku',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'subtotal',
        'recurring_billing_flag',
        'subscription_interval',
        'manual_pricing_override',
        'price_snapshot',
        'lock_version',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'price_snapshot' => 'decimal:2',
        'recurring_billing_flag' => 'boolean',
        'manual_pricing_override' => 'boolean',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate subtotal when creating or updating
        static::saving(function (OpportunityProduct $model) {
            if (!$model->manual_pricing_override) {
                // Subtotal = (Unit Price * Quantity) - Discount + Tax
                $base = $model->unit_price * $model->quantity;
                $model->subtotal = max(0, $base - $model->discount + $model->tax);
            }
        });
    }
}
