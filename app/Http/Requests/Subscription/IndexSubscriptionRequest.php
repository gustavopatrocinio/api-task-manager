<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
        ];
    }

    public function filters(): array
    {
        $filters = $this->validated();

        if (! $this->user()->isAdmin()) {
            unset($filters['user_id']);
        }

        return $filters;
    }
}
