<?php

namespace App\Domain\Workforce\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'employee_profile_id',
        'team_id',
        'name',
        'schedule_data',
    ];

    protected $casts = [
        'schedule_data' => 'array',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
