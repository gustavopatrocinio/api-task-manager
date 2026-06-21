<?php

namespace App\Http\Requests\Subscription;

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
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'payment_method' => ['sometimes', 'nullable', 'string', 'in:pix,credit_card,boleto'],
        ];

        if ($this->user()?->isAdmin()) {
            $rules['user_id'] = ['required', 'integer', 'exists:users,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = $this->user()->isAdmin()
                ? $this->integer('user_id')
                : $this->user()->id;

            $hasActiveSubscription = Subscription::query()
                ->where('user_id', $userId)
                ->active()
                ->exists();

            if ($hasActiveSubscription) {
                $validator->errors()->add(
                    'plan_id',
                    'The user already has an active subscription.',
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
