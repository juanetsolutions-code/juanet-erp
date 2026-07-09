<?php

namespace App\Domain\Workforce\Models;

use App\Models\Organization;
use App\Models\User;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'organization_id',
        'employee_profile_id',
        'type', // vacation, sick, emergency, remote_work
        'start_date',
        'end_date',
        'status', // pending, approved, rejected
        'reason',
        'approved_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
