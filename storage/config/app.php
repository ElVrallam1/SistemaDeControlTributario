<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'SENIAT Control'),
    'env' => env('APP_ENV', 'development'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/Caracas'),
    'key' => env('APP_KEY', ''),
    'force_https' => filter_var(env('FORCE_HTTPS', true), FILTER_VALIDATE_BOOLEAN),
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('ALLOWED_ORIGINS', '')))),
    'session' => [
        'name' => env('SESSION_NAME', 'seniat_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 1800),
    ],
    'csrf_ttl' => (int) env('CSRF_TTL', 3600),
    'rate_limit' => [
        'max_attempts' => (int) env('RATE_LIMIT_MAX_ATTEMPTS', 5),
        'window_seconds' => (int) env('RATE_LIMIT_WINDOW_SECONDS', 900),
    ],
    'password_reset_ttl' => (int) env('PASSWORD_RESET_TTL', 900),
    'temp_password_ttl' => (int) env('TEMP_PASSWORD_TTL', 3600),
    'uploads' => [
        'max_size_mb' => (int) env('MAX_UPLOAD_SIZE_MB', 5),
        'allowed_mime' => array_filter(array_map('trim', explode(',', (string) env('ALLOWED_UPLOAD_MIME', 'application/pdf,image/png,image/jpeg')))),
    ],
    'mail' => [
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'user' => env('MAIL_USER', ''),
        'pass' => env('MAIL_PASS', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@seniat-control.local'),
        'from_name' => env('MAIL_FROM_NAME', 'SENIAT Control'),
    ],
];
