<?php

namespace App\Helpers;

use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

class ExceptionHelper
{
    /**
     * Parse any Throwable into a safe, structured diagnostic array.
     * Stacks and file parameters are omitted/safeguarded in non-debug mode.
     */
    public static function toArray(Throwable $exception, bool $debug = false): array
    {
        $payload = [
            'message' => self::getFriendlyMessage($exception),
            'type' => get_class($exception),
            'code' => $exception->getCode(),
        ];

        if ($debug) {
            $payload['file'] = $exception->getFile();
            $payload['line'] = $exception->getLine();
            $payload['trace'] = array_slice($exception->getTrace(), 0, 10); // Return first 10 steps to save space
        }

        return $payload;
    }

    /**
     * Standardize user-friendly error messages from various exceptions.
     */
    public static function getFriendlyMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return 'The provided fields failed our security and validation parameters.';
        }

        if ($exception instanceof AuthorizationException) {
            return 'You do not possess the necessary administrative clearance for this operation.';
        }

        if ($exception instanceof QueryException) {
            return 'An internal storage layer discrepancy occurred. Please contact system support.';
        }

        return $exception->getMessage() ?: 'An unclassified system anomaly occurred.';
    }

    /**
     * Categorize whether the exception represents a catastrophic platform or database failure.
     */
    public static function isSystemFatal(Throwable $exception): bool
    {
        if ($exception instanceof QueryException) {
            return true;
        }

        $class = get_class($exception);
        
        $fatalIndicators = [
            'RuntimeException',
            'PDOException',
            'ErrorException',
            'FatalThrowableError',
        ];

        foreach ($fatalIndicators as $indicator) {
            if (str_contains($class, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
