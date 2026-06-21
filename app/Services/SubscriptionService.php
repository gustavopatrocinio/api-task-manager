<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private PaymentService $paymentService) {}

    public function subscribe(int $userId, Plan $plan, ?string $paymentMethod = null): Subscription
    {
        return DB::transaction(function () use ($userId, $plan, $paymentMethod) {
            $startsAt = now();

            $subscription = Subscription::query()->create([
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0
                    ? SubscriptionStatus::Trialing
                    : SubscriptionStatus::PastDue,
                'starts_at' => $startsAt,
                'trial_ends_at' => $plan->trial_days > 0
                    ? $startsAt->copy()->addDays($plan->trial_days)
                    : null,
            ]);

            $this->paymentService->createPending(
                $subscription->load('plan'),
                $paymentMethod,
            );

            return $subscription->load(['plan', 'user', 'latestPayment']);
        });
    }
}
