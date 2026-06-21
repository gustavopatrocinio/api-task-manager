<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
