<?php

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use App\Domain\Marketplace\Contracts\MarketplaceCategoryRepositoryInterface;
use App\Domain\Marketplace\Events\MarketplaceOpened;
use App\Domain\Marketplace\Events\CategoryViewed;
use App\Domain\Marketplace\Events\NewsletterSubmitted;
use App\Domain\Marketplace\Events\SearchPerformed;
use App\Domain\Marketplace\Events\FilterApplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('marketplace storefront page loads successfully and contains SEO meta tags', function () {
    $response = $this->get(route('marketplace'));

    $response->assertStatus(200);
    $response->assertSee('Digital Products &amp; Code Marketplace — JUANET', false);
    $response->assertSee('Discover premium pre-built website templates, enterprise Laravel starter kits, Tailwind admin dashboards, design systems, and developer prompt resources.', false);
});

test('marketplace storefront page renders featured products and categories', function () {
    $response = $this->get(route('marketplace'));

    $response->assertStatus(200);
    // Asserts existing sample product/category renders
    $response->assertSee('Laravel SaaS Starter Kit');
    $response->assertSee('Enterprise CRM Boilerplate');
    $response->assertSee('Restaurant POS');
});

test('marketplace search API returns filtered JSON products', function () {
    $response = $this->getJson('/api/marketplace/search?search=Boilerplate');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'count',
        'products'
    ]);

    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['products'][0]['title'])->toContain('Enterprise CRM Boilerplate');
});

test('marketplace attribute filtering returns correct price bounded results', function () {
    // KES 20,000 to KES 30,000 price range (Enterprise CRM Boilerplate and Property Management System fit here)
    $response = $this->getJson('/api/marketplace/search?min_price=20000&max_price=30000');

    $response->assertStatus(200);
    $data = $response->json();
    
    foreach ($data['products'] as $product) {
        expect($product['price'])->toBeGreaterThanOrEqual(20000);
        expect($product['price'])->toBeLessThanOrEqual(30000);
    }
});

test('submitting newsletter subscription triggers EventBus event and returns JSON response', function () {
    $response = $this->postJson('/api/marketplace/newsletter', [
        'email' => 'subscriber@enterprise.com'
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Thank you for subscribing! We will send you exclusive product offers.'
    ]);

    // Since our MarketplaceNewsletterService dispatches the event,
    // we assert that it got saved as a queued event into our outbox table!
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.newsletter.submitted',
    ]);
});

test('accessing marketplace storefront registers visitor cookies and publishes tracking events', function () {
    $response = $this->get(route('marketplace'));

    $response->assertCookie('juanet_visitor_id');
    $response->assertCookie('juanet_session_id');

    // Assert that the 'marketplace.opened' event was stored in the Outbox!
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.opened'
    ]);
});

test('filtering by category publishes category viewed event', function () {
    $response = $this->get(route('marketplace', ['category' => 'laravel']));

    $response->assertStatus(200);

    // Assert category viewed event was stored in the Outbox
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.category.viewed'
    ]);
});

test('marketplace category landing page loads and displays breadcrumbs, products and sidebar', function () {
    $response = $this->get(route('marketplace.category', ['slug' => 'laravel']));

    $response->assertStatus(200);
    $response->assertSee('Laravel Hub');
    $response->assertSee('Marketplace');
    $response->assertSee('Safaricom Daraja M-PESA');
    $response->assertSee('Curated Collections');
    $response->assertSee('Discover Categories');

    // Assert that the 'marketplace.category.viewed' event was stored in the Outbox
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.category.viewed',
    ]);
});

test('marketplace advanced filters and sorting return correct filtered sets', function () {
    // Filter by framework Laravel
    $response = $this->getJson('/api/marketplace/search?framework=Laravel');
    $response->assertStatus(200);
    $data = $response->json();
    foreach ($data['products'] as $product) {
        expect($product['framework'])->toBe('Laravel');
    }

    // Filter by license Regular License
    $response = $this->getJson('/api/marketplace/search?license=Regular');
    $response->assertStatus(200);
    $data = $response->json();
    foreach ($data['products'] as $product) {
        expect($product['license'])->toContain('Regular License');
    }

    // Sort by best_selling
    $response = $this->getJson('/api/marketplace/search?sort=best_selling');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    if ($data['count'] > 1) {
        expect($data['products'][0]['downloads'])->toBeGreaterThanOrEqual($data['products'][1]['downloads']);
    }
});

test('marketplace product detail page loads and displays visual details, features and seo markup', function () {
    $response = $this->get(route('marketplace.product', ['slug' => 'admin-dashboard']));

    $response->assertStatus(200);
    $response->assertSee('Admin Dashboard');
    $response->assertSee('JUANET Enterprise Marketplace');
    $response->assertSee('Personal License');
    $response->assertSee('Commercial License');
    $response->assertSee('Extended License');
    
    // Check that gallery structure exists
    $response->assertSee('Product image preview');
    
    // Check that JSON-LD Product schema exists
    $response->assertSee('@type": "Product"');
    $response->assertSee('SKU-');
    
    // Assert that the 'marketplace.product.viewed' event was stored in the Outbox
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.product.viewed',
    ]);
});

test('marketplace event tracking api dispatches events to outbox correctly', function () {
    // 1. Track gallery view
    $response = $this->postJson(route('marketplace.track'), [
        'event_name' => 'marketplace.gallery.viewed',
        'product_slug' => 'admin-dashboard',
        'additional_data' => ['action' => 'next', 'index' => 1]
    ]);
    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.gallery.viewed'
    ]);

    // 2. Track license selected
    $response = $this->postJson(route('marketplace.track'), [
        'event_name' => 'marketplace.license.selected',
        'product_slug' => 'admin-dashboard',
        'additional_data' => ['license_type' => 'commercial', 'price' => 14400]
    ]);
    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.license.selected'
    ]);

    // 3. Track purchase initiated
    $response = $this->postJson(route('marketplace.track'), [
        'event_name' => 'marketplace.purchase.initiated',
        'product_slug' => 'admin-dashboard',
        'additional_data' => ['license_type' => 'extended', 'quantity' => 1, 'price' => 28000]
    ]);
    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('event_outboxes', [
        'event_name' => 'marketplace.purchase.initiated'
    ]);
});


