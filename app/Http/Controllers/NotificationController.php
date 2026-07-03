<?php

namespace App\Http\Controllers;

use App\Services\NotificationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationServiceInterface $service;
    protected \App\Services\TenantContext $tenantContext;

    public function __construct(
        NotificationServiceInterface $service,
        \App\Services\TenantContext $tenantContext
    ) {
        $this->service = $service;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get list of notifications for the authenticated user (optionally filtered by organization/unread).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $unreadOnly = $request->boolean('unread_only', false);
        $orgId = $request->input('organization_id');

        $notifications = $this->service->getUserNotifications($user->id, $orgId, $unreadOnly);

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $success = $this->service->markAsRead($id);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found or already read.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $request->input('organization_id');

        $this->service->markAllAsRead($user->id, $orgId);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get notification preferences.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $request->input('organization_id');

        $prefs = $this->service->getPreferences($user->id, $orgId);

        return response()->json([
            'status' => 'success',
            'data' => $prefs,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $request->input('organization_id');

        $validated = $request->validate([
            'channels' => 'required|array',
            'channels.database' => 'boolean',
            'channels.email' => 'boolean',
            'channels.toast' => 'boolean',
            'categories' => 'required|array',
            'categories.system' => 'boolean',
            'categories.billing' => 'boolean',
            'categories.crm' => 'boolean',
            'categories.security' => 'boolean',
        ]);

        $prefs = $this->service->updatePreferences(
            $user->id,
            $validated['channels'],
            $validated['categories'],
            $orgId
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Preferences updated successfully.',
            'data' => $prefs,
        ]);
    }

    /**
     * Endpoint to test/trigger a notification for demonstration/real-time verification.
     */
    public function triggerTest(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $request->input('organization_id');

        $notification = $this->service->send(
            $user->id,
            $request->input('title', 'Test Notification'),
            $request->input('body', 'This is a test notification payload.'),
            $request->input('type', 'info'),
            $request->input('category', 'system'),
            $request->input('priority', 'normal'),
            $orgId,
            $request->input('data', [])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Test notification dispatched.',
            'data' => $notification,
        ]);
    }

    /**
     * Show the web In-App Notification Center and preference dashboard.
     */
    public function webIndex()
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        $notifications = $this->service->getUserNotifications($user->id, $orgId);
        $preferences = $this->service->getPreferences($user->id, $orgId);

        return view('notifications.index', [
            'notifications' => $notifications,
            'preferences' => $preferences,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }
}
