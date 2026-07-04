<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAddress extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_contact_addresses';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'type', // billing, shipping, office, home, branch, warehouse, emergency, primary
        'is_primary',
        'street',
        'city',
        'county',
        'region',
        'postal_code',
        'country',
        'coordinates',
        'timezone',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
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
