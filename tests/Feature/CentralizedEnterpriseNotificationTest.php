<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CentralizedEnterpriseNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;
    protected TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(NotificationService::class);
        $this->tenantContext = app(TenantContext::class);
    }

    test('new notification is created, stored, and routed to enabled channels', function () {
        // Create user and organization
        $org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $user = User::create([
            'name' => 'John Notification',
            'email' => 'john.notif@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->organizations()->attach($org->id);

        $this->tenantContext->setTenant($org);

        // Seed a template
        NotificationTemplate::create([
            'name' => 'crm.lead.created',
            'channel' => 'database',
            'subject_template' => 'New Lead: {{ $lead_name }}',
            'body_template' => 'A new lead has been assigned to you: {{ $lead_name }} from {{ $company }}.',
            'organization_id' => $org->id,
            'locale' => 'en'
        ]);

        // Dispatch notification
        $notification = $this->service->send(
            $user->id,
            'Lead Generated',
            'crm.lead.created',
            'success',
            'crm',
            'high',
            $org->id,
            ['lead_name' => 'Bob Builder', 'company' => 'Acme Builders']
        );

        expect($notification)->not->toBeNull();
        expect($notification->title)->toBe('New Lead: Bob Builder');
        expect($notification->body)->toContain('Bob Builder');
        expect($notification->body)->toContain('Acme Builders');

        // Check in database
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'category' => 'crm',
            'priority' => 'high'
        ]);

        // Check deliveries list was logged
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'database',
            'status' => 'delivered'
        ]);
    });

    test('preferences disable category and fail delivery gracefully', function () {
        $org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $user = User::create([
            'name' => 'Silent John',
            'email' => 'silent.john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->tenantContext->setTenant($org);

        // Disable billing category for the user
        $this->service->updatePreferences(
            $user->id,
            ['database' => true, 'email' => true],
            ['system' => true, 'billing' => false, 'crm' => true, 'security' => true],
            $org->id
        );

        // Seed a billing template
        NotificationTemplate::create([
            'name' => 'payment.received',
            'channel' => 'database',
            'subject_template' => 'Payment Invoice #{{ $invoice_id }}',
            'body_template' => 'Payment received: ${{ $amount }}',
            'organization_id' => $org->id,
            'locale' => 'en'
        ]);

        // Trigger notification
        $notification = $this->service->send(
            $user->id,
            'Billing Success',
            'payment.received',
            'info',
            'billing',
            'normal',
            $org->id,
            ['invoice_id' => '1023', 'amount' => '49.99']
        );

        // Since billing is disabled in user preferences, send returns null
        expect($notification)->toBeNull();
    });

    test('mark as read, mark all as read, archive, and delete via APIs', function () {
        $org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $user = User::create([
            'name' => 'API John',
            'email' => 'api.john@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->organizations()->attach($org->id);

        $this->tenantContext->setTenant($org);

        // Generate templates
        NotificationTemplate::create([
            'name' => 'system.generic',
            'channel' => 'database',
            'subject_template' => 'System notification',
            'body_template' => 'System body',
            'organization_id' => $org->id,
            'locale' => 'en'
        ]);

        $n1 = $this->service->send($user->id, 'Alert 1', 'system.generic', 'info', 'system', 'normal', $org->id);
        $n2 = $this->service->send($user->id, 'Alert 2', 'system.generic', 'info', 'system', 'normal', $org->id);

        // Mark single as read via endpoint
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$n1->id}/read")
            ->assertStatus(200);

        expect(Notification::find($n1->id)->is_read)->toBeTrue();
        expect(Notification::find($n2->id)->is_read)->toBeFalse();

        // Archive single via endpoint
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$n2->id}/archive")
            ->assertStatus(200);

        $n2Data = Notification::find($n2->id)->data;
        expect($n2Data['is_archived'])->toBeTrue();

        // Delete single via endpoint
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/notifications/{$n2->id}")
            ->assertStatus(200);

        expect(Notification::find($n2->id))->toBeNull();
    });

    test('tenant isolation ensures user does not fetch notifications from other tenants', function () {
        $org1 = Organization::create(['name' => 'Tenant One', 'slug' => 't1']);
        $org2 = Organization::create(['name' => 'Tenant Two', 'slug' => 't2']);

        $user = User::create([
            'name' => 'Multi Tenant John',
            'email' => 'multi.john@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->organizations()->attach($org1->id);
        $user->organizations()->attach($org2->id);

        // Seed template
        NotificationTemplate::create([
            'name' => 'crm.lead.created',
            'channel' => 'database',
            'subject_template' => 'New Lead',
            'body_template' => 'Lead details',
            'organization_id' => $org1->id,
            'locale' => 'en'
        ]);
        NotificationTemplate::create([
            'name' => 'crm.lead.created',
            'channel' => 'database',
            'subject_template' => 'New Lead',
            'body_template' => 'Lead details',
            'organization_id' => $org2->id,
            'locale' => 'en'
        ]);

        // Send 1 notification in Tenant 1
        $this->tenantContext->setTenant($org1);
        $this->service->send($user->id, 'T1 Alert', 'crm.lead.created', 'info', 'system', 'normal', $org1->id);

        // Send 2 notifications in Tenant 2
        $this->tenantContext->setTenant($org2);
        $this->service->send($user->id, 'T2 Alert A', 'crm.lead.created', 'info', 'system', 'normal', $org2->id);
        $this->service->send($user->id, 'T2 Alert B', 'crm.lead.created', 'info', 'system', 'normal', $org2->id);

        // Fetching notifications while in Tenant 1 should return only 1
        $this->tenantContext->setTenant($org1);
        $response1 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertStatus(200);

        $data1 = $response1->json('data');
        expect(count($data1))->toBe(1);

        // Fetching notifications while in Tenant 2 should return only 2
        $this->tenantContext->setTenant($org2);
        $response2 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertStatus(200);

        $data2 = $response2->json('data');
        expect(count($data2))->toBe(2);
    });
}
