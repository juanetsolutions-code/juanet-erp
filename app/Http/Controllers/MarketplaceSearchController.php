<?php

namespace App\Http\Controllers;

use App\Contracts\EventBus;
use App\Domain\Marketplace\Events\SearchPerformed;
use App\Domain\Marketplace\Events\FilterApplied;
use App\Domain\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketplaceSearchController extends Controller
{
    protected MarketplaceSearchService $searchService;
    protected EventBus $eventBus;

    public function __construct(MarketplaceSearchService $searchService, EventBus $eventBus)
    {
        $this->searchService = $searchService;
        $this->eventBus = $eventBus;
    }

    /**
     * Handle search/filter queries (API & Web compatible).
     */
    public function search(Request $request)
    {
        // 1. Collect inputs
        $filters = $request->only([
            'search', 'category', 'technology', 'min_price', 'max_price',
            'free', 'premium', 'on_sale', 'newest', 'popular', 'featured', 'rating'
        ]);

        // 2. Fetch matches
        $results = $this->searchService->search($filters);

        // 3. Track visitor session context
        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        // 4. Dispatch Domain Events
        if ($request->filled('search')) {
            $this->eventBus->dispatch(new SearchPerformed(
                $request->input('search'),
                $results->count(),
                $visitorId,
                $sessionId
            ));
        }

        // Check if any filter keys are set
        $activeFilters = array_filter($filters, function ($val, $key) {
            return $key !== 'search' && !is_null($val) && $val !== '';
        }, ARRAY_FILTER_USE_BOTH);

        if (!empty($activeFilters)) {
            $this->eventBus->dispatch(new FilterApplied(
                $activeFilters,
                $results->count(),
                $visitorId,
                $sessionId
            ));
        }

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'count' => $results->count(),
                'products' => $results,
            ]);
        }

        // Return to standard page view with filtering set
        return view('marketplace', [
            'featured_products' => $results,
            'categories' => collect(), // Loaded dynamically
            'trending_products' => collect(),
            'newest_products' => collect(),
            'best_sellers' => collect(),
            'search_filters' => $filters
        ]);
    }
}
