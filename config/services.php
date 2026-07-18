<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'authorizenet' => [
        'login_id'           => env('AUTHNET_API_LOGIN_ID'),
        'transaction_key'    => env('AUTHNET_TRANSACTION_KEY'),
        'signature_key'      => env('AUTHNET_SIGNATURE_KEY'),
        'public_client_key'  => env('AUTHNET_PUBLIC_CLIENT_KEY'),
        'environment'        => env('AUTHNET_ENVIRONMENT', 'production'),
        // Ship signature verification in log-only mode first; flip to true once logs prove a match.
        'webhook_enforce_signature' => env('AUTHNET_WEBHOOK_ENFORCE_SIGNATURE', false),
    ],

    // Apex Growth Solutions onboarding intake API (server-to-server).
    'apex' => [
        'key' => env('APEX_INTAKE_KEY'),
    ],

];
