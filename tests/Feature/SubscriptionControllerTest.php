<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_only_authenticated_customer_subscriptions(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $ownSubscription = Subscription::factory()->for($customer)->create();
        Subscription::factory()->for($otherCustomer)->create();

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/subscriptions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSubscription->id)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'user_id',
                        'plan_id',
                        'status',
                        'starts_at',
                        'ends_at',
                        'trial_ends_at',
                        'cancelled_at',
                        'created_at',
                        'updated_at',
                        'user',
                        'plan',
                    ],
                ],
            ]);
    }

    public function test_index_returns_empty_collection_when_customer_has_no_subscriptions(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_index_all_subscriptions_and_filter_results(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create();

        $matchingSubscription = Subscription::factory()
            ->for($customer)
            ->for($plan)
            ->create(['status' => SubscriptionStatus::Active]);

        Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Cancelled,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/subscriptions?status=active&user_id='.$customer->id.'&plan_id='.$plan->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingSubscription->id);
    }

    public function test_index_requires_authentication(): void
    {
        $this->assertUnauthenticatedJson($this->getJson('/api/v1/subscriptions'));
    }

    public function test_index_validates_filter_parameters(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/subscriptions?status=invalid-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonStructure(['message', 'errors']);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_subscription_for_active_plan_with_trial(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create(['trial_days' => 7]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ], $this->idempotencyHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $customer->id)
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.status', SubscriptionStatus::Trialing->value)
            ->assertJsonPath('data.trial_ends_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing->value,
        ]);
    }

    public function test_store_creates_past_due_subscription_when_plan_has_no_trial(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create(['trial_days' => 0]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id], $this->idempotencyHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::PastDue->value);
    }

    public function test_admin_can_store_subscription_for_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create(['trial_days' => 0]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
            'user_id' => $customer->id,
        ], $this->idempotencyHeaders())
            ->assertCreated()
            ->assertJsonPath('data.user_id', $customer->id);
    }

    public function test_store_requires_authentication(): void
    {
        $plan = Plan::factory()->create();

        $this->assertUnauthenticatedJson($this->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ]));
    }

    public function test_store_requires_idempotency_key(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/subscriptions', ['plan_id' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency-Key header is required.');
    }

    public function test_store_validates_required_plan_id(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/subscriptions', [], $this->idempotencyHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id'])
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_validates_plan_exists_and_is_active(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());
        $inactivePlan = Plan::factory()->inactive()->create();

        $this->postJson('/api/v1/subscriptions', ['plan_id' => 99999], $this->idempotencyHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $inactivePlan->id], $this->idempotencyHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_store_rejects_customer_with_existing_active_subscription(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create();

        Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id], $this->idempotencyHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_store_requires_user_id_when_authenticated_as_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $plan = Plan::factory()->create();

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id], $this->idempotencyHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_subscription_for_owner(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create();

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.user_id', $customer->id);
    }

    public function test_admin_can_show_any_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);
    }

    public function test_show_requires_authentication(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertUnauthenticatedJson(
            $this->getJson('/api/v1/subscriptions/'.$subscription->id),
        );
    }

    public function test_show_forbids_access_to_other_users_subscription(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($customer);

        $this->assertForbiddenJson(
            $this->getJson('/api/v1/subscriptions/'.$subscription->id),
        );
    }

    public function test_show_returns_not_found_for_missing_subscription(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->assertNotFoundJson($this->getJson('/api/v1/subscriptions/99999'));
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_subscription_for_owner(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        Sanctum::actingAs($customer);

        $this->putJson('/api/v1/subscriptions/'.$subscription->id, [
            'status' => SubscriptionStatus::Cancelled->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Cancelled->value)
            ->assertJsonPath('data.cancelled_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Cancelled->value,
        ]);
    }

    public function test_admin_can_update_subscription_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();
        $newPlan = Plan::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/subscriptions/'.$subscription->id, [
            'plan_id' => $newPlan->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_id', $newPlan->id);
    }

    public function test_update_requires_authentication(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertUnauthenticatedJson(
            $this->putJson('/api/v1/subscriptions/'.$subscription->id, [
                'status' => SubscriptionStatus::Cancelled->value,
            ]),
        );
    }

    public function test_update_forbids_modifying_other_users_subscription(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($customer);

        $this->assertForbiddenJson(
            $this->putJson('/api/v1/subscriptions/'.$subscription->id, [
                'status' => SubscriptionStatus::Cancelled->value,
            ]),
        );
    }

    public function test_update_validates_status_enum(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create();

        Sanctum::actingAs($customer);

        $this->putJson('/api/v1/subscriptions/'.$subscription->id, [
            'status' => 'invalid-status',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_update_requires_cancelled_at_when_status_is_cancelled_and_field_is_null(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        Sanctum::actingAs($customer);

        $this->putJson('/api/v1/subscriptions/'.$subscription->id, [
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cancelled_at']);
    }

    public function test_update_returns_not_found_for_missing_subscription(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->assertNotFoundJson(
            $this->putJson('/api/v1/subscriptions/99999', [
                'status' => SubscriptionStatus::Cancelled->value,
            ]),
        );
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_subscription_for_owner(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create();

        Sanctum::actingAs($customer);

        $this->deleteJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_admin_can_destroy_any_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/subscriptions/'.$subscription->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertUnauthenticatedJson(
            $this->deleteJson('/api/v1/subscriptions/'.$subscription->id),
        );
    }

    public function test_destroy_forbids_deleting_other_users_subscription(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($customer);

        $this->assertForbiddenJson(
            $this->deleteJson('/api/v1/subscriptions/'.$subscription->id),
        );

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    public function test_destroy_returns_not_found_for_missing_subscription(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->assertNotFoundJson($this->deleteJson('/api/v1/subscriptions/99999'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function assertUnauthenticatedJson(TestResponse $response): TestResponse
    {
        return $response
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    protected function assertForbiddenJson(TestResponse $response): TestResponse
    {
        return $response
            ->assertForbidden()
            ->assertExactJson(['message' => 'Forbidden.']);
    }

    protected function assertNotFoundJson(TestResponse $response): TestResponse
    {
        return $response
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }
}
