<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$c = new App\Controllers\AtencionContribuyenteController($app);
$action = $_GET['action'] ?? 'index';
match ($action) {
    'create' => $c->form(),
    'edit' => $c->form((int)($_GET['id'] ?? 0)),
    'delete' => $c->delete(),
    'export' => $c->export(),
    'export_consolidado' => $c->exportConsolidado(),
    'import' => $c->import(),
    'template' => $c->template(),
    default => $c->index(),
};
