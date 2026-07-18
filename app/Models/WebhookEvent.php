<?php

namespace App\Models;

use App\Support\CardRedactor;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'notification_id', 'event_type', 'entity_id', 'matched_enrollment_id',
        'customer_first_name', 'customer_last_name', 'customer_email',
        'description', 'amount', 'invoice_number', 'arb_status', 'response_code',
        'signature_valid', 'source_ip', 'received_at', 'payload',
    ];

    protected $casts = [
        'payload'         => 'array',
        'amount'          => 'decimal:2',
        'signature_valid' => 'boolean',
        'received_at'     => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'matched_enrollment_id');
    }

    /* ---------------- display helpers (keep views dumb) ---------------- */

    public function responseCodeLabel(): ?string
    {
        return match ((string) $this->response_code) {
            '1' => 'Approved',
            '2' => 'Declined',
            '3' => 'Error',
            '4' => 'Held for Review',
            default => null,
        };
    }

    /** payment | subscription | profile | customer | other */
    public function category(): string
    {
        $t = $this->event_type;
        if (str_contains($t, 'paymentProfile')) return 'profile';
        if (str_starts_with($t, 'net.authorize.payment')) return 'payment';
        if (str_starts_with($t, 'net.authorize.customer.subscription')) return 'subscription';
        if (str_starts_with($t, 'net.authorize.customer')) return 'customer';
        return 'other';
    }

    /** success | failed | refund | warning | info */
    public function statusKind(): string
    {
        $t = $this->event_type;

        if (str_contains($t, 'refund') || str_contains($t, 'void')) return 'refund';
        if (str_contains($t, 'fraud.declined') || str_contains($t, 'subscription.failed')
            || str_contains($t, 'subscription.suspended') || str_contains($t, 'subscription.terminated')) return 'failed';
        if (str_contains($t, 'fraud.held') || str_contains($t, 'subscription.expiring')
            || str_contains($t, 'subscription.expired') || str_contains($t, 'subscription.cancelled')) return 'warning';
        if (str_contains($t, 'authcapture') || str_contains($t, 'capture.created')
            || str_contains($t, 'authorization.created') || str_contains($t, 'fraud.approved')
            || str_contains($t, 'subscription.created')) return 'success';

        // fall back to response code for generic payment events
        return match ((string) $this->response_code) {
            '1' => 'success',
            '2', '3' => 'failed',
            '4' => 'warning',
            default => 'info',
        };
    }

    public function statusBadge(): array
    {
        return match ($this->statusKind()) {
            'success' => ['label' => 'Success',  'kind' => 'success'],
            'failed'  => ['label' => 'Failed',   'kind' => 'failed'],
            'refund'  => ['label' => 'Refund',   'kind' => 'refund'],
            'warning' => ['label' => 'Review',   'kind' => 'warning'],
            default   => ['label' => 'Info',     'kind' => 'info'],
        };
    }

    public function customerDisplayName(): string
    {
        $name = trim(($this->customer_first_name ?? '') . ' ' . ($this->customer_last_name ?? ''));
        return $name !== '' ? $name : ($this->customer_email ?: '—');
    }

    /** PCI-redacted payload — the only thing a view should ever echo. */
    public function sanitizedPayload(): array
    {
        return CardRedactor::redact($this->payload ?? []);
    }

    /**
     * Plain-English summary, computed at insert time and stored in `description`.
     */
    public static function describeEvent(string $eventType, array $payload, ?string $firstName, ?string $lastName): string
    {
        $name = trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: 'a customer';
        $amount = isset($payload['authAmount']) ? '$' . number_format((float) $payload['authAmount'], 2)
            : (isset($payload['amount']) ? '$' . number_format((float) $payload['amount'], 2) : null);
        $invoice = $payload['invoiceNumber'] ?? null;
        $invoiceTxt = $invoice ? " (invoice {$invoice})" : '';
        $status = $payload['status'] ?? null;

        return match ($eventType) {
            'net.authorize.payment.authcapture.created'      => trim(($amount ? "Payment of {$amount} " : 'Payment ') . "captured successfully for {$name}{$invoiceTxt}."),
            'net.authorize.payment.authorization.created'    => trim(($amount ? "{$amount} " : '') . "authorized (not yet captured) for {$name}{$invoiceTxt}."),
            'net.authorize.payment.capture.created',
            'net.authorize.payment.priorAuthCapture.created' => trim(($amount ? "{$amount} " : '') . "captured for {$name}{$invoiceTxt}."),
            'net.authorize.payment.refund.created'           => trim(($amount ? "Refund of {$amount} " : 'Refund ') . "issued to {$name}{$invoiceTxt}."),
            'net.authorize.payment.void.created'             => "Transaction voided before settlement for {$name}{$invoiceTxt}.",
            'net.authorize.payment.fraud.held'               => "Payment from {$name} held for fraud review{$invoiceTxt}.",
            'net.authorize.payment.fraud.approved'           => "Payment from {$name} released from fraud review{$invoiceTxt}.",
            'net.authorize.payment.fraud.declined'           => "Payment from {$name} declined by fraud rules{$invoiceTxt}.",
            'net.authorize.customer.subscription.created'    => "Recurring subscription created for {$name}" . ($amount ? " at {$amount}." : '.'),
            'net.authorize.customer.subscription.updated'    => "Subscription updated for {$name}" . ($status ? " (status: {$status})." : '.'),
            'net.authorize.customer.subscription.failed'     => "A recurring charge failed for {$name}.",
            'net.authorize.customer.subscription.suspended'  => "Subscription suspended for {$name} after repeated failures.",
            'net.authorize.customer.subscription.cancelled'  => "Subscription cancelled for {$name}.",
            'net.authorize.customer.subscription.expired'    => "Subscription reached end of term for {$name}.",
            'net.authorize.customer.subscription.expiring'   => "Subscription for {$name} is expiring within 30 days.",
            'net.authorize.customer.subscription.terminated' => "Subscription terminated for {$name}.",
            'net.authorize.customer.created'                 => "Customer profile created for {$name}.",
            'net.authorize.customer.updated'                 => "Customer profile updated for {$name}.",
            'net.authorize.customer.deleted'                 => "Customer profile deleted for {$name}.",
            'net.authorize.customer.paymentProfile.created'  => "New card stored for {$name}.",
            'net.authorize.customer.paymentProfile.updated'  => "Stored card updated for {$name}.",
            'net.authorize.customer.paymentProfile.deleted'  => "Stored card removed for {$name}.",
            default                                          => "Event {$eventType} received.",
        };
    }
}
