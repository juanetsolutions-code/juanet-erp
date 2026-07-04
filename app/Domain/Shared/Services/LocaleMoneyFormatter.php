<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Contracts\MoneyFormatter;
use App\Domain\Shared\ValueObjects\Money;
use NumberFormatter;

/**
 * Production-ready Locale-Aware Money Formatter.
 *
 * Utilizes PHP's internationalization extension NumberFormatter for precise currency presentation,
 * falling back gracefully to manual rules if Intl is not configured.
 */
class LocaleMoneyFormatter implements MoneyFormatter
{
    /**
     * Format a Money object to its localized string representation.
     */
    public function format(Money $money): string
    {
        $currency = $money->getCurrency();
        $amount = (float) $money->getAmount();

        if (class_exists(NumberFormatter::class)) {
            // Standardize locales to avoid issues on some systems
            $formatter = new NumberFormatter($currency->getLocale(), NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency->getCode());
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // Graceful fallback with human-centric symbol placement and spacing rules
        $decimals = $currency->getPrecision();
        $formattedAmount = number_format($amount, $decimals, '.', ',');

        switch ($currency->getCode()) {
            case 'KES':
                return 'KES ' . $formattedAmount;
            case 'USD':
                return '$' . $formattedAmount;
            case 'EUR':
                return '€' . $formattedAmount;
            case 'GBP':
                return '£' . $formattedAmount;
            case 'JPY':
                return '¥' . $formattedAmount;
            case 'UGX':
                return 'USh ' . $formattedAmount;
            case 'TZS':
                return 'TSh ' . $formattedAmount;
            case 'RWF':
                return 'RF ' . $formattedAmount;
            case 'NGN':
                return '₦' . $formattedAmount;
            case 'ZAR':
                return 'R ' . $formattedAmount;
            default:
                return $currency->getSymbol() . ' ' . $formattedAmount;
        }
    }
}
