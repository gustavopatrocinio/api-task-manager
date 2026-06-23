<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_creates_pending_payment(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create(['trial_days' => 0, 'price' => 49.90]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
            'payment_method' => 'pix',
        ], $this->idempotencyHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.latest_payment.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.latest_payment.amount', '49.90')
            ->assertJsonPath('data.latest_payment.payment_method', 'pix');

        $this->assertDatabaseHas('payments', [
            'status' => PaymentStatus::Pending->value,
            'payment_method' => 'pix',
        ]);
    }

    public function test_customer_can_list_own_payments(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->for($customer)->create();
        $payment = Payment::factory()->for($subscription)->create();

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);
    }

    public function test_customer_cannot_view_other_users_payment(): void
    {
        $payment = Payment::factory()->create();

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/payments/'.$payment->id)->assertForbidden();
    }

    public function test_admin_can_confirm_payment_and_activate_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::PastDue]);
        $payment = Payment::factory()->for($subscription)->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm', [
            'notes' => 'Pagamento recebido via PIX',
        ], $this->idempotencyHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.confirmed_by', $admin->id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_admin_can_fail_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::PastDue]);
        $payment = Payment::factory()->for($subscription)->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/fail', [], $this->idempotencyHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Failed->value);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::PastDue->value,
        ]);
    }

    public function test_admin_confirm_is_idempotent_for_already_paid_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = Payment::factory()->paid()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm', [], $this->idempotencyHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);
    }

    public function test_payments_require_authentication(): void
    {
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }
}
