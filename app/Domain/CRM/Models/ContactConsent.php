<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactConsent extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_contact_consents';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'channel', // email, sms, whatsapp, phone
        'status', // granted, revoked, pending
        'purpose', // marketing, transactional, support
        'ip_address',
        'user_agent',
        'consented_at',
        'source', // webform, agent, portal
        'notes',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
