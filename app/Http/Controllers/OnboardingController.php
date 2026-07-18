<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

    /**
     * Receive the onboarding form, then forward it server-to-server to Apex so
     * the client is created in their dashboard. The X-Intake-Key never leaves
     * the server. Card/SSN/passwords are never logged.
     */
    public function submit(Request $request)
    {
        $ref = strtoupper(Str::random(8));

        Log::info('[onboarding] === submit received ===', [
            'ref'   => $ref,
            'ip'    => $request->ip(),
            'email' => $request->input('email'),
            'name'  => trim($request->input('first_name', '') . ' ' . $request->input('last_name', '')),
            'files' => array_values(array_filter(['drivers_license', 'ssn_card', 'proof_of_address'], fn ($f) => $request->hasFile($f))),
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

        // ---- Attach files & forward to Apex ----
        $req = Http::withHeaders(['X-Intake-Key' => $key])->timeout(60);
        foreach (['drivers_license', 'ssn_card', 'proof_of_address'] as $f) {
            if ($request->hasFile($f)) {
                $file = $request->file($f);
                $req = $req->attach($f, fopen($file->getRealPath(), 'r'), $file->getClientOriginalName());
            }
        }

        try {
            $res = $req->post(self::APEX_ENDPOINT, $payload);
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
        return response()->json(['ok' => false, 'message' => data_get($json, 'message', 'Submission failed. Please try again.')], 502);
    }
}
