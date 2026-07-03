<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\SearchablePlaceholder;
use App\Models\SearchIndex;
use App\Models\Role;
use App\Models\Permission;
use App\Services\SearchServiceInterface;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('saving a searchable model automatically creates a search index entry', function () {
    $org = Organization::create([
        'name' => 'Search Enterprise Corp',
        'domain' => 'searchenterprise.com',
    ]);

    // Create a SearchablePlaceholder which implements SearchableInterface and uses Searchable trait
    $lead = SearchablePlaceholder::create([
        'organization_id' => $org->id,
        'name' => 'John Fulltext Doe',
        'email' => 'john.fulltext@example.com',
        'status' => 'new',
    ]);

    // Assert a search index record was automatically inserted via Eloquent saving hooks
    $this->assertDatabaseHas('search_indexes', [
        'searchable_type' => SearchablePlaceholder::class,
        'searchable_id' => $lead->id,
        'organization_id' => $org->id,
        'title' => 'John Fulltext Doe',
        'module' => 'general',
    ]);

    // Delete the model and assert the search index is automatically removed
    $lead->delete();

    $this->assertDatabaseMissing('search_indexes', [
        'searchable_type' => SearchablePlaceholder::class,
        'searchable_id' => $lead->id,
    ]);
});

test('global search filters results based on active tenant isolation', function () {
    $org1 = Organization::create(['name' => 'Acme Organization', 'domain' => 'acme.com']);
    $org2 = Organization::create(['name' => 'Stark Organization', 'domain' => 'stark.com']);

    // Create placeholder in Acme
    SearchablePlaceholder::create([
        'organization_id' => $org1->id,
        'name' => 'Thorin Oakenshield',
        'email' => 'thorin@acme.com',
        'status' => 'contacted',
    ]);

    // Create placeholder in Stark
    SearchablePlaceholder::create([
        'organization_id' => $org2->id,
        'name' => 'Thorin Shield Armor',
        'email' => 'armor@stark.com',
        'status' => 'new',
    ]);

    $service = app(SearchServiceInterface::class);

    // Search inside Acme's organization context
    $acmeResults = $service->search('Thorin', [], $org1->id);

    // Should ONLY return Thorin Oakenshield from Acme
    expect($acmeResults->count())->toBe(1);
    expect($acmeResults->first()->title)->toBe('Thorin Oakenshield');
    expect($acmeResults->first()->module)->toBe('general');

    // Search inside Stark's organization context
    $starkResults = $service->search('Thorin', [], $org2->id);

    // Should ONLY return Thorin Shield Armor from Stark
    expect($starkResults->count())->toBe(1);
    expect($starkResults->first()->title)->toBe('Thorin Shield Armor');
    expect($starkResults->first()->module)->toBe('general');
});

test('global search enforces role permission boundaries strictly', function () {
    $org = Organization::create(['name' => 'Secure Org', 'domain' => 'secure.com']);

    // Create a SearchablePlaceholder (requires 'view_leads' permission in SearchIndex)
    SearchablePlaceholder::create([
        'organization_id' => $org->id,
        'name' => 'Classified Lead Prospect',
        'email' => 'classified@prospect.com',
        'status' => 'won',
    ]);

    // Create a User with no permissions
    $user = User::create([
        'name' => 'Regular Employee',
        'email' => 'employee@secure.com',
        'password' => bcrypt('password123'),
    ]);

    Auth::login($user);

    $service = app(SearchServiceInterface::class);

    // Query 'Classified' - user has no roles or permissions inside organization
    $resultsWithoutPermission = $service->search('Classified', [], $org->id);
    expect($resultsWithoutPermission->count())->toBe(0);

    // Grant 'view_leads'
    $permission = Permission::create([
        'name' => 'View Leads',
        'slug' => 'view_leads',
        'module' => 'crm',
    ]);

    $role = Role::create([
        'organization_id' => $org->id,
        'name' => 'Lead Agent',
        'slug' => 'lead_agent',
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id, ['organization_id' => $org->id]);

    // Search again
    $resultsWithSomePermissions = $service->search('Classified', [], $org->id);

    // Should view the placeholder
    expect($resultsWithSomePermissions->count())->toBe(1);
    expect($resultsWithSomePermissions->first()->title)->toBe('Classified Lead Prospect');
    expect($resultsWithSomePermissions->first()->module)->toBe('general');
});

test('autocomplete and global search endpoints respond with structured API contracts', function () {
    $org = Organization::create(['name' => 'API Corp', 'domain' => 'api.com']);

    SearchablePlaceholder::create([
        'organization_id' => $org->id,
        'name' => 'Samantha Rogers',
        'email' => 'samantha@api.com',
        'status' => 'new',
    ]);

    // Set Tenant Context and authenticate
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenant($org);

    $user = User::create([
        'name' => 'API Admin',
        'email' => 'admin@api.com',
        'password' => bcrypt('password123'),
    ]);

    // Grant leads permissions so we can search
    $permission = Permission::create([
        'name' => 'View Leads',
        'slug' => 'view_leads',
        'module' => 'crm',
    ]);
    $role = Role::create([
        'organization_id' => $org->id,
        'name' => 'Admin Agent',
        'slug' => 'admin_agent',
    ]);
    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id, ['organization_id' => $org->id]);

    $response = $this->actingAs($user)
        ->getJson("/api/search?q=Samantha");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'query',
            'tenant_id',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'searchable_id',
                    'searchable_type',
                    'module',
                    'title',
                    'description',
                    'url',
                    'score',
                    'highlight',
                    'created_at',
                ]
            ]
        ]);

    // Verify autocomplete returns lightweight data contract
    $autocompleteResponse = $this->actingAs($user)
        ->getJson("/api/search/autocomplete?q=Sam");

    $autocompleteResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'query',
            'tenant_id',
            'count',
            'data'
        ]);
});

test('bulk index rebuilding correctly processes all models', function () {
    $org = Organization::create(['name' => 'Rebuild Corp', 'domain' => 'rebuild.com']);

    // Clear search_indexes table to test rebuilding
    SearchIndex::query()->delete();

    // Bypass lifecycle auto-indexing momentarily to simulate unindexed rows
    SearchablePlaceholder::withoutEvents(function () use ($org) {
        SearchablePlaceholder::create([
            'organization_id' => $org->id,
            'name' => 'Orphaned Prospect 1',
            'email' => 'orphan1@rebuild.com',
        ]);
    });

    // Make sure search indexes are indeed empty
    expect(SearchIndex::count())->toBe(0);

    // Execute bulk reindex rebuild
    $service = app(SearchServiceInterface::class);
    $reindexedCount = $service->reindexAll();

    expect($reindexedCount)->toBe(1);
    $this->assertDatabaseHas('search_indexes', ['title' => 'Orphaned Prospect 1']);
});
