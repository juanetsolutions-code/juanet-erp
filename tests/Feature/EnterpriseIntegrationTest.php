<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\SearchablePlaceholder;
use App\Models\StoredFile;
use App\Models\SearchIndex;
use App\Models\EventOutbox;
use App\Models\Setting;
use App\Models\FeatureFlag;
use App\Services\ActivityLogServiceInterface;
use App\Services\NotificationServiceInterface;
use App\Services\SearchServiceInterface;
use App\Services\EventBus\EventDispatcherInterface;
use App\Services\EventBus\OutboxPublisherInterface;
use App\Services\Cache\TenantCacheManagerInterface;
use App\Services\Cache\CacheInvalidator;
use App\Services\Configuration\ConfigurationServiceInterface;
use App\Helpers\MoneyHelper;
use App\Helpers\DateHelper;
use App\Helpers\UuidHelper;
use App\Helpers\ResponseBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use App\Events\QueuedEvent;

uses(RefreshDatabase::class);

test('full-stack infrastructure integration and e2e lifecycle validation', function () {
    // -------------------------------------------------------------------------
    // 0. Bootstrap tenant context and user profiles
    // -------------------------------------------------------------------------
    $org = Organization::create([
        'name' => 'JUANET Enterprise HQ',
        'domain' => 'hq.juanet.enterprise',
    ]);

    $user = User::create([
        'name' => 'Lead Architect',
        'email' => 'architect@juanet.enterprise',
        'password' => bcrypt('super-secret-pass-2026'),
    ]);

    // -------------------------------------------------------------------------
    // 1. Verify Logging Service & Audit Logging Integration
    // -------------------------------------------------------------------------
    $activityService = app(ActivityLogServiceInterface::class);
    $log = $activityService->log('core_verification', 'Starting end-to-end integration checklist', 'system', $user->id, $org->id);

    expect($log->id)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', [
        'id' => $log->id,
        'action' => 'core_verification',
        'organization_id' => $org->id,
    ]);

    // Verify Eloquent auditable lifecycle hook is wired up
    $user->name = 'Chief Architect';
    $user->save();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'event' => 'updated',
    ]);

    // -------------------------------------------------------------------------
    // 2. Verify Notifications & Real-Time Broadcast Integration
    // -------------------------------------------------------------------------
    Event::fake([App\Events\NotificationSentEvent::class]);

    $notificationService = app(NotificationServiceInterface::class);
    $notification = $notificationService->sendNotification(
        $user->id,
        'Infrastructure Alert',
        'All Phase 3.5 systems are online and integrated.',
        'alert',
        'high',
        $org->id
    );

    expect($notification->id)->not->toBeNull();
    $this->assertDatabaseHas('notifications', [
        'id' => $notification->id,
        'title' => 'Infrastructure Alert',
        'priority' => 'high',
    ]);

    Event::assertDispatched(App\Events\NotificationSentEvent::class, function ($event) use ($notification) {
        return $event->notification->id === $notification->id;
    });

    // -------------------------------------------------------------------------
    // 3. Verify Cloud Storage, Database Files, and Observers
    // -------------------------------------------------------------------------
    Storage::fake('local_disk');

    $storedFile = StoredFile::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'name' => 'architecture-blueprint.pdf',
        'path' => 'blueprints/architecture-blueprint.pdf',
        'disk' => 'local_disk',
        'mime_type' => 'application/pdf',
        'size' => 1024 * 1024,
        'visibility' => 'private',
    ]);

    // Place dummy file in storage disk
    Storage::disk('local_disk')->put('blueprints/architecture-blueprint.pdf', 'dummy content');
    expect(Storage::disk('local_disk')->exists('blueprints/architecture-blueprint.pdf'))->toBeTrue();

    // Delete model to trigger StoredFileObserver
    $storedFile->delete();

    // Verify database record is soft-deleted or removed, and physical file is deleted by observer
    $this->assertDatabaseMissing('stored_files', ['id' => $storedFile->id]);
    expect(Storage::disk('local_disk')->exists('blueprints/architecture-blueprint.pdf'))->toBeFalse();

    // -------------------------------------------------------------------------
    // 4. Verify Global Search & Automated Indexing Lifecycle
    // -------------------------------------------------------------------------
    $lead = SearchablePlaceholder::create([
        'organization_id' => $org->id,
        'name' => 'Bento SaaS Lead',
        'email' => 'bento@juanet.enterprise',
        'status' => 'new',
    ]);

    // Verify search index was automatically created through the Searchable model trait
    $this->assertDatabaseHas('search_indexes', [
        'searchable_type' => SearchablePlaceholder::class,
        'searchable_id' => $lead->id,
        'organization_id' => $org->id,
        'title' => 'Bento SaaS Lead',
    ]);

    $searchService = app(SearchServiceInterface::class);
    $searchResults = $searchService->search('Bento', [], $org->id);
    expect($searchResults->count())->toBe(1);
    expect($searchResults->first()->title)->toBe('Bento SaaS Lead');

    // -------------------------------------------------------------------------
    // 5. Verify Transactional Outbox & Event Dispatcher Integration
    // -------------------------------------------------------------------------
    $eventBus = app(EventDispatcherInterface::class);
    $outboxPublisher = app(OutboxPublisherInterface::class);

    $testEvent = new QueuedEvent('integration.test.job', ['metric' => 'stable'], $org->id, 'idempotency-key-e2e');
    $eventBus->dispatch($testEvent);

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'integration.test.job',
        'status' => 'pending',
        'idempotency_key' => 'idempotency-key-e2e',
    ]);

    // Listen for the custom event trigger during outbox consumption
    $dispatchedLocally = false;
    Event::listen(QueuedEvent::class, function (QueuedEvent $e) use (&$dispatchedLocally) {
        if ($e->getEventName() === 'integration.test.job') {
            $dispatchedLocally = true;
            expect($e->getPayload())->toBe(['metric' => 'stable']);
        }
    });

    $processedCount = $outboxPublisher->publishPending();
    expect($processedCount)->toBe(1);
    expect($dispatchedLocally)->toBeTrue();

    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'integration.test.job',
        'status' => 'published',
    ]);

    // -------------------------------------------------------------------------
    // 6. Verify Enterprise Cache Managers & CacheInvalidator
    // -------------------------------------------------------------------------
    $cacheManager = app(TenantCacheManagerInterface::class);
    $cacheInvalidator = app(CacheInvalidator::class);

    // Cache a dashboard state for user
    $cacheManager->setTenantId($org->id)->cacheDashboard($user->id, ['widgets' => 4]);
    $cachedData = $cacheManager->setTenantId($org->id)->getDashboard($user->id);
    expect($cachedData)->toBe(['widgets' => 4]);

    // Invalidate Cache using standard invalidate pattern
    $cacheInvalidator->invalidateUserDashboard($user->id, $org->id);
    $clearedData = $cacheManager->setTenantId($org->id)->getDashboard($user->id);
    expect($clearedData)->toBeNull();

    // -------------------------------------------------------------------------
    // 7. Verify Settings, Organization Overrides, and Feature Flags
    // -------------------------------------------------------------------------
    $configService = app(ConfigurationServiceInterface::class);

    // Global default configuration setting
    Setting::create([
        'key' => 'system.timezone',
        'value' => 'UTC',
        'type' => 'string',
        'is_public' => true,
    ]);

    // Organizational override setting
    Setting::create([
        'key' => 'system.timezone',
        'value' => 'Africa/Nairobi',
        'type' => 'string',
        'organization_id' => $org->id,
        'is_public' => true,
    ]);

    $resolvedTimezone = $configService->get('system.timezone', 'UTC', $org->id);
    expect($resolvedTimezone)->toBe('Africa/Nairobi');

    // Test feature flags
    FeatureFlag::create([
        'key' => 'beta_payment_analytics',
        'name' => 'Beta Payment Analytics',
        'status' => 'beta',
        'rules' => ['users' => [$user->id]],
    ]);

    $flagActive = $configService->isFeatureActive('beta_payment_analytics', $user->id, $org->id);
    expect($flagActive)->toBeTrue();

    // -------------------------------------------------------------------------
    // 8. Verify Reusable Enterprise Helpers and Utilities
    // -------------------------------------------------------------------------
    // MoneyHelper
    $formattedMoney = MoneyHelper::format(500000.75, 'KES');
    expect($formattedMoney)->toBe('KES 500,000.75');

    // DateHelper
    $timeago = DateHelper::timeAgo(now()->subHours(2));
    expect($timeago)->toContain('hours ago');

    // UuidHelper
    $v7 = UuidHelper::v7();
    expect(UuidHelper::isValid($v7))->toBeTrue();

    // ResponseBuilder
    $response = ResponseBuilder::success(['data' => 'ok'], 'Enterprise service check passed');
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toBe([
        'success' => true,
        'message' => 'Enterprise service check passed',
        'data' => ['data' => 'ok'],
    ]);
});
