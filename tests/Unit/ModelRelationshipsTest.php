<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_subscriptions_and_payments_through_subscriptions(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create();
        $payment = Payment::factory()->for($subscription)->create();

        $this->assertTrue($user->subscriptions->contains($subscription));
        $this->assertTrue($user->payments->contains($payment));
    }

    public function test_plan_has_subscriptions(): void
    {
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->for($plan)->create();

        $this->assertTrue($plan->subscriptions->contains($subscription));
    }

    public function test_subscription_belongs_to_user_and_plan_and_has_payments(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()
            ->for($user)
            ->for($plan)
            ->create(['status' => SubscriptionStatus::Active]);

        $payment = Payment::factory()->for($subscription)->create();

        $this->assertTrue($subscription->user->is($user));
        $this->assertTrue($subscription->plan->is($plan));
        $this->assertTrue($subscription->payments->contains($payment));
        $this->assertTrue($subscription->latestPayment->is($payment));
    }

    public function test_payment_belongs_to_subscription_and_confirmed_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()
            ->for($subscription)
            ->paid()
            ->create(['confirmed_by' => $admin->id]);

        $this->assertTrue($payment->subscription->is($subscription));
        $this->assertTrue($payment->confirmedBy->is($admin));
        $this->assertSame(PaymentStatus::Paid, $payment->status);
    }

    public function test_plan_active_scope_and_active_subscriptions(): void
    {
        $activePlan = Plan::factory()->create(['is_active' => true]);
        $inactivePlan = Plan::factory()->inactive()->create();

        Subscription::factory()->for($activePlan)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->for($activePlan)->create(['status' => SubscriptionStatus::Cancelled]);
        Subscription::factory()->for($inactivePlan)->create(['status' => SubscriptionStatus::Active]);

        $this->assertCount(1, Plan::query()->active()->get());
        $this->assertCount(1, $activePlan->activeSubscriptions);
    }
}
