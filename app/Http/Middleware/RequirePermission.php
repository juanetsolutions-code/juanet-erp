<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $nextRequest, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $tenantId = $this->tenantContext->getTenantId();

        if (!$tenantId) {
            return response()->json(['error' => 'Active organization context required'], 400);
        }

        if (!Auth::user()->hasPermission($permission, $tenantId)) {
            return response()->json(['error' => 'Forbidden: Missing permission: ' . $permission], 403);
        }

        return $next($nextRequest);
    }
}
