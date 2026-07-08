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

    // Melipayamak console API (console.melipayamak.com). The account UUID (key)
    // is appended to each endpoint as /{key} at send time. The key + bodyIds are
    // normally managed from admin (sms.* settings); env is the fallback.
    'melipayamak' => [
        'api_key' => env('MELIPAYAMAK_API_KEY'),
        'otp_url' => env('MELIPAYAMAK_OTP_URL', 'https://console.melipayamak.com/api/send/otp'),
        'text_url' => env('MELIPAYAMAK_TEXT_URL', 'https://console.melipayamak.com/api/send/simple'),
        'body_url' => env('MELIPAYAMAK_BODY_URL', 'https://console.melipayamak.com/api/send/shared'),
    ],

];
