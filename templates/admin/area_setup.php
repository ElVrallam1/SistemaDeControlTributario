<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card" style="max-width:560px;">
  <div class="card-header"><div><h3 class="card-title">Configurar contraseña de Administración</h3><p class="card-sub">Protección adicional para el área de Administración. Esta contraseña es independiente de tu clave de usuario.</p></div></div>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="admin.php">
    <?= csrfField($app) ?>
    <input type="hidden" name="admin_area_action" value="setup">
    <div class="field"><label>Nueva contraseña de administración</label><input type="password" name="admin_password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Confirmar contraseña</label><input type="password" name="admin_password_confirm" required minlength="8" autocomplete="new-password"></div>
    <div class="muted" style="font-size:.85rem;margin-bottom:14px;">Usa al menos 8 caracteres. Guárdala en un lugar seguro; se solicitará para entrar a Administración.</div>
    <button class="btn" type="submit">Guardar y entrar</button>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
