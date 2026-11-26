<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro - <?= e($app['name']) ?></title>
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="auth-wrap">
    <section class="brand">
      <span class="badge">Registro seguro</span>
      <h1>Crear cuenta</h1>
      <p>Registra tu cuenta para acceder al sistema de control operativo del SENIAT Plaza Venezuela.</p>
      <ul>
        <li>Contraseñas almacenadas con hash</li>
        <li>Validación en servidor</li>
        <li>Protección CSRF y control de intentos</li>
      </ul>
    </section>
    <section class="card">
      <h2>Registro de usuario</h2>
      <p class="sub">Completa tus datos para crear la cuenta.</p>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endforeach; ?>
      <form method="post" autocomplete="off" novalidate>
        <?= csrfField($app) ?>
        <div class="field">
          <label for="full_name">Nombre completo</label>
          <input id="full_name" name="full_name" type="text" maxlength="150" required value="<?= e($old['full_name']) ?>">
        </div>
        <div class="field">
          <label for="username">Usuario</label>
          <input id="username" name="username" type="text" maxlength="80" required value="<?= e($old['username']) ?>" autocomplete="username">
        </div>
        <div class="field">
          <label for="email">Correo electrónico</label>
          <input id="email" name="email" type="email" maxlength="150" required value="<?= e($old['email']) ?>" autocomplete="email">
        </div>
        <div class="field">
          <label for="department">Departamento / División</label>
          <select id="department" name="department" required>
            <option value="">Selecciona tu departamento</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= e($dept) ?>" <?= $old['department'] === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input id="password" name="password" type="password" required autocomplete="new-password">
          <div class="password-hint">Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.</div>
        </div>
        <div class="field">
          <label for="password_confirm">Confirmar contraseña</label>
          <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
        </div>
        <button class="btn" type="submit">Registrarme</button>
      </form>
      <div class="links">
        <a href="login.php">Ya tengo cuenta</a>
      </div>
    </section>
  </div>
</body>
</html>
