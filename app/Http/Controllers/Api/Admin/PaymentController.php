<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ConfirmPaymentRequest;
use App\Http\Requests\Payment\FailPaymentRequest;
use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['subscription.plan', 'confirmedBy'])
            ->latest();

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

    public function confirm(ConfirmPaymentRequest $request, Payment $payment): PaymentResource
    {
        try {
            $payment = $this->paymentService->confirm(
                $payment,
                $request->user(),
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        return new PaymentResource($payment);
    }

    public function fail(FailPaymentRequest $request, Payment $payment): PaymentResource
    {
        try {
            $payment = $this->paymentService->fail(
                $payment,
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        return new PaymentResource($payment);
    }
}
