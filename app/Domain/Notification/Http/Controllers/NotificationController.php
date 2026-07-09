<?php

namespace App\Domain\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Notification\Services\NotificationService;
use App\Services\TenantContext;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
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
     * Get user notifications with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $unreadOnly = $request->boolean('unread_only', false);
        $category = $request->input('category');
        $priority = $request->input('priority');
        $search = $request->input('search');
        $orgId = $this->tenantContext->getTenantId();

        $query = Notification::where('user_id', $user->id);

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('body', 'like', '%' . $search . '%');
            });
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
            'unread_count' => Notification::where('user_id', $user->id)->where('is_read', false)->count()
        ]);
    }

    /**
     * Mark a notification as read.
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
        $orgId = $this->tenantContext->getTenantId();

        $this->service->markAllAsRead($user->id, $orgId);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Archive a specific notification (soft status change).
     */
    public function archive(string $id): JsonResponse
    {
        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        // Set is_archived to true inside the json cast data field to preserve backward-compatible table schema!
        $data = $notification->data ?? [];
        $data['is_archived'] = true;
        
        $notification->update(['data' => $data]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification archived successfully.',
        ]);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(string $id): JsonResponse
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->delete();
            return response()->json(['status' => 'success', 'message' => 'Notification deleted successfully.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Notification not found.'], 404);
    }

    /**
     * Show Web Blade interfaces: Notification Center, Notification Settings, Notification History
     */
    public function webIndex(Request $request)
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        // Base notifications excluding archived ones for standard view
        $notificationsQuery = Notification::where('user_id', $user->id)
            ->where(function($q) {
                $q->whereNull('data->is_archived')
                  ->orWhere('data->is_archived', false);
            });

        if ($orgId) {
            $notificationsQuery->where('organization_id', $orgId);
        }

        $notifications = $notificationsQuery->orderBy('created_at', 'desc')->get();

        // History: all notifications including archived ones
        $historyQuery = Notification::where('user_id', $user->id);
        if ($orgId) {
            $historyQuery->where('organization_id', $orgId);
        }
        $history = $historyQuery->orderBy('created_at', 'desc')->get();

        // Get user preferences
        $preferences = $this->service->getPreferences($user->id, $orgId);

        // Templates
        $templates = NotificationTemplate::where('organization_id', $orgId)
            ->orWhereNull('organization_id')
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'history' => $history,
            'preferences' => $preferences,
            'templates' => $templates,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }
}
