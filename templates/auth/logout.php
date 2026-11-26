<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cerrar sesión - <?= e($app['name']) ?></title>
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="auth-wrap" style="max-width:560px;grid-template-columns:1fr;">
    <section class="card">
      <h2>Cerrar sesión</h2>
      <p class="sub">Confirma que deseas salir del sistema.</p>
      <form method="post">
        <?= csrfField($app) ?>
        <button class="btn" type="submit">Salir</button>
      </form>
      <div class="links"><a href="dashboard.php">Cancelar</a></div>
    </section>
  </div>
</body>
</html>
