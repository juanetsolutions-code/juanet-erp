<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\CurrencyMismatchException;
use App\Domain\Shared\Services\MoneyCalculator;
use App\Domain\Shared\Services\LocaleMoneyFormatter;
use App\Domain\Shared\Contracts\MoneyFormatter;
use JsonSerializable;
use Stringable;
use InvalidArgumentException;
use BadMethodCallException;

/**
 * Immutable Money Value Object.
 *
 * Implements strict financial math using BCMath. Avoids PHP floating-point errors
 * and operates using Banker's Rounding (Round Half to Even) as default.
 *
 * Why Banker's Rounding?
 * Banker's Rounding is the standard rounding algorithm in accounting and finance (ISO 80000-1).
 * It reduces statistical bias when rounding numbers in large datasets compared to standard round-half-up,
 * which systematically biases sums upwards.
 *
 * Why Floats are never used?
 * Floating-point numbers are represented in binary (IEEE 754), which cannot precisely represent
 * decimal fractions like 0.1 or 0.2. This results in precision drift and penny leakage.
 */
class Money implements JsonSerializable, Stringable
{
    private Currency $currency;
    private string $amount;
    private int $minorUnits;
    private int $precision;

    /**
     * Internal constructor. Use static factory methods.
     */
    public function __construct(string|float|int $amount, string|Currency $currency)
    {
        $this->currency = $currency instanceof Currency ? $currency : Currency::fromCode($currency);
        $this->precision = $this->currency->getPrecision();

        // Standardize input string
        $stringValue = (string) $amount;
        $this->amount = MoneyCalculator::round($stringValue, $this->precision, MoneyCalculator::ROUND_BANKERS);

        // Calculate minor units precisely
        $multiplier = bcpow('10', (string)$this->precision, 0);
        $minorVal = bcmul($this->amount, $multiplier, 0);
        $this->minorUnits = (int) $minorVal;
    }

    /**
     * Create Money from decimal.
     */
    public static function fromDecimal(string|float|int $amount, string|Currency $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Create Money from minor units.
     */
    public static function fromMinorUnits(int $minorUnits, string|Currency $currency = 'USD'): self
    {
        $currencyObj = $currency instanceof Currency ? $currency : Currency::fromCode($currency);
        $precision = $currencyObj->getPrecision();
        $divisor = bcpow('10', (string)$precision, 0);
        $decimalAmount = bcdiv((string)$minorUnits, $divisor, $precision);
        return new self($decimalAmount, $currencyObj);
    }

    /**
     * Create Money from database decimal/string format.
     */
    public static function fromDatabase(string|float|int $amount, string|Currency $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Handle dynamic static calls for currency codes (e.g. Money::USD(25.99)).
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        try {
            $currency = Currency::fromCode($method);
            $amount = $arguments[0] ?? '0';
            return new self($amount, $currency);
        } catch (InvalidArgumentException $e) {
            throw new BadMethodCallException("Method '{$method}' does not exist on " . self::class);
        }
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getMinorUnits(): int
    {
        return $this->minorUnits;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    /**
     * Add Money.
     */
    public function add(Money $other, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $this->assertSameCurrency($other);
        $result = MoneyCalculator::add($this->amount, $other->getAmount(), $this->precision, $mode);
        return new self($result, $this->currency);
    }

    /**
     * Subtract Money.
     */
    public function subtract(Money $other, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $this->assertSameCurrency($other);
        $result = MoneyCalculator::subtract($this->amount, $other->getAmount(), $this->precision, $mode);
        return new self($result, $this->currency);
    }

    /**
     * Multiply Money by multiplier.
     */
    public function multiply(string|float|int $multiplier, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $result = MoneyCalculator::multiply($this->amount, (string)$multiplier, $this->precision, $mode);
        return new self($result, $this->currency);
    }

    /**
     * Divide Money by divisor.
     */
    public function divide(string|float|int $divisor, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $result = MoneyCalculator::divide($this->amount, (string)$divisor, $this->precision, $mode);
        return new self($result, $this->currency);
    }

    /**
     * Get percentage of Money.
     */
    public function percentage(string|float $percentage, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $factor = bcdiv((string)$percentage, '100', MoneyCalculator::getInternalScale());
        $result = MoneyCalculator::multiply($this->amount, $factor, $this->precision, $mode);
        return new self($result, $this->currency);
    }

    /**
     * Apply discount (percentage, fixed amount, volume, etc.).
     */
    public function discount(string|float|Money $discountValue, string $type = 'percentage', string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        if ($type === 'percentage') {
            $discAmount = $this->percentage($discountValue, $mode);
            return $this->subtract($discAmount, $mode);
        }

        $discountMoney = $discountValue instanceof Money 
            ? $discountValue 
            : new self($discountValue, $this->currency);

        return $this->subtract($discountMoney, $mode);
    }

    /**
     * Apply tax rate (exclusive).
     */
    public function exclusiveTax(string|float $rate, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $taxStr = MoneyCalculator::multiply($this->amount, (string)$rate, $this->precision, $mode);
        return new self($taxStr, $this->currency);
    }

    /**
     * Extract inclusive tax rate.
     */
    public function inclusiveTax(string|float $rate, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $divisor = bcadd('1', (string)$rate, MoneyCalculator::getInternalScale());
        $baseStr = MoneyCalculator::divide($this->amount, $divisor, $this->precision, $mode);
        $baseMoney = new self($baseStr, $this->currency);
        return $this->subtract($baseMoney, $mode);
    }

    /**
     * Remove tax from inclusive rate.
     */
    public function removeTax(string|float $rate, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $divisor = bcadd('1', (string)$rate, MoneyCalculator::getInternalScale());
        $baseStr = MoneyCalculator::divide($this->amount, $divisor, $this->precision, $mode);
        return new self($baseStr, $this->currency);
    }

    /**
     * General tax amount helper.
     */
    public function taxAmount(string|float $rate, bool $inclusive = false, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        return $inclusive ? $this->inclusiveTax($rate, $mode) : $this->exclusiveTax($rate, $mode);
    }

    /**
     * Allocate proportionally among ratios.
     *
     * @return Money[]
     */
    public function allocate(array $ratios): array
    {
        $allocatedMinors = MoneyCalculator::allocate($this->minorUnits, $ratios);
        $result = [];
        foreach ($allocatedMinors as $minor) {
            $result[] = self::fromMinorUnits($minor, $this->currency);
        }
        return $result;
    }

    /**
     * Compare this Money to another.
     */
    public function compare(Money $other): int
    {
        $this->assertSameCurrency($other);
        return MoneyCalculator::compare($this->amount, $other->getAmount());
    }

    public function equals(Money $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function greaterThan(Money $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function lessThan(Money $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function lessThanOrEqual(Money $other): bool
    {
        return $this->compare($other) <= 0;
    }

    public function between(Money $min, Money $max): bool
    {
        return $this->greaterThanOrEqual($min) && $this->lessThanOrEqual($max);
    }

    public function isZero(): bool
    {
        return MoneyCalculator::compare($this->amount, '0') === 0;
    }

    public function isPositive(): bool
    {
        return MoneyCalculator::compare($this->amount, '0') > 0;
    }

    public function isNegative(): bool
    {
        return MoneyCalculator::compare($this->amount, '0') < 0;
    }

    public function min(Money $other): self
    {
        return $this->lessThan($other) ? $this : $other;
    }

    public function max(Money $other): self
    {
        return $this->greaterThan($other) ? $this : $other;
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function negate(): self
    {
        $negated = bcsub('0', $this->amount, $this->precision);
        return new self($negated, $this->currency);
    }

    public function round(int $precision, string $mode = MoneyCalculator::ROUND_BANKERS): self
    {
        $rounded = MoneyCalculator::round($this->amount, $precision, $mode);
        return new self($rounded, $this->currency);
    }

    public function copy(): self
    {
        return new self($this->amount, $this->currency);
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->getCurrency())) {
            throw new CurrencyMismatchException($this->currency->getCode(), $other->getCurrency()->getCode());
        }
    }

    public function __toString(): string
    {
        return $this->currency->getCode() . ' ' . $this->amount;
    }

    /**
     * JSON Serialization format.
     */
    public function jsonSerialize(): array
    {
        try {
            $formatter = app(MoneyFormatter::class);
        } catch (\Throwable $e) {
            $formatter = new LocaleMoneyFormatter();
        }

        return [
            'amount' => (float) $this->amount,
            'currency' => $this->currency->getCode(),
            'formatted' => $formatter->format($this),
            'minor_units' => $this->minorUnits,
        ];
    }
}
