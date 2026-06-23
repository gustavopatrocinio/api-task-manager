<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function __construct(private IdempotencyService $idempotencyService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return response()->json(
                ['message' => 'Idempotency-Key header is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (strlen($key) > 255) {
            return response()->json(
                ['message' => 'Idempotency-Key must not exceed 255 characters.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        return $this->idempotencyService->resolve(
            $request,
            $user,
            $key,
            fn () => $next($request),
        );
    }
}
