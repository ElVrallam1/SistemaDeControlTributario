<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$c = new App\Controllers\AdminController($app);
$action = $_GET['action'] ?? 'index';
match ($action) {
    'users' => $c->users(),
    'update_user' => $c->updateUser(),
    'toggle_active' => $c->toggleActive(),
    'reset_password' => $c->resetPassword(),
    'audit' => $c->audit(),
    default => $c->index(),
};
