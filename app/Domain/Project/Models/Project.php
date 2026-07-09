<?php

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Project extends Model
{
    protected $fillable = [
        'uuid',
        'reference_number',
        'user_id',
        'owner_id',
        'client_id',
        'organization_id',
        'name',
        'type',
        'status',
        'budget',
        'timeline',
        'expected_completion'
    ];

    public function transitionTo(string $newStatus)
    {
        $validTransitions = [
            'draft' => ['planning'],
            'planning' => ['in_development'],
            'in_development' => ['internal_review', 'client_review'],
            'internal_review' => ['in_development', 'client_review'],
            'client_review' => ['revision', 'completed'],
            'revision' => ['in_development', 'client_review'],
            'completed' => ['archived'],
        ];

        if (in_array($newStatus, $validTransitions[$this->status] ?? [])) {
            $this->update(['status' => $newStatus]);
        }
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }
}
