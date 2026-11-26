<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cambiar contraseña - <?= e($app['name']) ?></title>
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="auth-wrap" style="max-width:920px;grid-template-columns:1fr;">
    <section class="card">
      <h2>Cambiar contraseña</h2>
      <p class="sub"><?= $mustChange ? 'Debes cambiar tu contraseña temporal antes de continuar.' : 'Actualiza tu contraseña de acceso.' ?></p>
      <?php if ($mustChange): ?>
        <div class="alert alert-warning">Tu cuenta tiene un cambio de contraseña obligatorio por recuperación o política de seguridad.</div>
      <?php endif; ?>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endforeach; ?>
      <form method="post" autocomplete="off" novalidate>
        <?= csrfField($app) ?>
        <div class="field">
          <label for="current_password">Contraseña actual o temporal</label>
          <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label for="new_password">Nueva contraseña</label>
          <input id="new_password" name="new_password" type="password" required autocomplete="new-password">
          <div class="password-hint">Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.</div>
        </div>
        <div class="field">
          <label for="confirm_password">Confirmar nueva contraseña</label>
          <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
        </div>
        <button class="btn" type="submit">Actualizar contraseña</button>
      </form>
      <div class="links">
        <?php if (!$mustChange): ?><a href="dashboard.php">Volver al dashboard</a><?php endif; ?>
        <a href="logout.php">Cerrar sesión</a>
      </div>
    </section>
  </div>
</body>
</html>
