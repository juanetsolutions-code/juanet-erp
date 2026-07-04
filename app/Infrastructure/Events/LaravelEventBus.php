<?php

namespace App\Infrastructure\Events;

use App\Contracts\EventBus;
use App\Events\Interfaces\DomainEventInterface;
use App\Services\EventBus\TransactionalOutboxInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class LaravelEventBus implements EventBus
{
    protected TransactionalOutboxInterface $outbox;
    protected static ?DomainEventInterface $currentEvent = null;

    public function __construct(TransactionalOutboxInterface $outbox)
    {
        $this->outbox = $outbox;
    }

    /**
     * Get the active event context (useful for tracing and parent propagation).
     */
    public static function getCurrentEvent(): ?DomainEventInterface
    {
        return self::$currentEvent;
    }

    /**
     * Set the active event context.
     */
    public static function setCurrentEvent(?DomainEventInterface $event): void
    {
        self::$currentEvent = $event;
    }

    /**
     * Dispatch an event immediately or queue it according to its type.
     */
    public function dispatch(DomainEventInterface $event): void
    {
        $startTime = microtime(true);
        $this->enrich($event);

        $handlerCount = 0;
        try {
            $eventClass = get_class($event);
            if (class_exists($eventClass)) {
                $handlerCount = count(Event::getListeners($eventClass));
            }
        } catch (Throwable $e) {
            // Safe fallback
        }

        try {
            $this->executeDispatch($event);
            $duration = microtime(true) - $startTime;
            $this->logDispatch($event, 'dispatch', $duration, $handlerCount);
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;
            $this->logDispatch($event, 'dispatch', $duration, $handlerCount, $e);
            throw $e;
        }
    }

    /**
     * Dispatch an event after the current database transaction commits successfully.
     */
    public function dispatchAfterCommit(DomainEventInterface $event): void
    {
        $startTime = microtime(true);
        $this->enrich($event);

        $handlerCount = 0;
        try {
            $eventClass = get_class($event);
            if (class_exists($eventClass)) {
                $handlerCount = count(Event::getListeners($eventClass));
            }
        } catch (Throwable $e) {
            // Safe fallback
        }

        DB::afterCommit(function () use ($event, $startTime, $handlerCount) {
            try {
                $this->executeDispatch($event);
                $duration = microtime(true) - $startTime;
                $this->logDispatch($event, 'after_commit', $duration, $handlerCount);
            } catch (Throwable $e) {
                $duration = microtime(true) - $startTime;
                $this->logDispatch($event, 'after_commit', $duration, $handlerCount, $e);
                throw $e;
            }
        });
    }

    /**
     * Dispatch multiple events.
     */
    public function dispatchMany(array $events): void
    {
        foreach ($events as $event) {
            if ($event instanceof DomainEventInterface) {
                $this->dispatch($event);
            }
        }
    }

    /**
     * Execute the raw dispatch strategy.
     */
    protected function executeDispatch(DomainEventInterface $event): void
    {
        $type = $event->getEventType();

        if ($type === 'immediate') {
            Event::dispatch($event);
        } else {
            // Stores the event in the outbox database, ensuring transactional consistency.
            // When OutboxPublisher publishes it, OutboxConsumer will call fireLocalEvent(), which fires Event::dispatch().
            $this->outbox->store($event);
        }
    }

    /**
     * Automatically enrich dispatched events with metadata.
     */
    protected function enrich(DomainEventInterface $event): void
    {
        $reflection = new ReflectionClass($event);

        // 1. Generate or retrieve Event UUID
        $eventId = null;
        if ($reflection->hasProperty('eventId')) {
            $prop = $reflection->getProperty('eventId');
            $prop->setAccessible(true);
            if (!$prop->isInitialized($event) || $prop->getValue($event) === null) {
                $eventId = (string) Str::uuid7();
                $prop->setValue($event, $eventId);
            } else {
                $eventId = $prop->getValue($event);
            }
        } else {
            $eventId = (string) Str::uuid7();
        }

        // 2. Correlation & Causation IDs
        $correlationId = null;
        $causationId = null;

        $currentEvent = self::getCurrentEvent();
        if ($currentEvent) {
            if (method_exists($currentEvent, 'getEventId')) {
                $causationId = $currentEvent->getEventId();
            }
            if (method_exists($currentEvent, 'getCorrelationId')) {
                $correlationId = $currentEvent->getCorrelationId();
            }
        }

        if (!$correlationId) {
            $correlationId = (string) Str::uuid7();
        }

        if ($reflection->hasProperty('correlationId')) {
            $prop = $reflection->getProperty('correlationId');
            $prop->setAccessible(true);
            if (!$prop->isInitialized($event) || $prop->getValue($event) === null) {
                $prop->setValue($event, $correlationId);
            } else {
                $correlationId = $prop->getValue($event);
            }
        }

        if ($causationId && $reflection->hasProperty('causationId')) {
            $prop = $reflection->getProperty('causationId');
            $prop->setAccessible(true);
            if (!$prop->isInitialized($event) || $prop->getValue($event) === null) {
                $prop->setValue($event, $causationId);
            }
        }

        // 3. Timestamp (UTC)
        $occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($reflection->hasProperty('occurredAt')) {
            $prop = $reflection->getProperty('occurredAt');
            $prop->setAccessible(true);
            if (!$prop->isInitialized($event) || $prop->getValue($event) === null) {
                $prop->setValue($event, $occurredAt);
            } else {
                $occurredAt = $prop->getValue($event);
            }
        }

        // 4. User ID (actorId)
        $userId = auth()->id() ?? 'system';
        if ($reflection->hasProperty('actorId')) {
            $prop = $reflection->getProperty('actorId');
            $prop->setAccessible(true);
            if (!$prop->isInitialized($event) || $prop->getValue($event) === null) {
                $prop->setValue($event, $userId);
            } else {
                $userId = $prop->getValue($event);
            }
        }

        // 5. Aggregate details
        $aggregateType = 'Generic';
        $aggregateId = 'unknown';
        $aggregateVersion = 1;

        if ($reflection->hasProperty('aggregateType')) {
            $prop = $reflection->getProperty('aggregateType');
            $prop->setAccessible(true);
            if ($prop->isInitialized($event) && $prop->getValue($event) !== null) {
                $aggregateType = $prop->getValue($event);
            }
        }
        if ($reflection->hasProperty('aggregateId')) {
            $prop = $reflection->getProperty('aggregateId');
            $prop->setAccessible(true);
            if ($prop->isInitialized($event) && $prop->getValue($event) !== null) {
                $aggregateId = $prop->getValue($event);
            }
        }
        if ($reflection->hasProperty('aggregateVersion')) {
            $prop = $reflection->getProperty('aggregateVersion');
            $prop->setAccessible(true);
            if ($prop->isInitialized($event) && $prop->getValue($event) !== null) {
                $aggregateVersion = $prop->getValue($event);
            }
        }

        // 6. Organization ID
        $orgId = $event->getOrganizationId();

        // 7. Source Context
        $sourceContext = $reflection->getNamespaceName();

        // Now enrich the payload if possible
        if ($reflection->hasProperty('payload')) {
            $prop = $reflection->getProperty('payload');
            $prop->setAccessible(true);
            $payload = $prop->getValue($event) ?? [];

            $enrichedMetadata = array_merge($payload['metadata'] ?? [], [
                'enriched_by_event_bus' => true,
                'source_context' => $sourceContext,
                'aggregate_version' => $aggregateVersion,
            ]);

            $payload = array_merge([
                'event_id' => $eventId,
                'occurred_at' => $occurredAt->format(\DateTimeInterface::ATOM),
                'organization_id' => $orgId,
                'actor_id' => $userId,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'aggregate_version' => $aggregateVersion,
                'correlation_id' => $correlationId,
                'causation_id' => $causationId,
            ], $payload);

            $payload['metadata'] = $enrichedMetadata;
            $prop->setValue($event, $payload);
        }
    }

    /**
     * Log the event dispatch with structured context.
     */
    protected function logDispatch(DomainEventInterface $event, string $mode, float $duration, int $handlerCount, ?Throwable $failure = null): void
    {
        $correlationId = null;
        if (method_exists($event, 'getCorrelationId')) {
            $correlationId = $event->getCorrelationId();
        }

        $logData = [
            'event_name' => $event->getEventName(),
            'event_class' => get_class($event),
            'event_type' => $event->getEventType(),
            'mode' => $mode,
            'correlation_id' => $correlationId,
            'causation_id' => method_exists($event, 'getCausationId') ? $event->getCausationId() : null,
            'event_id' => method_exists($event, 'getEventId') ? $event->getEventId() : null,
            'org_id' => $event->getOrganizationId(),
            'duration_ms' => round($duration * 1000, 2),
            'handler_count' => $handlerCount,
        ];

        if ($failure !== null) {
            $logData['status'] = 'failed';
            $logData['error'] = $failure->getMessage();
            Log::error("EventBus dispatch failed for event: {$event->getEventName()}", $logData);
        } else {
            $logData['status'] = 'success';
            Log::info("EventBus dispatched event: {$event->getEventName()}", $logData);
        }
    }
}
