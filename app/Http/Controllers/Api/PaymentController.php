<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['subscription.plan'])
            ->latest();

        if (! $request->user()->isAdmin()) {
            $query->whereHas('subscription', fn ($builder) => $builder->where('user_id', $request->user()->id));
        }

        $filters = $request->filters();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        return PaymentResource::collection($query->get());
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        $payment->load(['subscription.plan', 'confirmedBy']);

        return new PaymentResource($payment);
    }
}
