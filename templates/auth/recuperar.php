<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar acceso - <?= e($app['name']) ?></title>
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="auth-wrap">
    <section class="brand">
      <span class="badge">Recuperación segura</span>
      <h1>Recuperar usuario y contraseña</h1>
      <p>Ingresa el correo asociado a tu cuenta. Si existe, recibirás tu usuario y una contraseña temporal automática.</p>
      <ul>
        <li>Contraseña temporal con caducidad</li>
        <li>Cambio obligatorio al iniciar sesión</li>
        <li>Respuesta genérica para proteger cuentas</li>
      </ul>
    </section>
    <section class="card">
      <h2>Recuperar acceso</h2>
      <p class="sub">Te enviaremos las credenciales temporales al correo registrado.</p>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endforeach; ?>
      <form method="post" autocomplete="off" novalidate>
        <?= csrfField($app) ?>
        <div class="field">
          <label for="email">Correo electrónico</label>
          <input id="email" name="email" type="email" maxlength="150" required value="<?= e($email) ?>" autocomplete="email">
        </div>
        <button class="btn" type="submit">Enviar recuperación</button>
      </form>
      <div class="links">
        <a href="login.php">Volver al inicio de sesión</a>
      </div>
      <p class="small" style="margin-top:16px;">Por seguridad, la contraseña temporal expira y deberás cambiarla al ingresar.</p>
    </section>
  </div>
</body>
</html>
