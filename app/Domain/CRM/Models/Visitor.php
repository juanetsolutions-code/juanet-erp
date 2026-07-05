<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visitor extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_visitors';

    protected $fillable = [
        'organization_id',
        'first_seen_at',
        'last_seen_at',
        'total_sessions',
        'total_page_views',
        'country',
        'city',
        'timezone',
        'preferred_language',
        'browser',
        'operating_system',
        'device_type',
        'screen_resolution',
        'viewport',
        'network_type',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'campaign_history',
        'referral_chain',
        'first_touch',
        'last_touch',
        'cookie_consent',
        'do_not_track',
        'anonymized_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'total_sessions' => 'integer',
        'total_page_views' => 'integer',
        'campaign_history' => 'array',
        'referral_chain' => 'array',
        'first_touch' => 'array',
        'last_touch' => 'array',
        'do_not_track' => 'boolean',
        'anonymized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class, 'visitor_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(VisitorPageView::class, 'visitor_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'visitor_id');
    }

    public function behaviorProfile(): HasOne
    {
        return $this->hasOne(VisitorBehaviorProfile::class, 'visitor_id');
    }
}
