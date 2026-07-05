<?php

namespace App\Http\Controllers;

use App\Contracts\EventBus;
use App\Domain\Marketplace\Events\MarketplaceOpened;
use App\Domain\Marketplace\Events\CategoryViewed;
use App\Domain\Marketplace\Events\QuickPreviewOpened;
use App\Domain\Marketplace\Events\ProductViewed;
use App\Domain\Marketplace\Events\GalleryViewed;
use App\Domain\Marketplace\Events\DocumentationViewed;
use App\Domain\Marketplace\Events\DemoLaunched;
use App\Domain\Marketplace\Events\DocDownloaded;
use App\Domain\Marketplace\Events\LicenseSelected;
use App\Domain\Marketplace\Events\WishlistClicked;
use App\Domain\Marketplace\Events\ShareClicked;
use App\Domain\Marketplace\Events\PurchaseInitiated;
use App\Domain\Marketplace\Services\MarketplaceHomepageService;
use App\Domain\Marketplace\Services\MarketplaceCategoryService;
use App\Domain\Marketplace\Services\MarketplaceFeaturedService;
use App\Domain\Marketplace\Services\MarketplaceRecommendationService;
use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    protected MarketplaceHomepageService $homepageService;
    protected MarketplaceCategoryService $categoryService;
    protected MarketplaceFeaturedService $featuredService;
    protected MarketplaceRecommendationService $recommendationService;
    protected MarketplaceProductRepositoryInterface $productRepo;
    protected EventBus $eventBus;

    public function __construct(
        MarketplaceHomepageService $homepageService,
        MarketplaceCategoryService $categoryService,
        MarketplaceFeaturedService $featuredService,
        MarketplaceRecommendationService $recommendationService,
        MarketplaceProductRepositoryInterface $productRepo,
        EventBus $eventBus
    ) {
        $this->homepageService = $homepageService;
        $this->categoryService = $categoryService;
        $this->featuredService = $featuredService;
        $this->recommendationService = $recommendationService;
        $this->productRepo = $productRepo;
        $this->eventBus = $eventBus;
    }

    /**
     * Render the main Marketplace homepage.
     */
    public function index(Request $request)
    {
        // 1. Resolve visitor/session IDs
        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        if (!$visitorId) {
            $visitorId = 'vis_' . Str::uuid();
            $request->cookies->set('juanet_visitor_id', $visitorId);
        }
        if (!$sessionId) {
            $sessionId = 'ses_' . Str::uuid();
            $request->cookies->set('juanet_session_id', $sessionId);
        }

        // 2. Load Homepage Data
        $data = $this->homepageService->getHomepageData();

        // 3. Dispatch MarketplaceOpened Domain Event
        $this->eventBus->dispatch(new MarketplaceOpened($visitorId, $sessionId));

        // 4. Handle specific query actions for tracking (Category viewed or specific Product preview)
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $this->eventBus->dispatch(new CategoryViewed($categorySlug, $visitorId, $sessionId));
        }

        $activeProduct = null;
        $recommendations = collect();
        if ($request->filled('product')) {
            $productSlug = $request->input('product');
            $activeProduct = $this->productRepo->findBySlug($productSlug);
            if ($activeProduct) {
                $this->eventBus->dispatch(new QuickPreviewOpened(
                    $activeProduct->slug,
                    $activeProduct->id,
                    $visitorId,
                    $sessionId
                ));
                $recommendations = $this->recommendationService->getRecommendationsForProduct($activeProduct->id);
            }
        }

        // We prepare our response
        $response = response()->view('marketplace', array_merge($data, [
            'active_product' => $activeProduct,
            'recommendations' => $recommendations,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]));

        // Attach cookies to maintain the session
        $response->headers->setCookie(cookie('juanet_visitor_id', $visitorId, 60 * 24 * 365, '/', null, true, true, false, 'None'));
        $response->headers->setCookie(cookie('juanet_session_id', $sessionId, 30, '/', null, true, true, false, 'None'));

        return $response;
    }

    /**
     * Render a specific Category page.
     */
    public function categoryShow(Request $request, string $slug)
    {
        // 1. Resolve visitor/session IDs
        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        if (!$visitorId) {
            $visitorId = 'vis_' . Str::uuid();
            $request->cookies->set('juanet_visitor_id', $visitorId);
        }
        if (!$sessionId) {
            $sessionId = 'ses_' . Str::uuid();
            $request->cookies->set('juanet_session_id', $sessionId);
        }

        // 2. Fetch Category details
        $category = $this->categoryService->getCategoryBySlug($slug);
        if (!$category) {
            abort(404, 'Category not found');
        }

        // 3. Dispatch CategoryViewed Domain Event
        $this->eventBus->dispatch(new CategoryViewed($slug, $visitorId, $sessionId));

        // 4. Fetch Products and Categories
        $categories = $this->categoryService->getAllCategories();
        $products = $this->productRepo->search(['category' => $slug]);
        $featuredProducts = $this->productRepo->getFeatured(3);

        // We prepare our response
        $response = response()->view('marketplace_category', [
            'category' => $category,
            'categories' => $categories,
            'initial_products' => $products,
            'featured_products' => $featuredProducts,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);

        // Attach cookies to maintain the session
        $response->headers->setCookie(cookie('juanet_visitor_id', $visitorId, 60 * 24 * 365, '/', null, true, true, false, 'None'));
        $response->headers->setCookie(cookie('juanet_session_id', $sessionId, 30, '/', null, true, true, false, 'None'));

        return $response;
    }

    /**
     * Render a specific Product page.
     */
    public function productShow(Request $request, string $slug)
    {
        // 1. Resolve visitor/session IDs
        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        if (!$visitorId) {
            $visitorId = 'vis_' . Str::uuid();
            $request->cookies->set('juanet_visitor_id', $visitorId);
        }
        if (!$sessionId) {
            $sessionId = 'ses_' . Str::uuid();
            $request->cookies->set('juanet_session_id', $sessionId);
        }

        // 2. Fetch Product details
        $product = $this->productRepo->findBySlug($slug);
        if (!$product) {
            abort(404, 'Product not found');
        }

        // Ensure category is populated if absent
        if (empty($product->category_name) && !empty($product->category_slug)) {
            $category = $this->categoryService->getCategoryBySlug($product->category_slug);
            if ($category) {
                $product->category_name = $category->name;
            }
        }

        // 3. Dispatch ProductViewed Domain Event
        $this->eventBus->dispatch(new ProductViewed(
            $product->slug,
            $product->id,
            $visitorId,
            $sessionId
        ));

        // 4. Fetch Products and Categories
        $categories = $this->categoryService->getAllCategories();
        $allProducts = $this->productRepo->search([]);

        // 5. Filter related sets
        $sameCategory = $allProducts->filter(fn($p) => ($p->category_slug ?? '') === ($product->category_slug ?? '') && $p->id !== $product->id)->values()->take(4);
        
        $sameTechnology = $allProducts->filter(function($p) use ($product) {
            if ($p->id === $product->id) return false;
            $pTech = $p->technology ?? [];
            $prodTech = $product->technology ?? [];
            if (is_string($pTech)) $pTech = [$pTech];
            if (is_string($prodTech)) $prodTech = [$prodTech];
            $shared = array_intersect($pTech, $prodTech);
            return count($shared) > 0;
        })->values()->take(4);

        $frequentlyBought = $allProducts->filter(fn($p) => $p->id !== $product->id)->shuffle()->values()->take(4);
        $customersViewed = $allProducts->filter(fn($p) => $p->id !== $product->id)->sortByDesc('views')->values()->take(4);
        $newestFromAuthor = $allProducts->filter(fn($p) => ($p->author ?? '') === ($product->author ?? '') && $p->id !== $product->id)->sortByDesc('is_new')->values()->take(4);

        // We prepare our response
        $response = response()->view('marketplace_product', [
            'product' => $product,
            'categories' => $categories,
            'same_category' => $sameCategory,
            'same_technology' => $sameTechnology,
            'frequently_bought' => $frequentlyBought,
            'customers_viewed' => $customersViewed,
            'newest_from_author' => $newestFromAuthor,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);

        // Attach cookies to maintain the session
        $response->headers->setCookie(cookie('juanet_visitor_id', $visitorId, 60 * 24 * 365, '/', null, true, true, false, 'None'));
        $response->headers->setCookie(cookie('juanet_session_id', $sessionId, 30, '/', null, true, true, false, 'None'));

        return $response;
    }

    /**
     * Track user/visitor intelligence actions.
     */
    public function trackEvent(Request $request)
    {
        $eventName = $request->input('event_name');
        $productSlug = $request->input('product_slug', 'generic');
        $data = $request->input('additional_data', []);

        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        if (!$visitorId) $visitorId = 'vis_untracked';
        if (!$sessionId) $sessionId = 'ses_untracked';

        switch ($eventName) {
            case 'marketplace.gallery.viewed':
                $this->eventBus->dispatch(new GalleryViewed($productSlug, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.documentation.viewed':
                $this->eventBus->dispatch(new DocumentationViewed($productSlug, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.demo.launched':
                $demoType = $data['demo_type'] ?? 'generic';
                $this->eventBus->dispatch(new DemoLaunched($productSlug, $demoType, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.download_docs.clicked':
                $this->eventBus->dispatch(new DocDownloaded($productSlug, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.license.selected':
                $licenseType = $data['license_type'] ?? 'Regular License';
                $this->eventBus->dispatch(new LicenseSelected($productSlug, $licenseType, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.wishlist.clicked':
                $added = filter_var($data['added'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $this->eventBus->dispatch(new WishlistClicked($productSlug, $added, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.share.clicked':
                $platform = $data['platform'] ?? 'generic';
                $this->eventBus->dispatch(new ShareClicked($productSlug, $platform, $visitorId, $sessionId, null, $data));
                break;
            case 'marketplace.purchase.initiated':
                $licenseType = $data['license_type'] ?? 'Regular License';
                $quantity = intval($data['quantity'] ?? 1);
                $price = intval($data['price'] ?? 0);
                $this->eventBus->dispatch(new PurchaseInitiated($productSlug, $licenseType, $quantity, $price, $visitorId, $sessionId, null, $data));
                break;
        }

        return response()->json(['success' => true]);
    }
}
