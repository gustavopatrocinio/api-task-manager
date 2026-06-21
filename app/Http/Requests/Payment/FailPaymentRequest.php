<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class FailPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fail', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
