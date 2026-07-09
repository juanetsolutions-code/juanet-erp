<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectComment extends Model
{
    protected $fillable = ['project_id', 'user_id', 'content', 'parent_id'];
}
