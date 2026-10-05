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

    // Base URLs of the frontend SPAs — used to build magic-link / voting-link targets.
    // `academy_url`/`voting_url` target the Academy and public-voting SPAs; the defaults are
    // their local dev ports.
    'frontend' => [
        'academy_url' => env('FRONTEND_ACADEMY_URL', 'http://localhost:3001'),
        'voting_url' => env('FRONTEND_VOTING_URL', 'http://localhost:3002'),
    ],

    // Brevo transactional email (Rominas\Delivery\Brevo\BrevoMailService). SMTP is the
    // active transport by default; enable the Brevo blocks in config/delivery.php and
    // AppServiceProvider to switch to it.
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'from_email' => env('BREVO_FROM_EMAIL'),
        'from_name' => env('BREVO_FROM_NAME'),
    ],

];
