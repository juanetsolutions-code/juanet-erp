<?php

namespace App\Domain\CRM\Events;

class TaskOverdue extends CrmDomainEvent
{
    public function __construct(
        array $taskData,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.task.overdue',
            eventType: 'queued',
            organizationId: $taskData['organization_id'] ?? null,
            aggregateType: 'Task',
            aggregateId: (string) ($taskData['id'] ?? 'unknown'),
            aggregateVersion: 1,
            payload: $taskData,
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
