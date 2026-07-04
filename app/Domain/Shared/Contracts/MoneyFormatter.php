<?php

namespace App\Domain\Shared\Contracts;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Money Formatter contract.
 *
 * Defines the standard for locale-aware representation of Money value objects.
 */
interface MoneyFormatter
{
    /**
     * Format a Money object to its localized string representation.
     */
    public function format(Money $money): string;
}
