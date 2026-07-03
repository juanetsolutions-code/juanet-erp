<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ResponseBuilder
{
    /**
     * Standard success response structure.
     */
    public static function success(mixed $data = null, string $message = 'Operation successful', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Standard error response structure.
     */
    public static function error(string $message = 'An error occurred', string $errorCode = 'INTERNAL_ERROR', mixed $details = null, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    /**
     * Standard paginated response structure.
     */
    public static function paginated(mixed $data, array $pagination, string $message = 'Data retrieved successfully', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'pagination' => $pagination,
            ],
        ], $status);
    }

    /**
     * Standard validation error response structure.
     */
    public static function validation(array $errors, string $message = 'The given data was invalid', int $status = 422): JsonResponse
    {
        return self::error($message, 'VALIDATION_ERROR', $errors, $status);
    }
}
