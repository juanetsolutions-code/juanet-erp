<?php

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Currency;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

/**
 * Custom Eloquent Cast for Money.
 *
 * Supports single-column serialization (compound string or JSON)
 * and dual-column schemas (associates currency column dynamically).
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    public function get($model, string $key, $value, array $attributes): ?Money
    {
        if (is_null($value)) {
            return null;
        }

        // 1. JSON handling
        if (is_string($value) && str_starts_with($value, '{')) {
            $data = json_decode($value, true);
            if (isset($data['amount'], $data['currency'])) {
                return new Money($data['amount'], $data['currency']);
            }
        }

        // 2. Compound string handling, e.g. "USD 25.99"
        if (is_string($value) && preg_match('/^([A-Z]{3})\s+(.+)$/', $value, $matches)) {
            return new Money($matches[2], $matches[1]);
        }

        // 3. Multi-column database schema handling.
        // Look for [attribute_name]_currency, or a fallback 'currency' column.
        $currencyKey = $key . '_currency';
        if (!isset($attributes[$currencyKey]) && isset($attributes['currency'])) {
            $currencyKey = 'currency';
        }

        $currency = $attributes[$currencyKey] ?? 'USD';
        return new Money($value, $currency);
    }

    /**
     * Prepare the value for storage.
     */
    public function set($model, string $key, $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (!$value instanceof Money) {
            if (is_numeric($value)) {
                $currencyKey = $key . '_currency';
                if (!isset($attributes[$currencyKey]) && isset($attributes['currency'])) {
                    $currencyKey = 'currency';
                }
                $currency = $attributes[$currencyKey] ?? 'USD';
                $value = new Money($value, $currency);
            } else {
                throw new InvalidArgumentException("Value must be an instance of " . Money::class . " or a numeric value.");
            }
        }

        // If the model has a matching currency column, update that column in sync
        $currencyKey = $key . '_currency';
        if (!isset($attributes[$currencyKey]) && array_key_exists('currency', $attributes)) {
            $currencyKey = 'currency';
        }

        if (array_key_exists($currencyKey, $attributes) || isset($model->$currencyKey)) {
            return [
                $key => $value->getAmount(),
                $currencyKey => $value->getCurrency()->getCode(),
            ];
        }

        // Fallback: serialize as compound string "USD 25.99"
        return $value->getCurrency()->getCode() . ' ' . $value->getAmount();
    }
}
