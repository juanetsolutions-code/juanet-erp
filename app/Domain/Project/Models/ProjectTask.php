<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    protected $table = 'project_tasks';

    protected $fillable = [
        'milestone_id',
        'assignee_id',
        'title',
        'priority',
        'status',
        'due_date'
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }
}
