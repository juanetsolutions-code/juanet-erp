<?php

namespace App\Domain\CRM\Events;

class MeetingScheduled extends CrmDomainEvent
{
    public function __construct(
        array $meetingData,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.meeting.scheduled',
            eventType: 'queued',
            organizationId: $meetingData['organization_id'] ?? null,
            aggregateType: 'Meeting',
            aggregateId: (string) ($meetingData['id'] ?? 'unknown'),
            aggregateVersion: 1,
            payload: $meetingData,
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
