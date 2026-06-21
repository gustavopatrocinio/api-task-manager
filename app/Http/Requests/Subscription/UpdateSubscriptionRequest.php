<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Subscription $subscription */
        $subscription = $this->route('subscription');

        return $this->user()?->can('update', $subscription) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'cancelled_at' => ['sometimes', 'nullable', 'date'],
        ];

        if ($this->user()?->isAdmin()) {
            $rules['plan_id'] = ['sometimes', 'integer', 'exists:plans,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('status') !== SubscriptionStatus::Cancelled->value) {
                return;
            }

            if (! $this->has('cancelled_at')) {
                return;
            }

            if ($this->input('cancelled_at') === null) {
                $validator->errors()->add(
                    'cancelled_at',
                    'The cancelled at field is required when status is cancelled.',
                );
            }
        });
    }
}
