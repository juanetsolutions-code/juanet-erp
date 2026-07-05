<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorBehaviorProfile extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_visitor_behavior_profiles';

    protected $fillable = [
        'visitor_id',
        'organization_id',
        'engagement_score',
        'purchase_intent',
        'service_interests',
        'product_interests',
        'content_intelligence',
        'customer_value',
        'score_history',
        'timeline_summary',
    ];

    protected $casts = [
        'engagement_score' => 'integer',
        'service_interests' => 'array',
        'product_interests' => 'array',
        'content_intelligence' => 'array',
        'customer_value' => 'array',
        'score_history' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
