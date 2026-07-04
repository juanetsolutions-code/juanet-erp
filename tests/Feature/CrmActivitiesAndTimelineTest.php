<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Models\StoredFile;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\Models\ActivityNote;
use App\Domain\CRM\Activities\Models\ActivityReminder;
use App\Domain\CRM\Activities\Models\ActivityAttachment;
use App\Domain\CRM\Activities\Services\ActivityService;
use App\Domain\CRM\Activities\Services\TimelineEngine;
use App\Domain\CRM\Activities\Services\ReminderQueueManager;
use App\Domain\CRM\Activities\DTO\ActivityData;
use App\Domain\CRM\Activities\Enums\ActivityType;
use App\Domain\CRM\Activities\Enums\ActivityPriority;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class CrmActivitiesAndTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Organization $organization;
    protected Organization $otherOrganization;
    protected TenantContext $tenantContext;
    protected ActivityService $activityService;
    protected TimelineEngine $timelineEngine;
    protected ReminderQueueManager $reminderManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);
        $this->activityService = app(ActivityService::class);
        $this->timelineEngine = app(TimelineEngine::class);
        $this->reminderManager = app(ReminderQueueManager::class);

        // 1. Setup Tenant organization
        $this->organization = Organization::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'First Acme Corp',
            'domain' => 'first.acme.io',
            'status' => 'active',
        ]);

        $this->otherOrganization = Organization::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Second Acme Corp',
            'domain' => 'second.acme.io',
            'status' => 'active',
        ]);

        // 2. Setup users
        $this->user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'First Admin',
            'email' => 'admin@first.acme.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->otherUser = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Second Admin',
            'email' => 'admin@second.acme.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        // Organization memberships
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'is_owner' => true, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->otherOrganization->id, 'user_id' => $this->otherUser->id, 'is_owner' => true, 'status' => 'active']);

        // Roles and permissions setup
        $role = Role::create([
            'name' => 'Tenant Admin',
            'slug' => 'tenant-admin',
            'organization_id' => $this->organization->id,
        ]);

        $permissions = [
            'view_activities', 'create_activities', 'update_activities', 'delete_activities',
        ];

        foreach ($permissions as $slug) {
            $permission = Permission::create([
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'slug' => $slug,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $this->user->roles()->attach($role->id, ['organization_id' => $this->organization->id]);

        $this->tenantContext->setTenant($this->organization);
    }

    public function test_create_and_update_polymorphic_activity_with_repository_and_service()
    {
        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Polymorphic Customer',
            'email' => 'poly@cust.com',
            'status' => 'new',
        ]);

        $data = ActivityData::fromArray([
            'type' => ActivityType::PHONE_CALL,
            'subject' => 'Introduction call',
            'description' => 'Introducing JUANET SaaS and scoping needs.',
            'loggable_type' => Lead::class,
            'loggable_id' => $lead->id,
            'priority' => ActivityPriority::HIGH,
            'due_at' => Carbon::now()->addDay(),
        ], $this->organization->id);

        $activity = $this->activityService->createActivity($data);

        $this->assertDatabaseHas('crm_activities', [
            'id' => $activity->id,
            'subject' => 'Introduction call',
            'loggable_type' => Lead::class,
            'loggable_id' => $lead->id,
        ]);

        // Complete the activity
        $this->activityService->completeActivity($activity);

        $this->assertTrue($activity->fresh()->is_completed);
        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_rich_notes_versioning_and_threading()
    {
        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Lead with Notes',
            'email' => 'notes@cust.com',
            'status' => 'new',
        ]);

        // Add Note V1
        $note = $this->activityService->addNote(
            Lead::class,
            $lead->id,
            'Initial scope discussed.',
            $this->organization->id,
            $this->user->id
        );

        $this->assertDatabaseHas('crm_activity_notes', [
            'id' => $note->id,
            'content' => 'Initial scope discussed.',
            'version' => 1,
        ]);

        // Update Note to V2
        $noteV2 = $this->activityService->updateNote($note, 'Updated scope discussion notes.', $this->user->id);

        // Note V1 should be soft-deleted to keep history but hide from active list
        $this->assertTrue($note->fresh()->trashed());

        // Note V2 should exist with version = 2 and link to V1
        $this->assertDatabaseHas('crm_activity_notes', [
            'id' => $noteV2->id,
            'content' => 'Updated scope discussion notes.',
            'version' => 2,
            'original_note_id' => $note->id,
        ]);
    }

    public function test_activity_attachments()
    {
        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Lead with File',
            'email' => 'file@cust.com',
            'status' => 'new',
        ]);

        $activity = $this->activityService->createActivity(ActivityData::fromArray([
            'type' => ActivityType::FILE_ATTACHMENT,
            'subject' => 'Upload contract spec',
        ], $this->organization->id));

        $storedFile = StoredFile::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'name' => 'contract.pdf',
            'path' => 'files/contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $attachment = $this->activityService->addAttachment($activity, $storedFile->id, $this->user->id);

        $this->assertDatabaseHas('crm_activity_attachments', [
            'id' => $attachment->id,
            'activity_id' => $activity->id,
            'stored_file_id' => $storedFile->id,
        ]);
    }

    public function test_reminder_scheduling_and_processing()
    {
        $activity = $this->activityService->createActivity(ActivityData::fromArray([
            'type' => ActivityType::MEETING,
            'subject' => 'Onboarding Demo',
        ], $this->organization->id));

        $remindAt = Carbon::now()->subMinutes(5); // already due
        $reminder = $this->activityService->addReminder(
            $activity,
            'Onboarding Demo Reminder',
            $remindAt,
            'in_app',
            'Starting soon',
            ['frequency' => 'daily', 'interval' => 1],
            $this->user->id
        );

        $this->assertDatabaseHas('crm_activity_reminders', [
            'id' => $reminder->id,
            'is_sent' => false,
        ]);

        // Process queue
        $processed = $this->reminderManager->processReminders();

        $this->assertEquals(1, $processed->count());
        $this->assertTrue($reminder->fresh()->is_sent);

        // Next recurrence should be scheduled (daily)
        $this->assertDatabaseHas('crm_activity_reminders', [
            'title' => 'Onboarding Demo Reminder',
            'is_sent' => false,
            'remind_at' => $remindAt->addDay()->toDateTimeString(),
        ]);
    }

    public function test_tenant_isolation_on_timeline_queries()
    {
        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Tenant 1 Lead',
            'email' => 't1@cust.com',
            'status' => 'new',
        ]);

        $this->activityService->createActivity(ActivityData::fromArray([
            'type' => ActivityType::MEETING,
            'subject' => 'Meeting for Org 1',
            'loggable_type' => Lead::class,
            'loggable_id' => $lead->id,
        ], $this->organization->id));

        // Fetch timeline as Organization 1
        $timeline = $this->timelineEngine->getTimeline(Lead::class, $lead->id, $this->organization->id);
        $this->assertEquals(1, $timeline->total());

        // Querying with Organization 2 should yield empty results (Isolation)
        $timelineIsolated = $this->timelineEngine->getTimeline(Lead::class, $lead->id, $this->otherOrganization->id);
        $this->assertEquals(0, $timelineIsolated->total());
    }
}
