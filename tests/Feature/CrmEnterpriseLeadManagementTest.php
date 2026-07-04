<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\CustomField;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Models\LeadStatusHistory;
use App\Domain\CRM\Models\LeadAssignmentHistory;
use App\Domain\CRM\Services\LeadService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmEnterpriseLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $salesRepA;
    protected User $salesRepB;
    protected Organization $organization;
    protected TenantContext $tenantContext;
    protected LeadService $leadService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);
        $this->leadService = app(LeadService::class);

        // 1. Setup Tenant organization
        $this->organization = Organization::create([
            'name' => 'Acme Corporation',
            'domain' => 'acme.io',
            'status' => 'active',
        ]);

        // 2. Setup users
        $this->user = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin@acme.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->salesRepA = User::create([
            'name' => 'Sales Rep A',
            'email' => 'repa@acme.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->salesRepB = User::create([
            'name' => 'Sales Rep B',
            'email' => 'repb@acme.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        // Organization memberships
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'is_owner' => true, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $this->salesRepA->id, 'is_owner' => false, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $this->salesRepB->id, 'is_owner' => false, 'status' => 'active']);

        // Roles and permissions setup
        $role = Role::create([
            'name' => 'Tenant Admin',
            'slug' => 'tenant-admin',
            'organization_id' => $this->organization->id,
        ]);

        $permissions = [
            'view_leads', 'create_leads', 'update_leads', 'delete_leads',
        ];

        foreach ($permissions as $slug) {
            $permission = Permission::create([
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'slug' => $slug,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $this->user->roles()->attach($role->id, ['organization_id' => $this->organization->id]);
        $this->salesRepA->roles()->attach($role->id, ['organization_id' => $this->organization->id]);
        $this->salesRepB->roles()->attach($role->id, ['organization_id' => $this->organization->id]);

        $this->tenantContext->setTenant($this->organization);
    }

    public function test_deterministic_fsm_transitions_and_history_logging()
    {
        $lead = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Lifecycle Prospect',
            'email' => 'prospect@lifecycle.com',
            'status' => 'new',
        ]);

        // Valid transition: new -> contacted
        $lead = $this->leadService->changeLeadStatus($lead->id, 'contacted', $this->user->id, 'Initial contact made.');
        $this->assertEquals('contacted', $lead->status);

        // Verify status history table
        $this->assertDatabaseHas('crm_lead_status_history', [
            'lead_id' => $lead->id,
            'from_status' => 'new',
            'to_status' => 'contacted',
            'changed_by' => $this->user->id,
            'reason' => 'Initial contact made.',
        ]);

        // Verify activity timeline logging
        $this->assertDatabaseHas('crm_lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'status_change',
        ]);

        // Invalid transition: contacted -> won (should throw exception)
        $this->expectException(\InvalidArgumentException::class);
        $this->leadService->changeLeadStatus($lead->id, 'won', $this->user->id, 'Direct close.');
    }

    public function test_lead_ownership_reassignment_and_history()
    {
        $lead = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Owner Assignment Prospect',
            'email' => 'assign@lifecycle.com',
            'status' => 'new',
        ]);

        // Assign manually to Sales Rep A
        $lead = $this->leadService->assignLead($lead->id, $this->salesRepA->id, $this->user->id, 'manual');
        $this->assertEquals($this->salesRepA->id, $lead->user_id);

        $this->assertDatabaseHas('crm_lead_assignment_history', [
            'lead_id' => $lead->id,
            'from_user_id' => null,
            'to_user_id' => $this->salesRepA->id,
            'assigned_by' => $this->user->id,
            'method' => 'manual',
        ]);

        // Reassign to Sales Rep B
        $lead = $this->leadService->assignLead($lead->id, $this->salesRepB->id, $this->user->id, 'reassign');
        $this->assertEquals($this->salesRepB->id, $lead->user_id);

        $this->assertDatabaseHas('crm_lead_assignment_history', [
            'lead_id' => $lead->id,
            'from_user_id' => $this->salesRepA->id,
            'to_user_id' => $this->salesRepB->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_round_robin_and_load_balanced_assignment_routing()
    {
        $lead1 = $this->leadService->createLead(['organization_id' => $this->organization->id, 'name' => 'Prospect One', 'email' => 'one@rr.com']);
        $lead2 = $this->leadService->createLead(['organization_id' => $this->organization->id, 'name' => 'Prospect Two', 'email' => 'two@rr.com']);

        $pool = [$this->salesRepA->id, $this->salesRepB->id];

        // 1. Round-Robin
        $lead1 = $this->leadService->assignLeadRoundRobin($lead1->id, $pool, $this->user->id);
        $this->assertContains($lead1->user_id, $pool);

        // Next round robin assignment should select the other rep
        $lead2 = $this->leadService->assignLeadRoundRobin($lead2->id, $pool, $this->user->id);
        $this->assertNotEquals($lead1->user_id, $lead2->user_id);

        // 2. Load-Balanced (rep with fewest active leads)
        $lead3 = $this->leadService->createLead(['organization_id' => $this->organization->id, 'name' => 'Prospect Three', 'email' => 'three@lb.com']);
        
        // Rep A has 1 active, Rep B has 1 active. Assign lead3 manually to Rep B. Rep B now has 2, Rep A has 1.
        $this->leadService->assignLead($lead3->id, $this->salesRepB->id, $this->user->id);

        $lead4 = $this->leadService->createLead(['organization_id' => $this->organization->id, 'name' => 'Prospect Four', 'email' => 'four@lb.com']);
        $lead4 = $this->leadService->assignLeadLoadBalanced($lead4->id, $pool, $this->user->id);

        // Should load balance to Rep A (since Rep A has 1 active, Rep B has 2 active)
        $this->assertEquals($this->salesRepA->id, $lead4->user_id);
    }

    public function test_automatic_lead_scoring_engine()
    {
        // Low demographic score lead (commercial email, no phone, no company)
        $leadLow = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Casual Visitor',
            'email' => 'visitor@gmail.com',
        ]);

        // High demographic score lead (corporate email, phone, company title)
        $leadHigh = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Corporate Decision Maker',
            'email' => 'ceo@microsoft.com',
            'phone' => '+14258828080',
            'custom_fields' => [
                'job_title' => 'Chief Executive Officer',
            ]
        ]);

        $this->assertTrue($leadHigh->score > $leadLow->score);
        $this->assertNotNull($leadHigh->score_breakdown);
    }

    public function test_duplicate_detection_and_merging()
    {
        $primary = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Double Entry',
            'email' => 'double@acme.io',
            'phone' => '+1234567890',
        ]);

        // Duplicate entry with same email
        $duplicate = $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Double Entry Duplicate',
            'email' => 'double@acme.io',
        ]);

        // Verify duplicate detector auto-flagged second lead
        $this->assertEquals('potential', $duplicate->duplicate_status);
        $this->assertEquals($primary->id, $duplicate->duplicate_of_id);

        // Execute Merge
        $merged = $this->leadService->mergeLeads($primary->id, $duplicate->id, ['phone' => 'primary'], $this->user->id);

        $this->assertEquals('Double Entry', $merged->name);
        
        // Assert duplicate lead is soft deleted
        $this->assertSoftDeleted('crm_leads', ['id' => $duplicate->id]);
    }

    public function test_dynamic_custom_fields_validation()
    {
        // Setup Tenant custom field rule: 'linkedin_url' of type 'url' that is required
        CustomField::create([
            'organization_id' => $this->organization->id,
            'model_type' => 'Lead',
            'name' => 'linkedin_url',
            'field_type' => 'url',
            'is_required' => true,
        ]);

        // Attempting to create lead without required linkedin_url should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->leadService->createLead([
            'organization_id' => $this->organization->id,
            'name' => 'Failed Custom Profile',
            'email' => 'fail@custom.com',
            'custom_fields' => []
        ]);
    }

    public function test_data_portability_csv_import_export_and_rollback()
    {
        $csvContent = "Name,Email,Phone,Status\nImported A,impa@gmail.com,+254711111111,new\nImported B,impb@gmail.com,+254722222222,contacted";

        // 1. Test CSV Import
        $importResult = $this->leadService->importLeads($csvContent, $this->organization->id, $this->user->id, false);

        $this->assertTrue($importResult['success']);
        $this->assertEquals(2, $importResult['imported_count']);

        $this->assertDatabaseHas('crm_leads', ['name' => 'Imported A', 'email' => 'impa@gmail.com']);
        $this->assertDatabaseHas('crm_leads', ['name' => 'Imported B', 'email' => 'impb@gmail.com']);

        // 2. Test Import Rollback
        $batchId = $importResult['batch_id'];
        $rollbackResult = $this->leadService->rollbackImport($batchId, $this->organization->id);

        $this->assertTrue($rollbackResult['success']);
        $this->assertEquals(2, $rollbackResult['rolled_back_count']);

        $this->assertDatabaseMissing('crm_leads', ['name' => 'Imported A']);
        $this->assertDatabaseMissing('crm_leads', ['name' => 'Imported B']);
    }
}
