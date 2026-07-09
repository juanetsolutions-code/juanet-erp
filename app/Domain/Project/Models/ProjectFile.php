<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectFile extends Model
{
    protected $fillable = ['project_id', 'filename', 'path', 'user_id', 'folder', 'version', 'parent_id'];
}
