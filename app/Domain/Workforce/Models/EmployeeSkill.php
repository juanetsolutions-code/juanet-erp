<?php

namespace App\Domain\Workforce\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSkill extends Model
{
    use HasUuidV7;

    protected $table = 'employee_skills';

    protected $fillable = [
        'employee_profile_id',
        'skill_id',
        'type', // primary, secondary
        'experience_level', // beginner, intermediate, advanced, expert
        'certification',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
