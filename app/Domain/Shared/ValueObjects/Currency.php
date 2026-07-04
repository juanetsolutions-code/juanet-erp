<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Currency Value Object.
 *
 * Represents an ISO 4217 currency with financial metadata such as decimal precision,
 * localized symbols, and locale specifications for formatting.
 */
class Currency
{
    private string $code;
    private string $name;
    private string $symbol;
    private int $minorUnits;
    private string $locale;
    private int $precision;
    private int $exchangePrecision;

    /**
     * Cache for instanced currencies to avoid unnecessary allocations.
     */
    private static array $instanceCache = [];

    /**
     * Supported currency definitions.
     */
    private static array $definitions = [
        'USD' => [
            'name' => 'US Dollar',
            'symbol' => '$',
            'minorUnits' => 2,
            'locale' => 'en_US',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
        'KES' => [
            'name' => 'Kenya Shilling',
            'symbol' => 'KES',
            'minorUnits' => 2,
            'locale' => 'en_KE',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
        'EUR' => [
            'name' => 'Euro',
            'symbol' => '€',
            'minorUnits' => 2,
            'locale' => 'de_DE',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
        'GBP' => [
            'name' => 'British Pound Sterling',
            'symbol' => '£',
            'minorUnits' => 2,
            'locale' => 'en_GB',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
        'JPY' => [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'minorUnits' => 0,
            'locale' => 'ja_JP',
            'precision' => 0,
            'exchangePrecision' => 4,
        ],
        'UGX' => [
            'name' => 'Ugandan Shilling',
            'symbol' => 'USh',
            'minorUnits' => 0,
            'locale' => 'en_UG',
            'precision' => 0,
            'exchangePrecision' => 4,
        ],
        'TZS' => [
            'name' => 'Tanzanian Shilling',
            'symbol' => 'TSh',
            'minorUnits' => 0,
            'locale' => 'en_TZ',
            'precision' => 0,
            'exchangePrecision' => 4,
        ],
        'RWF' => [
            'name' => 'Rwandan Franc',
            'symbol' => 'RF',
            'minorUnits' => 0,
            'locale' => 'rw_RW',
            'precision' => 0,
            'exchangePrecision' => 4,
        ],
        'NGN' => [
            'name' => 'Nigerian Naira',
            'symbol' => '₦',
            'minorUnits' => 2,
            'locale' => 'en_NG',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
        'ZAR' => [
            'name' => 'South African Rand',
            'symbol' => 'R',
            'minorUnits' => 2,
            'locale' => 'en_ZA',
            'precision' => 2,
            'exchangePrecision' => 6,
        ],
    ];

    private function __construct(
        string $code,
        string $name,
        string $symbol,
        int $minorUnits,
        string $locale,
        int $precision,
        int $exchangePrecision
    ) {
        $this->code = strtoupper($code);
        $this->name = $name;
        $this->symbol = $symbol;
        $this->minorUnits = $minorUnits;
        $this->locale = $locale;
        $this->precision = $precision;
        $this->exchangePrecision = $exchangePrecision;
    }

    /**
     * Factory method to obtain an immutable Currency instance.
     */
    public static function fromCode(string $code): self
    {
        $code = strtoupper(trim($code));

        if (isset(self::$instanceCache[$code])) {
            return self::$instanceCache[$code];
        }

        if (!isset(self::$definitions[$code])) {
            throw new InvalidArgumentException("Unsupported currency code: '{$code}'.");
        }

        $def = self::$definitions[$code];

        self::$instanceCache[$code] = new self(
            $code,
            $def['name'],
            $def['symbol'],
            $def['minorUnits'],
            $def['locale'],
            $def['precision'],
            $def['exchangePrecision']
        );

        return self::$instanceCache[$code];
    }

    public static function USD(): self
    {
        return self::fromCode('USD');
    }

    public static function KES(): self
    {
        return self::fromCode('KES');
    }

    public static function EUR(): self
    {
        return self::fromCode('EUR');
    }

    public static function GBP(): self
    {
        return self::fromCode('GBP');
    }

    public static function JPY(): self
    {
        return self::fromCode('JPY');
    }

    public static function UGX(): self
    {
        return self::fromCode('UGX');
    }

    public static function TZS(): self
    {
        return self::fromCode('TZS');
    }

    public static function RWF(): self
    {
        return self::fromCode('RWF');
    }

    public static function NGN(): self
    {
        return self::fromCode('NGN');
    }

    public static function ZAR(): self
    {
        return self::fromCode('ZAR');
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getMinorUnits(): int
    {
        return $this->minorUnits;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getExchangePrecision(): int
    {
        return $this->exchangePrecision;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->getCode();
    }
}
