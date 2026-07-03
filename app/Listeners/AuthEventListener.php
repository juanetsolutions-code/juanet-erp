<?php

namespace App\Listeners;

use App\Services\SecurityLogServiceInterface;
use App\Services\ActivityLogServiceInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

class AuthEventListener
{
    protected SecurityLogServiceInterface $securityLogService;
    protected ActivityLogServiceInterface $activityLogService;
    protected \App\Services\NotificationServiceInterface $notificationService;

    public function __construct(
        SecurityLogServiceInterface $securityLogService,
        ActivityLogServiceInterface $activityLogService,
        \App\Services\NotificationServiceInterface $notificationService
    ) {
        $this->securityLogService = $securityLogService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle Login Event.
     */
    public function onLogin(Login $event): void
    {
        $user = $event->user;
        $this->securityLogService->log(
            'login_success',
            'info',
            "User {$user->email} logged in successfully.",
            $user->id
        );

        $this->activityLogService->log(
            'user_login',
            "Logged in.",
            'auth',
            $user->id
        );

        // Notify user about login
        $this->notificationService->send(
            $user->id,
            'New Login Detected',
            'You have logged in successfully to the platform.',
            'info',
            'security',
            'normal'
        );
    }

    /**
     * Handle Failed Login Event.
     */
    public function onFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';
        $user = $event->user;

        $this->securityLogService->log(
            'failed_login',
            'warning',
            "Failed login attempt for email: {$email}",
            $user?->id
        );

        if ($user) {
            // Notify user of failed login attempt
            $this->notificationService->send(
                $user->id,
                'Failed Login Attempt',
                'A failed login attempt was recorded for your account from IP: ' . request()->ip(),
                'warning',
                'security',
                'high'
            );
        }
    }

    /**
     * Handle Logout Event.
     */
    public function onLogout(Logout $event): void
    {
        $user = $event->user;
        if ($user) {
            $this->securityLogService->log(
                'logout',
                'info',
                "User {$user->email} logged out.",
                $user->id
            );

            $this->activityLogService->log(
                'user_logout',
                "Logged out.",
                'auth',
                $user->id
            );
        }
    }

    /**
     * Handle Password Reset Event.
     */
    public function onPasswordReset(PasswordReset $event): void
    {
        $user = $event->user;
        $this->securityLogService->log(
            'password_reset',
            'warning',
            "Password reset for user: {$user->email}",
            $user->id
        );

        // Notify user about password reset
        $this->notificationService->send(
            $user->id,
            'Password Successfully Reset',
            'Your account password has been reset successfully.',
            'warning',
            'security',
            'high'
        );
    }

    /**
     * Register listeners.
     */
    public function subscribe($events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Logout::class => 'onLogout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }
}
