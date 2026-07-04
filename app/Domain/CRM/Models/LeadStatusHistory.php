<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    use HasUuidV7;

    protected $table = 'crm_lead_status_history';

    protected $fillable = [
        'organization_id',
        'lead_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
