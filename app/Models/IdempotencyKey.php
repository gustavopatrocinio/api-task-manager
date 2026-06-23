<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'fingerprint',
        'status',
        'response_code',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === IdempotencyStatus::Completed;
    }

    public function isProcessing(): bool
    {
        return $this->status === IdempotencyStatus::Processing;
    }
}
