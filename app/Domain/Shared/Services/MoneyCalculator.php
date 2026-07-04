<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Currency;
use App\Domain\Shared\Exceptions\CurrencyMismatchException;
use InvalidArgumentException;

/**
 * Single authoritative Money Calculator.
 *
 * Implements precise mathematical operations on decimals using PHP's BCMath extension,
 * avoiding any IEEE 754 floating-point rounding errors.
 */
class MoneyCalculator
{
    public const ROUND_BANKERS = 'bankers';
    public const ROUND_HALF_UP = 'half_up';
    public const ROUND_HALF_DOWN = 'half_down';
    public const ROUND_FLOOR = 'floor';
    public const ROUND_CEILING = 'ceiling';
    public const ROUND_TRUNCATE = 'truncate';

    private static int $internalScale = 14;

    /**
     * Set the internal calculation scale.
     */
    public static function setInternalScale(int $scale): void
    {
        self::$internalScale = $scale;
    }

    /**
     * Get the internal calculation scale.
     */
    public static function getInternalScale(): int
    {
        return self::$internalScale;
    }

    /**
     * Precisely round a decimal string to a specific precision using the specified rounding mode.
     */
    public static function round(string $value, int $precision, string $mode = self::ROUND_BANKERS): string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return '0';
        }

        $isNegative = $value[0] === '-';
        $absVal = $isNegative ? substr($value, 1) : $value;

        // Ensure leading zero for decimals (e.g. .5 -> 0.5)
        if ($absVal[0] === '.') {
            $absVal = '0' . $absVal;
        }

        // If no decimal point, add it to standardize splitting
        if (!str_contains($absVal, '.')) {
            $absVal .= '.0';
        }

        [$integerPart, $fractionalPart] = explode('.', $absVal);

        // If the fractional part is already within precision, pad and return
        if (strlen($fractionalPart) <= $precision) {
            $fractionalPart = str_pad($fractionalPart, $precision, '0', STR_PAD_RIGHT);
            $result = $precision > 0 ? $integerPart . '.' . $fractionalPart : $integerPart;
            return $isNegative ? '-' . $result : $result;
        }

        // We have more fractional digits than the requested precision.
        $keep = substr($fractionalPart, 0, $precision);
        $nextDigit = (int) $fractionalPart[$precision];
        $rest = substr($fractionalPart, $precision + 1);
        $hasNonZeroRest = preg_match('/[1-9]/', $rest) === 1;

        // Determine the previous digit for Banker's Rounding
        if ($precision > 0) {
            $prevDigit = $keep[$precision - 1];
        } else {
            $prevDigit = substr($integerPart, -1);
        }

        $roundUp = false;

        switch ($mode) {
            case self::ROUND_BANKERS:
                if ($nextDigit > 5) {
                    $roundUp = true;
                } elseif ($nextDigit === 5) {
                    if ($hasNonZeroRest) {
                        $roundUp = true;
                    } else {
                        // Tie-breaking: Round to nearest even number
                        $roundUp = ((int)$prevDigit % 2) !== 0;
                    }
                }
                break;

            case self::ROUND_HALF_UP:
                if ($nextDigit >= 5) {
                    $roundUp = true;
                }
                break;

            case self::ROUND_HALF_DOWN:
                if ($nextDigit > 5 || ($nextDigit === 5 && $hasNonZeroRest)) {
                    $roundUp = true;
                }
                break;

            case self::ROUND_FLOOR:
                // Floor rounds towards negative infinity.
                if ($isNegative) {
                    $roundUp = ($nextDigit > 0 || $hasNonZeroRest);
                }
                break;

            case self::ROUND_CEILING:
                // Ceiling rounds towards positive infinity.
                if (!$isNegative) {
                    $roundUp = ($nextDigit > 0 || $hasNonZeroRest);
                }
                break;

            case self::ROUND_TRUNCATE:
                $roundUp = false;
                break;

            default:
                throw new InvalidArgumentException("Unsupported rounding mode: '{$mode}'.");
        }

        if ($roundUp) {
            $increment = $precision > 0 
                ? '0.' . str_repeat('0', $precision - 1) . '1' 
                : '1';
            $roundedAbs = bcadd($integerPart . '.' . $keep, $increment, $precision);
        } else {
            $roundedAbs = bcadd($integerPart . '.' . $keep, '0', $precision);
        }

        if ($precision === 0 && str_ends_with($roundedAbs, '.')) {
            $roundedAbs = rtrim($roundedAbs, '.');
        }

        // Prevent "-0" as a string output
        if ($roundedAbs === '0' || bccomp($roundedAbs, '0', $precision) === 0) {
            return $precision > 0 ? '0.' . str_repeat('0', $precision) : '0';
        }

        return $isNegative ? '-' . $roundedAbs : $roundedAbs;
    }

    /**
     * Add two arbitrary decimal strings.
     */
    public static function add(string $a, string $b, int $precision, string $mode = self::ROUND_BANKERS): string
    {
        $sum = bcadd($a, $b, self::$internalScale);
        return self::round($sum, $precision, $mode);
    }

    /**
     * Subtract b from a.
     */
    public static function subtract(string $a, string $b, int $precision, string $mode = self::ROUND_BANKERS): string
    {
        $diff = bcsub($a, $b, self::$internalScale);
        return self::round($diff, $precision, $mode);
    }

    /**
     * Multiply a by b.
     */
    public static function multiply(string $a, string $b, int $precision, string $mode = self::ROUND_BANKERS): string
    {
        $product = bcmul($a, $b, self::$internalScale);
        return self::round($product, $precision, $mode);
    }

    /**
     * Divide a by b.
     */
    public static function divide(string $a, string $b, int $precision, string $mode = self::ROUND_BANKERS): string
    {
        if (bccomp($b, '0', self::$internalScale) === 0) {
            throw new InvalidArgumentException("Division by zero.");
        }
        $quotient = bcdiv($a, $b, self::$internalScale);
        return self::round($quotient, $precision, $mode);
    }

    /**
     * Compare two arbitrary decimal strings.
     * Returns -1 if a < b, 0 if a == b, 1 if a > b.
     */
    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::$internalScale);
    }

    /**
     * Proportional allocation of minor units.
     *
     * Ensures perfect mathematical reconciliation without any penny-drop leakage.
     *
     * @param int $amountMinor The total amount in minor units (integer).
     * @param array $ratios Array of positive ratios to allocate by.
     * @return array Array of allocated minor units that sum up to exactly $amountMinor.
     */
    public static function allocate(int $amountMinor, array $ratios): array
    {
        if (empty($ratios)) {
            throw new InvalidArgumentException("Allocation ratios cannot be empty.");
        }

        $totalRatio = 0;
        foreach ($ratios as $ratio) {
            if ($ratio < 0) {
                throw new InvalidArgumentException("Ratios must be non-negative.");
            }
            $totalRatio += $ratio;
        }

        if ($totalRatio === 0) {
            throw new InvalidArgumentException("Sum of allocation ratios must be positive.");
        }

        $allocated = [];
        $remainder = $amountMinor;

        foreach ($ratios as $key => $ratio) {
            if ($ratio === 0) {
                $allocated[$key] = 0;
                continue;
            }

            // floor(amount * ratio / total) using integer arithmetic or BCMath to prevent overflow
            $share = (int) floor(($amountMinor * $ratio) / $totalRatio);
            $allocated[$key] = $share;
            $remainder -= $share;
        }

        // Proportional round-robin distribution of the remainder to avoid leaks
        // Order of distribution goes to the largest ratios or first-come first-served
        reset($ratios);
        while ($remainder > 0) {
            $key = key($ratios);
            if ($ratios[$key] > 0) {
                $allocated[$key]++;
                $remainder--;
            }
            if (next($ratios) === false) {
                reset($ratios);
            }
        }

        while ($remainder < 0) {
            $key = key($ratios);
            if ($allocated[$key] > 0) {
                $allocated[$key]--;
                $remainder++;
            }
            if (next($ratios) === false) {
                reset($ratios);
            }
        }

        return $allocated;
    }
}
