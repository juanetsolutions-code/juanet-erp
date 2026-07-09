<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    protected $fillable = ['project_id', 'title', 'status', 'due_date'];
}
