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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cashfree' => [
        'key' => '',
        'secret' => '',
        'url' => 'https://sandbox.cashfree.com/pg/orders',
    ],

    'google_drive' => [
        'sa_client_email' => env('GOOGLE_SA_CLIENT_EMAIL'),
        'sa_private_key'  => env('GOOGLE_SA_PRIVATE_KEY'),
    ],

    'meta' => [
        'app_id'     => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        // Long-lived (Never-expiring) Facebook USER token of the person who manages the
        // Pages. Used by `php artisan marketing:sync-meta-tokens` to refresh every linked
        // Page/Instagram access token automatically so they never silently expire.
        'user_token' => env('META_USER_TOKEN'),
    ],

    'whatsapp' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

];
