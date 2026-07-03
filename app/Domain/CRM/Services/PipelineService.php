<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\PipelineRepositoryInterface;
use App\Domain\CRM\Models\Pipeline;
use Illuminate\Support\Collection;

class PipelineService
{
    protected PipelineRepositoryInterface $repo;

    public function __construct(PipelineRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getPipeline(string $id): ?Pipeline
    {
        return $this->repo->find($id);
    }

    public function createPipeline(array $data): Pipeline
    {
        return $this->repo->create($data);
    }

    public function updatePipeline(string $id, array $data): ?Pipeline
    {
        return $this->repo->update($id, $data);
    }

    public function deletePipeline(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listPipelines(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }
}
