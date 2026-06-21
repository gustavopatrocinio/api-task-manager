<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at,
            'confirmed_by' => $this->confirmed_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'confirmed_by_user' => new UserResource($this->whenLoaded('confirmedBy')),
        ];
    }
}
