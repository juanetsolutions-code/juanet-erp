<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $activeOrg = $this->tenantContext->getTenant();
        $memberships = $user->memberships()->with('organization')->get();

        $activeOrgMembers = [];
        $isOwner = false;

        if ($activeOrg) {
            $activeOrgMembers = OrganizationMember::where('organization_id', $activeOrg->id)
                ->with('user')
                ->get();

            $myMembership = $user->memberships()
                ->where('organization_id', $activeOrg->id)
                ->first();

            $isOwner = $myMembership ? $myMembership->is_owner : false;
        }

        return view('dashboard', [
            'user' => $user,
            'activeOrganization' => $activeOrg,
            'memberships' => $memberships,
            'members' => $activeOrgMembers,
            'isOwner' => $isOwner,
        ]);
    }
}
