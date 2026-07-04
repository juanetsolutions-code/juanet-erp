<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

/**
 * Exception thrown when a monetary operation is attempted between two different currencies.
 *
 * This maintains perfect domain safety by preventing operations like KES + USD,
 * which would violate accounting consistency.
 */
class CurrencyMismatchException extends Exception
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct("Currency mismatch: Expected '{$expected}' but got '{$actual}'.");
    }
}
