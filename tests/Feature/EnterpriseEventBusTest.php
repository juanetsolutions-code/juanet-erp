<?php

use App\Models\Organization;
use App\Models\EventOutbox;
use App\Models\EventDlq;
use App\Models\IdempotentKey;
use App\Events\ImmediateEvent;
use App\Events\QueuedEvent;
use App\Events\ScheduledEvent;
use App\Events\WebhookEvent;
use App\Events\InternalEvent;
use App\Services\EventBus\EventDispatcherInterface;
use App\Services\EventBus\OutboxPublisherInterface;
use App\Services\EventBus\DeadLetterQueueInterface;
use App\Services\EventBus\IdempotencyCheckerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('immediate events execute synchronously and bypass the outbox', function () {
    $triggered = false;
    
    Event::listen(ImmediateEvent::class, function (ImmediateEvent $event) use (&$triggered) {
        $triggered = true;
        expect($event->getPayload())->toBe(['foo' => 'bar']);
    });

    $event = new ImmediateEvent('test.immediate', ['foo' => 'bar']);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    expect($triggered)->toBeTrue();
    $this->assertDatabaseMissing('event_outboxes', ['event_name' => 'test.immediate']);
});

test('queued events are stored in the transactional outbox as pending', function () {
    $org = Organization::create([
        'name' => 'Event Bus Corp',
        'domain' => 'eventbus.com',
    ]);

    $event = new QueuedEvent('test.queued', ['data' => 'payload_val'], $org->id);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'test.queued',
        'event_type' => 'queued',
        'organization_id' => $org->id,
        'status' => 'pending',
    ]);
});

test('scheduled events are stored with correct run timestamp', function () {
    $runAt = now()->addHours(4);
    $event = new ScheduledEvent('test.scheduled', $runAt, ['key' => 'value']);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'test.scheduled',
        'event_type' => 'scheduled',
        'scheduled_at' => $runAt->toDateTimeString(),
    ]);
});

test('outbox publisher processes pending items and updates status to published', function () {
    $triggered = false;

    Event::listen(QueuedEvent::class, function (QueuedEvent $event) use (&$triggered) {
        $triggered = true;
        expect($event->getPayload())->toBe(['test' => 'it']);
    });

    $event = new QueuedEvent('test.queued.exec', ['test' => 'it']);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    $publisher = app(OutboxPublisherInterface::class);
    $count = $publisher->publishPending();

    expect($count)->toBe(1);
    expect($triggered)->toBeTrue();

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'test.queued.exec',
        'status' => 'published',
    ]);
});

test('failed outbox executions schedule exponential retries with updated status', function () {
    // Register a listener that throws an exception to simulate failure
    Event::listen(QueuedEvent::class, function (QueuedEvent $event) {
        if ($event->getEventName() === 'test.crashing') {
            throw new Exception("Listener runtime exception occurred.");
        }
    });

    $event = new QueuedEvent('test.crashing', ['foo' => 'bar']);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    $publisher = app(OutboxPublisherInterface::class);
    
    try {
        $publisher->publishPending();
    } catch (Throwable $e) {
        // Ignored as it is expected to throw internally on the worker execution
    }

    $outbox = EventOutbox::where('event_name', 'test.crashing')->first();
    
    expect($outbox->status)->toBe('failed');
    expect($outbox->attempts)->toBe(1);
    expect($outbox->scheduled_at)->not->toBeNull();
    expect($outbox->error_message)->toContain('Listener runtime exception occurred.');
});

test('exhausted retry failures move the outbox event to the dead letter queue', function () {
    Event::listen(QueuedEvent::class, function (QueuedEvent $event) {
        if ($event->getEventName() === 'test.exhaustion') {
            throw new Exception("Crash definitively.");
        }
    });

    $event = new QueuedEvent('test.exhaustion', ['crash' => true]);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    // Lock the item and force attempts to 4 of 5 so the next execution is the final retry
    $outbox = EventOutbox::where('event_name', 'test.exhaustion')->first();
    $outbox->update([
        'attempts' => 4,
        'max_attempts' => 5,
    ]);

    $publisher = app(OutboxPublisherInterface::class);
    
    try {
        $publisher->publishPending();
    } catch (Throwable $e) {
        // Ignored
    }

    // Event should be moved out of the outbox completely, and inside the DLQ instead
    $this->assertDatabaseMissing('event_outboxes', ['event_name' => 'test.exhaustion']);
    $this->assertDatabaseHas('event_dlqs', [
        'event_name' => 'test.exhaustion',
        'failure_reason' => 'Exhausted all 5 retry attempts. Error: Crash definitively.',
    ]);
});

test('idempotency checker prevents duplicate event listener executions', function () {
    $executionCount = 0;

    Event::listen(QueuedEvent::class, function (QueuedEvent $event) use (&$executionCount) {
        if ($event->getEventName() === 'test.idempotency.run') {
            $executionCount++;
        }
    });

    $dispatcher = app(EventDispatcherInterface::class);
    $publisher = app(OutboxPublisherInterface::class);

    // Dispatch two events sharing the identical idempotency key
    $event1 = new QueuedEvent('test.idempotency.run', ['foo' => 'bar'], null, 'unique_idempotency_key_abc');
    $event2 = new QueuedEvent('test.idempotency.run', ['foo' => 'bar'], null, 'unique_idempotency_key_abc');

    $dispatcher->dispatch($event1);
    $dispatcher->dispatch($event2);

    // Process both outbox items
    $publisher->publishPending();

    // Only one execution should have triggered the listener
    expect($executionCount)->toBe(1);

    // Verify both items in the outbox are marked published (second is handled as completed due to idempotency bypass)
    $outboxes = EventOutbox::where('event_name', 'test.idempotency.run')->get();
    expect($outboxes->count())->toBe(2);
    expect($outboxes[0]->status)->toBe('published');
    expect($outboxes[1]->status)->toBe('published');
});

test('webhook event fires standard HTTP signature secured POST request', function () {
    Http::fake();

    $event = new WebhookEvent('crm.lead.sync', 'https://api.externalpartner.com/leads', ['lead_id' => 'lead_123']);
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch($event);

    $publisher = app(OutboxPublisherInterface::class);
    $publisher->publishPending();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.externalpartner.com/leads'
            && $request->hasHeader('X-Juanet-Event', 'crm.lead.sync')
            && $request->hasHeader('X-Juanet-Signature')
            && $request['lead_id'] === 'lead_123';
    });
});

test('dlq replay moves event back to outbox and deletes DLQ entry', function () {
    $org = Organization::create([
        'name' => 'DLQ Admin Corp',
        'domain' => 'dlqadmin.com',
    ]);

    $dlq = EventDlq::create([
        'organization_id' => $org->id,
        'event_name' => 'crm.webhook.retrial',
        'event_type' => 'webhook',
        'payload' => ['item' => 're-do'],
        'failure_reason' => 'Connection timeout to recipient.'
    ]);

    $dlqService = app(DeadLetterQueueInterface::class);
    $dlqService->replay($dlq->id);

    // Replayed event must be deleted from DLQ, and restocked as pending in outbox
    $this->assertDatabaseMissing('event_dlqs', ['id' => $dlq->id]);
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'crm.webhook.retrial',
        'event_type' => 'webhook',
        'status' => 'pending',
        'attempts' => 0,
    ]);
});

class TestImmediateCrmEvent extends \App\Domain\CRM\Events\CrmDomainEvent
{
    public function __construct(\App\Domain\CRM\Models\Lead $lead)
    {
        parent::__construct(
            'test.crm.immediate',
            'immediate',
            $lead->organization_id,
            'Lead',
            $lead->id,
            1,
            ['some' => 'payload']
        );
    }
}

class TestQueuedCrmEvent extends \App\Domain\CRM\Events\CrmDomainEvent
{
    public function __construct(\App\Domain\CRM\Models\Lead $lead)
    {
        parent::__construct(
            'test.crm.queued',
            'queued',
            $lead->organization_id,
            'Lead',
            $lead->id,
            1,
            ['some' => 'payload']
        );
    }
}

test('event bus dependency injection and contract resolution works', function () {
    $eventBus = app(\App\Contracts\EventBus::class);
    expect($eventBus)->toBeInstanceOf(\App\Infrastructure\Events\LaravelEventBus::class);
});

test('event bus dispatches immediate events directly and logs dispatch', function () {
    $org = Organization::create([
        'name' => 'Test Corp',
        'domain' => 'test.com',
    ]);
    
    $lead = \App\Domain\CRM\Models\Lead::create([
        'organization_id' => $org->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 'new',
        'source' => 'web',
    ]);

    $triggered = false;
    Event::listen(TestImmediateCrmEvent::class, function (TestImmediateCrmEvent $event) use (&$triggered, $org, $lead) {
        $triggered = true;
        expect($event->getOrganizationId())->toBe($org->id);
        expect($event->getAggregateId())->toBe($lead->id);
        expect($event->getEventId())->not->toBeNull();
        expect($event->getCorrelationId())->not->toBeNull();
    });

    $event = new TestImmediateCrmEvent($lead);
    $eventBus = app(\App\Contracts\EventBus::class);
    $eventBus->dispatch($event);

    expect($triggered)->toBeTrue();
    $this->assertDatabaseMissing('event_outboxes', ['event_name' => 'test.crm.immediate']);
});

test('event bus enriches queued events and writes to outbox', function () {
    $org = Organization::create([
        'name' => 'Test Corp',
        'domain' => 'test.com',
    ]);
    
    $lead = \App\Domain\CRM\Models\Lead::create([
        'organization_id' => $org->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'new',
        'source' => 'web',
    ]);

    $event = new TestQueuedCrmEvent($lead);
    $eventBus = app(\App\Contracts\EventBus::class);
    $eventBus->dispatch($event);

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'test.crm.queued',
        'event_type' => 'queued',
        'organization_id' => $org->id,
        'status' => 'pending',
    ]);

    $outbox = EventOutbox::where('event_name', 'test.crm.queued')->first();
    expect($outbox->payload)->toHaveKey('event_id');
    expect($outbox->payload)->toHaveKey('correlation_id');
    expect($outbox->payload['actor_id'])->toBe('system');
    expect($outbox->payload['aggregate_type'])->toBe('Lead');
    expect($outbox->payload['aggregate_id'])->toBe($lead->id);
});

test('event bus after commit dispatch triggers on commit', function () {
    $org = Organization::create([
        'name' => 'Test Corp',
        'domain' => 'test.com',
    ]);
    
    $lead = \App\Domain\CRM\Models\Lead::create([
        'organization_id' => $org->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'new',
        'source' => 'web',
    ]);

    $triggered = false;
    Event::listen(TestImmediateCrmEvent::class, function (TestImmediateCrmEvent $event) use (&$triggered) {
        $triggered = true;
    });

    $event = new TestImmediateCrmEvent($lead);
    $eventBus = app(\App\Contracts\EventBus::class);

    \Illuminate\Support\Facades\DB::transaction(function () use ($eventBus, $event, &$triggered) {
        $eventBus->dispatchAfterCommit($event);
        expect($triggered)->toBeFalse(); // Should not trigger yet
    });

    expect($triggered)->toBeTrue(); // Should trigger after commit
});

test('event bus dispatchMany dispatches all events', function () {
    $org = Organization::create([
        'name' => 'Test Corp',
        'domain' => 'test.com',
    ]);
    
    $lead = \App\Domain\CRM\Models\Lead::create([
        'organization_id' => $org->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'new',
        'source' => 'web',
    ]);

    $triggeredCount = 0;
    Event::listen(TestImmediateCrmEvent::class, function (TestImmediateCrmEvent $event) use (&$triggeredCount) {
        $triggeredCount++;
    });

    $event1 = new TestImmediateCrmEvent($lead);
    $event2 = new TestImmediateCrmEvent($lead);

    $eventBus = app(\App\Contracts\EventBus::class);
    $eventBus->dispatchMany([$event1, $event2]);

    expect($triggeredCount)->toBe(2);
});

test('correlation and causation chains are propagated across dispatches', function () {
    $org = Organization::create([
        'name' => 'Test Corp',
        'domain' => 'test.com',
    ]);
    
    $lead = \App\Domain\CRM\Models\Lead::create([
        'organization_id' => $org->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'new',
        'source' => 'web',
    ]);

    $eventBus = app(\App\Contracts\EventBus::class);

    $parentEvent = new TestImmediateCrmEvent($lead);
    $eventBus->dispatch($parentEvent);

    // Set parent context
    \App\Infrastructure\Events\LaravelEventBus::setCurrentEvent($parentEvent);

    $childEvent = new TestImmediateCrmEvent($lead);
    $eventBus->dispatch($childEvent);

    expect($childEvent->getCausationId())->toBe($parentEvent->getEventId());
    expect($childEvent->getCorrelationId())->toBe($parentEvent->getCorrelationId());

    // Reset parent context
    \App\Infrastructure\Events\LaravelEventBus::setCurrentEvent(null);
});
