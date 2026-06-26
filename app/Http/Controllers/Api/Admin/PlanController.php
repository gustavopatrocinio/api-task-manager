<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\StorePlanRequest;
use App\Http\Requests\Plan\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plan::class);

        $plans = Plan::query()->orderBy('name')->get();

        return PlanResource::collection($plans);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::query()->create($request->validated());

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Plan $plan): PlanResource
    {
        $this->authorize('view', $plan);

        return new PlanResource($plan);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $plan->update($request->validated());

        return new PlanResource($plan);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'plan' => ['Cannot delete a plan that has subscriptions.'],
            ]);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

    public function activate(Plan $plan): PlanResource
    {
        $this->authorize('update', $plan);

        $plan->update(['is_active' => true]);

        return new PlanResource($plan);
    }
}
