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

    // API paths that should handle CORS
    'paths' => [
        'api/*', 
        'glove-academy/api/*', 
        'glove-academy/public/api/*',
        'sanctum/csrf-cookie'
    ],

    'allowed_methods' => ['*'],

    // Allowed origins - read from environment or use defaults
    'allowed_origins' => array_filter(array_merge(
        explode(',', env('CORS_ALLOWED_ORIGINS', '')),
        [
            'http://localhost:5173',
            'http://localhost:3000',
            'https://gloveacademy.termresult.com',
        ]
    )),

    // Allow patterns for dynamic origins (e.g., *.termresult.com)
    'allowed_origins_patterns' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

]; 