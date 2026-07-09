<?php

namespace App\Domain\Project\Repositories;

use App\Domain\Project\Models\Project;

class ProjectRepository
{
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function findForClient(int $userId)
    {
        return Project::where('user_id', $userId)->get();
    }
}
