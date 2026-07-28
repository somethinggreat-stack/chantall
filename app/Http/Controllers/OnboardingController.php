<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    /** Apex Growth Solutions intake endpoint. */
    private const APEX_ENDPOINT = 'https://apexgrowthsolution.com/api/intake';

    /** Allowed upload extensions / max size (KB) — mirrors Apex's rules. */
    private const FILE_RULES = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

    /** Document fields, in the order Apex expects them. */
    private const DOC_FIELDS = ['drivers_license', 'ssn_card', 'proof_of_address'];

    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Receive the onboarding form, then forward it server-to-server to Apex so
     * the client is created in their dashboard. The X-Intake-Key never leaves
     * the server. Card/SSN/passwords are never logged.
     */
    public function submit(Request $request)
    {
        $ref = strtoupper(Str::random(8));

        // Some hosts' WAF (mod_security) rejects multipart file uploads with a
        // 406 before PHP ever runs, so the browser may instead send the three
        // documents base64-encoded inside a JSON body. Turn those back into
        // real uploads so everything below is identical for both transports.
        $temp = $this->hydrateBase64Documents($request, $ref);

        try {
            return $this->forward($request, $ref);
        } finally {
            foreach ($temp as $path) {
                @unlink($path);
            }
        }
    }

    private function forward(Request $request, string $ref)
    {
        Log::info('[onboarding] === submit received ===', [
            'ref'   => $ref,
            'ip'    => $request->ip(),
            'email' => $request->input('email'),
            'name'  => trim($request->input('first_name', '') . ' ' . $request->input('last_name', '')),
            'files' => array_values(array_filter(self::DOC_FIELDS, fn ($f) => $request->hasFile($f))),
        ]);

        // ---- Confirm the intake key is configured on the server ----
        $key = config('services.apex.key');
        if (! $key) {
            Log::error('[onboarding] APEX_INTAKE_KEY missing on server', ['ref' => $ref]);
            return response()->json(['ok' => false, 'message' => 'Onboarding is not configured yet. Please contact support.'], 500);
        }

        // ---- Validate on our side (fast, clean errors) before forwarding ----
        try {
            $request->validate([
                'first_name'     => ['required', 'string', 'max:100'],
                'middle_name'    => ['nullable', 'string', 'max:100'],
                'last_name'      => ['required', 'string', 'max:100'],
                'suffix'         => ['nullable', 'in:None,Jr.,Sr.,I,II,III,IV,V'],
                'email'          => ['required', 'email', 'max:150'],
                'phone'          => ['required', 'string', 'max:40'],
                'date_of_birth'  => ['required', 'date_format:Y-m-d'],
                'ssn'            => ['required', 'string', 'max:20'],
                'current_address'=> ['required', 'string', 'max:200'],
                'address_line2'  => ['nullable', 'string', 'max:200'],
                'city'           => ['required', 'string', 'max:100'],
                'state'          => ['required', 'string', 'max:60'],
                'zipcode'        => ['required', 'string', 'max:20'],
                'credit_monitoring_username'        => ['required', 'string', 'max:150'],
                'credit_monitoring_password'        => ['required', 'string', 'max:200'],
                'credit_monitoring_security_answer' => ['nullable', 'string', 'max:200'],
                'drivers_license'  => array_merge(['required'], self::FILE_RULES),
                'ssn_card'         => array_merge(['nullable'], self::FILE_RULES),
                'proof_of_address' => array_merge(['required'], self::FILE_RULES),
            ]);
            Log::info('[onboarding] validation passed', ['ref' => $ref]);
        } catch (ValidationException $e) {
            Log::warning('[onboarding] validation failed', ['ref' => $ref, 'fields' => array_keys($e->errors())]);
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        // ---- Build the text payload (provider is fixed server-side) ----
        $payload = $request->only([
            'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone',
            'date_of_birth', 'ssn', 'current_address', 'address_line2', 'city', 'state', 'zipcode',
            'credit_monitoring_username', 'credit_monitoring_password', 'credit_monitoring_security_answer',
        ]);
        $payload['credit_monitoring_name'] = 'MyFreeScoreNow';

        // ---- Attach documents as base64 & forward to Apex ----
        // Apex's host firewall (mod_security) rejects multipart uploads with a
        // 406 before PHP even runs, so documents are sent base64-encoded inside
        // the JSON body instead. This is the transport Apex's intake API is
        // built to accept (see IntakeController::decodeBase64Documents).
        foreach (self::DOC_FIELDS as $f) {
            if ($request->hasFile($f)) {
                $file = $request->file($f);
                $payload[$f . '_base64']   = base64_encode(file_get_contents($file->getRealPath()));
                $payload[$f . '_filename'] = $file->getClientOriginalName();
            }
        }

        try {
            $res = Http::withHeaders(['X-Intake-Key' => $key])
                ->timeout(60)
                ->asJson()
                ->post(self::APEX_ENDPOINT, $payload);
        } catch (\Throwable $e) {
            Log::error('[onboarding] could not reach Apex', ['ref' => $ref, 'message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'We could not reach the intake service. Please try again.'], 502);
        }

        $json = $res->json();
        Log::info('[onboarding] Apex responded', ['ref' => $ref, 'status' => $res->status(), 'ok' => (bool) data_get($json, 'ok')]);

        // ---- 201 / 2xx: success → let the browser show our thank-you popup ----
        if ($res->created() || $res->successful()) {
            return response()->json(['ok' => true, 'id' => data_get($json, 'id')]);
        }

        // ---- 401: bad/missing key (config issue on our side) ----
        if ($res->status() === 401) {
            Log::error('[onboarding] Apex rejected the intake key (401)', ['ref' => $ref]);
            return response()->json(['ok' => false, 'message' => data_get($json, 'message', 'Onboarding authorization failed. Please contact support.')], 401);
        }

        // ---- 422: validation from Apex → surface field errors on the form ----
        if ($res->status() === 422) {
            return response()->json(['ok' => false, 'errors' => data_get($json, 'errors', [])], 422);
        }

        // ---- Anything else ----
        Log::warning('[onboarding] Apex returned an unexpected status', ['ref' => $ref, 'status' => $res->status()]);
        return response()->json([
            'ok'      => false,
            'message' => data_get($json, 'message', 'The intake service returned an error (' . $res->status() . '). Please try again or contact support.'),
        ], 502);
    }

    /**
     * Convert `<field>_b64` JSON payloads ({name, data}) into real UploadedFile
     * instances on the request. Returns the temp paths so they can be cleaned
     * up once the request has been forwarded.
     *
     * @return string[]
     */
    private function hydrateBase64Documents(Request $request, string $ref): array
    {
        $temp = [];

        foreach (self::DOC_FIELDS as $field) {
            $doc = $request->input($field . '_b64');

            if (! is_array($doc) || ! is_string($doc['data'] ?? null) || $doc['data'] === '') {
                continue;
            }

            $name = (string) ($doc['name'] ?? $field);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Accept a bare payload or a data: URI, and reject anything that
            // isn't valid base64 before it can reach the filesystem.
            $data = $doc['data'];
            if (str_contains($data, ',') && str_starts_with($data, 'data:')) {
                $data = substr($data, strpos($data, ',') + 1);
            }
            $binary = base64_decode(strtr($data, '-_', '+/'), true);

            if ($binary === false || $binary === '' || ! in_array($ext, self::ALLOWED_EXT, true) || strlen($binary) > self::MAX_BYTES) {
                Log::warning('[onboarding] rejected base64 document', [
                    'ref' => $ref, 'field' => $field, 'ext' => $ext, 'bytes' => $binary === false ? 0 : strlen($binary),
                ]);
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'ob_');
            file_put_contents($path, $binary);
            $temp[] = $path;

            $request->files->set($field, new UploadedFile($path, $name, null, null, true));
            $request->request->remove($field . '_b64');
        }

        return $temp;
    }
}
