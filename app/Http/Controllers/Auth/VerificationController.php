<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    /**
     * Display the email verification prompt view.
     */
    public function showNotice()
    {
        return view('auth.verify-email');
    }

    /**
     * Handle the active email verification request.
     */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email verified successfully']);
        }

        return redirect()->route('dashboard')->with('status', 'Email verified successfully!');
    }

    /**
     * Resend verification link notification.
     */
    public function resend(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Verification link sent!']);
        }

        return back()->with('status', 'Verification link sent!');
    }
}
