<?php

namespace App\Domain\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Notification\Services\NotificationService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    protected NotificationService $service;
    protected TenantContext $tenantContext;

    public function __construct(
        NotificationService $service,
        TenantContext $tenantContext
    ) {
        $this->service = $service;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get preferences for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        $preferences = $this->service->getPreferences($user->id, $orgId);

        return response()->json([
            'status' => 'success',
            'data' => $preferences,
        ]);
    }

    /**
     * Update preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        $validated = $request->validate([
            'channels' => 'required|array',
            'categories' => 'required|array',
        ]);

        $preferences = $this->service->updatePreferences(
            $user->id,
            $validated['channels'],
            $validated['categories'],
            $orgId
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Notification preferences updated successfully.',
            'data' => $preferences,
        ]);
    }
}
