<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\Services\MarketplaceNewsletterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketplaceNewsletterController extends Controller
{
    protected MarketplaceNewsletterService $newsletterService;

    public function __construct(MarketplaceNewsletterService $newsletterService)
    {
        $this->newsletterService = $newsletterService;
    }

    /**
     * Subscribe a visitor's email address.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $visitorId = $request->cookie('juanet_visitor_id');
        $sessionId = $request->cookie('juanet_session_id');

        $this->newsletterService->subscribe($validated['email'], $visitorId, $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for subscribing! We will send you exclusive product offers.',
        ], 200);
    }
}
