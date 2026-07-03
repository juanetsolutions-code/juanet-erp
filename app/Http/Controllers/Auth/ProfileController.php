<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserService;
// Use explicit request classes or handle directly
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Show the user profile form.
     */
    public function showProfileForm()
    {
        return view('auth.profile', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $this->userService->updateUser($user->id, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'user' => $user->fresh(),
            ]);
        }

        return back()->with('status', 'Profile updated successfully.');
    }

    /**
     * Update current user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $this->userService->updateUser($user->id, [
            'password' => Hash::make($validated['password']),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Password changed successfully']);
        }

        return back()->with('status', 'Password changed successfully.');
    }
}
