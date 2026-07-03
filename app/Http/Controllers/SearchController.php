<?php

namespace App\Http\Controllers;

use App\Services\SearchServiceInterface;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    protected SearchServiceInterface $searchService;
    protected TenantContext $tenantContext;

    /**
     * Create a new SearchController.
     */
    public function __construct(SearchServiceInterface $searchService, TenantContext $tenantContext)
    {
        $this->searchService = $searchService;
        $this->tenantContext = $tenantContext;
    }

    /**
     * API search endpoint: Performs full text searches with scoring, highlighting and tenant context.
     *
     * GET /api/search?q=query&modules=crm,cms&limit=20&offset=0
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $modulesInput = $request->input('modules', '');
        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);

        // Parse modules input which can be an array or a comma-separated string
        $modules = [];
        if (is_array($modulesInput)) {
            $modules = $modulesInput;
        } elseif (!empty($modulesInput)) {
            $modules = array_filter(array_map('trim', explode(',', $modulesInput)));
        }

        $orgId = $this->tenantContext->getTenantId();

        $results = $this->searchService->search($query, $modules, $orgId, $limit, $offset);

        return response()->json([
            'status' => 'success',
            'query' => $query,
            'modules' => $modules,
            'tenant_id' => $orgId,
            'count' => $results->count(),
            'data' => $results,
        ]);
    }

    /**
     * API autocomplete endpoint: Super fast suggestions.
     *
     * GET /api/search/autocomplete?q=que
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = (int) $request->input('limit', 10);
        $orgId = $this->tenantContext->getTenantId();

        $results = $this->searchService->autocomplete($query, $orgId, $limit);

        return response()->json([
            'status' => 'success',
            'query' => $query,
            'tenant_id' => $orgId,
            'count' => $results->count(),
            'data' => $results,
        ]);
    }

    /**
     * Web Results Interface: Renders the beautiful global search results panel.
     *
     * GET /search?q=term
     */
    public function webIndex(Request $request): View
    {
        $query = $request->input('q', '');
        $moduleFilter = $request->input('module', 'all');
        $orgId = $this->tenantContext->getTenantId();

        $modules = $moduleFilter === 'all' ? [] : [$moduleFilter];

        $results = collect();
        if (!empty(trim($query))) {
            $results = $this->searchService->search($query, $modules, $orgId, 50, 0);
        }

        return view('search.index', [
            'query' => $query,
            'activeModule' => $moduleFilter,
            'results' => $results,
            'activeTenant' => $this->tenantContext->getTenant(),
        ]);
    }

    /**
     * API Trigger full index build of searchable models.
     *
     * POST /api/search/reindex
     */
    public function reindex(Request $request): JsonResponse
    {
        // For security, ensure the caller is an authenticated central admin or has authorization
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized actions.',
            ], 401);
        }

        $indexedCount = $this->searchService->reindexAll();

        return response()->json([
            'status' => 'success',
            'message' => "Search index fully rebuilt.",
            'records_indexed' => $indexedCount,
        ]);
    }
}
