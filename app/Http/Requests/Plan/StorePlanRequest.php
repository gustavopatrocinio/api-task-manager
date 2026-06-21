<?php

namespace App\Http\Requests\Plan;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'interval' => ['required', Rule::enum(PlanInterval::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
