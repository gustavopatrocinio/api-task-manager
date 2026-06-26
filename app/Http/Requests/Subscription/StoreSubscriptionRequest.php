<?php

namespace App\Http\Requests\Subscription;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Subscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'payment_method' => ['sometimes', 'nullable', 'string', 'in:pix,credit_card,boleto'],
        ];

        if ($this->user()?->isAdmin()) {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Customer->value),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'A plan must be selected.',
            'plan_id.exists' => 'The selected plan was not found.',
            'user_id.required' => 'A customer must be specified.',
            'user_id.exists' => 'The selected user is not a valid customer.',
            'payment_method.in' => 'Payment method must be pix, credit_card, or boleto.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $plan = Plan::query()->find($this->integer('plan_id'));

            if ($plan && ! $plan->is_active) {
                $validator->errors()->add(
                    'plan_id',
                    'This plan is no longer available.',
                );

                return;
            }

            $userId = $this->user()->isAdmin()
                ? $this->integer('user_id')
                : $this->user()->id;

            $hasBlockingSubscription = Subscription::query()
                ->where('user_id', $userId)
                ->blocking()
                ->exists();

            if ($hasBlockingSubscription) {
                $validator->errors()->add(
                    'subscription',
                    'You already have an ongoing subscription. Cancel it or resolve pending payments before subscribing to a new plan.',
                );
            }
        });
    }

    public function plan(): Plan
    {
        return Plan::query()->findOrFail($this->integer('plan_id'));
    }

    public function ownerUserId(): int
    {
        if ($this->user()->isAdmin()) {
            return $this->integer('user_id');
        }

        return $this->user()->id;
    }
}
