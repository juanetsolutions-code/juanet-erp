<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    protected OrganizationService $orgService;
    protected TenantContext $tenantContext;

    public function __construct(OrganizationService $orgService, TenantContext $tenantContext)
    {
        $this->orgService = $orgService;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Show all organizations for the logged in user.
     */
    public function index()
    {
        $user = Auth::user();
        $memberships = $user->memberships()->with('organization')->get();

        return view('organization.index', [
            'memberships' => $memberships,
            'activeOrgId' => session('active_organization_id'),
        ]);
    }

    /**
     * Show organization creation form.
     */
    public function create()
    {
        return view('organization.create');
    }

    /**
     * Store a newly created organization and attach current user as owner.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:organizations'],
        ]);

        $org = DB::transaction(function () use ($request) {
            $org = $this->orgService->registerOrganization([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'domain' => $request->domain ?: null,
                'status' => 'active',
            ]);

            // Add logged in user as owner
            $org->members()->create([
                'user_id' => Auth::id(),
                'is_owner' => true,
                'status' => 'active',
            ]);

            // Attach standard 'administrator' role to current user for this tenant
            $adminRole = DB::table('roles')->where('slug', 'administrator')->first();
            if ($adminRole) {
                Auth::user()->roles()->attach($adminRole->id, [
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $org->id
                ]);
            }

            return $org;
        });

        // Set active in session
        session(['active_organization_id' => $org->id]);

        return redirect()->route('organization.index')->with('status', 'Organization created successfully!');
    }

    /**
     * Switch active organization context in session.
     */
    public function switch(string $id)
    {
        $membership = OrganizationMember::where('user_id', Auth::id())
            ->where('organization_id', $id)
            ->where('status', 'active')
            ->firstOrFail();

        session(['active_organization_id' => $membership->organization_id]);

        return redirect()->route('dashboard')->with('status', 'Switched organization successfully!');
    }

    /**
     * Leave an organization.
     */
    public function leave(string $id)
    {
        $membership = OrganizationMember::where('user_id', Auth::id())
            ->where('organization_id', $id)
            ->firstOrFail();

        // Prevent sole owner from leaving without assigning a new owner
        if ($membership->is_owner) {
            $otherOwnerExists = OrganizationMember::where('organization_id', $id)
                ->where('user_id', '!=', Auth::id())
                ->where('is_owner', true)
                ->where('status', 'active')
                ->exists();

            if (!$otherOwnerExists) {
                return back()->withErrors(['error' => 'You are the sole owner of this organization. You must assign another owner before leaving.']);
            }
        }

        $membership->delete();

        // Clear active session organization if it was the one left
        if (session('active_organization_id') === $id) {
            session()->forget('active_organization_id');
        }

        return redirect()->route('organization.index')->with('status', 'Left organization successfully.');
    }

    /**
     * Show Organization profile/settings.
     */
    public function settings(string $id)
    {
        $org = Organization::findOrFail($id);
        
        // Ensure user belongs to organization and has permission or is owner
        $membership = OrganizationMember::where('user_id', Auth::id())
            ->where('organization_id', $id)
            ->firstOrFail();

        $members = OrganizationMember::where('organization_id', $id)->with('user')->get();

        return view('organization.settings', [
            'organization' => $org,
            'members' => $members,
            'isOwner' => $membership->is_owner,
        ]);
    }

    /**
     * Update Organization profile details.
     */
    public function updateSettings(Request $request, string $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:organizations,domain,' . $id],
        ]);

        $this->orgService->updateOrganization($id, [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'domain' => $request->domain ?: null,
        ]);

        return back()->with('status', 'Organization details updated successfully.');
    }

    /**
     * Invite user to join organization (Creates pending membership).
     */
    public function invite(Request $request, string $id)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $org = Organization::findOrFail($id);

        // Find or create the user to join
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // Auto-provision placeholder account or prompt
            $user = User::create([
                'name' => explode('@', $request->email)[0],
                'email' => $request->email,
                'password' => bcrypt(Str::random(16)),
                'status' => 'pending',
            ]);
        }

        // Check if membership already exists
        $exists = OrganizationMember::where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => 'User is already a member or has a pending invitation.']);
        }

        // Create a pending membership
        $member = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'is_owner' => false,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Invitation successfully extended to ' . $request->email);
    }

    /**
     * Accept organization membership invitation.
     */
    public function acceptInvite(string $memberId)
    {
        $membership = OrganizationMember::findOrFail($memberId);
        
        if ($membership->user_id !== Auth::id()) {
            abort(403, 'Unauthorized invitation acceptance.');
        }

        $membership->update([
            'status' => 'active',
        ]);

        // Make user status active too if they were pending
        $user = Auth::user();
        if ($user->status === 'pending') {
            $user->update(['status' => 'active']);
        }

        // Auto-assign Employee role
        $employeeRole = DB::table('roles')->where('slug', 'employee')->first();
        if ($employeeRole) {
            $user->roles()->attach($employeeRole->id, [
                'id' => (string) Str::uuid7(),
                'organization_id' => $membership->organization_id
            ]);
        }

        session(['active_organization_id' => $membership->organization_id]);

        return redirect()->route('dashboard')->with('status', 'Welcome! Joined organization successfully!');
    }
}
