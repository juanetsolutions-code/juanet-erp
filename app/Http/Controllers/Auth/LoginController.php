<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login authentication.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Set primary active organization in session
            $user = Auth::user();
            $membership = $user->memberships()->first();
            if ($membership) {
                $request->session()->put('active_organization_id', $membership->organization_id);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'user' => $user,
                    'token' => $user->createToken('auth_token')->plainTextToken
                ]);
            }

            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully']);
        }

        return redirect('/');
    }

    /**
     * Logout other devices / invalidate concurrent sessions.
     */
    public function logoutOtherDevices(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices($request->password);

        // Invalidate Sanctum tokens as well
        if (Auth::check()) {
            Auth::user()->tokens()->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out of all other devices successfully']);
        }

        return back()->with('status', 'Logged out of all other devices.');
    }
}
