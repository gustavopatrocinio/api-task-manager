<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $payment->subscription->user_id === $user->id;
    }

    public function confirm(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function fail(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
