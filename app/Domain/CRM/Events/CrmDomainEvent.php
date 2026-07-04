<?php

namespace App\Domain\CRM\Events;

use App\Events\DomainEvent;
use Illuminate\Support\Str;
use DateTimeImmutable;
use DateTimeInterface;

abstract class CrmDomainEvent extends DomainEvent
{
    protected string $eventId;
    protected DateTimeInterface $occurredAt;
    protected ?string $actorId;
    protected string $aggregateType;
    protected string $aggregateId;
    protected int $aggregateVersion;
    protected array $metadata;
    protected ?string $correlationId;
    protected ?string $causationId;

    public function __construct(
        string $eventName,
        string $eventType,
        ?string $organizationId,
        string $aggregateType,
        string $aggregateId,
        int $aggregateVersion = 1,
        array $payload = [],
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $scheduledAt = null,
        ?string $webhookUrl = null
    ) {
        $this->eventId = (string) Str::uuid7();
        $this->occurredAt = new DateTimeImmutable();
        $this->actorId = $actorId ?? auth()->id() ?? 'system';
        $this->aggregateType = $aggregateType;
        $this->aggregateId = $aggregateId;
        $this->aggregateVersion = $aggregateVersion;
        $this->metadata = $metadata;
        $this->correlationId = $correlationId ?? (string) Str::uuid7();
        $this->causationId = $causationId;

        // Automatically merge domain contract fields into the payload for maximum visibility
        $fullPayload = array_merge([
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'organization_id' => $organizationId,
            'actor_id' => $this->actorId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'metadata' => $metadata,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
        ], $payload);

        parent::__construct(
            $eventName,
            $eventType,
            $fullPayload,
            $organizationId,
            $idempotencyKey ?? ('idemp_' . str_replace('.', '_', $eventName) . '_' . $aggregateId . '_' . $this->eventId),
            $scheduledAt,
            $webhookUrl
        );
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getOccurredAt(): DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getCausationId(): ?string
    {
        return $this->causationId;
    }
}
