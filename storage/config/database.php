<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 5432),
    'name' => env('DB_NAME', 'seniat_control'),
    'user' => env('DB_USER', 'postgres'),
    'pass' => env('DB_PASS', ''),
    'sslmode' => env('DB_SSLMODE', 'prefer'),
];
