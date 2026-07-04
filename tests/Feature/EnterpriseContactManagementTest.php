<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\User;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\ContactAddress;
use App\Domain\CRM\Models\ContactConsent;
use App\Domain\CRM\Models\ContactMethod;
use App\Domain\CRM\Models\ContactRelationship;
use App\Domain\CRM\Services\ContactHealthService;
use App\Domain\CRM\Services\ContactDuplicateDetector;
use App\Domain\CRM\Services\ContactMergeService;
use App\Contracts\EventBus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

class EnterpriseContactManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Test Corp']);
        $this->user = User::create([
            'name' => 'Agent Juan',
            'email' => 'juan@acme.test',
            'password' => bcrypt('secret123'),
            'organization_id' => $this->org->id,
        ]);
    }

    /** @test */
    public function it_calculates_contact_health_score_with_various_metrics()
    {
        $contact = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Caleb',
            'last_name' => 'Kirui',
            'email' => 'caleb@acme.test',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
            'is_decision_maker' => true,
        ]);

        ContactMethod::create([
            'organization_id' => $this->org->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'caleb@acme.test',
            'is_verified' => true,
        ]);

        $healthService = app(ContactHealthService::class);
        $result = $healthService->calculate($contact);

        $this->assertGreaterThanOrEqual(60, $result['score']);
        $this->assertEquals('Healthy', $result['status']);
        $this->assertTrue(isset($result['breakdown']['sales_influence']));
    }

    /** @test */
    public function it_detects_duplicates_by_similarity_rules()
    {
        $contact1 = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Mary',
            'last_name' => 'Kamau',
            'email' => 'mary.k@safaricom.co.ke',
        ]);

        $detector = app(ContactDuplicateDetector::class);
        $duplicates = $detector->findDuplicates([
            'first_name' => 'Mary',
            'last_name' => 'Kamau',
            'email' => 'mary.k@safaricom.co.ke',
        ], $contact1->id);

        $this->assertNotEmpty($duplicates);
        $this->assertEquals($contact1->id, $duplicates[0]['contact']->id);
        $this->assertGreaterThanOrEqual(80, $duplicates[0]['confidence']);
    }

    /** @test */
    public function it_merges_contacts_and_reassociates_child_records()
    {
        $master = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Mary Master',
            'last_name' => 'Kamau',
            'email' => 'mary.master@safaricom.co.ke',
        ]);

        $duplicate = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Mary Dup',
            'last_name' => 'Kamau',
            'email' => 'mary.dup@safaricom.co.ke',
        ]);

        $address = ContactAddress::create([
            'organization_id' => $this->org->id,
            'contact_id' => $duplicate->id,
            'type' => 'billing',
            'street' => 'Waiyaki Way',
            'city' => 'Nairobi',
            'country' => 'Kenya',
        ]);

        $mergeService = app(ContactMergeService::class);
        $merged = $mergeService->merge($master->id, [$duplicate->id]);

        $this->assertEquals($master->id, $merged->id);
        $this->assertSoftDeleted('crm_contacts', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('crm_contact_addresses', [
            'id' => $address->id,
            'contact_id' => $master->id,
        ]);
    }

    /** @test */
    public function it_prevents_circular_graphs_in_relationships()
    {
        $contactA = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Contact A',
            'last_name' => 'Test',
            'email' => 'a@test.com',
        ]);

        $contactB = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Contact B',
            'last_name' => 'Test',
            'email' => 'b@test.com',
        ]);

        ContactRelationship::create([
            'organization_id' => $this->org->id,
            'contact_id' => $contactA->id,
            'related_contact_id' => $contactB->id,
            'type' => 'manager',
        ]);

        $isCircular = ContactRelationship::wouldCreateCircularGraph($contactB->id, $contactA->id);
        $this->assertTrue($isCircular);
    }

    /** @test */
    public function it_enforces_tenant_isolation_on_duplicate_detection()
    {
        $otherOrg = Organization::create(['name' => 'Other Corp']);
        
        $contactOther = Contact::create([
            'organization_id' => $otherOrg->id,
            'first_name' => 'Isolated',
            'last_name' => 'Kamau',
            'email' => 'isolated@safaricom.co.ke',
        ]);

        $this->actingAs($this->user);
        app(\App\Services\TenantContext::class)->setTenantId($this->org->id);

        $detector = app(ContactDuplicateDetector::class);
        $duplicates = $detector->findDuplicates([
            'email' => 'isolated@safaricom.co.ke',
        ]);

        // Should be empty because it is in a different organization / tenant
        $this->assertEmpty($duplicates);
    }
}
