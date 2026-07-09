<?php

namespace App\Domain\Project\Services;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectApproval;
use Illuminate\Contracts\Events\Dispatcher;

class ProjectCollaborationService
{
    public function __construct(private Dispatcher $eventBus) {}

    public function addComment(int $projectId, int $userId, string $content, ?int $parentId = null)
    {
        return \App\Domain\Project\Models\ProjectComment::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'content' => $content,
            'parent_id' => $parentId
        ]);
    }

    public function approve(int $projectId, int $userId, string $type, int $id)
    {
        return ProjectApproval::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'approvable_type' => $type,
            'approvable_id' => $id,
            'status' => 'approved'
        ]);
    }
}
