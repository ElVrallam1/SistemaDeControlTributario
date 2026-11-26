<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<?php $dv = fn($k)=> e(substr((string)($item[$k]??''),0,10)); ?>
<style>.section-title{margin:18px 0 10px;font-size:.92rem;font-weight:700;color:var(--primary,#8b1e1e);border-bottom:1px solid #eadfdf;padding-bottom:6px}.fg4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}@media(max-width:900px){.fg4{grid-template-columns:repeat(2,minmax(0,1fr));}}@media(max-width:560px){.fg4{grid-template-columns:1fr;}}</style>
<div class="card">
  <div class="card-header"><div><h3 class="card-title"><?= $isEdit ? 'Editar gestión de sucesión' : 'Nueva gestión de sucesión' ?></h3><p class="card-sub">Seguimiento operativo del formato Queli.</p></div><a class="btn btn-outline" href="gestion-sucesiones.php">Volver</a></div>
  <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate><?= csrfField($app) ?>
    <div class="section-title">Datos generales</div>
    <div class="form-grid">
      <div class="field"><label>N° de expediente</label><input name="numero_expediente" required maxlength="80" value="<?= e($item['numero_expediente']??'') ?>"></div>
      <div class="field"><label>Gerencia/Dependencia</label><select name="gerencia_regional"><?php foreach($gerencias as $g): ?><option value="<?= e($g) ?>" <?= (($item['gerencia_regional']??'')===$g)?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Fecha ingreso a coordinación</label><input type="date" name="fecha_ingreso_coordinacion" value="<?= $dv('fecha_ingreso_coordinacion') ?>"></div>
      <div class="field"><label>Estatus</label><select name="estatus"><?php foreach($statuses as $s): ?><option value="<?= e($s) ?>" <?= (($item['estatus']??'')===$s)?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Auto de reapertura</label><select name="auto_reapertura"><option value="NO" <?= (($item['auto_reapertura']??'NO')==='NO')?'selected':'' ?>>NO</option><option value="SI" <?= (($item['auto_reapertura']??'')==='SI')?'selected':'' ?>>SI</option></select></div>
    </div>

    <div class="section-title">Hitos del proceso (fechas)</div>
    <div class="fg4">
      <div class="field"><label>Consignó recaudo</label><input type="date" name="fecha_consigno_recaudo" value="<?= $dv('fecha_consigno_recaudo') ?>"></div>
      <div class="field"><label>Asignado analista</label><input type="date" name="fecha_asignado_analista" value="<?= $dv('fecha_asignado_analista') ?>"></div>
      <div class="field"><label>Acta de requerimiento</label><input type="date" name="fecha_acta_requerimiento" value="<?= $dv('fecha_acta_requerimiento') ?>"></div>
      <div class="field"><label>Consignó requerimiento</label><input type="date" name="fecha_consigno_requerimiento" value="<?= $dv('fecha_consigno_requerimiento') ?>"></div>
      <div class="field"><label>Series especiales</label><input type="date" name="fecha_series_especiales" value="<?= $dv('fecha_series_especiales') ?>"></div>
      <div class="field"><label>Tramitaciones</label><input type="date" name="fecha_tramitaciones" value="<?= $dv('fecha_tramitaciones') ?>"></div>
      <div class="field"><label>Consignó pago</label><input type="date" name="fecha_consigno_pago" value="<?= $dv('fecha_consigno_pago') ?>"></div>
      <div class="field"><label>Control de calidad</label><input type="date" name="fecha_control_calidad" value="<?= $dv('fecha_control_calidad') ?>"></div>
      <div class="field"><label>Elaboración solvencia</label><input type="date" name="fecha_elaboracion_solvencia" value="<?= $dv('fecha_elaboracion_solvencia') ?>"></div>
      <div class="field"><label>Entrega de solvencia</label><input type="date" name="fecha_entrega_solvencia" value="<?= $dv('fecha_entrega_solvencia') ?>"></div>
      <div class="field"><label>Auto de cierre expediente</label><input type="date" name="fecha_auto_cierre_expediente" value="<?= $dv('fecha_auto_cierre_expediente') ?>"></div>
      <div class="field"><label>Enviado a archivo</label><input type="date" name="fecha_enviado_archivo" value="<?= $dv('fecha_enviado_archivo') ?>"></div>
    </div>

    <div class="section-title">Observación</div>
    <div class="field"><textarea name="observacion" maxlength="2000" rows="3"><?= e($item['observacion']??'') ?></textarea></div>
    <div style="margin-top:16px;display:flex;gap:10px;"><button class="btn" type="submit">Guardar</button><a class="btn btn-outline" href="gestion-sucesiones.php">Cancelar</a></div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
