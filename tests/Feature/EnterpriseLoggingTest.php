<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\ExceptionLog;
use App\Services\ActivityLogServiceInterface;
use App\Services\AuditLogServiceInterface;
use App\Services\SecurityLogServiceInterface;
use App\Services\ExceptionLogServiceInterface;
use App\Jobs\LogActivityJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;

uses(RefreshDatabase::class);

test('activity log service registers properly and saves to db', function () {
    $service = app(ActivityLogServiceInterface::class);
    $log = $service->log('test_action', 'This is a test activity', 'billing');

    expect($log->id)->not->toBeNull();
    expect($log->action)->toBe('test_action');
    expect($log->description)->toBe('This is a test activity');
    expect($log->module)->toBe('billing');
    expect($log->version)->toBe(1);

    $this->assertDatabaseHas('activity_logs', [
        'id' => $log->id,
        'action' => 'test_action',
    ]);
});

test('audit log service automatically logs created/updated/deleted events of models', function () {
    $org = Organization::create([
        'name' => 'Acme Holding Corp',
        'domain' => 'acmeholding.com',
    ]);

    // Should automatically trigger EloquentAuditListener and create an AuditLog
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Organization::class,
        'auditable_id' => $org->id,
        'event' => 'created',
    ]);

    // Update organization
    $org->name = 'Acme Global Holding Corp';
    $org->save();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Organization::class,
        'auditable_id' => $org->id,
        'event' => 'updated',
    ]);

    // Check old and new values are tracked
    $lastAudit = AuditLog::where('auditable_id', $org->id)->where('event', 'updated')->first();
    expect($lastAudit->old_values['name'])->toBe('Acme Holding Corp');
    expect($lastAudit->new_values['name'])->toBe('Acme Global Holding Corp');
});

test('security log service logs failed login and password change events', function () {
    $user = User::create([
        'name' => 'John Security',
        'email' => 'john.sec@example.com',
        'password' => 'initial_secure_pass_123',
    ]);

    // Simulate password change
    $user->password = 'new_highly_secure_pass_789';
    $user->save();

    // Verify security log for password change
    $this->assertDatabaseHas('security_logs', [
        'user_id' => $user->id,
        'event_type' => 'password_change',
        'severity' => 'warning',
    ]);

    // Fire Failed login event
    event(new Failed('web', $user, ['email' => 'john.sec@example.com']));

    $this->assertDatabaseHas('security_logs', [
        'event_type' => 'failed_login',
        'severity' => 'warning',
    ]);
});

test('exception log service logs standard system exceptions', function () {
    $service = app(ExceptionLogServiceInterface::class);
    $exception = new \Exception('SaaS Database Timeout', 503);

    $log = $service->log($exception);

    expect($log->id)->not->toBeNull();
    expect($log->exception_class)->toBe(\Exception::class);
    expect($log->message)->toBe('SaaS Database Timeout');

    $this->assertDatabaseHas('exception_logs', [
        'id' => $log->id,
        'message' => 'SaaS Database Timeout',
    ]);
});

test('logging infrastructure supports queueing when configured', function () {
    Queue::fake();

    config(['logging.enterprise.queue' => true]);

    $service = app(ActivityLogServiceInterface::class);
    $service->log('queued_action', 'This should be queued');

    Queue::assertDispatched(LogActivityJob::class);
});

test('optimistic locking is enforced on all log models', function () {
    $service = app(ActivityLogServiceInterface::class);
    $log = $service->log('conflict_action', 'Lock testing');

    $instance1 = ActivityLog::find($log->id);
    $instance2 = ActivityLog::find($log->id);

    $instance1->description = 'Updated Description 1';
    $instance1->save();

    expect($instance1->version)->toBe(2);

    $this->expectException(\RuntimeException::class);
    $instance2->description = 'Updated Description 2';
    $instance2->save();
});
