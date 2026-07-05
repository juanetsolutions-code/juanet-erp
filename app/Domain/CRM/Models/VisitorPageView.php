<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorPageView extends Model
{
    use HasUuidV7;

    protected $table = 'crm_visitor_page_views';

    protected $fillable = [
        'session_id',
        'visitor_id',
        'organization_id',
        'url',
        'route_name',
        'page_title',
        'timestamp',
        'time_on_page',
        'scroll_depth',
        'cta_clicks',
        'downloads',
        'outbound_links',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'time_on_page' => 'integer',
        'scroll_depth' => 'integer',
        'cta_clicks' => 'array',
        'downloads' => 'array',
        'outbound_links' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class, 'session_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
