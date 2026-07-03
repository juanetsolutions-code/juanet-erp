<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EnterpriseDashboardAndShellTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;
    protected OrganizationService $organizationService;
    protected User $testUser;
    protected Organization $testOrg1;
    protected Organization $testOrg2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = $this->app->make(UserService::class);
        $this->organizationService = $this->app->make(OrganizationService::class);

        // Seed roles for verification
        Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        Role::create(['name' => 'Employee', 'slug' => 'employee']);

        // Create standard test user
        $this->testUser = $this->userService->createUser([
            'name' => 'Enterprise Admin',
            'email' => 'admin@juanet.io',
            'password' => 'SecurePassword123!',
            'status' => 'active',
        ]);

        // Register organization 1
        $this->testOrg1 = $this->organizationService->registerOrganization([
            'name' => 'JUANET Core Operations',
            'slug' => 'juanet-core',
            'domain' => 'core.juanet.io',
        ]);

        $this->testOrg1->members()->create([
            'user_id' => $this->testUser->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        // Register organization 2
        $this->testOrg2 = $this->organizationService->registerOrganization([
            'name' => 'JUANET Secondary Node',
            'slug' => 'juanet-secondary',
            'domain' => 'secondary.juanet.io',
        ]);

        $this->testOrg2->members()->create([
            'user_id' => $this->testUser->id,
            'is_owner' => false,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function dashboard_loads_successfully_and_displays_tenant_widgets()
    {
        $this->actingAs($this->testUser);

        // Access dashboard
        $response = $this->get('/dashboard');

        $response->assertStatus(200);

        // Check if tenant title/details are visible
        $response->assertSee('JUANET Core Operations');
        $response->assertSee('core.juanet.io');

        // Check if user details are present
        $response->assertSee('Enterprise Admin');
        $response->assertSee('admin@juanet.io');

        // Check if Dashboard widgets are rendered
        $response->assertSee('Total Monthly Revenue');
        $response->assertSee('Active CRM Leads');
        $response->assertSee('MinIO Object Storage');
        $response->assertSee('Active Projects');
        $response->assertSee('Latest Marketplace Orders');
        $response->assertSee('Infrastructure Topology');
        $response->assertSee('Workspace Task List');
        $response->assertSee('Recent Enterprise Support Tickets');
    }

    /** @test */
    public function sidebar_renders_with_navigation_items_and_permissions()
    {
        $this->actingAs($this->testUser);

        $response = $this->get('/dashboard');

        // Verify key navigation options exist in side drawer
        $response->assertSee('Dashboard');
        $response->assertSee('CRM');
        $response->assertSee('Marketplace');
        $response->assertSee('CMS');
        $response->assertSee('Projects');
        $response->assertSee('Finance');
        $response->assertSee('Support');
        $response->assertSee('Automation');
        $response->assertSee('AI Agent');
        $response->assertSee('Administration');
        $response->assertSee('Settings');
    }

    /** @test */
    public function notification_center_global_variables_are_correctly_injected()
    {
        $this->actingAs($this->testUser);

        $response = $this->get('/dashboard');

        // Ensure global notification counts and alerts are shared with layout
        $response->assertViewHas('unreadNotifications');
        $response->assertViewHas('currentPermissions');
        $response->assertViewHas('featureFlags');

        $notifications = $response->viewData('unreadNotifications');
        $this->assertCount(4, $notifications);
        $this->assertEquals('Database Sync Successful', $notifications[0]['title']);
    }

    /** @test */
    public function it_can_switch_active_organization_context()
    {
        $this->actingAs($this->testUser);

        // Set initial session to org 1
        session(['active_organization_id' => $this->testOrg1->id]);

        $response = $this->post("/organization/switch/{$this->testOrg2->id}");

        $response->assertRedirect('/dashboard');
        $this->assertEquals($this->testOrg2->id, session('active_organization_id'));

        // Visit dashboard again, verify org 2 title is displayed
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertSee('JUANET Secondary Node');
        $dashboardResponse->assertSee('secondary.juanet.io');
    }

    /** @test */
    public function reusable_blade_components_render_successfully()
    {
        $this->actingAs($this->testUser);

        // Verify UI components rendering directly by asserting static markers in dashboard
        $response = $this->get('/dashboard');
        $response->assertSee('Active Tenant Membership Roll Directory');
        $response->assertSee('Workspace Organization Context');
    }
}
