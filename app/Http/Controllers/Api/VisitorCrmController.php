<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crm\VisitorTrackerService;
use App\Services\Crm\VisitorAnalyticsService;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VisitorCrmController extends Controller
{
    protected VisitorTrackerService $trackerService;
    protected VisitorAnalyticsService $analyticsService;
    protected TenantContext $tenantContext;

    public function __construct(
        VisitorTrackerService $trackerService,
        VisitorAnalyticsService $analyticsService,
        TenantContext $tenantContext
    ) {
        $this->trackerService = $trackerService;
        $this->analyticsService = $analyticsService;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get aggregated visitor metrics for the CRM.
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $metrics = $this->analyticsService->getDashboardMetrics($tenantId);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ], 200);
    }

    /**
     * List visitors for the CRM with pagination and tenant isolation.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $query = Visitor::withCount(['sessions', 'pageViews', 'leads']);

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        $visitors = $query->orderBy('last_seen_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $visitors,
        ], 200);
    }

    /**
     * Build and return a chronological timeline of a visitor's complete journey.
     */
    public function timeline(string $visitorId): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $visitor = Visitor::where('id', $visitorId);
        
        if ($tenantId) {
            $visitor->where('organization_id', $tenantId);
        }

        $visitorModel = $visitor->first();

        if (!$visitorModel) {
            return response()->json([
                'success' => false,
                'error' => 'Visitor not found or unauthorized access.',
            ], 404);
        }

        // Gather all activities
        $timeline = [];

        // 1. Visitor first seen
        $timeline[] = [
            'type' => 'visitor_created',
            'title' => 'First Identified',
            'description' => "Visitor first seen from {$visitorModel->city}, {$visitorModel->country} using {$visitorModel->browser} on {$visitorModel->operating_system}",
            'timestamp' => $visitorModel->first_seen_at->toIso8601String(),
            'metadata' => [
                'device_type' => $visitorModel->device_type,
                'utm_source' => $visitorModel->utm_source,
                'utm_medium' => $visitorModel->utm_medium,
                'utm_campaign' => $visitorModel->utm_campaign,
            ]
        ];

        // 2. Add sessions
        $sessions = VisitorSession::where('visitor_id', $visitorId)->get();
        foreach ($sessions as $session) {
            $timeline[] = [
                'type' => 'session_started',
                'title' => 'Session Started',
                'description' => "Started new browsing session. Referrer: " . ($session->referrer ?? 'Direct'),
                'timestamp' => $session->start_time->toIso8601String(),
                'metadata' => [
                    'session_id' => $session->id,
                    'landing_page' => $session->landing_page,
                    'returning' => $session->returning_visitor,
                ]
            ];

            if ($session->end_time) {
                $timeline[] = [
                    'type' => 'session_ended',
                    'title' => 'Session Ended',
                    'description' => "Ended browsing session. Duration: {$session->duration} seconds across {$session->pages_visited} pages",
                    'timestamp' => $session->end_time->toIso8601String(),
                    'metadata' => [
                        'session_id' => $session->id,
                        'exit_page' => $session->exit_page,
                        'bounce' => $session->bounce,
                    ]
                ];
            }
        }

        // 3. Add page views and CTA clicks
        $pageViews = VisitorPageView::where('visitor_id', $visitorId)->get();
        foreach ($pageViews as $pv) {
            $timeline[] = [
                'type' => 'page_view',
                'title' => 'Page Viewed',
                'description' => "Viewed: {$pv->page_title}",
                'timestamp' => $pv->timestamp->toIso8601String(),
                'metadata' => [
                    'url' => $pv->url,
                    'route_name' => $pv->route_name,
                    'scroll_depth' => $pv->scroll_depth,
                ]
            ];

            $ctas = $pv->cta_clicks ?? [];
            foreach ($ctas as $cta) {
                $timeline[] = [
                    'type' => 'cta_click',
                    'title' => 'CTA Clicked',
                    'description' => "Clicked button '{$cta['label']}' (ID: {$cta['cta_id']})",
                    'timestamp' => Carbon::parse($cta['clicked_at'])->toIso8601String(),
                    'metadata' => [
                        'url' => $pv->url,
                        'cta_id' => $cta['cta_id'],
                    ]
                ];
            }
        }

        // 4. Add Lead conversions
        $leads = $visitorModel->leads;
        foreach ($leads as $lead) {
            $timeline[] = [
                'type' => 'lead_converted',
                'title' => 'Converted to Lead',
                'description' => "Visitor converted to Lead: {$lead->name} ({$lead->email})",
                'timestamp' => $lead->created_at->toIso8601String(),
                'metadata' => [
                    'lead_id' => $lead->id,
                    'status' => $lead->status,
                ]
            ];
        }

        // Sort timeline chronologically (newest first)
        usort($timeline, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return response()->json([
            'success' => true,
            'visitor' => $visitorModel,
            'timeline' => $timeline,
        ], 200);
    }

    /**
     * Anonymize visitor records (GDPR compliance).
     */
    public function anonymize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => 'required|uuid',
        ]);

        $tenantId = $this->tenantContext->getTenantId();
        $visitor = Visitor::where('id', $validated['visitor_id']);
        
        if ($tenantId) {
            $visitor->where('organization_id', $tenantId);
        }

        $visitorModel = $visitor->first();

        if (!$visitorModel) {
            return response()->json([
                'success' => false,
                'error' => 'Visitor not found or unauthorized.',
            ], 404);
        }

        $this->trackerService->anonymize($visitorModel->id);

        return response()->json([
            'success' => true,
            'message' => 'Visitor data successfully anonymized for GDPR compliance.',
        ], 200);
    }

    /**
     * Soft delete visitor records (GDPR compliance / data retention).
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => 'required|uuid',
        ]);

        $tenantId = $this->tenantContext->getTenantId();
        $visitor = Visitor::where('id', $validated['visitor_id']);
        
        if ($tenantId) {
            $visitor->where('organization_id', $tenantId);
        }

        $visitorModel = $visitor->first();

        if (!$visitorModel) {
            return response()->json([
                'success' => false,
                'error' => 'Visitor not found or unauthorized.',
            ], 404);
        }

        $visitorModel->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Visitor record successfully soft-deleted for GDPR compliance.',
        ], 200);
    }

    /**
     * Export complete visitor dossier (GDPR export request).
     */
    public function export(string $visitorId): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $visitor = Visitor::where('id', $visitorId);
        
        if ($tenantId) {
            $visitor->where('organization_id', $tenantId);
        }

        $visitorModel = $visitor->first();

        if (!$visitorModel) {
            return response()->json([
                'success' => false,
                'error' => 'Visitor not found or unauthorized.',
            ], 404);
        }

        $sessions = VisitorSession::where('visitor_id', $visitorId)->get();
        $pageViews = VisitorPageView::where('visitor_id', $visitorId)->get();

        return response()->json([
            'success' => true,
            'export' => [
                'visitor' => $visitorModel,
                'sessions' => $sessions,
                'page_views' => $pageViews,
                'exported_at' => now()->toIso8601String(),
            ]
        ], 200);
    }
}
