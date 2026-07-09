<?php

namespace App\Domain\Workforce\Models;

use App\Models\Organization;
use App\Models\User;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'user_id',
        'department_id',
        'position_id',
        'reporting_to_id',
        'skills_expert_score',
        'status', // active, on_leave, inactive
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function reportingTo(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'reporting_to_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'reporting_to_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'employee_profile_teams',
            'employee_profile_id',
            'team_id'
        );
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'employee_skills',
            'employee_profile_id',
            'skill_id'
        )->withPivot(['id', 'type', 'experience_level', 'certification'])->withTimestamps();
    }

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
