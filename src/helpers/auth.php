<?php

declare(strict_types=1);

use App\Models\User;

function findUserByLogin(string $login): ?array
{
    return User::findByLogin($login);
}

function findUserByEmail(string $email): ?array
{
    return User::findByEmail($email);
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    static $loadedId = null;
    $id = (int) $_SESSION['user_id'];
    if ($user !== null && $loadedId === $id) {
        return $user;
    }
    $user = User::findAuthById($id);
    $loadedId = $id;
    if (!$user || !(bool) $user['is_active']) {
        return null;
    }
    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('login.php');
    }
    if ($user['must_change_password'] && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'cambiar-clave.php') {
        redirect('cambiar-clave.php');
    }
    return $user;
}

function requireAdmin(): array
{
    return requireAnyRole(['admin']);
}

function requireAnyRole(array $roles): array
{
    $user = requireAuth();
    $role = (string) ($user['role_name'] ?? '');
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        auditLog((int) $user['id'], 'auth.forbidden_role', 'warning', 'route', $_SERVER['REQUEST_URI'] ?? '', ['required' => $roles, 'role' => $role]);
        exit('Acceso denegado.');
    }
    return $user;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role_name'] = $user['role_name'] ?? '';
    $_SESSION['_last_activity'] = time();
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function passwordStrong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function generateTempPassword(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?';
    $max = strlen($chars) - 1;
    $pass = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, $max)];
    }
    return $pass;
}
