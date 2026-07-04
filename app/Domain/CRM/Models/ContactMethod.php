<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMethod extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_contact_methods';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'type', // email, phone
        'value',
        'label', // work, personal, secondary, mobile, whatsapp, etc.
        'is_primary',
        'is_verified',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
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
