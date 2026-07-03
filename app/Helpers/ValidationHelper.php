<?php

namespace App\Helpers;

class ValidationHelper
{
    /**
     * Check if an email uses a valid corporate domain (rejecting public providers like gmail, yahoo, outlook, etc.).
     */
    public static function isCorporateEmail(string $email, array $additionalBlacklist = []): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        if (!$domain) {
            return false;
        }

        $publicProviders = array_merge([
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'aol.com',
            'icloud.com',
            'zoho.com',
            'protonmail.com',
            'mail.com',
            'gmx.com',
            'yandex.com',
        ], $additionalBlacklist);

        return !in_array(strtolower($domain), $publicProviders);
    }

    /**
     * Evaluate if a password meets enterprise password complexity standards.
     * Returns an array of failed criteria or true if strong enough.
     */
    public static function checkPasswordStrength(string $password, int $minLength = 8): array|bool
    {
        $failed = [];

        if (strlen($password) < $minLength) {
            $failed['length'] = "Password must be at least {$minLength} characters.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $failed['uppercase'] = "Password must contain at least one uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $failed['lowercase'] = "Password must contain at least one lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $failed['number'] = "Password must contain at least one number.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $failed['special'] = "Password must contain at least one special character.";
        }

        return empty($failed) ? true : $failed;
    }

    /**
     * Validate an E.164 phone number pattern (e.g. +254712345678).
     */
    public static function isValidE164Phone(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Validate a tax PIN number format (specifically matching standard East African / Kenya KRA PIN which starts with an alpha, followed by 9 digits, and ends with an alpha).
     */
    public static function isValidKraPin(string $pin): bool
    {
        return preg_match('/^[A-Z][0-9]{9}[A-Z]$/i', trim($pin)) === 1;
    }

    /**
     * Verify if an IP matches a CIDR range.
     */
    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $binMask = ~((1 << (32 - $mask)) - 1);
            return ($ipLong & $binMask) === ($subnetLong & $binMask);
        }

        return false; // For simplcity, handle IPv4 first. Returns false on IPv6 mismatches.
    }
}
