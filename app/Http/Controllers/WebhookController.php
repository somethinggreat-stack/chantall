<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SubscriptionEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Authorize.Net webhook receiver.
     *
     * Auth.net retries any non-2xx for ~24h with exponential backoff, so this
     * method ALWAYS returns 200 except for an enforced signature rejection.
     */
    public function handle(Request $request)
    {
        // 1) Raw body BEFORE parsing — the signature is computed over raw bytes.
        $raw = $request->getContent();

        // 2) Verify signature (true | false | null=unverifiable). Log-only unless enforced.
        $signatureValid = $this->verifySignature(
            $raw,
            $request->header('X-ANET-Signature'),
            config('services.authorizenet.signature_key')
        );

        Log::info('Auth.net webhook received', [
            'signature_valid' => $signatureValid,
            'ip'              => $request->ip(),
            'length'          => strlen($raw),
        ]);

        if (config('services.authorizenet.webhook_enforce_signature') && $signatureValid !== true) {
            Log::warning('Auth.net webhook REJECTED — signature invalid (enforcement on)');
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        // 3) Decode (strip BOM that Auth.net prepends).
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw), true);
        if (! is_array($data)) {
            Log::error('Auth.net webhook: unparseable body', ['body' => substr($raw, 0, 500)]);
            return response()->json(['ok' => true], 200);
        }

        $notificationId = $data['notificationId'] ?? null;
        $eventType      = $data['eventType'] ?? 'unknown';
        $payload        = (array) ($data['payload'] ?? []);

        // 5) Idempotency: skip if we've already seen this notificationId in the last 24h.
        $cacheKey = 'anet_wh_' . ($notificationId ?: md5($raw));
        if (Cache::has($cacheKey)) {
            return response()->json(['ok' => true, 'dedupe' => true], 200);
        }

        // 4) Persist the audit row (best-effort — never throw).
        $event = null;
        try {
            $event = $this->persistEvent($data, $eventType, $payload, $signatureValid, $request->ip());
        } catch (\Throwable $e) {
            Log::error('Auth.net webhook: failed to persist event', ['message' => $e->getMessage()]);
        }

        Cache::put($cacheKey, true, now()->addHours(24));

        // 6) Dispatch side-effects (best-effort — never throw).
        try {
            $this->dispatchEvent($eventType, $payload, $event);
        } catch (\Throwable $e) {
            Log::error('Auth.net webhook: dispatch failed', ['eventType' => $eventType, 'message' => $e->getMessage()]);
        }

        // 7) Always 200.
        return response()->json(['ok' => true], 200);
    }

    private function verifySignature(string $raw, ?string $header, ?string $key): ?bool
    {
        if (! $key) {
            return null; // cannot verify without a signature key
        }
        if (! $header) {
            return false;
        }
        // Header looks like:  sha512=<UPPERCASE_HEX>
        $provided = str_contains($header, '=') ? substr($header, strpos($header, '=') + 1) : $header;
        $computed = hash_hmac('sha512', $raw, $key);

        return hash_equals(strtoupper($computed), strtoupper(trim($provided)));
    }

    private function persistEvent(array $data, string $eventType, array $payload, ?bool $signatureValid, ?string $ip): WebhookEvent
    {
        $entityId = isset($payload['id']) ? (string) $payload['id'] : null;
        $invoice  = $payload['invoiceNumber'] ?? null;
        $amount   = $payload['authAmount'] ?? ($payload['amount'] ?? null);

        // Resolve the customer this event belongs to.
        $enrollment = $this->matchEnrollment($entityId, $invoice, $payload);

        $first = $enrollment->first_name ?? null;
        $last  = $enrollment->last_name ?? null;
        $email = $enrollment->email ?? ($payload['email'] ?? null);

        return WebhookEvent::updateOrCreate(
            ['notification_id' => $data['notificationId'] ?? Str::uuid()->toString()],
            [
                'event_type'            => $eventType,
                'entity_id'             => $entityId,
                'matched_enrollment_id' => $enrollment?->id,
                'customer_first_name'   => $first,
                'customer_last_name'    => $last,
                'customer_email'        => $email,
                'description'           => WebhookEvent::describeEvent($eventType, $payload, $first, $last),
                'amount'                => $amount !== null ? (float) $amount : null,
                'invoice_number'        => $invoice,
                'arb_status'            => $payload['status'] ?? null,
                'response_code'         => isset($payload['responseCode']) ? (string) $payload['responseCode'] : null,
                'signature_valid'       => $signatureValid,
                'source_ip'             => $ip,
                'received_at'           => now(),
                'payload'               => $data, // full envelope; redacted on display
            ]
        );
    }

    private function matchEnrollment(?string $entityId, ?string $invoice, array $payload): ?Enrollment
    {
        if ($entityId) {
            $byTxn = Enrollment::where('transaction_id', $entityId)->first();
            if ($byTxn) return $byTxn;
        }
        if ($invoice) {
            $byInv = Enrollment::where('invoice_number', $invoice)->first();
            if ($byInv) return $byInv;
        }
        if (! empty($payload['email'])) {
            return Enrollment::where('email', $payload['email'])->latest()->first();
        }
        return null;
    }

    private function dispatchEvent(string $eventType, array $payload, ?WebhookEvent $event): void
    {
        $enrollmentId = $event?->matched_enrollment_id;

        switch ($eventType) {
            case 'net.authorize.payment.authcapture.created':
                $this->recordPayment($payload, $event, 'initial', 'captured', $eventType);
                break;

            case 'net.authorize.payment.authorization.created':
                $this->recordPayment($payload, $event, 'auth_only', 'authorized', $eventType);
                break;

            case 'net.authorize.payment.capture.created':
            case 'net.authorize.payment.priorAuthCapture.created':
                $this->recordPayment($payload, $event, 'initial', 'captured', $eventType);
                break;

            case 'net.authorize.payment.refund.created':
                $this->recordPayment($payload, $event, 'refund', 'refunded', $eventType);
                break;

            case 'net.authorize.payment.void.created':
                $this->recordPayment($payload, $event, 'void', 'voided', $eventType);
                break;

            case 'net.authorize.payment.fraud.declined':
                $this->recordPayment($payload, $event, 'initial', 'failed', $eventType);
                break;

            case 'net.authorize.customer.subscription.failed':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'payment_failed');
                break;
            case 'net.authorize.customer.subscription.suspended':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'suspended');
                break;
            case 'net.authorize.customer.subscription.cancelled':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'cancelled');
                break;
            case 'net.authorize.customer.subscription.expired':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'expired');
                break;
            case 'net.authorize.customer.subscription.terminated':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'terminated');
                break;
            case 'net.authorize.customer.subscription.updated':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'updated');
                break;
            case 'net.authorize.customer.subscription.created':
                $this->recordSubscriptionEvent($enrollmentId, $payload, 'created');
                break;

            default:
                // fraud.held / fraud.approved / subscription.expiring / customer.* / paymentProfile.*
                // are informational — fully captured in webhook_events, nothing else to do.
                Log::info('Auth.net webhook informational event', ['eventType' => $eventType]);
        }
    }

    private function recordPayment(array $payload, ?WebhookEvent $event, string $type, string $status, string $eventType): void
    {
        $txnId = isset($payload['id']) ? (string) $payload['id'] : ($event?->notification_id ?? Str::uuid()->toString());
        $amount = $payload['authAmount'] ?? ($payload['amount'] ?? 0);

        Payment::updateOrCreate(
            ['transaction_id' => $txnId, 'type' => $type],
            [
                'enrollment_id'  => $event?->matched_enrollment_id,
                'invoice_number' => $payload['invoiceNumber'] ?? null,
                'amount'         => (float) $amount,
                'status'         => $status,
                'event_type_raw' => $eventType,
                'card_type'      => $event?->enrollment->card_type ?? null,
                'card_last4'     => $event?->enrollment->card_last4 ?? null,
                'customer_name'  => $event?->customerDisplayName(),
                'customer_email' => $event?->customer_email,
                'charged_at'     => now(),
                'raw_payload'    => $payload,
            ]
        );
    }

    private function recordSubscriptionEvent(?int $enrollmentId, array $payload, string $type): void
    {
        SubscriptionEvent::create([
            'enrollment_id'       => $enrollmentId,
            'arb_subscription_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'event_type'          => $type,
            'payload'             => $payload,
        ]);
    }
}
