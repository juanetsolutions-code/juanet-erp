<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crm\VisitorTrackerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VisitorTrackingController extends Controller
{
    protected VisitorTrackerService $trackerService;

    public function __construct(VisitorTrackerService $trackerService)
    {
        $this->trackerService = $trackerService;
    }

    /**
     * Track a visitor page view and update session/visitor state.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|string',
            'route_name' => 'nullable|string',
            'page_title' => 'nullable|string',
            'referrer' => 'nullable|string',
            'time_on_page' => 'nullable|integer',
            'scroll_depth' => 'nullable|integer',
            'browser' => 'nullable|string',
            'operating_system' => 'nullable|string',
            'device_type' => 'nullable|string',
            'screen_resolution' => 'nullable|string',
            'viewport' => 'nullable|string',
            'timezone' => 'nullable|string',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
            'utm_term' => 'nullable|string',
            'utm_content' => 'nullable|string',
            'cookie_consent' => 'nullable|string',
            'dnt' => 'nullable|boolean',
        ]);

        $result = $this->trackerService->track($request, $validated);

        $response = response()->json([
            'success' => true,
            'visitor_id' => $result['visitor_id'],
            'session_id' => $result['session_id'],
            'page_view_id' => $result['page_view_id'],
            'is_new_visitor' => $result['is_new_visitor'],
            'is_new_session' => $result['is_new_session'],
        ], 200);

        // Queue cookies to be attached to the response (365 days for visitor_id, 30 mins for session_id)
        $response->cookie('juanet_visitor_id', $result['visitor_id'], 60 * 24 * 365, '/', null, true, true, false, 'None');
        $response->cookie('juanet_session_id', $result['session_id'], 30, '/', null, true, true, false, 'None');

        return $response;
    }

    /**
     * Track CTA button click.
     */
    public function trackCta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_view_id' => 'required|uuid',
            'cta_id' => 'required|string',
            'label' => 'required|string',
        ]);

        $correlationId = $request->header('X-Correlation-ID');
        $this->trackerService->trackCta($validated['page_view_id'], $validated, $correlationId);

        return response()->json([
            'success' => true,
            'message' => 'CTA button click tracked successfully.',
        ], 200);
    }

    /**
     * Terminate visitor browsing session.
     */
    public function endSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|uuid',
        ]);

        $correlationId = $request->header('X-Correlation-ID');
        $this->trackerService->endSession($validated['session_id'], $correlationId);

        return response()->json([
            'success' => true,
            'message' => 'Session ended successfully.',
        ], 200);
    }
}
