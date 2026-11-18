<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // برای withCredentials و HttpOnly Cookie نمی‌توان از wildcard استفاده کرد
    // دامنه‌های مجاز را از متغیر محیطی بخوانید (با کاما جدا کنید)
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))),

    // اگر allowed_origins خالی باشد، از پترن‌ها استفاده می‌شود
    'allowed_origins_patterns' => [
        '/^https:\/\/.*$/',                     // تمام HTTPS
        '/^http:\/\/localhost(:\d+)?$/',        // localhost
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',     // localhost IP
        '/^http:\/\/172\.17\.0\.\d+(:\d+)?$/',  // Docker IPs
    ],

    'allowed_headers' => ['*'],

    // Expose Content-Disposition so frontend can read filename from responses
    'exposed_headers' => [
        'Content-Disposition',
        'Content-Length',
        'Content-Type',
        'Cache-Control',
        'x-server-time'
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
