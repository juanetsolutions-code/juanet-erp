<?php

use App\Events\CacheInvalidatedEvent;
use App\Services\Cache\CacheServiceInterface;
use App\Services\Cache\TenantCacheManagerInterface;
use App\Services\Cache\RedisRepositoryInterface;
use App\Services\Cache\CacheInvalidator;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

test('cache service can store, retrieve, and delete items', function () {
    $service = app(CacheServiceInterface::class);

    $service->put('test_key', 'hello_world', 60);
    expect($service->get('test_key'))->toBe('hello_world');

    $service->forget('test_key');
    expect($service->get('test_key'))->toBeNull();
});

test('cache service handles remember closures seamlessly', function () {
    $service = app(CacheServiceInterface::class);
    $service->forget('calculated_key');

    $calls = 0;
    $callback = function () use (&$calls) {
        $calls++;
        return 'computed_value';
    };

    $val1 = $service->remember('calculated_key', 60, $callback);
    $val2 = $service->remember('calculated_key', 60, $callback);

    expect($val1)->toBe('computed_value');
    expect($val2)->toBe('computed_value');
    expect($calls)->toBe(1); // Callback only runs once
});

test('tenant cache manager builds correct tenant-scoped keys', function () {
    $tenantContext = app(TenantContext::class);
    $manager = app(TenantCacheManagerInterface::class);

    // Default global state
    $manager->setTenantId(null);
    expect($manager->getTenantKey('search', 'query_abc'))->toBe('global:search:query_abc');

    // Scoped state
    $manager->setTenantId('tenant_123');
    expect($manager->getTenantKey('search', 'query_abc'))->toBe('tenant:tenant_123:search:query_abc');
});

test('dashboard cache stores under tenant prefix and allows user-specific invalidation', function () {
    $manager = app(TenantCacheManagerInterface::class);
    $manager->setTenantId('org_alpha');

    $called = 0;
    $data = $manager->rememberDashboard('user_99', function () use (&$called) {
        $called++;
        return ['widget_count' => 12];
    });

    expect($data)->toBe(['widget_count' => 12]);
    expect($called)->toBe(1);

    // Call again, should load from cache
    $data2 = $manager->rememberDashboard('user_99', function () use (&$called) {
        $called++;
        return ['widget_count' => 12];
    });

    expect($data2)->toBe(['widget_count' => 12]);
    expect($called)->toBe(1);

    // Invalidate
    $manager->invalidateDashboard('user_99');

    // Call again, should compute again
    $data3 = $manager->rememberDashboard('user_99', function () use (&$called) {
        $called++;
        return ['widget_count' => 15];
    });

    expect($data3)->toBe(['widget_count' => 15]);
    expect($called)->toBe(2);
});

test('cache invalidator dispatches events on invalidating different segments', function () {
    Event::fake();

    $invalidator = app(CacheInvalidator::class);

    $invalidator->invalidateUserDashboard('user_456', 'tenant_xyz');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'user_456:dashboard' && $event->tenantId === 'tenant_xyz' && $event->type === 'dashboard';
    });

    $invalidator->invalidateUserPermissions('user_456', 'tenant_xyz');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'user_456:permissions' && $event->tenantId === 'tenant_xyz' && $event->type === 'permissions';
    });

    $invalidator->invalidateOrganization('org_789');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'org_789' && $event->tenantId === 'org_789' && $event->type === 'organization';
    });

    $invalidator->invalidateSearch('tenant_xyz');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'search_index' && $event->tenantId === 'tenant_xyz' && $event->type === 'search';
    });

    $invalidator->invalidateConfig('mail_settings', 'tenant_xyz');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'config:mail_settings' && $event->tenantId === 'tenant_xyz' && $event->type === 'config';
    });

    $invalidator->invalidateFeature('beta_dashboard', 'tenant_xyz');
    Event::assertDispatched(CacheInvalidatedEvent::class, function ($event) {
        return $event->tagOrKey === 'feature:beta_dashboard' && $event->tenantId === 'tenant_xyz' && $event->type === 'feature';
    });
});

test('redis repository supports low-level structured data operations with fallback protection', function () {
    $redis = app(RedisRepositoryInterface::class);

    // 1. Hash operations
    $redis->hSet('enterprise:setting:1', 'max_users', '500');
    expect($redis->hGet('enterprise:setting:1', 'max_users'))->toBe('500');
    expect($redis->hGetAll('enterprise:setting:1'))->toBe(['max_users' => '500']);

    $redis->hDel('enterprise:setting:1', 'max_users');
    expect($redis->hGet('enterprise:setting:1', 'max_users'))->toBeNull();

    // 2. Set operations
    $redis->sAdd('enterprise:active_users', 'user_1', 'user_2');
    expect($redis->sIsMember('enterprise:active_users', 'user_1'))->toBeTrue();
    expect($redis->sIsMember('enterprise:active_users', 'user_3'))->toBeFalse();

    $redis->sRem('enterprise:active_users', 'user_1');
    expect($redis->sIsMember('enterprise:active_users', 'user_1'))->toBeFalse();

    // 3. Sorted Set operations
    $redis->zAdd('enterprise:leaderboard', 150.0, 'player_one');
    $redis->zAdd('enterprise:leaderboard', 350.0, 'player_two');
    
    $range = $redis->zRange('enterprise:leaderboard', 0, -1);
    expect($range)->toContain('player_one');
    expect($range)->toContain('player_two');
});
