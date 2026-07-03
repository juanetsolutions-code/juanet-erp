<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Complete dictionary of standard currencies supported.
     */
    protected static array $currencies = [
        'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'decimals' => 2, 'position' => 'before'],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'decimals' => 2, 'position' => 'before'],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'decimals' => 2, 'position' => 'before'],
        'KES' => ['symbol' => 'KSh', 'name' => 'Kenyan Shilling', 'decimals' => 2, 'position' => 'before_space'],
        'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'decimals' => 0, 'position' => 'before'],
        'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee', 'decimals' => 2, 'position' => 'before'],
        'ZAR' => ['symbol' => 'R', 'name' => 'South African Rand', 'decimals' => 2, 'position' => 'before_space'],
        'NGN' => ['symbol' => '₦', 'name' => 'Nigerian Naira', 'decimals' => 2, 'position' => 'before'],
    ];

    /**
     * Check if currency is in active dictionary.
     */
    public static function isSupported(string $currency): bool
    {
        return array_key_exists(strtoupper($currency), self::$currencies);
    }

    /**
     * Get metadata attributes for a specific currency code.
     */
    public static function getMetadata(string $currency): ?array
    {
        return self::$currencies[strtoupper($currency)] ?? null;
    }

    /**
     * Convert an amount from one currency to another using a direct exchange multiplier rate.
     */
    public static function convert(float|string $amount, float|string $exchangeRate, string $targetCurrency = 'USD'): float
    {
        $converted = ((float) $amount) * ((float) $exchangeRate);
        
        // Get decimal precision for the target currency
        $meta = self::getMetadata($targetCurrency);
        $decimals = $meta['decimals'] ?? 2;

        return round($converted, $decimals);
    }

    /**
     * Highly customized currency formatting matching specific placement and spacing preferences.
     */
    public static function formatCustom(float|string $amount, string $currency = 'USD'): string
    {
        $currency = strtoupper($currency);
        $meta = self::getMetadata($currency) ?? ['symbol' => $currency, 'decimals' => 2, 'position' => 'before_space'];

        $decimals = $meta['decimals'];
        $symbol = $meta['symbol'];
        $position = $meta['position'];

        $numberFormatted = number_format((float) $amount, $decimals, '.', ',');

        return match ($position) {
            'before' => $symbol . $numberFormatted,
            'before_space' => $symbol . ' ' . $numberFormatted,
            'after' => $numberFormatted . $symbol,
            'after_space' => $numberFormatted . ' ' . $symbol,
            default => $symbol . $numberFormatted,
        };
    }
}
