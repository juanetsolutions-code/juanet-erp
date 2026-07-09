<?php

namespace App\Domain\Workforce\Models;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\Project\Models\Project;
use App\Models\Organization;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'employee_profile_id',
        'project_id',
        'opportunity_id',
        'role', // Developer, Designer, QA, Project Manager, Content Writer, Support Agent, Finance Officer
        'start_date',
        'end_date',
        'estimated_workload',
        'actual_workload',
        'status', // active, completed, planned
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_workload' => 'decimal:2',
        'actual_workload' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
