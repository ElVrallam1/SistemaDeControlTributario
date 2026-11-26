<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

(new App\Controllers\AuthController($app))->recover();
