<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$controller = new App\Controllers\ExpedienteController($app);
$action = $_GET['action'] ?? 'index';

match ($action) {
    'create' => $controller->form(),
    'edit' => $controller->form((int) ($_GET['id'] ?? 0)),
    'delete' => $controller->delete(),
    'export' => $controller->export(),
    'import' => $controller->import(),
    'template' => $controller->template(),
    default => $controller->index(),
};
