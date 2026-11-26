<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card">
  <div class="card-header"><div><h3 class="card-title"><?= $isEdit ? 'Editar expediente' : 'Nuevo expediente de sucesión/donación' ?></h3><p class="card-sub">Campos basados en el Inventario Consolidado.</p></div><a class="btn btn-outline" href="inventario-sucesiones.php">Volver</a></div>
  <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate><?= csrfField($app) ?>
    <div class="form-grid">
      <div class="field"><label>N° de expediente</label><input name="numero_expediente" required maxlength="80" value="<?= e($item['numero_expediente']??'') ?>"></div>
      <div class="field"><label>RIF</label><input name="rif" maxlength="30" value="<?= e($item['rif']??'') ?>" placeholder="J-00000000-0"></div>
      <div class="field full"><label>Razón social</label><input name="razon_social" required maxlength="220" value="<?= e($item['razon_social']??'') ?>"></div>
      <div class="field"><label>Unidad de adscripción</label><input name="unidad_adscripcion" maxlength="120" value="<?= e($item['unidad_adscripcion']??'CENTRO AUXILIAR') ?>"></div>
      <div class="field"><label>Tipo de caso</label><select name="tipo_caso"><?php foreach($tipos as $t): ?><option value="<?= e($t) ?>" <?= (($item['tipo_caso']??'')===$t)?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Fecha de solicitud</label><input type="date" name="fecha_solicitud" required value="<?= e(substr((string)($item['fecha_solicitud']??date('Y-m-d')),0,10)) ?>"></div>
      <div class="field"><label>Estatus</label><select name="estatus"><?php foreach($statuses as $s): ?><option value="<?= e($s) ?>" <?= (($item['estatus']??'')===$s)?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Fecha acta de requerimiento (si aplica)</label><input type="date" name="fecha_acta_requerimiento" value="<?= e(substr((string)($item['fecha_acta_requerimiento']??''),0,10)) ?>"></div>
      <div class="field"><label>Fecha notificación acto administrativo (si aplica)</label><input type="date" name="fecha_notificacion_acto" value="<?= e(substr((string)($item['fecha_notificacion_acto']??''),0,10)) ?>"></div>
      <div class="field"><label>N° planilla de liquidación (si aplica)</label><input name="numero_planilla_liquidacion" maxlength="80" value="<?= e($item['numero_planilla_liquidacion']??'') ?>"></div>
      <div class="field"><label>Importe (si aplica)</label><input type="number" min="0" step="0.01" name="importe" value="<?= e((string)($item['importe']??'0')) ?>"></div>
      <div class="field full"><label>Observación</label><textarea name="observacion" maxlength="2000" rows="3"><?= e($item['observacion']??'') ?></textarea></div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px;"><button class="btn" type="submit">Guardar</button><a class="btn btn-outline" href="inventario-sucesiones.php">Cancelar</a></div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
