<?php

namespace App\Domain\Project\Services;

use App\Domain\Project\Repositories\ProjectRepository;
use App\Domain\Project\Models\Project;

class ProjectService
{
    public function __construct(private ProjectRepository $repository) {}

    public function initiateProject(array $data): Project
    {
        return $this->repository->create($data);
    }
}
