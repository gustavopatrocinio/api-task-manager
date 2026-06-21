<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function createPending(Subscription $subscription, ?string $paymentMethod = null): Payment
    {
        $plan = $subscription->plan;

        return Payment::query()->create([
            'subscription_id' => $subscription->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => PaymentStatus::Pending,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function confirm(Payment $payment, User $admin, ?string $notes = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new InvalidArgumentException('Only pending payments can be confirmed.');
        }

        return DB::transaction(function () use ($payment, $admin, $notes) {
            $subscription = $payment->subscription()->with('plan')->firstOrFail();
            $paidAt = now();

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $paidAt,
                'confirmed_by' => $admin->id,
                'notes' => $notes,
            ]);

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $this->calculateEndsAt($subscription->plan, $paidAt),
            ]);

            return $payment->fresh(['subscription.plan', 'confirmedBy']);
        });
    }

    public function fail(Payment $payment, ?string $notes = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new InvalidArgumentException('Only pending payments can be marked as failed.');
        }

        return DB::transaction(function () use ($payment, $notes) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'notes' => $notes,
            ]);

            $payment->subscription()->update([
                'status' => SubscriptionStatus::PastDue,
            ]);

            return $payment->fresh(['subscription.plan']);
        });
    }

    private function calculateEndsAt(Plan $plan, Carbon $from): Carbon
    {
        return match ($plan->interval) {
            PlanInterval::Monthly => $from->copy()->addMonths($plan->interval_count),
            PlanInterval::Yearly => $from->copy()->addYears($plan->interval_count),
        };
    }
}
