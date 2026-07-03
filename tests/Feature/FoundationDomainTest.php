<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationDomainTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationService $organizationService;
    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationService = $this->app->make(OrganizationService::class);
        $this->userService = $this->app->make(UserService::class);
    }

    /** @test */
    public function it_creates_organization_with_uuidv7_and_auditing()
    {
        $org = $this->organizationService->registerOrganization([
            'name' => 'Test Company LLC',
            'domain' => 'testcompany.com',
        ]);

        $this->assertNotNull($org->id);
        $this->assertEquals('string', gettype($org->id));
        $this->assertEquals('Test Company LLC', $org->name);
        $this->assertEquals('test-company-llc', $org->slug);
        $this->assertEquals(1, $org->version);
    }

    /** @test */
    public function it_creates_user_with_hashed_password()
    {
        $user = $this->userService->createUser([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret_password_123',
        ]);

        $this->assertNotNull($user->id);
        $this->assertNotEquals('secret_password_123', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret_password_123', $user->password));
    }

    /** @test */
    public function it_enforces_optimistic_locking_integrity()
    {
        $org = $this->organizationService->registerOrganization([
            'name' => 'Locking Inc',
        ]);

        $orgInstance1 = Organization::find($org->id);
        $orgInstance2 = Organization::find($org->id);

        $orgInstance1->name = 'New Name 1';
        $orgInstance1->save();

        $this->expectException(\RuntimeException::class);
        $orgInstance2->name = 'New Name 2';
        $orgInstance2->save(); // Should throw optimistic locking conflict exception
    }

    /** @test */
    public function it_supports_many_to_many_role_permission_association()
    {
        $role = Role::create([
            'name' => 'Custom Agent',
            'slug' => 'custom-agent',
        ]);

        $permission = Permission::create([
            'name' => 'Manage invoices',
            'slug' => 'invoice.manage',
            'module' => 'billing',
        ]);

        $role->permissions()->attach($permission->id);

        $this->assertCount(1, $role->permissions);
        $this->assertEquals('invoice.manage', $role->permissions->first()->slug);
    }
}
