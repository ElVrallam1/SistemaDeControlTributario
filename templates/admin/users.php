<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card mb-24">
  <div class="toolbar">
    <div><h3 class="card-title" style="margin:0">Gestión de usuarios</h3><p class="card-sub">Roles, estado, departamento y contraseñas temporales.</p></div>
    <a class="btn btn-outline" href="admin.php">Volver</a>
  </div>
  <?php if (!empty($flash)): ?><div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['msg'] ?? '') ?></div><?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Usuario</th><th>Correo</th><th>Departamento</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $uid=(int)$u['id']; ?>
      <tr>
        <td><strong><?= e($u['full_name']) ?></strong><div class="muted" style="font-size:.82rem"><?= e($u['username']) ?> · ID <?= $uid ?></div></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['department']) ?></td>
        <td><span class="badge badge-info"><?= e($u['role_name']) ?></span></td>
        <td>
          <?= $u['is_active'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>' ?>
          <?= $u['must_change_password'] ? '<div><span class="badge badge-warning">Cambio clave</span></div>' : '' ?>
        </td>
        <td>
          <details>
            <summary class="btn btn-outline btn-sm" style="cursor:pointer;display:inline-flex;">Editar</summary>
            <div style="margin-top:10px;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fbfdff;min-width:280px;">
              <form method="post" action="admin.php?action=update_user">
                <?= csrfField($app) ?>
                <input type="hidden" name="id" value="<?= $uid ?>">
                <div class="field"><label>Nombre</label><input name="full_name" value="<?= e($u['full_name']) ?>" required maxlength="150"></div>
                <div class="field"><label>Departamento</label><input name="department" value="<?= e($u['department']) ?>" required maxlength="120"></div>
                <div class="field"><label>Rol</label>
                  <select name="role_id">
                    <?php foreach ($roles as $r): ?>
                      <option value="<?= (int)$r['id'] ?>" <?= $r['name']===$u['role_name']?'selected':'' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field"><label>Estado</label>
                  <select name="is_active">
                    <option value="1" <?= $u['is_active']?'selected':'' ?>>Activo</option>
                    <option value="0" <?= !$u['is_active']?'selected':'' ?>>Inactivo</option>
                  </select>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button class="btn btn-sm" type="submit">Guardar</button>
                </div>
              </form>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <?php if ($uid !== (int)$user['id']): ?>
                <form method="post" action="admin.php?action=toggle_active" style="margin:0"><?= csrfField($app) ?><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $u['is_active'] ? 'Desactivar' : 'Activar' ?></button></form>
                <?php endif; ?>
                <form method="post" action="admin.php?action=reset_password" style="margin:0" onsubmit="return confirm('¿Generar contraseña temporal para este usuario?');"><?= csrfField($app) ?><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-outline btn-sm" type="submit">Reset clave</button></form>
              </div>
              <?php if ($uid === (int)$user['id']): ?><p class="muted" style="font-size:.8rem;margin:8px 0 0;">No puedes desactivarte ni cambiar tu propio rol desde aquí.</p><?php endif; ?>
            </div>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
