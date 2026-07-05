<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorSession extends Model
{
    use HasUuidV7;

    protected $table = 'crm_visitor_sessions';

    protected $fillable = [
        'visitor_id',
        'organization_id',
        'start_time',
        'end_time',
        'duration',
        'referrer',
        'landing_page',
        'exit_page',
        'pages_visited',
        'bounce',
        'returning_visitor',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'pages_visited' => 'integer',
        'bounce' => 'boolean',
        'returning_visitor' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(VisitorPageView::class, 'session_id');
    }
}
