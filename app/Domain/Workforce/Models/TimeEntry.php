<?php

namespace App\Domain\Workforce\Models;

use App\Domain\Project\Models\Project;
use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'employee_profile_id',
        'assignment_id',
        'project_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_billable',
        'is_manual',
        'description',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'is_billable' => 'boolean',
        'is_manual' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
