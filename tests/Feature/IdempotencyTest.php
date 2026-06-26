<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_idempotency_key(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());
        $plan = Plan::factory()->create();

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency-Key header is required.');
    }

    public function test_replaying_subscription_store_returns_cached_response(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create(['trial_days' => 0]);

        Sanctum::actingAs($customer);

        $key = '550e8400-e29b-41d4-a716-446655440000';
        $headers = $this->idempotencyHeaders($key);
        $payload = ['plan_id' => $plan->id, 'payment_method' => 'pix'];

        $first = $this->postJson('/api/v1/subscriptions', $payload, $headers);
        $first->assertCreated();

        $second = $this->postJson('/api/v1/subscriptions', $payload, $headers);
        $second->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJson($first->json());

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_idempotency_key_is_scoped_by_route_fingerprint(): void
    {
        $admin = User::factory()->admin()->create();
        $confirmPayment = \App\Models\Payment::factory()->create();
        $failPayment = \App\Models\Payment::factory()->create();

        Sanctum::actingAs($admin);

        $key = 'same-key-different-routes';
        $headers = $this->idempotencyHeaders($key);

        $this->postJson('/api/v1/admin/payments/'.$confirmPayment->id.'/confirm', [], $headers)
            ->assertOk();

        $this->postJson('/api/v1/admin/payments/'.$failPayment->id.'/fail', [], $headers)
            ->assertOk();

        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_validation_errors_are_cached_for_idempotent_replay(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $headers = $this->idempotencyHeaders('validation-replay-key');

        $this->postJson('/api/v1/subscriptions', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);

        $this->postJson('/api/v1/subscriptions', [], $headers)
            ->assertUnprocessable()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_subscription_service_lock_prevents_duplicate_active_subscriptions(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create();

        Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ], $this->idempotencyHeaders('different-key'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription']);
    }
}
