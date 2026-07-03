<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\NotificationServiceInterface;
use App\Events\NotificationSentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as LaravelNotification;

uses(RefreshDatabase::class);

test('notification service creates a database record and fires real-time event', function () {
    Event::fake();

    $user = User::create([
        'name' => 'Jane Notification',
        'email' => 'jane.notif@example.com',
        'password' => 'password123',
    ]);

    $service = app(NotificationServiceInterface::class);

    $notification = $service->send(
        $user->id,
        'Server Overheating Warning',
        'Node 4 is reporting temperatures above normal operating threshold.',
        'warning',
        'system',
        'high'
    );

    expect($notification)->not->toBeNull();
    expect($notification->title)->toBe('Server Overheating Warning');
    expect($notification->is_read)->toBeFalse();

    $this->assertDatabaseHas('notifications', [
        'id' => $notification->id,
        'user_id' => $user->id,
        'title' => 'Server Overheating Warning',
    ]);

    Event::assertDispatched(NotificationSentEvent::class);
});

test('user preferences disable specific channels or categories', function () {
    $user = User::create([
        'name' => 'Silent User',
        'email' => 'silent@example.com',
        'password' => 'password123',
    ]);

    $service = app(NotificationServiceInterface::class);

    // Disable system category
    $service->updatePreferences(
        $user->id,
        ['database' => true, 'email' => true, 'toast' => true],
        ['system' => false, 'billing' => true, 'crm' => true, 'security' => true]
    );

    // Attempt to send a system notification
    $notification = $service->send(
        $user->id,
        'System Upgraded',
        'The core engine has been successfully upgraded to v12.',
        'info',
        'system'
    );

    // Should return null and NOT save to db since category is disabled
    expect($notification)->toBeNull();
    $this->assertDatabaseMissing('notifications', [
        'user_id' => $user->id,
        'title' => 'System Upgraded',
    ]);
});

test('marking notifications as read works correctly', function () {
    $user = User::create([
        'name' => 'Reader User',
        'email' => 'reader@example.com',
        'password' => 'password123',
    ]);

    $service = app(NotificationServiceInterface::class);

    $notif = $service->send($user->id, 'Alert 1', 'Detail 1');
    $notif2 = $service->send($user->id, 'Alert 2', 'Detail 2');

    expect($notif->is_read)->toBeFalse();

    $service->markAsRead($notif->id);

    expect(Notification::find($notif->id)->is_read)->toBeTrue();
    expect(Notification::find($notif2->id)->is_read)->toBeFalse();

    $service->markAllAsRead($user->id);

    expect(Notification::find($notif2->id)->is_read)->toBeTrue();
});

test('api endpoints can retrieve, read, and manage notifications', function () {
    $user = User::create([
        'name' => 'API Tester',
        'email' => 'api.tester@example.com',
        'password' => 'password123',
    ]);

    $service = app(NotificationServiceInterface::class);
    $notif = $service->send($user->id, 'API Title', 'API Body');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonFragment(['title' => 'API Title']);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/notifications/{$notif->id}/read")
        ->assertStatus(200);

    expect(Notification::find($notif->id)->is_read)->toBeTrue();
});
