<?php

namespace App\Domain\Workforce\Models;

use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'category',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            EmployeeProfile::class,
            'employee_skills',
            'skill_id',
            'employee_profile_id'
        )->withPivot(['id', 'type', 'experience_level', 'certification'])->withTimestamps();
    }
}
