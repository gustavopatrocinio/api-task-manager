<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ApiErrorResponse
{
    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function make(string $message, int $status, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function validation(ValidationException $exception): JsonResponse
    {
        return self::make(
            $exception->getMessage(),
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            $exception->errors(),
        );
    }

    public static function unauthenticated(): JsonResponse
    {
        return self::make('Unauthenticated.', JsonResponse::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(): JsonResponse
    {
        return self::make('Forbidden.', JsonResponse::HTTP_FORBIDDEN);
    }

    public static function notFound(): JsonResponse
    {
        return self::make('Resource not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
