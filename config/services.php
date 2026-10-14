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
    // their local dev ports. `admin_url` targets the management dashboard — used for the links in
    // admin notification e-mails.
    'frontend' => [
        'admin_url' => env('FRONTEND_ADMIN_URL', 'http://localhost:3000'),
        'academy_url' => env('FRONTEND_ACADEMY_URL', 'http://localhost:3001'),
        'voting_url' => env('FRONTEND_VOTING_URL', 'http://localhost:3002'),
    ],

    // Brevo's transactional e-mail API (Rominas\Delivery\Brevo\BrevoMailService), used when
    // MAIL_DELIVERY_TRANSPORT=brevo (config/delivery.php). The sender is MAIL_FROM_* for both
    // transports. `timeout` is in seconds.
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'timeout' => (int) env('BREVO_TIMEOUT', 10),
    ],

    // ZeroBounce e-mail validation (Rominas\EmailValidation\ZeroBounce\ZeroBounceEmailValidator), run on
    // public voting-link requests. No API key = validation is off and every well-formed address is
    // accepted unchecked. The default base URL is the EU endpoint (GDPR); `timeout` is in seconds.
    'zerobounce' => [
        'api_key' => env('ZEROBOUNCE_API_KEY'),
        'base_url' => env('ZEROBOUNCE_BASE_URL', 'https://api-eu.zerobounce.net/v2'),
        'timeout' => (int) env('ZEROBOUNCE_TIMEOUT', 8),
    ],

];
