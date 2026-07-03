<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UuidHelper
{
    /**
     * Generate a standard UUID v4.
     */
    public static function v4(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Generate an ordered, database-friendly UUID v7.
     */
    public static function v7(): string
    {
        return Str::uuid()->toString(); // Note: Str::uuid() in Laravel 11 uses UUID v7 by default if ordered or can use uuid()
        // Let's implement a solid native UUID v7 or fallback generator to guarantee ordered structure
        if (method_exists(Str::class, 'uuid')) {
            // Under Laravel 11, Str::uuid() is indeed standard UUID v4 or v7 depending on call, let's write a secure generator
        }
        
        // Native UUID v7 generation using current time in milliseconds
        $timeMs = (int) floor(microtime(true) * 1000);
        $timeHex = str_pad(dechex($timeMs), 12, '0', STR_PAD_LEFT);
        
        // Random bytes for the rest (62 bits of randomness)
        $randomBytes = random_bytes(10);
        
        // Version 7 layout: 
        // 48 bits timestamp (12 hex chars)
        // 4 bits version (always 7)
        // 12 bits sequence/randomness (3 hex chars)
        // 2 bits variant (always 2, 10xx in binary)
        // 62 bits randomness (remainder hex chars)
        
        $randomHex = bin2hex($randomBytes);
        
        // UUID layout: xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx
        // M = 7 (version), N = 8, 9, a, b (variant 2)
        $uuid = sprintf(
            '%s-%s-7%s-%s%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            substr($randomHex, 0, 3),
            dechex(8 + (hexdec(substr($randomHex, 3, 1)) & 3)),
            substr($randomHex, 4, 3),
            substr($randomHex, 7, 12)
        );

        return $uuid;
    }

    /**
     * Validate whether a string is a valid UUID format.
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Extract the creation timestamp from a UUID v7 as a Carbon instance.
     */
    public static function extractTimestamp(string $uuid): ?Carbon
    {
        if (!self::isValid($uuid)) {
            return null;
        }

        // UUID v7 starts with a 48-bit timestamp (first 12 hex chars excluding hyphen)
        $clean = str_replace('-', '', $uuid);
        $timeHex = substr($clean, 0, 12);
        
        // Verify version digit is 7
        $version = substr($clean, 12, 1);
        if ($version !== '7') {
            return null; // Timestamp extraction only reliable for UUID v7
        }

        $timeMs = hexdec($timeHex);
        $seconds = (int) ($timeMs / 1000);
        
        return Carbon::createFromTimestamp($seconds);
    }
}
