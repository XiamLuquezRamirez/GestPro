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

    // SECOP II, contratos electrónicos (datos abiertos, API Socrata pública). El token es opcional:
    // sin él, datos.gov.co limita más las consultas por dirección IP.
    'secop' => [
        'url' => env('SECOP_URL', 'https://www.datos.gov.co/resource/jbjy-vk9h.json'),
        'token' => env('SECOP_TOKEN'),
        'timeout' => (int) env('SECOP_TIMEOUT', 15),
        'ca_bundle' => env('SECOP_CA_BUNDLE'),
    ],

];
