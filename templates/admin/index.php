<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<?php if (!empty($flash)): ?><div class="alert alert-<?= e($flash['type'] ?? 'success') ?>"><?= e($flash['msg'] ?? '') ?></div><?php endif; ?>
<div class="card mb-24">
  <div class="card-header"><div><h3 class="card-title">Área administrativa</h3><p class="card-sub">Acceso restringido a administradores.</p></div><span class="badge badge-danger">Admin</span></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
    <a class="card" href="admin.php?action=users" style="text-decoration:none;color:inherit;box-shadow:none;border:1px solid var(--line);">
      <h4 style="margin:0 0 6px;">Gestión de usuarios</h4>
      <p class="muted" style="margin:0;font-size:.92rem;">Crear/editar cuentas, roles, activar/desactivar y resetear contraseñas temporales.</p>
    </a>
    <a class="card" href="admin.php?action=audit" style="text-decoration:none;color:inherit;box-shadow:none;border:1px solid var(--line);">
      <h4 style="margin:0 0 6px;">Auditoría</h4>
      <p class="muted" style="margin:0;font-size:.92rem;">Revisar actividad del sistema, eventos de seguridad y acciones administrativas.</p>
    </a>
  </div>
</div>
<div class="card mb-24">
  <div class="card-header"><div><h3 class="card-title">Contraseña del área de Administración</h3><p class="card-sub">Cambia la contraseña adicional que protege esta sección.</p></div></div>
  <form method="post" action="admin.php" style="max-width:520px;">
    <?= csrfField($app) ?>
    <input type="hidden" name="admin_area_action" value="change">
    <div class="field"><label>Contraseña actual de administración</label><input type="password" name="current_admin_password" required autocomplete="current-password"></div>
    <div class="field"><label>Nueva contraseña</label><input type="password" name="new_admin_password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Confirmar nueva contraseña</label><input type="password" name="new_admin_password_confirm" required minlength="8" autocomplete="new-password"></div>
    <button class="btn" type="submit">Actualizar contraseña de administración</button>
  </form>
</div>
<div class="card">
  <h3 class="card-title">Usuarios registrados</h3>
  <div class="table-wrap"><table class="table"><thead><tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Último acceso</th></tr></thead><tbody>
    <?php foreach($users as $u): ?>
      <tr>
        <td><strong><?= e($u['full_name']) ?></strong><div class="muted" style="font-size:.82rem"><?= e($u['username']) ?></div></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge badge-info"><?= e($u['role_name']) ?></span></td>
        <td><?= $u['is_active'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>' ?></td>
        <td><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime((string)$u['last_login_at']))) : '-' ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
