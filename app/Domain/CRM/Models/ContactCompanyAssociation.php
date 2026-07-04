<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCompanyAssociation extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_contact_company_associations';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'company_id',
        'role',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
