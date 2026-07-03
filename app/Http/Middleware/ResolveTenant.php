<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = null;

        // 1. Check custom HTTP Header (useful for API clients)
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');
        } 
        // 2. Check Session (useful for web requests)
        elseif ($request->session()->has('active_organization_id')) {
            $tenantId = $request->session()->get('active_organization_id');
        }

        // 3. Fallback to authenticated user's first/primary organization
        if (!$tenantId && Auth::check()) {
            $user = Auth::user();
            $membership = $user->memberships()->first();
            if ($membership) {
                $tenantId = $membership->organization_id;
                $request->session()->put('active_organization_id', $tenantId);
            }
        }

        $currentPermissions = [];
        $unreadNotifications = [
            [
                'id' => 'notif-1',
                'title' => 'Database Sync Successful',
                'body' => 'PostgreSQL main database cluster successfully synced with MinIO object backup storage.',
                'time' => '12 minutes ago',
                'type' => 'success',
                'unread' => true,
            ],
            [
                'id' => 'notif-2',
                'title' => 'New Enterprise Lead Captured',
                'body' => 'Contact subscription recorded from juanetsolutions@gmail.com.',
                'time' => '1 hour ago',
                'type' => 'info',
                'unread' => true,
            ],
            [
                'id' => 'notif-3',
                'title' => 'Automation Action Triggered',
                'body' => 'Workflow "Lipa Na M-PESA Webhook Router" executed with 200 OK status.',
                'time' => '2 hours ago',
                'type' => 'success',
                'unread' => true,
            ],
            [
                'id' => 'notif-4',
                'title' => 'Security Audit Warning',
                'body' => 'Concurrent session limit of 3 exceeded for user john@dev.com.',
                'time' => '5 hours ago',
                'type' => 'warning',
                'unread' => true,
            ],
        ];

        $featureFlags = [
            'crm' => true,
            'marketplace' => true,
            'cms' => true,
            'projects' => true,
            'finance' => true,
            'support' => true,
            'automation' => true,
            'ai' => true,
        ];

        if ($tenantId) {
            $tenant = Organization::find($tenantId);
            if ($tenant) {
                // Ensure authenticated user actually belongs to this tenant organization
                if (Auth::check()) {
                    $user = Auth::user();
                    $hasMembership = $user->memberships()
                        ->where('organization_id', $tenantId)
                        ->where('status', 'active')
                        ->exists();

                    if (!$hasMembership) {
                        return response()->json(['error' => 'Unauthorized organization context access'], 403);
                    }

                    // Fetch user's role permissions in this organization
                    $roles = $user->rolesInOrganization($tenantId)->with('permissions')->get();
                    foreach ($roles as $role) {
                        foreach ($role->permissions as $permission) {
                            $currentPermissions[] = $permission->slug;
                        }
                    }
                }

                $this->tenantContext->setTenant($tenant);
                view()->share('currentTenant', $tenant);
            }
        }

        // Shared view parameters
        view()->share('currentPermissions', array_unique($currentPermissions));
        view()->share('unreadNotifications', $unreadNotifications);
        view()->share('featureFlags', $featureFlags);

        return $next($request);
    }
}
