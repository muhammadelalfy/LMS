<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Reads handwriting in photos (homework). Server-side only; the key never
    // reaches the app. The model can be swapped without code changes.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'ocr_model' => env('ANTHROPIC_OCR_MODEL', 'claude-opus-5-5'),
    ],

    // Fawry (Pay at Fawry reference numbers and e-wallets such as Vodafone
    // Cash). Values come from the Fawry merchant account. Use the staging host
    // https://atfawry.fawrystaging.com while testing and Fawry's production
    // host when live; `webhook_url` must be reachable by Fawry.
    'fawry' => [
        'merchant_code' => env('FAWRY_MERCHANT_CODE'),
        'secure_key' => env('FAWRY_SECURE_KEY'),
        'base_url' => env('FAWRY_BASE_URL', 'https://atfawry.fawrystaging.com'),
        'webhook_url' => env('FAWRY_WEBHOOK_URL'),
        'reference_method' => env('FAWRY_REFERENCE_METHOD', 'PAYATFAWRY'),
        'expiry_hours' => (int) env('FAWRY_EXPIRY_HOURS', 48),
        // Fawry requires a customer e-mail; used when the payer has none.
        'fallback_email' => env('FAWRY_FALLBACK_EMAIL', 'payments@example.com'),
    ],

    // Firebase Cloud Messaging push (free). Path to a service-account JSON key
    // from Firebase console → Project settings → Service accounts.
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

];
