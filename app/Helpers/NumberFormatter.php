<?php

namespace App\Helpers;

class NumberFormatter
{
    /**
     * Format numbers into highly compact, human-friendly abbreviations.
     * e.g. 1500 -> "1.5K", 2300000 -> "2.3M"
     */
    public static function compact(float|int $number, int $precision = 1): string
    {
        if ($number < 1000) {
            return (string) $number;
        }

        $abbreviations = [
            12 => 'T', // Trillion
            9 => 'B',  // Billion
            6 => 'M',  // Million
            3 => 'K',  // Thousand
        ];

        foreach ($abbreviations as $exponent => $suffix) {
            $value = pow(10, $exponent);
            if ($number >= $value) {
                return round($number / $value, $precision) . $suffix;
            }
        }

        return (string) $number;
    }

    /**
     * Convert an integer into its English ordinal suffix representation.
     * e.g. 1 -> "1st", 22 -> "22nd", 103 -> "103rd"
     */
    public static function ordinal(int $number): string
    {
        $abs = abs($number);
        
        // Handle teen numbers (11th, 12th, 13th, etc.)
        if (($abs % 100) >= 11 && ($abs % 100) <= 13) {
            return $number . 'th';
        }

        return match ($abs % 10) {
            1 => $number . 'st',
            2 => $number . 'nd',
            3 => $number . 'rd',
            default => $number . 'th',
        };
    }

    /**
     * Format raw numbers into a standard, percentage representation.
     * e.g. 0.2345 -> "23.45%", 50 -> "50.00%"
     */
    public static function percentage(float|int $value, int $precision = 2, bool $isRawFraction = false): string
    {
        $factor = $isRawFraction ? 100.0 : 1.0;
        $multiplied = ((float) $value) * $factor;

        return number_format($multiplied, $precision, '.', '') . '%';
    }
}
