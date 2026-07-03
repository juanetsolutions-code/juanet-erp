<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;
    protected OrganizationService $organizationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = $this->app->make(UserService::class);
        $this->organizationService = $this->app->make(OrganizationService::class);

        // Seed basic roles and permissions for tests
        Role::create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);

        Role::create([
            'name' => 'Employee',
            'slug' => 'employee',
        ]);
    }

    /** @test */
    public function it_can_register_user_and_create_associated_organization()
    {
        $response = $this->post('/register', [
            'name' => 'John Developer',
            'email' => 'john@dev.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'organization_name' => 'Dev Sandbox Corp',
        ]);

        $response->assertRedirect('/dashboard');

        // Verify User and Organization in DB
        $user = User::where('email', 'john@dev.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Developer', $user->name);

        $org = Organization::where('name', 'Dev Sandbox Corp')->first();
        $this->assertNotNull($org);

        // Verify Membership
        $membership = OrganizationMember::where('user_id', $user->id)
            ->where('organization_id', $org->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->is_owner);
        $this->assertEquals('active', $membership->status);

        // Verify session active organization set
        $this->assertEquals($org->id, session('active_organization_id'));
    }

    /** @test */
    public function it_can_login_user_and_establish_tenant_session_context()
    {
        // 1. Create User & Org
        $user = $this->userService->createUser([
            'name' => 'Login User',
            'email' => 'login@user.com',
            'password' => 'secret1234',
            'status' => 'active',
        ]);

        $org = $this->organizationService->registerOrganization([
            'name' => 'Login Org',
            'slug' => 'login-org',
        ]);

        $org->members()->create([
            'user_id' => $user->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        // 2. Perform Login Request
        $response = $this->post('/login', [
            'email' => 'login@user.com',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        // Verify tenant resolved in session fallback
        $this->assertEquals($org->id, session('active_organization_id'));
    }

    /** @test */
    public function it_can_logout_safely_and_invalidate_sessions()
    {
        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout@user.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function it_can_switch_tenant_workspace_context_securely()
    {
        $user = User::create([
            'name' => 'Switch User',
            'email' => 'switch@user.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $org1 = Organization::create(['name' => 'Workspace One', 'slug' => 'workspace-one']);
        $org2 = Organization::create(['name' => 'Workspace Two', 'slug' => 'workspace-two']);

        // Attach to both
        OrganizationMember::create(['organization_id' => $org1->id, 'user_id' => $user->id, 'is_owner' => true, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org2->id, 'user_id' => $user->id, 'is_owner' => false, 'status' => 'active']);

        $this->actingAs($user);
        session(['active_organization_id' => $org1->id]);

        // Switch to Org 2
        $response = $this->post("/organizations/{$org2->id}/switch");

        $response->assertRedirect('/dashboard');
        $this->assertEquals($org2->id, session('active_organization_id'));
    }

    /** @test */
    public function it_prevents_switching_to_unauthorized_organization_context()
    {
        $user = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant@user.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $otherOrg = Organization::create(['name' => 'Secret Company', 'slug' => 'secret-company']);

        $this->actingAs($user);

        // Try to switch to an organization they do not belong to
        $response = $this->post("/organizations/{$otherOrg->id}/switch");

        $response->assertStatus(404); // Should fail with 404 or ModelNotFound
    }

    /** @test */
    public function it_can_invite_colleague_and_accept_invitation()
    {
        $owner = User::create([
            'name' => 'Owner Person',
            'email' => 'owner@corp.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'is_owner' => true, 'status' => 'active']);

        $invitee = User::create([
            'name' => 'New Guy',
            'email' => 'newguy@corp.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($owner);
        session(['active_organization_id' => $org->id]);

        // Owner sends invite
        $response = $this->post("/organizations/{$org->id}/invite", [
            'email' => 'newguy@corp.com',
        ]);

        $response->assertSessionHasNoErrors();

        // Verify pending member created
        $membership = OrganizationMember::where('user_id', $invitee->id)
            ->where('organization_id', $org->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals('pending', $membership->status);

        // Login as Invitee and accept invitation
        $this->actingAs($invitee);

        $acceptResponse = $this->post("/organizations/invitation/{$membership->id}/accept");

        $acceptResponse->assertRedirect('/dashboard');
        $this->assertEquals('active', $membership->fresh()->status);
        $this->assertEquals($org->id, session('active_organization_id'));
    }

    /** @test */
    public function it_enforces_permission_based_middleware_authorization()
    {
        $user = User::create([
            'name' => 'Staff Person',
            'email' => 'staff@corp.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'is_owner' => false, 'status' => 'active']);

        // Create Custom Admin role with permission
        $role = Role::create([
            'name' => 'Billing Manager',
            'slug' => 'billing-manager',
        ]);

        $permission = Permission::create([
            'name' => 'View Invoices',
            'slug' => 'invoices.view',
            'module' => 'billing',
        ]);

        $role->permissions()->attach($permission->id);

        // Attach Role to User for this org context
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid7(),
            'organization_id' => $org->id,
        ]);

        // Act as user
        $this->actingAs($user);
        session(['active_organization_id' => $org->id]);

        // Resolve context
        $this->get('/dashboard')->assertOk();

        // Check helper methods on model
        $this->assertTrue($user->hasPermission('invoices.view', $org->id));
        $this->assertFalse($user->hasPermission('reports.edit', $org->id));
    }
}
