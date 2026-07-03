<?php

namespace App\Events;

use App\Events\Interfaces\DomainEventInterface;
use DateTimeInterface;

abstract class DomainEvent implements DomainEventInterface
{
    protected string $eventName;
    protected string $eventType;
    protected array $payload;
    protected ?string $organizationId;
    protected ?string $idempotencyKey;
    protected ?DateTimeInterface $scheduledAt;
    protected ?string $webhookUrl;

    public function __construct(
        string $eventName,
        string $eventType,
        array $payload = [],
        ?string $organizationId = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $scheduledAt = null,
        ?string $webhookUrl = null
    ) {
        $this->eventName = $eventName;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->organizationId = $organizationId;
        $this->idempotencyKey = $idempotencyKey;
        $this->scheduledAt = $scheduledAt;
        $this->webhookUrl = $webhookUrl;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function getScheduledAt(): ?DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }
}
