<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectApproval extends Model
{
    protected $fillable = ['project_id', 'user_id', 'approvable_type', 'approvable_id', 'status'];
}
