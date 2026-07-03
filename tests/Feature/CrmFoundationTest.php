<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\Pipeline;
use App\Domain\CRM\Models\PipelineStage;
use App\Domain\CRM\Models\LeadSource;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);

        // Create a user and tenant organization
        $this->user = User::create([
            'name' => 'John Developer',
            'email' => 'john@juanet.io',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->organization = Organization::create([
            'name' => 'Acme Enterprise',
            'domain' => 'acme.juanet.io',
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        // Create roles and permissions
        $role = Role::create([
            'name' => 'Tenant Owner',
            'slug' => 'tenant-owner',
            'organization_id' => $this->organization->id,
        ]);

        $permissions = [
            'view_leads', 'create_leads', 'update_leads', 'delete_leads',
            'view_contacts', 'create_contacts', 'update_contacts', 'delete_contacts',
            'view_companies', 'create_companies', 'update_companies', 'delete_companies',
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

        // Initialize tenant context
        $this->tenantContext->setTenant($this->organization);
    }

    public function test_can_create_leads_with_tenant_isolation()
    {
        $source = LeadSource::create([
            'organization_id' => $this->organization->id,
            'name' => 'Google Search',
        ]);

        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Lead One',
            'email' => 'lead1@gmail.com',
            'phone' => '+254712345678',
            'status' => 'new',
            'lead_source_id' => $source->id,
        ]);

        $this->assertDatabaseHas('crm_leads', [
            'id' => $lead->id,
            'organization_id' => $this->organization->id,
            'name' => 'Lead One',
        ]);
    }

    public function test_can_retrieve_leads_via_isolated_repository()
    {
        $repo = app(\App\Domain\CRM\Contracts\LeadRepositoryInterface::class);

        $lead = $repo->create([
            'name' => 'Lead Two',
            'email' => 'lead2@gmail.com',
            'status' => 'new',
        ]);

        $found = $repo->find($lead->id);
        $this->assertNotNull($found);
        $this->assertEquals('Lead Two', $found->name);
    }

    public function test_api_endpoints_list_and_create_leads()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->getJson('/api/crm/leads');

        $response->assertStatus(200);

        $createResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->postJson('/api/crm/leads', [
                'name' => 'API Prospect',
                'email' => 'api@enterprise.com',
                'status' => 'new',
            ]);

        $createResponse->assertStatus(201);
        $this->assertDatabaseHas('crm_leads', [
            'name' => 'API Prospect',
            'email' => 'api@enterprise.com',
        ]);
    }

    public function test_convert_lead_action_executes_successfully()
    {
        $pipeline = Pipeline::create([
            'organization_id' => $this->organization->id,
            'name' => 'Enterprise Pipeline',
        ]);

        $stage = PipelineStage::create([
            'pipeline_id' => $pipeline->id,
            'organization_id' => $this->organization->id,
            'name' => 'Qualified Stage',
            'order' => 1,
        ]);

        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Lead Three',
            'email' => 'lead3@gmail.com',
            'status' => 'qualified',
        ]);

        $action = app(\App\Domain\CRM\Actions\ConvertLead::class);
        $result = $action->execute($lead->id, [
            'create_company' => true,
            'company_name' => 'MegaCorp Ltd',
            'create_contact' => true,
            'create_opportunity' => true,
            'opportunity_name' => 'MegaCorp Big License Deal',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'amount' => 12500.00,
        ]);

        $this->assertNotNull($result['company']);
        $this->assertNotNull($result['contact']);
        $this->assertNotNull($result['opportunity']);

        $this->assertDatabaseHas('crm_companies', ['name' => 'MegaCorp Ltd']);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'lead3@gmail.com']);
        $this->assertDatabaseHas('crm_opportunities', ['amount' => 12500.00]);
        $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'status' => 'converted']);
    }
}
