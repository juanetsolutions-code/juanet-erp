<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLocation extends Model
{
    use HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    protected $table = 'crm_company_locations';

    protected $fillable = [
        'organization_id',
        'company_id',
        'type', // headquarters, branch, warehouse, billing, shipping
        'name',
        'address',
        'country',
        'state',
        'county',
        'city',
        'postal_code',
        'gps_coordinates',
        'timezone',
        'phone',
        'email',
        'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
