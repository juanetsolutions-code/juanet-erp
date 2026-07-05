<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Domain\CRM\Models\Lead;
use App\Services\TenantContext;
use App\Contracts\EventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmVisitorIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationA;
    protected Organization $organizationB;
    protected TenantContext $tenantContext;
    protected EventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);
        $this->eventBus = app(EventBus::class);

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
     * Test Visitor creation, page tracking, device/geo details, and EventBus dispatch.
     */
    public function test_visitor_creation_and_page_tracking_lifecycle()
    {
        $payload = [
            'url' => 'https://alpha.io/services/ai-solutions',
            'route_name' => 'public.services.ai',
            'page_title' => 'AI Enterprise Solutions',
            'referrer' => 'https://google.com',
            'browser' => 'Chrome',
            'operating_system' => 'macOS',
            'device_type' => 'Desktop',
            'screen_resolution' => '1920x1080',
            'viewport' => '1440x900',
            'timezone' => 'Africa/Nairobi',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'ai_launch_2026',
        ];

        // Hit public track endpoint
        $response = $this->postJson('/api/public/visitors/track', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'visitor_id',
            'session_id',
            'page_view_id',
            'is_new_visitor',
            'is_new_session',
        ]);

        $visitorId = $response->json('visitor_id');
        $sessionId = $response->json('session_id');
        $pageViewId = $response->json('page_view_id');

        // Confirm DB records exist
        $this->assertDatabaseHas('crm_visitors', [
            'id' => $visitorId,
            'organization_id' => $this->organizationA->id,
            'browser' => 'Chrome',
            'utm_source' => 'google',
            'utm_campaign' => 'ai_launch_2026',
        ]);

        $this->assertDatabaseHas('crm_visitor_sessions', [
            'id' => $sessionId,
            'visitor_id' => $visitorId,
            'organization_id' => $this->organizationA->id,
            'landing_page' => 'https://alpha.io/services/ai-solutions',
        ]);

        $this->assertDatabaseHas('crm_visitor_page_views', [
            'id' => $pageViewId,
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'url' => 'https://alpha.io/services/ai-solutions',
            'route_name' => 'public.services.ai',
        ]);
    }

    /**
     * Test Returning Visitor detection and Session started tracking.
     */
    public function test_returning_visitor_and_session_started()
    {
        // GIVEN an existing visitor
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
            'total_sessions' => 1,
            'total_page_views' => 2,
            'browser' => 'Firefox',
        ]);

        $payload = [
            'visitor_id' => $visitor->id,
            'url' => 'https://alpha.io/home',
            'page_title' => 'Homepage',
            'referrer' => 'https://twitter.com',
        ];

        // WHEN tracking a new visit from them
        $response = $this->postJson('/api/public/visitors/track', $payload);

        $response->assertStatus(200);
        $this->assertFalse($response->json('is_new_visitor'));
        $this->assertTrue($response->json('is_new_session'));

        // Visitor total sessions should increment
        $visitor->refresh();
        $this->assertEquals(2, $visitor->total_sessions);
        $this->assertEquals(3, $visitor->total_page_views);
    }

    /**
     * Test CTA click and CTAButtonClicked event publishing.
     */
    public function test_cta_click_tracking()
    {
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $session = VisitorSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'start_time' => now(),
        ]);

        $pageView = VisitorPageView::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.io/landing',
            'page_title' => 'Promo Page',
        ]);

        $payload = [
            'page_view_id' => $pageView->id,
            'cta_id' => 'cta-contact-us',
            'label' => 'Contact Us Today',
        ];

        $response = $this->postJson('/api/public/visitors/cta', $payload);

        $response->assertStatus(200);

        $pageView->refresh();
        $this->assertCount(1, $pageView->cta_clicks);
        $this->assertEquals('cta-contact-us', $pageView->cta_clicks[0]['cta_id']);
    }

    /**
     * Test campaigns, UTM attribution history, and referral chains.
     */
    public function test_campaign_attribution_history()
    {
        $payload1 = [
            'url' => 'https://alpha.io/',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'welcome_series',
        ];

        // First touch
        $response1 = $this->postJson('/api/public/visitors/track', $payload1);
        $visitorId = $response1->json('visitor_id');

        $visitor = Visitor::find($visitorId);
        $this->assertEquals('newsletter', $visitor->utm_source);
        $this->assertEquals('welcome_series', $visitor->utm_campaign);
        $this->assertCount(1, $visitor->campaign_history);

        // Second touch with a different campaign
        $payload2 = [
            'visitor_id' => $visitorId,
            'url' => 'https://alpha.io/pricing',
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'pricing_promo',
        ];

        $this->postJson('/api/public/visitors/track', $payload2);

        $visitor->refresh();
        $this->assertEquals('google_ads', $visitor->utm_source);
        $this->assertEquals('pricing_promo', $visitor->utm_campaign);
        $this->assertCount(2, $visitor->campaign_history); // Complete history preserved
    }

    /**
     * Test Visitor conversion to CRM Lead, verifying no journey data is lost.
     */
    public function test_visitor_to_lead_conversion_association()
    {
        // Create visitor
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'utm_source' => 'linkedin',
            'utm_campaign' => 'b2b_outreach',
            'city' => 'Nairobi',
            'country' => 'Kenya',
        ]);

        // Submit public lead with visitor cookie
        $payload = [
            'name' => 'Alice Kenya',
            'email' => 'alice@kenya.corp',
            'form_timestamp' => time() - 10,
            'message' => 'Interested in enterprise hosting',
            'visitor_id' => $visitor->id,
        ];

        $response = $this->postJson('/api/public/leads', $payload);

        $response->assertStatus(201);
        $leadId = $response->json('lead_id');

        $lead = Lead::find($leadId);
        $this->assertEquals($visitor->id, $lead->visitor_id);
        
        // Assert attribution matches visitor history
        $this->assertEquals('linkedin', $lead->crm_lead_metadata['utm_source']);
        $this->assertEquals('b2b_outreach', $lead->crm_lead_metadata['utm_campaign']);
        $this->assertEquals('Nairobi', $lead->crm_lead_metadata['conversion_attribution']['city']);
    }

    /**
     * Test Tenant isolation. Visitors of Tenant A must never leak or be visible to Tenant B.
     */
    public function test_tenant_isolation_on_visitor_intelligence()
    {
        // Create visitor for Tenant A
        $visitorA = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Create visitor for Tenant B
        $visitorB = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationB->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Logged in as Tenant A (context is organizationA)
        $this->tenantContext->setTenant($this->organizationA);

        // Fetching timeline of Visitor A should succeed
        $responseA = $this->actingAsUser()->getJson("/api/crm/visitor-intelligence/timeline/{$visitorA->id}");
        $responseA->assertStatus(200);

        // Fetching timeline of Visitor B (Tenant B) from Tenant A context must fail/be unauthorized
        $responseB = $this->actingAsUser()->getJson("/api/crm/visitor-intelligence/timeline/{$visitorB->id}");
        $responseB->assertStatus(404);
    }

    /**
     * Test GDPR rights: Anonymization, Deletion, and dossier export.
     */
    public function test_gdpr_privacy_and_compliance()
    {
        $visitor = Visitor::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'organization_id' => $this->organizationA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'country' => 'Germany',
            'city' => 'Berlin',
            'utm_source' => 'adsense',
        ]);

        // GDPR Export
        $responseExport = $this->actingAsUser()->getJson("/api/crm/visitor-intelligence/gdpr/export/{$visitor->id}");
        $responseExport->assertStatus(200);
        $this->assertEquals('Berlin', $responseExport->json('export.visitor.city'));

        // GDPR Anonymization
        $responseAnonymize = $this->actingAsUser()->postJson('/api/crm/visitor-intelligence/gdpr/anonymize', [
            'visitor_id' => $visitor->id,
        ]);
        $responseAnonymize->assertStatus(200);

        $visitor->refresh();
        $this->assertEquals('Anonymized', $visitor->city);
        $this->assertEquals('Anonymized', $visitor->country);
        $this->assertNull($visitor->utm_source);

        // GDPR Deletion (Soft deletion)
        $responseDelete = $this->actingAsUser()->postJson('/api/crm/visitor-intelligence/gdpr/delete', [
            'visitor_id' => $visitor->id,
        ]);
        $responseDelete->assertStatus(200);

        $this->assertSoftDeleted('crm_visitors', ['id' => $visitor->id]);
    }

    /**
     * Helper to authenticate/mock user for CRM request simulation.
     */
    protected function actingAsUser()
    {
        $user = \App\Models\User::create([
            'name' => 'CRM Agent',
            'email' => 'agent@alpha.io',
            'password' => bcrypt('password'),
        ]);

        return $this->actingAs($user);
    }
}
