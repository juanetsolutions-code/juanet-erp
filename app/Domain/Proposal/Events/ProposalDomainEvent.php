<?php

namespace App\Domain\Proposal\Events;

use App\Events\DomainEvent;
use Illuminate\Support\Str;
use DateTimeImmutable;
use DateTimeInterface;

abstract class ProposalDomainEvent extends DomainEvent
{
    protected string $eventId;
    protected DateTimeInterface $occurredAt;
    protected ?string $actorId;

    public function __construct(
        string $eventName,
        array $payload = [],
        ?string $organizationId = null,
        ?string $actorId = null
    ) {
        $this->eventId = (string) Str::uuid7();
        $this->occurredAt = new DateTimeImmutable();
        $this->actorId = $actorId ?? auth()->id() ?? 'visitor';

        $fullPayload = array_merge([
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'organization_id' => $organizationId,
            'actor_id' => $this->actorId,
        ], $payload);

        parent::__construct(
            $eventName,
            'queued',
            $fullPayload,
            $organizationId,
            'idemp_' . str_replace('.', '_', $eventName) . '_' . $this->eventId
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
}
