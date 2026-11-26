<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\User;

class PerfilController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    public function index(): void {
        $user = requireAuth();
        $errors = [];
        $success = isset($_GET['ok']);
        if (isPost()) {
            if (!verifyCsrf($this->app)) { $errors[] = 'Sesión de seguridad inválida.'; }
            else {
                $current = (string)($_POST['current_password'] ?? '');
                $new = (string)($_POST['new_password'] ?? '');
                $confirm = (string)($_POST['confirm_password'] ?? '');
                $dbUser = User::findById((int)$user['id']);
                if (!$dbUser || !password_verify($current, (string)$dbUser['password_hash'])) $errors[] = 'La contraseña actual no es correcta.';
                if (!passwordStrong($new)) $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres, mayúscula, minúscula, número y símbolo.';
                if ($new !== $confirm) $errors[] = 'La confirmación no coincide.';
                if ($current !== '' && $current === $new) $errors[] = 'La nueva contraseña debe ser diferente a la actual.';
                if (!$errors) {
                    User::updatePassword((int)$user['id'], password_hash($new, PASSWORD_DEFAULT), false, null);
                    auditLog((int)$user['id'], 'auth.password_changed', 'info', 'user', (string)$user['id']);
                    redirect('perfil.php?ok=1');
                }
            }
        }
        $fresh = User::findAuthById((int)$user['id']) ?: $user;
        view('perfil/index', [
            'app'=>$this->app,'user'=>$fresh,'errors'=>$errors,'success'=>$success,
            'pageTitle'=>'Mi perfil','pageSubtitle'=>'Información de cuenta y seguridad','activeMenu'=>'perfil'
        ]);
    }
}
