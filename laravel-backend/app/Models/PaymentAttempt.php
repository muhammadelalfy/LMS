<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One try to pay a payment online; see the create_payment_attempts migration. */
class PaymentAttempt extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const CANCELED = 'canceled';

    protected $fillable = [
        'payment_id', 'provider', 'method', 'merchant_ref', 'reference_number', 'wallet_mobile',
        'amount', 'status', 'expires_at', 'paid_at', 'requested_by',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'paid_at' => 'datetime', 'amount' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Still waiting for the customer: not paid, not closed, not past its expiry. */
    public function isOpen(): bool
    {
        return $this->status === self::PENDING && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** What the app shows for the attempt. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => $this->amount,
            'reference_number' => $this->reference_number,
            'wallet_mobile' => $this->wallet_mobile,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
