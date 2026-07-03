<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class ActivityHelper
{
    /**
     * Build unified request metadata context for audit or event logging.
     */
    public static function buildContext(): array
    {
        $request = request();
        
        return [
            'ip_address' => $request ? $request->ip() : '127.0.0.1',
            'user_agent' => $request ? $request->userAgent() : 'CLI/System',
            'user_id' => Auth::id(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Interpolate activity template descriptions.
     * e.g. formatDescription("User {name} completed task {task_id}", ["name" => "Jane", "task_id" => "99"])
     * Returns: "User Jane completed task 99"
     */
    public static function formatDescription(string $template, array $replacements): string
    {
        $formatted = $template;
        foreach ($replacements as $key => $value) {
            $formatted = str_replace('{' . $key . '}', (string) $value, $formatted);
        }
        return $formatted;
    }

    /**
     * Map clean readable labels to standard system modules.
     */
    public static function moduleLabel(string $module): string
    {
        return match (strtolower($module)) {
            'core' => 'Core Platform',
            'auth' => 'Identity & Access',
            'billing' => 'Billing & Subscriptions',
            'settings' => 'Configuration Panel',
            'notifications' => 'Message Dispatcher',
            'storage' => 'Enterprise Vault',
            default => ucfirst($module),
        };
    }
}
