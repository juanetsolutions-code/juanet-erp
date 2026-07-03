<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class StringHelper
{
    /**
     * Mask an email address for privacy.
     * e.g. "juanet.solutions@gmail.com" -> "j*************s@gmail.com"
     */
    public static function maskEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email; // Fallback if invalid
        }

        [$local, $domain] = explode('@', $email);
        $length = strlen($local);

        if ($length <= 2) {
            return $local[0] . '*@' . $domain;
        }

        $maskedLocal = $local[0] . str_repeat('*', $length - 2) . $local[$length - 1];
        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask a phone number for privacy.
     * e.g. "+254712345678" -> "+2547******78"
     */
    public static function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        $len = strlen($phone);

        if ($len < 7) {
            return str_repeat('*', $len);
        }

        // Keep first 4 characters (usually country code or start) and last 2 characters
        return substr($phone, 0, 4) . str_repeat('*', $len - 6) . substr($phone, -2);
    }

    /**
     * Truncate a text block cleanly without cutting words.
     */
    public static function truncateWords(string $text, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $limit);
        
        // Find last space to avoid cutting a word in half
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated) . $end;
    }

    /**
     * Generate a cryptographic high-entropy random code.
     */
    public static function secureRandomCode(int $length = 6, string $type = 'alphanumeric'): string
    {
        $pools = [
            'numeric' => '0123456789',
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alphanumeric' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'complex' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_+=',
        ];

        $pool = $pools[$type] ?? $pools['alphanumeric'];
        $poolLength = strlen($pool);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, $poolLength - 1)];
        }

        return $code;
    }
}
