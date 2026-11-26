<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card">
  <div class="card-header"><div><h3 class="card-title"><?= $isEdit ? 'Editar atención' : 'Nueva atención al contribuyente' ?></h3><p class="card-sub">Registro detallado por área (Asistencia, Vivienda, RIF, Sucesiones, Cobranza).</p></div><a class="btn btn-outline" href="atenciones.php">Volver</a></div>
  <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate><?= csrfField($app) ?>
    <div class="form-grid">
      <div class="field"><label>Fecha</label><input type="date" name="fecha" required value="<?= e(substr((string)($item['fecha']??date('Y-m-d')),0,10)) ?>"></div>
      <div class="field"><label>Área</label><select name="area" id="areaSel"><?php foreach($areas as $a): ?><option value="<?= e($a) ?>" <?= (($item['area']??'')===$a)?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
      <div class="field full"><label>Contribuyente / Razón social</label><input name="contribuyente" required maxlength="220" value="<?= e($item['contribuyente']??'') ?>"></div>
      <div class="field"><label>Cédula / RIF</label><input name="cedula_rif" maxlength="40" value="<?= e($item['cedula_rif']??'') ?>"></div>
      <div class="field"><label>Tipo persona</label><select name="tipo_persona"><?php foreach(['NATURAL','JURIDICA','SUCESION','GOBIERNO','COMUNIDAD','OTRO',''] as $tp): ?><option value="<?= e($tp) ?>" <?= (($item['tipo_persona']??'')===$tp)?'selected':'' ?>><?= $tp===''?'—':e($tp) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Trámite</label><input name="tramite" maxlength="160" value="<?= e($item['tramite']??'') ?>"></div>
      <div class="field"><label>Funcionario</label><input name="funcionario" maxlength="150" value="<?= e($item['funcionario']??'') ?>"></div>
      <div class="field"><label>Orientado</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px;"><input type="checkbox" name="orientado" value="1" <?= !empty($item['orientado'])?'checked':'' ?>> Sí</label></div>
      <div class="field"><label>Usuario y clave</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px;"><input type="checkbox" name="usuario_clave" value="1" <?= !empty($item['usuario_clave'])?'checked':'' ?>> Sí</label></div>
      <div class="field"><label>N° declaración (Cobranza)</label><input name="n_declaracion" maxlength="80" value="<?= e($item['n_declaracion']??'') ?>"></div>
      <div class="field"><label>Monto (Cobranza)</label><input type="number" min="0" step="0.01" name="monto" value="<?= e((string)($item['monto']??'0')) ?>"></div>
      <div class="field"><label>N° aprobación (Cobranza)</label><input name="n_aprobacion" maxlength="80" value="<?= e($item['n_aprobacion']??'') ?>"></div>
      <div class="field"><label>Teléfono (Cobranza)</label><input name="telefono" maxlength="60" value="<?= e($item['telefono']??'') ?>"></div>
      <div class="field full"><label>Observaciones</label><textarea name="observaciones" maxlength="2000" rows="3"><?= e($item['observaciones']??'') ?></textarea></div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px;"><button class="btn" type="submit">Guardar</button><a class="btn btn-outline" href="atenciones.php">Cancelar</a></div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
