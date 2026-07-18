<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Support\CardRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Server-side source of truth for plan pricing. The client never dictates
     * the amount — we look it up here by the plan key it sends.
     */
    private const PLANS = [
        'standard'  => ['name' => 'Standard Plan',       'amount' => 997.00],
        'fasttrack' => ['name' => 'Fast Track Plan',     'amount' => 1297.00],
        'vip'       => ['name' => 'VIP Credit Rebuild',  'amount' => 1497.00],
    ];

    private function isProduction(): bool
    {
        return config('services.authorizenet.environment') === 'production';
    }

    private function apiEndpoint(): string
    {
        return $this->isProduction()
            ? 'https://api.authorize.net/xml/v1/request.api'
            : 'https://apitest.authorize.net/xml/v1/request.api';
    }

    /**
     * Public, non-secret config the browser needs to tokenize the card with
     * Accept.js. The API Login ID and Public Client Key are designed to be
     * exposed client-side; the Transaction Key is NEVER sent to the browser.
     */
    public function config()
    {
        $loginId   = config('services.authorizenet.login_id');
        $clientKey = config('services.authorizenet.public_client_key');

        Log::info('[checkout] config requested by browser', [
            'env'           => config('services.authorizenet.environment'),
            'has_login_id'  => ! empty($loginId),
            'has_client_key'=> ! empty($clientKey),
        ]);

        return response()->json([
            'apiLoginID'  => $loginId,
            'clientKey'   => $clientKey,
            // Accept.js is served at /v1/ (there is no /v3/ — that 404s).
            'acceptJsUrl' => $this->isProduction()
                ? 'https://js.authorize.net/v1/Accept.js'
                : 'https://jstest.authorize.net/v1/Accept.js',
            'environment' => config('services.authorizenet.environment'),
        ]);
    }

    /**
     * Charge a card. The browser has already tokenized the card via Accept.js,
     * so we only ever receive an opaque, single-use payment nonce here — never
     * the raw card number, expiry or CVV.
     *
     * Every step below is logged (with a per-attempt "ref" so the steps can be
     * correlated in storage/logs/laravel.log). No card data or opaque token
     * value is ever written to the log.
     */
    public function charge(Request $request)
    {
        // A short id that ties every log line for this one attempt together.
        $ref = strtoupper(Str::random(8));

        Log::info('[checkout] === charge attempt STARTED ===', [
            'ref'        => $ref,
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 200),
            'plan'       => $request->input('plan'),
            'email'      => $request->input('email'),
            'name'       => trim($request->input('first', '') . ' ' . $request->input('last', '')),
            'has_token'  => ! empty($request->input('opaqueDataValue')),
        ]);

        // ---- Step 1: validate the incoming payload ----
        try {
            $data = $request->validate([
                'opaqueDataDescriptor' => ['required', 'string'],
                'opaqueDataValue'      => ['required', 'string'],
                'plan'                 => ['required', 'string'],
                'first'                => ['required', 'string', 'max:100'],
                'last'                 => ['required', 'string', 'max:100'],
                'email'                => ['required', 'email', 'max:150'],
                'phone'                => ['nullable', 'string', 'max:40'],
                'address'              => ['nullable', 'string', 'max:200'],
                'city'                 => ['nullable', 'string', 'max:100'],
                'state'                => ['nullable', 'string', 'max:60'],
                'zip'                  => ['nullable', 'string', 'max:20'],
                'agree_terms'          => ['nullable', 'boolean'],
                'agree_privacy'        => ['nullable', 'boolean'],
                'agree_marketing'      => ['nullable', 'boolean'],
            ]);
            Log::info('[checkout] step 1 OK — validation passed', ['ref' => $ref]);
        } catch (ValidationException $e) {
            Log::warning('[checkout] step 1 FAILED — validation errors', [
                'ref'    => $ref,
                'fields' => array_keys($e->errors()),
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        // ---- Step 2: resolve the plan & price server-side ----
        $planKey = strtolower($data['plan']);
        if (! isset(self::PLANS[$planKey])) {
            Log::warning('[checkout] step 2 FAILED — unknown plan', [
                'ref'           => $ref,
                'plan_received' => $data['plan'],
                'valid_plans'   => array_keys(self::PLANS),
            ]);
            return response()->json(['ok' => false, 'error' => 'Unknown plan selected.'], 422);
        }
        $plan    = self::PLANS[$planKey];
        $amount  = number_format($plan['amount'], 2, '.', '');
        $invoice = substr('INV' . now()->format('ymdHis') . rand(10, 99), 0, 20);

        Log::info('[checkout] step 2 OK — plan resolved', [
            'ref'     => $ref,
            'plan'    => $plan['name'],
            'amount'  => $amount,
            'invoice' => $invoice,
        ]);

        // ---- Step 3: confirm server-side merchant credentials exist ----
        $login = config('services.authorizenet.login_id');
        $txKey = config('services.authorizenet.transaction_key');
        if (! $login || ! $txKey) {
            Log::error('[checkout] step 3 FAILED — merchant credentials missing on server', [
                'ref'           => $ref,
                'has_login_id'  => ! empty($login),
                'has_txn_key'   => ! empty($txKey),
            ]);
            return response()->json(['ok' => false, 'error' => 'Payment is not configured.'], 500);
        }
        Log::info('[checkout] step 3 OK — merchant credentials present', [
            'ref'         => $ref,
            'environment' => config('services.authorizenet.environment'),
            'endpoint'    => $this->apiEndpoint(),
        ]);

        // ---- Step 4: build the Authorize.Net request payload ----
        // NOTE: Authorize.Net validates this against an XSD where element ORDER
        // matters. Within transactionRequest the required order is:
        //   transactionType, amount, payment, order, customer, billTo, customerIP, ...
        // so `customer` MUST come before `billTo`. (Matches the working Victoria
        // Funnel implementation.)
        $payload = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name'           => $login,
                    'transactionKey' => $txKey,
                ],
                'refId' => substr('PFC' . now()->format('ymdHis'), 0, 20),
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount'          => $amount,
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => $data['opaqueDataDescriptor'],
                            'dataValue'      => $data['opaqueDataValue'],
                        ],
                    ],
                    'order' => [
                        'invoiceNumber' => $invoice,
                        'description'   => $plan['name'] . ' enrollment',
                    ],
                    'customer' => ['email' => $data['email']],
                    'billTo' => array_filter([
                        'firstName' => $data['first'],
                        'lastName'  => $data['last'],
                        'address'   => $data['address'] ?? null,
                        'city'      => $data['city'] ?? null,
                        'state'     => $data['state'] ?? null,
                        'zip'       => $data['zip'] ?? null,
                        'country'   => 'USA',
                    ]),
                    'customerIP' => $request->ip(),
                ],
            ],
        ];

        Log::info('[checkout] step 4 OK — sending request to Authorize.Net', [
            'ref'     => $ref,
            // CardRedactor scrubs the opaque token (dataValue/dataDescriptor)
            // and the transaction key before anything is written to the log.
            'payload' => CardRedactor::redact([
                'amount'      => $amount,
                'invoice'     => $invoice,
                'description' => $plan['name'] . ' enrollment',
                'customer'    => $payload['createTransactionRequest']['transactionRequest']['customer'],
                'billTo'      => $payload['createTransactionRequest']['transactionRequest']['billTo'],
            ]),
        ]);

        // ---- Step 5: call Authorize.Net ----
        $startedAt = microtime(true);
        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post($this->apiEndpoint(), $payload);
        } catch (\Throwable $e) {
            Log::error('[checkout] step 5 FAILED — could not reach Authorize.Net', [
                'ref'     => $ref,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'We could not reach the payment processor. Please try again.'], 502);
        }
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::info('[checkout] step 5 OK — Authorize.Net responded', [
            'ref'         => $ref,
            'http_status' => $response->status(),
            'elapsed_ms'  => $elapsedMs,
        ]);

        // ---- Step 6: parse the response ----
        // Authorize.Net's JSON responses are prefixed with a UTF-8 BOM that breaks json_decode.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body());
        $json = json_decode(trim((string) $body), true);

        if (! is_array($json)) {
            Log::error('[checkout] step 6 FAILED — unparseable response from Authorize.Net', [
                'ref'  => $ref,
                'body' => substr((string) $body, 0, 1000),
            ]);
            return response()->json(['ok' => false, 'error' => 'Unexpected response from the payment processor.'], 502);
        }

        $resultCode   = data_get($json, 'messages.resultCode');
        $txn          = data_get($json, 'transactionResponse');
        $responseCode = data_get($txn, 'responseCode');

        Log::info('[checkout] step 6 OK — response parsed', [
            'ref'             => $ref,
            'result_code'     => $resultCode,
            'response_code'   => $responseCode,
            'transaction_id'  => (string) data_get($txn, 'transId'),
            'auth_code'       => (string) data_get($txn, 'authCode'),
            'avs'             => (string) data_get($txn, 'avsResultCode'),
            'cvv'             => (string) data_get($txn, 'cvvResultCode'),
            'messages'        => data_get($json, 'messages.message'),
            'txn_messages'    => data_get($txn, 'messages'),
            'txn_errors'      => data_get($txn, 'errors'),
        ]);

        // ---- Step 7a: APPROVED ----
        if ($resultCode === 'Ok' && $responseCode === '1') {
            $transId       = (string) data_get($txn, 'transId');
            $accountNumber = (string) data_get($txn, 'accountNumber'); // e.g. XXXX1111

            Log::info('[checkout] step 7 — transaction APPROVED, writing to database', [
                'ref'            => $ref,
                'transaction_id' => $transId,
                'amount'         => $amount,
            ]);

            try {
                $enrollment = Enrollment::create([
                    'plan'            => $plan['name'],
                    'amount'          => $plan['amount'],
                    'first_name'      => $data['first'],
                    'last_name'       => $data['last'],
                    'email'           => $data['email'],
                    'phone'           => $data['phone'] ?? null,
                    'address'         => $data['address'] ?? null,
                    'city'            => $data['city'] ?? null,
                    'state'           => $data['state'] ?? null,
                    'zip'             => $data['zip'] ?? null,
                    'agree_terms'     => (bool) ($data['agree_terms'] ?? false),
                    'agree_privacy'   => (bool) ($data['agree_privacy'] ?? false),
                    'agree_marketing' => (bool) ($data['agree_marketing'] ?? false),
                    'transaction_id'  => $transId,
                    'invoice_number'  => $invoice,
                    'auth_code'       => (string) data_get($txn, 'authCode'),
                    'card_type'       => (string) data_get($txn, 'accountType'),
                    'card_last4'      => substr($accountNumber, -4) ?: null,
                    'payment_status'  => 'paid',
                    'paid_at'         => now(),
                    'status'          => 'new',
                ]);

                Log::info('[checkout] step 7a OK — enrollment row created', [
                    'ref'           => $ref,
                    'enrollment_id' => $enrollment->id,
                ]);

                // Record the money row immediately (the webhook will also arrive and is idempotent).
                $payment = \App\Models\Payment::updateOrCreate(
                    ['transaction_id' => $transId, 'type' => 'initial'],
                    [
                        'enrollment_id'  => $enrollment->id,
                        'invoice_number' => $invoice,
                        'amount'         => $plan['amount'],
                        'status'         => 'captured',
                        'event_type_raw' => 'accept.js.charge',
                        'card_type'      => $enrollment->card_type,
                        'card_last4'     => $enrollment->card_last4,
                        'customer_name'  => trim($data['first'] . ' ' . $data['last']),
                        'customer_email' => $data['email'],
                        'charged_at'     => now(),
                        'raw_payload'    => ['source' => 'accept.js', 'transId' => $transId],
                    ]
                );

                Log::info('[checkout] step 7b OK — payment row recorded', [
                    'ref'        => $ref,
                    'payment_id' => $payment->id,
                ]);
            } catch (\Throwable $e) {
                // The card WAS charged, but we failed to persist it. Log loudly so
                // it can be reconciled by transaction id — do not lose the money.
                Log::critical('[checkout] step 7 DB WRITE FAILED — card was charged but NOT recorded', [
                    'ref'            => $ref,
                    'transaction_id' => $transId,
                    'invoice'        => $invoice,
                    'amount'         => $amount,
                    'message'        => $e->getMessage(),
                ]);

                return response()->json([
                    'ok'            => true,
                    'transactionId' => $transId,
                    'message'       => 'Payment approved.',
                ]);
            }

            Log::info('[checkout] === charge attempt COMPLETE (approved) ===', [
                'ref'            => $ref,
                'transaction_id' => $transId,
            ]);

            return response()->json([
                'ok'            => true,
                'transactionId' => $enrollment->transaction_id,
                'message'       => 'Payment approved.',
            ]);
        }

        // ---- Step 7b: DECLINED / HELD / ERROR — surface a clean message, store nothing. ----
        $error = data_get($txn, 'errors.0.errorText')
            ?? data_get($txn, 'messages.0.description')
            ?? data_get($json, 'messages.message.0.text')
            ?? 'Your card could not be processed. Please check your details and try again.';

        Log::warning('[checkout] === charge attempt COMPLETE (NOT approved) ===', [
            'ref'          => $ref,
            'resultCode'   => $resultCode,
            'responseCode' => $responseCode,
            'error'        => $error,
        ]);

        return response()->json(['ok' => false, 'error' => $error], 402);
    }
}
