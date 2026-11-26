<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (isLoggedIn()) {
    $u = currentUser();
    if ($u && (int) $u['must_change_password'] === 1) {
        redirect('cambiar-clave.php');
    }
    redirect('dashboard.php');
}

redirect('login.php');
