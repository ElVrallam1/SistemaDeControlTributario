<?php

declare(strict_types=1);

function view(string $template, array $data = []): void
{
    $file = BASE_PATH . '/templates/' . ltrim($template, '/') . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo 'Vista no encontrada.';
        return;
    }
    extract($data, EXTR_SKIP);
    include $file;
}
