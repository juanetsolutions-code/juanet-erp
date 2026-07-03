<?php

namespace App\Helpers;

class MoneyHelper
{
    /**
     * Convert decimal amount to cents integer representation.
     */
    public static function toCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Convert cents integer to decimal float representation.
     */
    public static function toDecimal(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Format decimal money to human-readable format with currency symbol.
     */
    public static function format(float|string $amount, string $currency = 'USD', string $locale = 'en_US'): string
    {
        $amount = (float) $amount;
        $symbol = self::getSymbol($currency);
        
        // Custom robust formatting to avoid locale dependency issues in pure php without ext-intl
        $formatted = number_format($amount, 2, '.', ',');
        
        return $symbol . $formatted;
    }

    /**
     * Get currency symbol.
     */
    public static function getSymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'KES' => 'KSh ',
            'JPY' => '¥',
            'INR' => '₹',
            default => $currency . ' ',
        };
    }

    /**
     * High precision addition.
     */
    public static function add(string|float $amount1, string|float $amount2): float
    {
        if (function_exists('bcadd')) {
            return (float) bcadd(sprintf('%.4f', $amount1), sprintf('%.4f', $amount2), 4);
        }
        return (float) sprintf('%.2f', (float) $amount1 + (float) $amount2);
    }

    /**
     * High precision subtraction.
     */
    public static function subtract(string|float $amount1, string|float $amount2): float
    {
        if (function_exists('bcsub')) {
            return (float) bcsub(sprintf('%.4f', $amount1), sprintf('%.4f', $amount2), 4);
        }
        return (float) sprintf('%.2f', (float) $amount1 - (float) $amount2);
    }

    /**
     * High precision multiplication.
     */
    public static function multiply(string|float $amount, string|float|int $factor): float
    {
        if (function_exists('bcmul')) {
            return (float) bcmul(sprintf('%.4f', $amount), sprintf('%.4f', $factor), 4);
        }
        return (float) sprintf('%.2f', (float) $amount * (float) $factor);
    }

    /**
     * High precision division.
     */
    public static function divide(string|float $amount, string|float|int $divisor): float
    {
        if ((float) $divisor === 0.0) {
            throw new \InvalidArgumentException('Division by zero is not permitted.');
        }

        if (function_exists('bcdiv')) {
            return (float) bcdiv(sprintf('%.4f', $amount), sprintf('%.4f', $divisor), 4);
        }
        return (float) sprintf('%.2f', (float) $amount / (float) $divisor);
    }

    /**
     * Distribute/allocate money across shares proportionally without losing pennies.
     * e.g. Distributing $0.05 across 2 equal shares returns [$0.03, $0.02]
     */
    public static function allocate(int $cents, array $ratios): array
    {
        $totalRatio = array_sum($ratios);
        if ($totalRatio <= 0) {
            throw new \InvalidArgumentException('Ratios sum must be greater than zero.');
        }

        $remainder = $cents;
        $results = [];

        foreach ($ratios as $ratio) {
            $share = (int) floor(($cents * $ratio) / $totalRatio);
            $results[] = $share;
            $remainder -= $share;
        }

        // Distribute remainder pennies to highest ratio or first shares
        for ($i = 0; $i < $remainder; $i++) {
            $results[$i]++;
        }

        return $results;
    }
}
