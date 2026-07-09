<?php

namespace App\Domain\Workforce\Models;

use App\Models\Organization;
use App\Models\User;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'manager_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
