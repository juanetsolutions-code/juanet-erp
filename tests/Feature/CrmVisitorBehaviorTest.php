<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Domain\CRM\Models\VisitorBehaviorProfile;
use App\Domain\CRM\Models\Lead;
use App\Services\TenantContext;
use App\Services\Crm\VisitorBehaviorService;
use App\Services\Crm\VisitorAnalyticsService;
use App\Contracts\EventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmVisitorBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationA;
    protected Organization $organizationB;
    protected TenantContext $tenantContext;
    protected VisitorBehaviorService $behaviorService;
    protected VisitorAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);
        $this->behaviorService = app(VisitorBehaviorService::class);
        $this->analyticsService = app(VisitorAnalyticsService::class);

        // Set up Tenant A
        $this->organizationA = Organization::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'domain' => 'alpha.io',
            'status' => 'active',
        ]);

        // Set up Tenant B
        $this->organizationB = Organization::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'domain' => 'beta.io',
            'status' => 'active',
        ]);

        // Default to Tenant A
        $this->tenantContext->setTenant($this->organizationA);
    }

    /**
     * Test Engagement Scoring and Purchase Intent Detection.
     */
    public function test_engagement_scoring_and_intent_classification()
    {
        // Create highly active visitor
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now()->subHours(2),
            'last_seen_at' => now(),
            'total_sessions' => 3,
            'total_page_views' => 15,
        ]);

        // 3 Sessions
        for ($i = 0; $i < 3; $i++) {
            VisitorSession::create([
                'id' => (string) \Illuminate\Support\Str::uuid7(),
                'visitor_id' => $visitor->id,
                'organization_id' => $this->organizationA->id,
                'start_time' => now()->subHours(2 - $i),
                'end_time' => now()->subHours(2 - $i)->addMinutes(10),
                'duration' => 600, // 600 seconds = 10 minutes
                'pages_visited' => 5,
            ]);
        }

        // 5 Page views including pricing, services (automation, AI), and CTA click
        VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/pricing',
            'page_title' => 'Pricing Plans',
        ]);

        VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/services/ai-solutions',
            'page_title' => 'Artificial Intelligence Services',
            'cta_clicks' => [
                ['cta_id' => 'cta-1', 'label' => 'Book AI Demo', 'clicked_at' => now()->toIso8601String()]
            ],
        ]);

        // Perform analysis
        $profile = $this->behaviorService->analyze($visitor->id);

        $this->assertNotNull($profile);
        $this->assertGreaterThan(50, $profile->engagement_score);
        $this->assertEquals('Enterprise Buyer', $profile->purchase_intent);
        $this->assertArrayHasKey('Artificial Intelligence', $profile->service_interests);
        $this->assertGreaterThan(0, $profile->service_interests['Artificial Intelligence']);
    }

    /**
     * Test Marketplace Product tracking.
     */
    public function test_marketplace_product_interest_tracking()
    {
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'start_time' => now(),
            'end_time' => now(),
            'duration' => 60,
        ]);

        // View Laravel Boilerplate page twice
        for ($i = 0; $i < 2; $i++) {
            VisitorPageView::create([
                'id' => (string) \Illuminate\Support\Str::uuid7(),
                'visitor_id' => $visitor->id,
                'organization_id' => $this->organizationA->id,
                'url' => 'https://alpha.io/marketplace/laravel-boilerplate-v1',
                'page_title' => 'Laravel Boilerplates Ultimate',
            ]);
        }

        $profile = $this->behaviorService->analyze($visitor->id);

        $this->assertArrayHasKey('Laravel Boilerplates', $profile->product_interests);
        $this->assertTrue($profile->product_interests['Laravel Boilerplates']['viewed']);
        $this->assertTrue($profile->product_interests['Laravel Boilerplates']['revisited']);
        $this->assertEquals(2, $profile->product_interests['Laravel Boilerplates']['count']);
    }

    /**
     * Test Content Intelligence (blog engagement).
     */
    public function test_content_intelligence_blog_reading()
    {
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'start_time' => now(),
            'end_time' => now(),
            'duration' => 300,
        ]);

        // 3 blog views
        VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/blog/scaling-laravel-apis',
            'page_title' => 'Scaling Laravel APIs',
            'scroll_depth' => 85,
            'time_on_page' => 120,
        ]);

        VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/blog/how-to-use-tailwind-css',
            'page_title' => 'Tailwind CSS Secrets',
            'scroll_depth' => 90,
            'time_on_page' => 180,
        ]);

        $profile = $this->behaviorService->analyze($visitor->id);

        $ci = $profile->content_intelligence;
        $this->assertNotEmpty($ci);
        $this->assertEquals('Intermediate', $ci['estimated_expertise_level']);
        $this->assertContains('laravel', $ci['favorite_topics']);
        $this->assertEquals(87.5, $ci['reading_depth']);
    }

    /**
     * Test customer value estimation.
     */
    public function test_customer_value_estimation()
    {
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'start_time' => now(),
            'end_time' => now(),
            'duration' => 60,
        ]);

        // Low intent profile
        $profile = $this->behaviorService->analyze($visitor->id);
        $cv = $profile->customer_value;

        $this->assertNotEmpty($cv);
        $this->assertGreaterThan(0, $cv['estimated_deal_size']);
        $this->assertGreaterThan(0, $cv['estimated_lifetime_value']);
        $this->assertLessThan(0.5, $cv['enterprise_probability']); // Enterprise prob should be low for low intent
    }

    /**
     * Test CRM promotion of visitor profile to a newly created Lead.
     */
    public function test_visitor_to_lead_promotion_with_behavior_profile()
    {
        // 1. GIVEN a visitor with a behavioral profile
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'start_time' => now(),
            'end_time' => now(),
            'duration' => 60,
        ]);

        VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/pricing',
            'page_title' => 'Pricing Plans',
        ]);

        // Run analysis first
        $profile = $this->behaviorService->analyze($visitor->id);

        // 2. WHEN we convert the visitor to a lead
        $lead = Lead::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'name' => 'Alice Dev',
            'email' => 'alice@dev.com',
            'status' => 'new',
        ]);

        $tracker = app(\App\Services\Crm\VisitorTrackerService::class);
        $tracker->associateLead($visitor->id, $lead);

        // 3. THEN the lead should have the behavior details attached
        $lead->refresh();
        $meta = $lead->crm_lead_metadata;
        
        $this->assertNotNull($meta);
        $this->assertArrayHasKey('behavior_profile', $meta);
        $this->assertEquals($profile->id, $meta['behavior_profile']['id']);
        $this->assertEquals($profile->engagement_score, $meta['behavior_profile']['engagement_score']);
        $this->assertEquals($profile->purchase_intent, $meta['behavior_profile']['purchase_intent']);
    }

    /**
     * Test Tenant Isolation on profiles.
     */
    public function test_tenant_isolation_on_visitor_profiles()
    {
        // Visitor under Tenant A
        $visitorA = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $profileA = $this->behaviorService->analyze($visitorA->id);

        // Visitor under Tenant B
        $visitorB = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationB->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $profileB = $this->behaviorService->analyze($visitorB->id);

        // Ensure Tenant ID matches the respective Visitor's Tenant ID
        $this->assertEquals($this->organizationA->id, $profileA->organization_id);
        $this->assertEquals($this->organizationB->id, $profileB->organization_id);
    }
}
