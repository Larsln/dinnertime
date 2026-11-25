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

    'mealie' => [
        'url' => env('MEALIE_URL'),
        'token' => env('MEALIE_TOKEN'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'video_model' => env('GEMINI_VIDEO_MODEL', 'gemini-3.8-flash'),
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash-lite'),
    ],

    'mercure' => [
        'key' => env('MERCURE_JWT_KEY'),
        'issuer' => env('MERCURE_ISSUER', 'dinnertime'),
        'publish_url' => env('MERCURE_PUBLISH_URL', 'http://app/.well-known/mercure'),
    ],

    'ytdlp' => [
        'cookies' => env('YTDLP_COOKIES_FILE'),
        'keep_videos' => env('KEEP_VIDEOS', true),
        'max_video_mb' => (int) env('KEEP_VIDEOS_MAX_MB', 300),
    ],

    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
    ],

    // Sign-in with username and password. Turn off to allow only the
    // identity provider; local accounts then cannot sign in at all.
    'password_login' => (bool) env('PASSWORD_LOGIN', true),

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

];
