<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use DateTimeImmutable;

class AuthController
{
    private array $app;

    public function __construct(array $app)
    {
        $this->app = $app;
    }

    public static function departments(): array
    {
        return [
            'Despacho de la Gerencia',
            'División de Recaudación',
            'División de Fiscalización',
            'División de Sumario Administrativo',
            'División Jurídica Tributaria',
            'División de Asistencia al Contribuyente',
            'Contribuyentes Especiales (Región Capital)',
            'Sectores y Unidades de Tributos Internos',
        ];
    }

    public function login(): void
    {
        if (isLoggedIn()) {
            $u = currentUser();
            if ($u && (int) $u['must_change_password'] === 1) redirect('cambiar-clave.php');
            redirect('dashboard.php');
        }

        $errors = [];
        $oldLogin = '';

        if (isPost()) {
            if (!verifyCsrf($this->app)) {
                $errors[] = 'La sesión de seguridad expiró. Recarga la página e intenta de nuevo.';
                auditLog(null, 'auth.csrf_invalid', 'warning', 'login');
            } else {
                $oldLogin = sanitizeText($_POST['login'] ?? '', 150);
                $password = (string) ($_POST['password'] ?? '');
                $ip = clientIp();
                $identifier = mb_strtolower($oldLogin);

                if ($oldLogin === '' || $password === '') {
                    $errors[] = 'Usuario/correo y contraseña son obligatorios.';
                }

                $maxAttempts = (int) $this->app['rate_limit']['max_attempts'];
                $window = (int) $this->app['rate_limit']['window_seconds'];
                if (!$errors && (rateLimitExceeded('ip:' . $ip, $maxAttempts, $window) || ($identifier !== '' && rateLimitExceeded('login:' . $identifier, $maxAttempts, $window)))) {
                    $errors[] = 'Demasiados intentos. Espera unos minutos antes de intentar nuevamente.';
                    auditLog(null, 'auth.rate_limited', 'warning', 'login', null, ['identifier' => $identifier]);
                }

                if (!$errors) {
                    $user = User::findByLogin($oldLogin);
                    $ok = false;
                    if ($user && (int) $user['is_active'] === 1) {
                        if (!empty($user['temp_password_expires_at']) && strtotime((string) $user['temp_password_expires_at']) < time()) {
                            $errors[] = 'La contraseña temporal expiró. Solicita una nueva recuperación.';
                            auditLog((int) $user['id'], 'auth.temp_password_expired', 'warning', 'user', (string) $user['id']);
                        } elseif (password_verify($password, (string) $user['password_hash'])) {
                            $ok = true;
                        }
                    }

                    recordLoginAttempt('ip:' . $ip, $ok);
                    if ($identifier !== '') {
                        recordLoginAttempt('login:' . $identifier, $ok);
                    }

                    if ($ok && $user) {
                        User::markLoginSuccess((int) $user['id'], $ip);
                        loginUser($user);
                        auditLog((int) $user['id'], 'auth.login_success', 'info', 'user', (string) $user['id']);
                        if ((int) $user['must_change_password'] === 1) {
                            redirect('cambiar-clave.php');
                        }
                        redirect('dashboard.php');
                    } else {
                        if ($user) {
                            User::incrementFailedLogin((int) $user['id']);
                            auditLog((int) $user['id'], 'auth.login_failed', 'warning', 'user', (string) $user['id']);
                        } else {
                            auditLog(null, 'auth.login_failed_unknown', 'warning', 'login', null, ['login' => $identifier]);
                        }
                        if (!$errors) {
                            $errors[] = 'Credenciales inválidas o cuenta inactiva.';
                        }
                    }
                }
            }
        }

        $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
        view('auth/login', ['app' => $this->app, 'errors' => $errors, 'oldLogin' => $oldLogin, 'flash' => $flash]);
    }

    public function register(): void
    {
        if (isLoggedIn()) redirect('dashboard.php');

        $errors = [];
        $old = ['full_name' => '', 'username' => '', 'email' => '', 'department' => ''];
        $departments = self::departments();

        if (isPost()) {
            if (!verifyCsrf($this->app)) {
                $errors[] = 'La sesión de seguridad expiró. Recarga la página.';
            } else {
                $old['full_name'] = sanitizeText($_POST['full_name'] ?? '', 150);
                $old['username'] = sanitizeText($_POST['username'] ?? '', 80);
                $old['email'] = mb_strtolower(sanitizeText($_POST['email'] ?? '', 150));
                $old['department'] = sanitizeText($_POST['department'] ?? '', 120);
                $password = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['password_confirm'] ?? '');
                $ip = clientIp();

                if (rateLimitExceeded('reg:' . $ip, 10, 3600)) {
                    $errors[] = 'Demasiados intentos de registro desde esta red. Intenta más tarde.';
                }
                if ($old['full_name'] === '' || mb_strlen($old['full_name']) < 3) $errors[] = 'El nombre completo es obligatorio (mínimo 3 caracteres).';
                if ($old['username'] === '' || !preg_match('/^[a-zA-Z0-9._-]{4,80}$/', $old['username'])) $errors[] = 'El usuario debe tener 4-80 caracteres (letras, números, punto, guion o guion bajo).';
                if (!validateEmail($old['email'])) $errors[] = 'El correo electrónico no es válido.';
                if ($old['department'] === '' || !in_array($old['department'], $departments, true)) $errors[] = 'Selecciona un departamento o división válido.';
                if (!passwordStrong($password)) $errors[] = 'La contraseña debe tener al menos 8 caracteres, mayúscula, minúscula, número y símbolo.';
                if ($password !== $confirm) $errors[] = 'Las contraseñas no coinciden.';

                if (!$errors && User::existsByUsernameOrEmail($old['username'], $old['email'])) {
                    $errors[] = 'El usuario o correo ya está registrado.';
                }

                recordLoginAttempt('reg:' . $ip, empty($errors));

                if (!$errors) {
                    $roleId = User::roleIdByName('operador');
                    if ($roleId <= 0) {
                        $errors[] = 'No se encontró el rol de operador. Verifica la base de datos.';
                    } else {
                        $newId = User::create([
                            'full_name' => $old['full_name'],
                            'username' => $old['username'],
                            'email' => $old['email'],
                            'department' => $old['department'],
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'role_id' => $roleId,
                        ]);
                        auditLog($newId, 'auth.register', 'info', 'user', (string) $newId);
                        redirect('login.php?registered=1');
                    }
                }
            }
        }

        view('auth/register', ['app' => $this->app, 'errors' => $errors, 'old' => $old, 'departments' => $departments]);
    }

    public function recover(): void
    {
        if (isLoggedIn()) redirect('dashboard.php');

        $errors = [];
        $email = '';

        if (isPost()) {
            if (!verifyCsrf($this->app)) {
                $errors[] = 'La sesión de seguridad expiró. Recarga la página.';
            } else {
                $email = mb_strtolower(sanitizeText($_POST['email'] ?? '', 150));
                $ip = clientIp();

                if (!validateEmail($email)) {
                    $errors[] = 'Ingresa un correo electrónico válido.';
                }
                if (!$errors && (rateLimitExceeded('recover:ip:' . $ip, 5, 900) || rateLimitExceeded('recover:email:' . $email, 3, 900))) {
                    $errors[] = 'Demasiados intentos de recuperación. Espera unos minutos e intenta de nuevo.';
                    auditLog(null, 'auth.recover_rate_limited', 'warning', 'recovery', null, ['email' => $email]);
                }

                if (!$errors) {
                    $user = User::findByEmail($email);
                    auditLog($user ? (int) $user['id'] : null, 'auth.recover_requested', 'info', 'user', $user ? (string) $user['id'] : null, ['email' => $email]);

                    if ($user && (int) $user['is_active'] === 1) {
                        $tempPassword = generateTempPassword(12);
                        $tempHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                        $ttl = (int) $this->app['temp_password_ttl'];
                        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttl . ' seconds')->format('Y-m-d H:i:sP');

                        User::updatePassword((int) $user['id'], $tempHash, true, $expiresAt);
                        User::createRecoveryRequest((int) $user['id'], $email, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000), $tempHash, $expiresAt);

                        $safeUser = e($user['username']);
                        $safeName = e($user['full_name']);
                        $safePass = e($tempPassword);
                        $minutes = (int) ceil($ttl / 60);
                        $subject = 'Recuperación de acceso - SENIAT Control';
                        $html = "<div style='font-family:Arial,sans-serif;color:#1f2937;line-height:1.6'>"
                            . "<h2 style='color:#8b1e1e'>Recuperación de acceso</h2>"
                            . "<p>Hola <strong>{$safeName}</strong>,</p>"
                            . "<p>Se solicitó la recuperación de acceso para tu cuenta en SENIAT Control.</p>"
                            . "<p><strong>Usuario:</strong> {$safeUser}<br>"
                            . "<strong>Contraseña temporal:</strong> {$safePass}</p>"
                            . "<p>Esta contraseña temporal expira en <strong>{$minutes} minutos</strong>. Al iniciar sesión, el sistema te pedirá cambiarla.</p>"
                            . "<p>Si no solicitaste esta recuperación, contacta al administrador inmediatamente.</p>"
                            . "</div>";
                        $text = "Recuperación de acceso\nUsuario: {$user['username']}\nContraseña temporal: {$tempPassword}\nExpira en {$minutes} minutos. Debes cambiarla al iniciar sesión.";

                        $sent = sendAppMail($this->app, $email, $subject, $html, $text);
                        auditLog((int) $user['id'], $sent ? 'auth.recover_email_sent' : 'auth.recover_email_failed', $sent ? 'info' : 'critical', 'user', (string) $user['id'], [
                            'email' => $email,
                            'smtp_configured' => (($this->app['mail']['host'] ?? '') !== '' && ($this->app['mail']['host'] ?? '') !== 'smtp.example.com'),
                        ]);
                        if (!$sent) {
                            error_log('[SENIAT] Recuperación: no se pudo enviar correo a ' . $email . '. Verifique configuración SMTP en .env');
                            if ($this->app['debug']) {
                                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Depuración: el correo de recuperación no pudo enviarse. Verifica SMTP en .env.'];
                            }
                        }
                    }

                    redirect('login.php?recovered=1');
                }
            }
        }

        view('auth/recuperar', ['app' => $this->app, 'errors' => $errors, 'email' => $email]);
    }

    public function changePassword(): void
    {
        $user = currentUser();
        if (!$user) redirect('login.php');

        $errors = [];
        $mustChange = (int) $user['must_change_password'] === 1;

        if (isPost()) {
            if (!verifyCsrf($this->app)) {
                $errors[] = 'La sesión de seguridad expiró. Recarga la página.';
            } else {
                $current = (string) ($_POST['current_password'] ?? '');
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['confirm_password'] ?? '');

                $dbUser = User::findById((int) $user['id']);
                if (!$dbUser || !password_verify($current, (string) $dbUser['password_hash'])) {
                    $errors[] = 'La contraseña actual no es correcta.';
                }
                if (!passwordStrong($new)) $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres, mayúscula, minúscula, número y símbolo.';
                if ($new !== $confirm) $errors[] = 'La confirmación no coincide.';
                if ($current !== '' && $current === $new) $errors[] = 'La nueva contraseña debe ser diferente a la actual.';

                if (!$errors) {
                    User::updatePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT), false, null);
                    auditLog((int) $user['id'], 'auth.password_changed', 'info', 'user', (string) $user['id']);
                    redirect('dashboard.php?password_changed=1');
                }
            }
        }

        view('auth/cambiar-clave', ['app' => $this->app, 'errors' => $errors, 'mustChange' => $mustChange, 'user' => $user]);
    }

    public function logout(): void
    {
        $user = currentUser();
        if (isPost()) {
            if (!verifyCsrf($this->app)) {
                http_response_code(400);
                exit('Solicitud inválida.');
            }
            if ($user) {
                auditLog((int) $user['id'], 'auth.logout', 'info', 'user', (string) $user['id']);
            }
            logoutUser();
            redirect('login.php');
        }

        if (!$user) redirect('login.php');
        view('auth/logout', ['app' => $this->app, 'user' => $user]);
    }
}
