<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card">
  <div class="card-header">
    <div><h3 class="card-title"><?= $isEdit ? 'Editar expediente' : 'Nuevo expediente' ?></h3><p class="card-sub">Completa los datos del expediente.</p></div>
    <a class="btn btn-outline" href="expedientes.php">Volver</a>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrfField($app) ?>
    <div class="form-grid">
      <div class="field"><label>N° Expediente</label><input name="numero_expediente" required maxlength="80" value="<?= e($item['numero_expediente'] ?? '') ?>"></div>
      <div class="field"><label>RIF</label><input name="rif" maxlength="30" value="<?= e($item['rif'] ?? '') ?>"></div>
      <div class="field full"><label>Contribuyente</label><input name="contribuyente" required maxlength="200" value="<?= e($item['contribuyente'] ?? '') ?>"></div>
      <div class="field"><label>Tipo de trámite</label><input name="tipo_tramite" required maxlength="120" value="<?= e($item['tipo_tramite'] ?? '') ?>"></div>
      <div class="field"><label>División</label>
        <select name="division" required>
          <option value="">Seleccionar</option>
          <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>" <?= ($item['division'] ?? '')===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Estado</label><select name="estado"><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= ($item['estado'] ?? '')===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Prioridad</label><select name="prioridad"><?php foreach ($priorities as $p): ?><option value="<?= e($p) ?>" <?= ($item['prioridad'] ?? '')===$p?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Fecha apertura</label><input type="date" name="fecha_apertura" required value="<?= e(substr((string)($item['fecha_apertura'] ?? date('Y-m-d')),0,10)) ?>"></div>
      <div class="field"><label>Fecha cierre</label><input type="date" name="fecha_cierre" value="<?= e($item['fecha_cierre'] ? substr((string)$item['fecha_cierre'],0,10) : '') ?>"></div>
      <div class="field full"><label>Descripción</label><textarea name="descripcion" maxlength="2000"><?= e($item['descripcion'] ?? '') ?></textarea></div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px;"><button class="btn" type="submit">Guardar</button><a class="btn btn-outline" href="expedientes.php">Cancelar</a></div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
