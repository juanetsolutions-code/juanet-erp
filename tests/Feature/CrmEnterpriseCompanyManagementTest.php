<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\CompanyLocation;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Activities\Models\Activity;
use App\Services\TenantContext;
use App\Services\EventBus\TransactionalOutboxInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmEnterpriseCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);

        // Create user & tenant organization
        $this->user = User::create([
            'name' => 'Jane Admin',
            'email' => 'jane@juanet.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->organization = Organization::create([
            'name' => 'Acme Corporation',
            'domain' => 'acme.juanet.io',
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        // Roles & Perms
        $role = Role::create([
            'name' => 'Enterprise Manager',
            'slug' => 'enterprise-manager',
            'organization_id' => $this->organization->id,
        ]);

        $permissions = [
            'view_companies', 'create_companies', 'update_companies', 'delete_companies',
            'view_activities', 'create_activities', 'update_activities', 'delete_activities',
            'view_opportunities', 'create_opportunities', 'update_opportunities', 'delete_opportunities',
        ];

        foreach ($permissions as $slug) {
            $permission = Permission::create([
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'slug' => $slug,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $this->user->roles()->attach($role->id, ['organization_id' => $this->organization->id]);

        // Set context
        $this->tenantContext->setTenant($this->organization);
    }

    /**
     * Test tenant isolation.
     */
    public function test_tenant_isolation_on_companies_and_locations()
    {
        // Tenant 1 (Acme) Company
        $company1 = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Company One',
            'domain' => 'one.com',
            'status' => 'Prospect',
        ]);

        $location1 = CompanyLocation::create([
            'organization_id' => $this->organization->id,
            'company_id' => $company1->id,
            'type' => 'headquarters',
            'name' => 'Nairobi HQ',
        ]);

        // Tenant 2 (Foreign Acme)
        $org2 = Organization::create([
            'name' => 'Foreign Corp',
            'domain' => 'foreign.io',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'name' => 'Foreign User',
            'email' => 'foreign@foreign.io',
            'password' => bcrypt('password123'),
        ]);

        $company2 = Company::create([
            'organization_id' => $org2->id,
            'name' => 'Company Two',
            'domain' => 'two.com',
            'status' => 'Prospect',
        ]);

        // Verify Nairobi HQ of Tenant 1 can be loaded inside Tenant 1
        $this->tenantContext->setTenant($this->organization);
        $repo = app(\App\Domain\CRM\Contracts\CompanyRepositoryInterface::class);
        $this->assertNotNull($repo->find($company1->id));
        $this->assertNull($repo->find($company2->id));

        // Switch to Tenant 2
        $this->tenantContext->setTenant($org2);
        $this->assertNull($repo->find($company1->id));
        $this->assertNotNull($repo->find($company2->id));
    }

    /**
     * Test hierarchy integrity & recursive traversal.
     */
    public function test_hierarchy_integrity_and_recursive_traversal()
    {
        $this->tenantContext->setTenant($this->organization);

        $parent = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Global Parent Holding',
            'status' => 'Customer',
        ]);

        $subsidiary1 = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sub EMEA',
            'parent_id' => $parent->id,
            'status' => 'Customer',
        ]);

        $subsidiary2 = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sub Kenya branch',
            'parent_id' => $subsidiary1->id,
            'status' => 'Prospect',
        ]);

        $service = app(\App\Domain\CRM\Services\CompanyService::class);
        $hierarchy = $service->getHierarchy($subsidiary2->id);

        $this->assertEquals('Sub Kenya branch', $hierarchy['company']['name']);
        $this->assertCount(2, $hierarchy['ancestors']);
        $this->assertEquals('Global Parent Holding', $hierarchy['ancestors'][0]['name']);
        $this->assertEquals('Sub EMEA', $hierarchy['ancestors'][1]['name']);
    }

    /**
     * Test account health engine calculation.
     */
    public function test_account_health_engine_mathematical_shift()
    {
        $this->tenantContext->setTenant($this->organization);

        $company = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Enterprise Inc.',
            'status' => 'Customer',
        ]);

        // Health initial
        $company->recalculateHealthScore();
        $this->assertEquals(70, $company->health_score); // baseline
        $this->assertEquals('Warning', $company->health_status);

        // Add a won opportunity to boost score
        Opportunity::create([
            'organization_id' => $this->organization->id,
            'company_id' => $company->id,
            'name' => 'Giant Enterprise Contract',
            'amount' => 500000,
            'status' => 'won',
            'close_date' => now()->addDays(30),
        ]);

        $company->recalculateHealthScore();
        $this->assertEquals(80, $company->health_score); // baseline + 10 won points
        $this->assertEquals('Healthy', $company->health_status);

        // Add completed activities
        $activity = Activity::create([
            'organization_id' => $this->organization->id,
            'loggable_type' => Company::class,
            'loggable_id' => $company->id,
            'type' => 'phone_call',
            'subject' => 'Follow up completed',
            'is_completed' => true,
        ]);

        $company->recalculateHealthScore();
        $this->assertEquals(83, $company->health_score); // 80 + 3 engagement points

        // Add overdue task to deduct score
        $activity2 = Activity::create([
            'organization_id' => $this->organization->id,
            'loggable_type' => Company::class,
            'loggable_id' => $company->id,
            'type' => 'follow_up_task',
            'subject' => 'Overdue call',
            'is_completed' => false,
            'due_at' => now()->subDays(5),
        ]);

        $company->recalculateHealthScore();
        $this->assertEquals(78, $company->health_score); // 83 - 5 deduction
        $this->assertEquals('Warning', $company->health_status);
    }

    /**
     * Test nested location sub-resource endpoints.
     */
    public function test_api_nested_locations_crud()
    {
        $company = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Nest Tech Corp',
            'status' => 'Customer',
        ]);

        // Create location via API
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->postJson("/api/crm/companies/{$company->id}/locations", [
                'type' => 'warehouse',
                'name' => 'Mombasa Port Depot',
                'country' => 'Kenya',
                'city' => 'Mombasa',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_company_locations', [
            'company_id' => $company->id,
            'name' => 'Mombasa Port Depot',
            'type' => 'warehouse',
        ]);

        $locationId = $response->json('data.id');

        // Update location via API
        $updateResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->putJson("/api/crm/companies/{$company->id}/locations/{$locationId}", [
                'name' => 'Mombasa Port Depot Upgraded',
            ]);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('crm_company_locations', [
            'id' => $locationId,
            'name' => 'Mombasa Port Depot Upgraded',
        ]);

        // Delete location via API
        $deleteResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->deleteJson("/api/crm/companies/{$company->id}/locations/{$locationId}");

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('crm_company_locations', [
            'id' => $locationId,
        ]);
    }

    /**
     * Test Transactional events are stored in the Outbox.
     */
    public function test_transactional_events_dispatched_in_outbox()
    {
        $company = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Event Dispatching Inc.',
            'status' => 'Customer',
            'health_score' => 90,
            'health_status' => 'Healthy',
        ]);

        // Dispatches on update
        $company->update(['name' => 'Event Dispatching Inc. Renamed']);

        $this->assertDatabaseHas('event_outbox', [
            'event_name' => 'crm.company.updated',
            'organization_id' => $this->organization->id,
        ]);

        // Deteriorate health and check health decline event
        $company->update([
            'health_score' => 45,
            'health_status' => 'Critical',
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'event_name' => 'crm.company.health_deteriorated',
            'organization_id' => $this->organization->id,
        ]);
    }
}
