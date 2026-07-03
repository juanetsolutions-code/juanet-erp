<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    protected UserService $userService;
    protected OrganizationService $organizationService;

    public function __construct(UserService $userService, OrganizationService $organizationService)
    {
        $this->userService = $userService;
        $this->organizationService = $organizationService;
    }

    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle user and organization registration.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'organization_name' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($request) {
            // 1. Create User
            $user = $this->userService->createUser([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'status' => 'active',
            ]);

            // 2. Create Default Organization
            $org = $this->organizationService->registerOrganization([
                'name' => $request->organization_name,
                'slug' => Str::slug($request->organization_name),
                'status' => 'active',
            ]);

            // 3. Create Membership as Owner
            $org->members()->create([
                'user_id' => $user->id,
                'is_owner' => true,
                'status' => 'active',
            ]);

            // 4. Resolve 'administrator' role & assign to user for this organization
            $adminRole = DB::table('roles')->where('slug', 'administrator')->first();
            if ($adminRole) {
                $user->roles()->attach($adminRole->id, [
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $org->id
                ]);
            }

            return $user;
        });

        Auth::login($user);

        // Store active organization in session
        $membership = $user->memberships()->first();
        if ($membership) {
            $request->session()->put('active_organization_id', $membership->organization_id);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken
            ], 201);
        }

        return redirect()->route('dashboard');
    }
}
