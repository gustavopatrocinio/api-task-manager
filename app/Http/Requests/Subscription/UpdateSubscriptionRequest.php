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
        if ($this->user()?->isAdmin()) {
            return [
                'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
                'starts_at' => ['sometimes', 'date'],
                'ends_at' => ['sometimes', 'nullable', 'date'],
                'trial_ends_at' => ['sometimes', 'nullable', 'date'],
                'cancelled_at' => ['sometimes', 'nullable', 'date'],
                'plan_id' => [
                    'sometimes',
                    'integer',
                    Rule::exists('plans', 'id')->where('is_active', true),
                ],
            ];
        }

        return [
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected plan is not available.',
            'cancelled_at.required' => 'The cancellation date cannot be null when cancelling.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Subscription $subscription */
            $subscription = $this->route('subscription');

            if ($this->user()->isAdmin()) {
                $this->validateAdminStatusChange($validator, $subscription);

                return;
            }

            $this->validateCustomerUpdate($validator, $subscription);
        });
    }

    private function validateCustomerUpdate(Validator $validator, Subscription $subscription): void
    {
        foreach (['plan_id', 'starts_at', 'ends_at', 'trial_ends_at'] as $field) {
            if ($this->has($field)) {
                $validator->errors()->add($field, 'You are not allowed to update this field.');
            }
        }

        if (! $this->has('status')) {
            return;
        }

        if ($this->input('status') !== SubscriptionStatus::Cancelled->value) {
            $validator->errors()->add('status', 'You may only cancel your subscription.');

            return;
        }

        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            $validator->errors()->add('status', 'This subscription can no longer be cancelled.');

            return;
        }

        if ($this->has('cancelled_at') && $this->input('cancelled_at') === null) {
            $validator->errors()->add(
                'cancelled_at',
                'The cancellation date cannot be null when cancelling.',
            );
        }
    }

    private function validateAdminStatusChange(Validator $validator, Subscription $subscription): void
    {
        if (! $this->has('status')) {
            return;
        }

        $newStatus = SubscriptionStatus::from($this->input('status'));

        if ($newStatus === $subscription->status) {
            return;
        }

        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            $validator->errors()->add(
                'status',
                'Cannot change the status of a cancelled or expired subscription.',
            );
        }
    }
}
