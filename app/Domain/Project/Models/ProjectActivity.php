<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectActivity extends Model
{
    protected $fillable = ['project_id', 'activity', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}
