<?php

namespace App\Http\Requests\Plan;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('plan')) ?? false;
    }

    public function rules(): array
    {
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'interval' => ['sometimes', Rule::enum(PlanInterval::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
