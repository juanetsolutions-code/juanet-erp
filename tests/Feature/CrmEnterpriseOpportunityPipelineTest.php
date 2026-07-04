<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\OpportunityProduct;
use App\Domain\CRM\Models\Pipeline;
use App\Domain\CRM\Models\PipelineStage;
use App\Domain\CRM\Services\PipelineStateMachine;
use App\Services\TenantContext;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmEnterpriseOpportunityPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected TenantContext $tenantContext;
    protected Pipeline $pipeline;
    protected PipelineStage $qualificationStage;
    protected PipelineStage $proposalStage;
    protected PipelineStage $closedWonStage;
    protected PipelineStage $closedLostStage;

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

        // Create Pipelines and Stages
        $this->pipeline = Pipeline::create([
            'organization_id' => $this->organization->id,
            'name' => 'Main Enterprise Sales Pipeline',
        ]);

        $this->qualificationStage = PipelineStage::create([
            'pipeline_id' => $this->pipeline->id,
            'organization_id' => $this->organization->id,
            'name' => 'Qualification',
            'order' => 1,
            'probability' => 10,
        ]);

        $this->proposalStage = PipelineStage::create([
            'pipeline_id' => $this->pipeline->id,
            'organization_id' => $this->organization->id,
            'name' => 'Proposal Sent',
            'order' => 2,
            'probability' => 50,
        ]);

        $this->closedWonStage = PipelineStage::create([
            'pipeline_id' => $this->pipeline->id,
            'organization_id' => $this->organization->id,
            'name' => 'Closed Won',
            'order' => 3,
            'probability' => 100,
        ]);

        $this->closedLostStage = PipelineStage::create([
            'pipeline_id' => $this->pipeline->id,
            'organization_id' => $this->organization->id,
            'name' => 'Closed Lost',
            'order' => 4,
            'probability' => 0,
        ]);
    }

    public function test_can_create_opportunity_with_extended_attributes()
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->organization->id,
            'name' => 'Big Enterprise Licensing Deal',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->qualificationStage->id,
            'amount' => 50000.00,
            'expected_close_date' => now()->addDays(30),
            'currency' => 'USD',
            'forecast_category' => 'pipeline',
            'competitor' => 'Oracle Inc',
            'description' => 'A massive deal with some custom requirements.',
            'status' => 'open',
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunity->id,
            'organization_id' => $this->organization->id,
            'competitor' => 'Oracle Inc',
            'currency' => 'USD',
        ]);

        $this->assertNotEmpty($opportunity->opportunity_number);
    }

    public function test_fsm_stage_transitions_correctness_and_validation()
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->organization->id,
            'name' => 'SaaS Deal',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->qualificationStage->id,
            'amount' => 20000.00,
            'status' => 'open',
            'user_id' => $this->user->id,
        ]);

        $stateMachine = app(PipelineStateMachine::class);

        // Transition from Qualification to Proposal Sent (Valid)
        $stateMachine->transition($opportunity, $this->proposalStage, $this->user->id);
        $opportunity->refresh();
        $this->assertEquals($this->proposalStage->id, $opportunity->pipeline_stage_id);
        $this->assertEquals(50, $opportunity->win_probability);

        // Transition to Closed Won
        $stateMachine->transition($opportunity, $this->closedWonStage, $this->user->id, 'Won after deep negotiation');
        $opportunity->refresh();
        $this->assertEquals($this->closedWonStage->id, $opportunity->pipeline_stage_id);
        $this->assertEquals('won', $opportunity->status);
        $this->assertEquals('Won after deep negotiation', $opportunity->won_reason);

        // Try invalid transition Closed Won -> Proposal Sent (Should throw exception)
        $this->expectException(\InvalidArgumentException::class);
        $stateMachine->transition($opportunity, $this->proposalStage, $this->user->id);
    }

    public function test_line_items_pricing_recalculation_and_forecasting_accuracy()
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->organization->id,
            'name' => 'SaaS Licensing Combo',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->proposalStage->id,
            'amount' => 0.00, // Will be computed
            'win_probability' => 50,
            'currency' => 'USD',
            'status' => 'open',
            'user_id' => $this->user->id,
        ]);

        // Add Product Line Item 1 (Subscription Product)
        $prod1 = OpportunityProduct::create([
            'organization_id' => $this->organization->id,
            'opportunity_id' => $opportunity->id,
            'product_name' => 'User Licenses',
            'quantity' => 10,
            'unit_price' => 100.00,
            'discount' => 10.00, // 10% discount
            'tax' => 5.00, // 5% tax
            'recurring_billing_flag' => true,
            'subscription_interval' => 'monthly',
            'manual_pricing_override' => false,
        ]);

        // Add Product Line Item 2 (One-Time Services)
        $prod2 = OpportunityProduct::create([
            'organization_id' => $this->organization->id,
            'opportunity_id' => $opportunity->id,
            'product_name' => 'Onboarding Services',
            'quantity' => 1,
            'unit_price' => 2000.00,
            'discount' => 200.00, // Flat discount $200
            'tax' => 10.00, // 10% tax
            'recurring_billing_flag' => false,
            'manual_pricing_override' => false,
        ]);

        // Recalculate Totals
        $opportunity->recalculateTotals();
        $opportunity->refresh();

        // Computations:
        // Prod 1: Subtotal = 10 * 100 = 1000. Discount = 10% = 100. Taxable = 900. Tax = 5% = 45. Total = 945. MRR = 945, ARR = 11340
        // Prod 2: Subtotal = 2000. Discount = 200. Taxable = 1800. Tax = 10% = 180. Total = 1980. (One-time, no MRR/ARR)
        // Grand Total Amount = 945 + 1980 = 2925.
        // Weighted Forecast = 2925 * (50 / 100.0) = 1462.50.

        $this->assertEquals(2925.00, $opportunity->amount);
        $this->assertEquals(1462.50, $opportunity->weighted_revenue);
    }

    public function test_notifications_dispatch_for_milestones()
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->organization->id,
            'name' => 'Whale Account SaaS Deal',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->proposalStage->id,
            'amount' => 150000.00, // High-value deal (>100,000)
            'status' => 'open',
            'user_id' => $this->user->id,
        ]);

        // Trigger Created events/observers
        // Assert Notification created for High Value Deal Alert
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'high_value_deal',
            'priority' => 'high',
        ]);

        // Move to Closed Won
        $stateMachine = app(PipelineStateMachine::class);
        $stateMachine->transition($opportunity, $this->closedWonStage, $this->user->id);

        // Assert Notification created for Deal Won
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'deal_won',
        ]);
    }

    public function test_api_line_item_and_bulk_operations()
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->organization->id,
            'name' => 'API Pipeline Deal',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->qualificationStage->id,
            'amount' => 1000.00,
            'status' => 'open',
            'user_id' => $this->user->id,
        ]);

        // Add Product via API
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->postJson("/api/crm/opportunities/{$opportunity->id}/products", [
                'product_name' => 'API Added Licenses',
                'quantity' => 5,
                'unit_price' => 500.00,
                'discount' => 0.00,
                'tax' => 0.00,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_opportunity_products', [
            'opportunity_id' => $opportunity->id,
            'product_name' => 'API Added Licenses',
            'quantity' => 5,
        ]);

        // Opportunity amount should now be updated to 2500.00
        $opportunity->refresh();
        $this->assertEquals(2500.00, $opportunity->amount);

        // Bulk stage movement
        $bulkResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id])
            ->postJson('/api/crm/opportunities/bulk-move-stage', [
                'ids' => [$opportunity->id],
                'pipeline_stage_id' => $this->proposalStage->id,
            ]);

        $bulkResponse->assertStatus(200);
        $opportunity->refresh();
        $this->assertEquals($this->proposalStage->id, $opportunity->pipeline_stage_id);
    }
}
