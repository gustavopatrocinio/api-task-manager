<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\IndexSubscriptionRequest;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService)
    {
        $this->middleware('idempotent')->only('store');
    }

    public function index(IndexSubscriptionRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        $query = Subscription::query()
            ->with(['plan', 'user', 'latestPayment'])
            ->latest();

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $filters = $request->filters();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        return SubscriptionResource::collection($query->get());
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->subscriptionService->subscribe(
            $request->ownerUserId(),
            $request->plan(),
            $request->validated('payment_method'),
        );

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);

        $subscription->load(['plan', 'user', 'latestPayment']);

        return new SubscriptionResource($subscription);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validated();

        if (($data['status'] ?? null) === SubscriptionStatus::Cancelled->value && ! array_key_exists('cancelled_at', $data)) {
            $data['cancelled_at'] = now();
        }

        $subscription->update($data);
        $subscription->load(['plan', 'user', 'latestPayment']);

        return new SubscriptionResource($subscription);
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return response()->json(null, 204);
    }
}
