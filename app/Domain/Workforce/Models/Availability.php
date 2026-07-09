<?php

namespace App\Domain\Workforce\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    use HasUuidV7;

    protected $table = 'availabilities';

    protected $fillable = [
        'employee_profile_id',
        'date',
        'status', // available, unavailable, partially_available
        'capacity_percentage',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'capacity_percentage' => 'integer',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
