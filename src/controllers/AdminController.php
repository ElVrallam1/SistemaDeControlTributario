<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\User;
use App\Models\AuditLog;

class AdminController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    private function guardArea(): array {
        $user = requireAdmin();
        require_once BASE_PATH . '/src/helpers/settings.php';
        $hash = settingGet('admin_area_password_hash');
        $ttl = 30 * 60;
        $verifiedAt = (int)($_SESSION['admin_area_verified_at'] ?? 0);
        $isVerified = $hash && $verifiedAt > 0 && (time() - $verifiedAt) < $ttl;

        if (isPost() && isset($_POST['admin_area_action'])) {
            if (!verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
            $action = (string)$_POST['admin_area_action'];
            if ($action === 'setup') {
                if ($hash) { redirect('admin.php'); }
                $p1 = (string)($_POST['admin_password'] ?? '');
                $p2 = (string)($_POST['admin_password_confirm'] ?? '');
                $err = null;
                if (strlen($p1) < 8) $err = 'La contraseña debe tener al menos 8 caracteres.';
                elseif ($p1 !== $p2) $err = 'Las contraseñas no coinciden.';
                if ($err) { view('admin/area_setup', ['app'=>$this->app,'user'=>$user,'error'=>$err,'pageTitle'=>'Administración','pageSubtitle'=>'Configurar acceso','activeMenu'=>'admin']); exit; }
                settingSet('admin_area_password_hash', password_hash($p1, PASSWORD_DEFAULT));
                $_SESSION['admin_area_verified_at'] = time();
                auditLog((int)$user['id'], 'admin.area.setup', 'warning', 'admin', null);
                redirect('admin.php');
            }
            if ($action === 'unlock') {
                $p = (string)($_POST['admin_password'] ?? '');
                if (!$hash || !password_verify($p, $hash)) {
                    auditLog((int)$user['id'], 'admin.area.unlock_fail', 'warning', 'admin', null);
                    view('admin/area_unlock', ['app'=>$this->app,'user'=>$user,'error'=>'Contraseña de administración incorrecta.','pageTitle'=>'Administración','pageSubtitle'=>'Acceso protegido','activeMenu'=>'admin']); exit;
                }
                $_SESSION['admin_area_verified_at'] = time();
                auditLog((int)$user['id'], 'admin.area.unlock', 'info', 'admin', null);
                $next = sanitizeText($_POST['next'] ?? 'admin.php', 200);
                if (strpos($next, 'admin.php') !== 0) $next = 'admin.php';
                redirect($next);
            }
        }

        if ($isVerified && isPost() && (string)($_POST['admin_area_action'] ?? '') === 'change') {
            $current = (string)($_POST['current_admin_password'] ?? '');
            $p1 = (string)($_POST['new_admin_password'] ?? '');
            $p2 = (string)($_POST['new_admin_password_confirm'] ?? '');
            $err = null;
            if (!$hash || !password_verify($current, $hash)) $err = 'La contraseña actual de administración es incorrecta.';
            elseif (strlen($p1) < 8) $err = 'La nueva contraseña debe tener al menos 8 caracteres.';
            elseif ($p1 !== $p2) $err = 'La confirmación no coincide.';
            if ($err) { $_SESSION['flash'] = ['type'=>'danger','msg'=>$err]; redirect('admin.php'); }
            settingSet('admin_area_password_hash', password_hash($p1, PASSWORD_DEFAULT));
            $_SESSION['admin_area_verified_at'] = time();
            auditLog((int)$user['id'], 'admin.area.change_password', 'warning', 'admin', null);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Contraseña de administración actualizada.'];
            redirect('admin.php');
        }

        if (!$hash) {
            view('admin/area_setup', ['app'=>$this->app,'user'=>$user,'error'=>null,'pageTitle'=>'Administración','pageSubtitle'=>'Configurar acceso','activeMenu'=>'admin']);
            exit;
        }
        if (!$isVerified) {
            view('admin/area_unlock', ['app'=>$this->app,'user'=>$user,'error'=>null,'pageTitle'=>'Administración','pageSubtitle'=>'Acceso protegido','activeMenu'=>'admin']);
            exit;
        }
        return $user;
    }

    public function index(): void {
        $user = $this->guardArea();
        $users = User::all();
        $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
        view('admin/index', [
            'app'=>$this->app,'user'=>$user,'users'=>$users,'flash'=>$flash,
            'pageTitle'=>'Administración','pageSubtitle'=>'Gestión de usuarios y auditoría','activeMenu'=>'admin'
        ]);
    }

    public function users(): void {
        $user = $this->guardArea();
        $users = User::all();
        $roles = User::roles();
        $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
        view('admin/users', [
            'app'=>$this->app,'user'=>$user,'users'=>$users,'roles'=>$roles,'flash'=>$flash,
            'pageTitle'=>'Usuarios','pageSubtitle'=>'Administración de cuentas y roles','activeMenu'=>'admin'
        ]);
    }

    public function updateUser(): void {
        $admin = $this->guardArea();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        $target = $id > 0 ? User::findById($id) : null;
        if (!$target) { redirect('admin.php?action=users&err=notfound'); }
        $fullName = sanitizeText($_POST['full_name'] ?? '', 150);
        $department = sanitizeText($_POST['department'] ?? '', 120);
        $roleId = (int)($_POST['role_id'] ?? 0);
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
        // prevent self-lockout
        if ((int)$admin['id'] === $id) { $isActive = true; $roleId = (int)$target['role_id']; }
        $validRole = false; foreach (User::roles() as $r) { if ((int)$r['id'] === $roleId) $validRole = true; }
        if ($fullName === '' || $department === '' || !$validRole) {
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Datos inválidos para actualizar usuario.'];
            redirect('admin.php?action=users');
        }
        User::updateAdmin($id, ['full_name'=>$fullName,'department'=>$department,'role_id'=>$roleId,'is_active'=>$isActive]);
        auditLog((int)$admin['id'],'admin.user.update','warning','users',(string)$id,['role_id'=>$roleId,'is_active'=>$isActive]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Usuario actualizado.'];
        redirect('admin.php?action=users');
    }

    public function toggleActive(): void {
        $admin = $this->guardArea();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $id === (int)$admin['id']) { redirect('admin.php?action=users'); }
        $target = User::findById($id);
        if (!$target) { redirect('admin.php?action=users'); }
        $newActive = !(bool)$target['is_active'];
        User::setActive($id, $newActive);
        auditLog((int)$admin['id'], $newActive ? 'admin.user.activate' : 'admin.user.deactivate','warning','users',(string)$id);
        $_SESSION['flash'] = ['type'=>'success','msg'=> $newActive ? 'Usuario activado.' : 'Usuario desactivado.'];
        redirect('admin.php?action=users');
    }

    public function resetPassword(): void {
        $admin = $this->guardArea();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        $target = User::findById($id);
        if (!$target) { redirect('admin.php?action=users'); }
        $temp = 'SENIAT-' . strtoupper(bin2hex(random_bytes(4))) . '-' . random_int(100,999);
        $hash = password_hash($temp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + (int)$this->app['temp_password_ttl']);
        User::updatePassword($id, $hash, true, $expires);
        auditLog((int)$admin['id'],'admin.user.reset_password','warning','users',(string)$id,['target'=>$target['username']]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Contraseña temporal generada para ' . $target['username'] . ': ' . $temp . ' (caduca en ' . ((int)$this->app['temp_password_ttl']/60) . ' min). Comuníquela de forma segura.'];
        redirect('admin.php?action=users');
    }

    public function audit(): void {
        $user = $this->guardArea();
        $filters = [
            'action' => sanitizeText($_GET['q'] ?? '', 100),
            'severity' => sanitizeText($_GET['severity'] ?? '', 20),
            'from' => sanitizeText($_GET['from'] ?? '', 20),
            'to' => sanitizeText($_GET['to'] ?? '', 20),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;
        $result = AuditLog::search($filters, $limit, $offset);
        $totalPages = max(1, (int)ceil($result['total'] / $limit));
        view('admin/audit', [
            'app'=>$this->app,'user'=>$user,'items'=>$result['items'],'total'=>$result['total'],
            'filters'=>$filters,'page'=>$page,'totalPages'=>$totalPages,
            'pageTitle'=>'Auditoría','pageSubtitle'=>'Registro de actividad y eventos de seguridad','activeMenu'=>'admin'
        ]);
    }
}
