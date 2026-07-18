<?php

namespace App\Models;

use App\Support\CardRedactor;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'enrollment_id', 'transaction_id', 'invoice_number', 'amount',
        'type', 'status', 'event_type_raw', 'card_type', 'card_last4',
        'customer_name', 'customer_email', 'charged_at', 'raw_payload',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'charged_at'  => 'datetime',
        'raw_payload' => 'array',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    /** Refunds and voids reduce money; render their amount as negative. */
    public function signedAmount(): float
    {
        $amount = (float) $this->amount;
        return in_array($this->type, ['refund', 'void'], true) ? -abs($amount) : $amount;
    }

    public function statusKind(): string
    {
        return match ($this->status) {
            'captured', 'authorized' => 'success',
            'refunded', 'voided'     => 'refund',
            'failed'                 => 'failed',
            default                  => 'info',
        };
    }

    public function sanitizedPayload(): array
    {
        return CardRedactor::redact($this->raw_payload ?? []);
    }
}
