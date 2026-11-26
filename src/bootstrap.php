<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once BASE_PATH . '/src/helpers/env.php';
loadEnv(BASE_PATH . '/.env');

$app = require BASE_PATH . '/config/app.php';

date_default_timezone_set($app['timezone']);

$debug = (bool) $app['debug'] && $app['env'] !== 'production';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app.log');

require_once BASE_PATH . '/src/helpers/security.php';
require_once BASE_PATH . '/src/helpers/db.php';
require_once BASE_PATH . '/src/helpers/auth.php';
require_once BASE_PATH . '/src/helpers/mail.php';
require_once BASE_PATH . '/src/helpers/view.php';

enforceHttps($app);
sendSecurityHeaders($app);
enforceCors($app);
secureSessionStart($app);

if ($app['key'] === '' || $app['key'] === 'CAMBIA_ESTA_CLAVE_POR_UNA_ALEATORIA_LARGA') {
    error_log('APP_KEY no configurada correctamente.');
}
