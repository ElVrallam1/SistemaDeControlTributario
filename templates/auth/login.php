<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión - <?= e($app['name']) ?></title>
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="auth-wrap">
    <section class="brand">
      <span class="badge">SENIAT · Plaza Venezuela</span>
      <h1>Sistema de Control Tributario</h1>
      <p>Acceso seguro para la gestión de expedientes, productividad, solvencias, multas, requerimientos y control diario.</p>
      <ul>
        <li>Autenticación protegida</li>
        <li>Monitoreo de actividad</li>
        <li>Datos operativos centralizados</li>
      </ul>
    </section>
    <section class="card">
      <h2>Iniciar sesión</h2>
      <p class="sub">Ingresa con tu usuario o correo electrónico.</p>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endforeach; ?>
      <?php if (!empty($flash)): ?><div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['msg'] ?? '') ?></div><?php endif; ?>
      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Registro exitoso. Ya puedes iniciar sesión.</div>
      <?php endif; ?>
      <?php if (isset($_GET['recovered'])): ?>
        <div class="alert alert-success">Si el correo existe, se enviaron las credenciales temporales. Revisa tu bandeja.</div>
      <?php endif; ?>
      <?php if (isset($_GET['changed'])): ?>
        <div class="alert alert-success">Contraseña actualizada. Inicia sesión con tu nueva contraseña.</div>
      <?php endif; ?>
      <form method="post" autocomplete="off" novalidate>
        <?= csrfField($app) ?>
        <div class="field">
          <label for="login">Usuario o correo</label>
          <input id="login" name="login" type="text" maxlength="150" required value="<?= e($oldLogin) ?>" autocomplete="username">
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn" type="submit">Entrar</button>
      </form>
      <div class="links">
        <a href="register.php">Crear cuenta</a>
        <a href="recuperar.php">Recuperar usuario o contraseña</a>
      </div>
    </section>
  </div>
</body>
</html>
