<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\Permission;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\ContactMethod;
use App\Domain\CRM\Models\ContactRelationship;
use App\Domain\CRM\Models\ContactCompanyAssociation;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmEnterpriseContactManagementTest extends TestCase
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
            'view_contacts', 'create_contacts', 'update_contacts', 'delete_contacts',
            'view_companies', 'create_companies', 'update_companies', 'delete_companies',
            'view_activities', 'create_activities', 'update_activities', 'delete_activities',
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
     * Test full contact profile persistence and relationships.
     */
    public function test_contact_profile_crud_and_extended_attributes()
    {
        $company = Company::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Company LLC',
            'domain' => 'testcompany.com',
            'status' => 'Prospect',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/crm/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'preferred_name' => 'Johnny',
            'email' => 'john.doe@testcompany.com',
            'phone' => '+254711223344',
            'company_id' => $company->id,
            'job_title' => 'Chief Technology Officer',
            'department' => 'Engineering',
            'decision_maker_level' => 'C-Level',
            'buying_influence' => 'Decision Maker',
            'linkedin_url' => 'https://linkedin.com/in/johndoe',
            'preferred_language' => 'en',
            'timezone' => 'Africa/Nairobi',
            'birthday' => '1985-06-15',
            'anniversary' => '2015-10-10',
            'notes' => 'Key decision maker for EMEA enterprise accounts.',
            'gdpr_consent_status' => 'Consented',
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_contacts', [
            'first_name' => 'John',
            'preferred_name' => 'Johnny',
            'department' => 'Engineering',
            'decision_maker_level' => 'C-Level',
            'buying_influence' => 'Decision Maker',
            'gdpr_consent_status' => 'Consented',
        ]);

        $contactId = $response->json('data.id');

        // Test update
        $updateResponse = $this->actingAs($this->user)->putJson("/api/crm/contacts/{$contactId}", [
            'department' => 'Product Strategy',
            'decision_maker_level' => 'VP',
        ]);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contactId,
            'department' => 'Product Strategy',
            'decision_maker_level' => 'VP',
        ]);
    }

    /**
     * Test circular relationship prevention.
     */
    public function test_circular_relationship_prevention()
    {
        $contactA = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@test.com',
        ]);

        $contactB = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@test.com',
        ]);

        $contactC = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@test.com',
        ]);

        // Establish relationship: A manages B
        $rel1 = ContactRelationship::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contactA->id,
            'related_contact_id' => $contactB->id,
            'type' => 'manager',
        ]);

        // Establish relationship: B manages C
        $rel2 = ContactRelationship::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contactB->id,
            'related_contact_id' => $contactC->id,
            'type' => 'manager',
        ]);

        // Try to establish: C manages A (Creating circular loop charlie -> alice -> bob -> charlie)
        $this->assertTrue(ContactRelationship::wouldCreateCircularGraph($contactC->id, $contactA->id));

        $response = $this->actingAs($this->user)->postJson("/api/crm/contacts/{$contactC->id}/relationships", [
            'related_contact_id' => $contactA->id,
            'type' => 'manager',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Circular relationship graph detected. This relationship cannot be established to avoid loops.',
        ]);
    }

    /**
     * Test managing multiple contact communication methods.
     */
    public function test_multi_contact_methods_management()
    {
        $contact = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@test.com',
        ]);

        // Add extra email
        $response = $this->actingAs($this->user)->postJson("/api/crm/contacts/{$contact->id}/methods", [
            'type' => 'email',
            'value' => 'alice.work@test.com',
            'label' => 'work',
            'is_primary' => true,
            'is_verified' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_contact_methods', [
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'alice.work@test.com',
            'is_primary' => true,
        ]);

        // Add secondary phone
        $responsePhone = $this->actingAs($this->user)->postJson("/api/crm/contacts/{$contact->id}/methods", [
            'type' => 'phone',
            'value' => '+254700000000',
            'label' => 'whatsapp',
            'is_primary' => false,
            'is_verified' => false,
        ]);

        $responsePhone->assertStatus(201);
        $methodId = $responsePhone->json('data.id');

        // Update phone to verified and primary
        $updateResponse = $this->actingAs($this->user)->putJson("/api/crm/contacts/{$contact->id}/methods/{$methodId}", [
            'is_primary' => true,
            'is_verified' => true,
        ]);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('crm_contact_methods', [
            'id' => $methodId,
            'is_primary' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * Test bulk tagging and bulk updates.
     */
    public function test_bulk_operations()
    {
        $contact1 = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'One',
            'last_name' => 'Contact',
            'email' => 'one@test.com',
        ]);

        $contact2 = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Two',
            'last_name' => 'Contact',
            'email' => 'two@test.com',
        ]);

        // Test bulk update
        $responseUpdate = $this->actingAs($this->user)->postJson('/api/crm/contacts/bulk-update', [
            'ids' => [$contact1->id, $contact2->id],
            'data' => [
                'department' => 'Operations Support',
                'buying_influence' => 'Champion',
            ],
        ]);

        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contact1->id,
            'department' => 'Operations Support',
        ]);
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contact2->id,
            'department' => 'Operations Support',
        ]);

        // Test bulk tag
        $responseTag = $this->actingAs($this->user)->postJson('/api/crm/contacts/bulk-tag', [
            'ids' => [$contact1->id, $contact2->id],
            'tags' => ['VIP', 'Enterprise'],
            'action' => 'add',
        ]);

        $responseTag->assertStatus(200);
        $this->assertEquals(2, $contact1->tags()->count());
        $this->assertEquals(2, $contact2->tags()->count());

        // Test bulk archive (soft-delete)
        $responseArchive = $this->actingAs($this->user)->postJson('/api/crm/contacts/bulk-archive', [
            'ids' => [$contact1->id, $contact2->id],
        ]);

        $responseArchive->assertStatus(200);
        $this->assertSoftDeleted('crm_contacts', ['id' => $contact1->id]);
        $this->assertSoftDeleted('crm_contacts', ['id' => $contact2->id]);
    }

    /**
     * Test contact health recalculation score.
     */
    public function test_contact_health_recalculation()
    {
        $contact = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@test.com',
            'decision_maker_level' => 'C-Level',
            'buying_influence' => 'Decision Maker',
            'preferred_language' => 'en',
            'timezone' => 'Africa/Nairobi',
        ]);

        // Run health calculation
        $response = $this->actingAs($this->user)->postJson("/api/crm/contacts/{$contact->id}/recalculate-health");

        $response->assertStatus(200);
        
        $contact->refresh();
        $this->assertNotNull($contact->health_score);
        $this->assertEquals('Healthy', $contact->health_status);
        $this->assertGreaterThanOrEqual(70, $contact->health_score);
    }
}
