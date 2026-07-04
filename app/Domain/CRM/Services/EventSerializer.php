<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Events\CrmDomainEvent;
use App\Events\Interfaces\DomainEventInterface;
use Illuminate\Support\Str;

/**
 * Reusable Serializer that converts domain events into the platform event contract.
 */
class EventSerializer
{
    /**
     * Convert any DomainEvent into our platform event contract array format.
     */
    public function serialize(DomainEventInterface $event): array
    {
        if ($event instanceof CrmDomainEvent) {
            return [
                'event_id' => $event->getEventId(),
                'event_name' => $event->getEventName(),
                'event_version' => $event->getAggregateVersion(),
                'occurred_at' => $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
                'tenant' => [
                    'organization_id' => $event->getOrganizationId(),
                    'actor_id' => $event->getActorId(),
                ],
                'aggregate' => [
                    'type' => $event->getAggregateType(),
                    'id' => $event->getAggregateId(),
                    'version' => $event->getAggregateVersion(),
                ],
                'payload' => $event->getPayload(),
                'metadata' => array_merge([
                    'correlation_id' => $event->getCorrelationId(),
                    'causation_id' => $event->getCausationId(),
                ], $event->getMetadata()),
            ];
        }

        // Fallback for general non-CRM domain events
        return [
            'event_id' => method_exists($event, 'getEventId') ? $event->getEventId() : (string) Str::uuid7(),
            'event_name' => $event->getEventName(),
            'event_version' => 1,
            'occurred_at' => method_exists($event, 'getOccurredAt') ? $event->getOccurredAt()->format(\DateTimeInterface::ATOM) : now()->format(\DateTimeInterface::ATOM),
            'tenant' => [
                'organization_id' => $event->getOrganizationId(),
                'actor_id' => auth()->id(),
            ],
            'aggregate' => [
                'type' => 'unknown',
                'id' => 'unknown',
                'version' => 1,
            ],
            'payload' => $event->getPayload(),
            'metadata' => [
                'correlation_id' => (string) Str::uuid7(),
                'causation_id' => null,
            ],
        ];
    }
}
