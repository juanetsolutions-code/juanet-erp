<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureFlag extends Model
{
    use HasUuidV7;

    protected $table = 'feature_flags';

    protected $fillable = [
        'key',
        'description',
        'is_enabled',
        'is_beta',
        'rules',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_beta' => 'boolean',
        'rules' => 'array',
    ];

    /**
     * Get beta enrollments for this feature flag.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(BetaEnrollment::class, 'feature_flag_key', 'key');
    }
}
