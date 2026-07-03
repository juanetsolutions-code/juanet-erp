<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BetaEnrollment extends Model
{
    use HasUuidV7;

    protected $table = 'beta_enrollments';

    protected $fillable = [
        'feature_flag_key',
        'organization_id',
        'user_id',
    ];

    /**
     * Get the feature flag associated with this enrollment.
     */
    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class, 'feature_flag_key', 'key');
    }

    /**
     * Get the organization associated with this enrollment.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the user associated with this enrollment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
