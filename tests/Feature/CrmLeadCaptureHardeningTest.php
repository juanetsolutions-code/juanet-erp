<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\Tag;
use App\Domain\CRM\Models\LeadActivity;
use App\Services\TenantContext;
use App\Contracts\EventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmLeadCaptureHardeningTest extends TestCase
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

        // Put Tenant A in the context
        $this->tenantContext->setTenant($this->organizationA);
    }

    /**
     * Test 1 & 9: Honeypot & Spam Rejection Protection.
     */
    public function test_honeypot_and_spam_rejection_protection()
    {
        // GIVEN a submission containing a honeypot field
        $payload = [
            'name' => 'Spam Bot',
            'email' => 'spambot@gmail.com',
            'honeypot' => 'malicious-bot-auto-fill',
            'form_timestamp' => time(),
            'message' => 'Buy cheap pills!',
        ];

        // WHEN submitting the lead capture form
        $response = $this->postJson('/api/leads/public', $payload);

        // THEN it should be rejected with 422
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
        ]);
        
        // GIVEN a submission containing a spam keyword
        $payload2 = [
            'name' => 'SEO Seller',
            'email' => 'seoseller@gmail.com',
            'form_timestamp' => time() - 10, // safe time
            'message' => 'Earn casino money buy backlinks now',
        ];

        // WHEN submitting
        $response2 = $this->postJson('/api/leads/public', $payload2);

        // THEN it should be rejected as spam
        $response2->assertStatus(422);
    }

    /**
     * Test 2: Submission Timestamp minimum completion validation.
     */
    public function test_submission_timestamp_speed_protection()
    {
        // GIVEN a submission completed in under 3 seconds (e.g., elapsed = 0)
        $payload = [
            'name' => 'Fast Submitter',
            'email' => 'fast@example.com',
            'form_timestamp' => time(), // submitted right now
            'message' => 'I am real, but submitted too quickly',
        ];

        // WHEN submitting
        $response = $this->postJson('/api/leads/public', $payload);

        // THEN it should be rejected
        $response->assertStatus(422);
    }

    /**
     * Test 3: Duplicate Submission Detection & Merging.
     */
    public function test_duplicate_submission_detection_and_merging()
    {
        // GIVEN an initial valid lead submission
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@acme.com',
            'phone' => '+254711223344',
            'company' => 'Acme Corp',
            'service_interest' => 'SaaS Billing Platform',
            'budget_range' => 'KES 1M - 3M',
            'message' => 'Looking for a robust multi-tenant billing engine.',
            'form_timestamp' => time() - 10,
        ];

        $response1 = $this->postJson('/api/leads/public', $payload);
        $response1->assertStatus(201);
        $leadId = $response1->json('lead_id');

        $lead = Lead::find($leadId);
        $this->assertEquals(1, $lead->crm_lead_metadata['inquiry_count']);

        // GIVEN a duplicate submission with the same email
        $duplicatePayload = [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@acme.com',
            'phone' => '+254711223344',
            'company' => 'Acme Corp',
            'service_interest' => 'SaaS Billing Platform',
            'budget_range' => 'KES 3M - 5M',
            'message' => 'Follow up on previous request.',
            'form_timestamp' => time() - 10,
        ];

        // WHEN submitting again
        $response2 = $this->postJson('/api/leads/public', $duplicatePayload);

        // THEN it should return the same lead ID, increment inquiry count, and NOT create a new database row
        $response2->assertStatus(201);
        $this->assertEquals($leadId, $response2->json('lead_id'));
        $this->assertEquals(1, Lead::count());

        $updatedLead = Lead::find($leadId);
        $this->assertEquals(2, $updatedLead->crm_lead_metadata['inquiry_count']);
        $this->assertNotNull($updatedLead->crm_lead_metadata['last_contact_at']);
        $this->assertEquals('potential', $updatedLead->duplicate_status);
    }

    /**
     * Test 4: Campaign Attribution & Metadata Storage.
     */
    public function test_campaign_attribution_and_metadata_storage()
    {
        // GIVEN a submission with UTM parameters and site session info
        $payload = [
            'name' => 'David Kimani',
            'email' => 'david.kimani@safari.com',
            'form_timestamp' => time() - 15,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'eid_saas_2026',
            'utm_term' => 'enterprise-billing',
            'utm_content' => 'header-banner',
            'landing_page' => 'https://juanet.io/landing/enterprise-billing',
            'exit_page' => 'https://juanet.io/checkout',
            'session_id' => 'sess_abc123xyz',
            'referrer' => 'https://linkedin.com',
        ];

        // WHEN capturing the lead
        $response = $this->postJson('/api/leads/public', $payload);

        // THEN it should succeed and persist campaign tracking in crm_lead_metadata
        $response->assertStatus(201);
        $lead = Lead::find($response->json('lead_id'));

        $metadata = $lead->crm_lead_metadata;
        $this->assertEquals('google', $metadata['utm_source']);
        $this->assertEquals('cpc', $metadata['utm_medium']);
        $this->assertEquals('eid_saas_2026', $metadata['utm_campaign']);
        $this->assertEquals('sess_abc123xyz', $metadata['session_id']);
        $this->assertEquals('https://linkedin.com', $metadata['referrer']);
    }

    /**
     * Test 5: Automatic Tag Generation.
     */
    public function test_automatic_tag_generation()
    {
        // GIVEN a submission with AI and automation keywords
        $payload = [
            'name' => 'Alice AI Engineer',
            'email' => 'alice@neural.tech',
            'form_timestamp' => time() - 10,
            'service_interest' => 'Gemini integrations & automated agents',
            'message' => 'We need help building an LLM based agent saas workflow.',
        ];

        // WHEN submitting
        $response = $this->postJson('/api/leads/public', $payload);

        // THEN tags like 'ai', 'automation', and 'saas' should be assigned automatically
        $response->assertStatus(201);
        $lead = Lead::find($response->json('lead_id'));

        $tags = $lead->tags->pluck('slug')->toArray();
        $this->assertTrue(in_array('ai', $tags));
        $this->assertTrue(in_array('saas', $tags));
        $this->assertTrue(in_array('automation', $tags));
    }

    /**
     * Test 6: Lead Conversion Scoring and Estimated Deal Size.
     */
    public function test_conversion_scoring_and_deal_size()
    {
        // GIVEN a high-quality corporate lead with business email, enterprise budget, and deep description
        $payload = [
            'name' => 'John Corporate',
            'email' => 'john@safaricom.co.ke', // business domain
            'company' => 'Safaricom PLC',
            'budget_range' => 'KES 5M+', // enterprise budget
            'form_timestamp' => time() - 20,
            'message' => 'Extremely long details detailing our architectural requirements for integration with daraja api, mobile payment collection, saas accounting, and scale workflows.',
        ];

        // WHEN capturing
        $response = $this->postJson('/api/leads/public', $payload);

        // THEN it should score high, have 'high' priority, and estimate a large deal size
        $response->assertStatus(201);
        $lead = Lead::find($response->json('lead_id'));

        $this->assertGreaterThanOrEqual(70, $lead->score);
        $this->assertEquals('high', $lead->crm_lead_metadata['priority'] ?? $lead->custom_fields['priority']);
        $this->assertEquals(5000000, $lead->crm_lead_metadata['estimated_deal_size']);
    }

    /**
     * Test 8: Tenant Isolation.
     */
    public function test_tenant_isolation_limits()
    {
        // GIVEN an email is captured in Tenant Alpha
        $payload = [
            'name' => 'Unique Tenant Person',
            'email' => 'tenantperson@gmail.com',
            'form_timestamp' => time() - 10,
        ];

        $this->tenantContext->setTenant($this->organizationA);
        $responseA = $this->postJson('/api/leads/public', $payload);
        $responseA->assertStatus(201);
        $leadA = Lead::find($responseA->json('lead_id'));

        // WHEN submitting the identical payload to Tenant Beta
        $this->tenantContext->setTenant($this->organizationB);
        $responseB = $this->postJson('/api/leads/public', $payload);

        // THEN it should create a completely separate record on Tenant Beta with NO deduplication merge
        $responseB->assertStatus(201);
        $this->assertEquals(2, Lead::count());
        $leadB = Lead::find($responseB->json('lead_id'));

        $this->assertNotEquals($leadA->id, $leadB->id);
        $this->assertEquals($this->organizationA->id, $leadA->organization_id);
        $this->assertEquals($this->organizationB->id, $leadB->organization_id);
    }
}
