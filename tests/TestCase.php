<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function idempotencyHeaders(?string $key = null): array
    {
        return [
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
        ];
    }
}
