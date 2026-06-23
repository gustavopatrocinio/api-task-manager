<?php

namespace App\Services;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyService
{
    public function fingerprint(Request $request): string
    {
        return hash('sha256', $request->method().'|'.$request->path());
    }

    public function resolve(
        Request $request,
        User $user,
        string $key,
        Closure $callback,
    ): Response {
        $fingerprint = $this->fingerprint($request);

        $existing = IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->where('fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing?->isCompleted()) {
            return response()->json(
                $existing->response_body,
                $existing->response_code,
                ['Idempotent-Replay' => 'true'],
            );
        }

        if ($existing?->isProcessing()) {
            return response()->json(
                ['message' => 'Request is already being processed.'],
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $record = IdempotencyKey::query()->create([
                'user_id' => $user->id,
                'key' => $key,
                'fingerprint' => $fingerprint,
                'status' => IdempotencyStatus::Processing,
                'expires_at' => now()->addHours(24),
            ]);
        } catch (QueryException) {
            $existing = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $key)
                ->where('fingerprint', $fingerprint)
                ->where('expires_at', '>', now())
                ->first();

            if ($existing?->isCompleted()) {
                return response()->json(
                    $existing->response_body,
                    $existing->response_code,
                    ['Idempotent-Replay' => 'true'],
                );
            }

            return response()->json(
                ['message' => 'Request is already being processed.'],
                Response::HTTP_CONFLICT,
            );
        }

        $response = $callback();

        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return $response;
        }

        $content = $response->getContent();
        $decoded = $content !== '' ? json_decode($content, true) : null;

        $record->update([
            'status' => IdempotencyStatus::Completed,
            'response_code' => $response->getStatusCode(),
            'response_body' => $decoded,
        ]);

        return $response;
    }
}
