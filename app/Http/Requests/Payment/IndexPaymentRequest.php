<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'subscription_id' => ['sometimes', 'integer', 'exists:subscriptions,id'],
        ];
    }

    public function filters(): array
    {
        $filters = $this->validated();

        if (! $this->user()->isAdmin()) {
            unset($filters['subscription_id']);
        }

        return $filters;
    }
}
