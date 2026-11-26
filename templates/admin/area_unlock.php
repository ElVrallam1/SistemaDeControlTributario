<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card" style="max-width:560px;">
  <div class="card-header"><div><h3 class="card-title">Acceso a Administración</h3><p class="card-sub">Esta sección está protegida con una contraseña adicional.</p></div></div>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? 'admin.php') ?>">
    <?= csrfField($app) ?>
    <input type="hidden" name="admin_area_action" value="unlock">
    <input type="hidden" name="next" value="<?= e($_SERVER['REQUEST_URI'] ?? 'admin.php') ?>">
    <div class="field"><label>Contraseña de administración</label><input type="password" name="admin_password" required autocomplete="current-password" autofocus></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn" type="submit">Entrar</button>
      <a class="btn btn-outline" href="dashboard.php">Volver al dashboard</a>
    </div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
