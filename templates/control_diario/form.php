<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<?php
$rif = [
  'rif_insc_pn'=>'INSC PN','rif_act_pn'=>'ACT PN','rif_insc_pj'=>'INSC PJ','rif_act_pj'=>'ACT PJ',
  'rif_insc_suc'=>'INSC SUC','rif_act_suc'=>'ACT SUC','rif_insc_emp'=>'INSC EMP','rif_act_emp'=>'ACT EMP',
  'rif_insc_gob'=>'INSC GOB','rif_act_gob'=>'ACT GOB','rif_insc_cc'=>'INSC CC','rif_act_cc'=>'ACT CC',
];
$num = function(string $k) use ($item){ return (int)($item[$k] ?? 0); };
?>
<style>
  .section-title{margin:18px 0 10px;font-size:.92rem;font-weight:700;color:var(--primary,#8b1e1e);border-bottom:1px solid #eadfdf;padding-bottom:6px}
  .form-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
  .form-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
  @media(max-width:900px){.form-grid-4,.form-grid-3{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media(max-width:560px){.form-grid-4,.form-grid-3{grid-template-columns:1fr;}}
  .calc-box{background:#fbfdff;border:1px solid #e5d6d6;border-radius:10px;padding:12px}
</style>
<div class="card">
  <div class="card-header"><div><h3 class="card-title"><?= $isEdit ? 'Editar reporte diario' : 'Nuevo reporte diario' ?></h3><p class="card-sub">Campos alineados al formato real de Reporte Diario Plaza Venezuela.</p></div><a class="btn btn-outline" href="control-diario.php">Volver</a></div>
  <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate><?= csrfField($app) ?>
    <div class="section-title">Datos generales</div>
    <div class="form-grid">
      <div class="field"><label>Fecha</label><input type="date" name="fecha" required value="<?= e(substr((string)($item['fecha']??date('Y-m-d')),0,10)) ?>"></div>
      <div class="field"><label>Dependencia</label><input type="text" name="dependencia" maxlength="120" required value="<?= e($item['dependencia'] ?? 'Plaza Venezuela') ?>"></div>
    </div>

    <div class="section-title">Vivienda y copias certificadas</div>
    <div class="form-grid-4">
      <div class="field"><label>Solicitudes vivienda recibidas</label><input type="number" min="0" name="vivienda_solicitudes_recibidas" value="<?= $num('vivienda_solicitudes_recibidas') ?>"></div>
      <div class="field"><label>Registros vivienda entregados</label><input type="number" min="0" name="vivienda_registros_entregados" value="<?= $num('vivienda_registros_entregados') ?>"></div>
      <div class="field"><label>Solicitud copias certificadas</label><input type="number" min="0" name="copias_solicitadas" value="<?= $num('copias_solicitadas') ?>"></div>
      <div class="field"><label>Copias certificadas entregadas</label><input type="number" min="0" name="copias_entregadas" value="<?= $num('copias_entregadas') ?>"></div>
    </div>

    <div class="section-title">Atención y RIF contribuyentes atendidos</div>
    <div class="form-grid-4">
      <div class="field"><label>Orientaciones</label><input type="number" min="0" name="orientaciones" value="<?= $num('orientaciones') ?>"></div>
      <div class="field calc-box"><label>Total RIF atendidos (calculado)</label><input type="number" id="rif_total" name="rif_total_atendidos_display" value="<?= (int)($item['rif_total_atendidos'] ?? 0) ?>" readonly style="background:#f3f4f6;font-weight:700;"><small class="muted">Se recalcula en servidor.</small></div>
    </div>
    <div class="form-grid-4" style="margin-top:10px;">
      <?php foreach($rif as $k=>$label): ?>
        <div class="field"><label><?= e($label) ?></label><input type="number" min="0" class="rif-input" name="<?= e($k) ?>" value="<?= $num($k) ?>"></div>
      <?php endforeach; ?>
    </div>

    <div class="section-title">Asistencia, sucesiones y otros</div>
    <div class="form-grid-4">
      <div class="field"><label>Asistencia contribuyentes atendidos</label><input type="number" min="0" name="asistencia_contribuyentes" value="<?= $num('asistencia_contribuyentes') ?>"></div>
      <div class="field"><label>Sucesiones contribuyentes atendidos</label><input type="number" min="0" name="sucesiones_atendidos" value="<?= $num('sucesiones_atendidos') ?>"></div>
      <div class="field"><label>Sucesiones ingresadas</label><input type="number" min="0" name="sucesiones_ingresadas" value="<?= $num('sucesiones_ingresadas') ?>"></div>
      <div class="field"><label>Solvencias sucesiones entregadas</label><input type="number" min="0" name="solvencias_sucesiones_entregadas" value="<?= $num('solvencias_sucesiones_entregadas') ?>"></div>
      <div class="field"><label>Multas notificadas</label><input type="number" min="0" name="multas_notificadas" value="<?= $num('multas_notificadas') ?>"></div>
      <div class="field"><label>Papel seguridad anulado</label><input type="number" min="0" name="papel_seguridad_anulado" value="<?= $num('papel_seguridad_anulado') ?>"></div>
    </div>

    <div class="section-title">Observaciones</div>
    <div class="field"><textarea name="observaciones" maxlength="2000" rows="3"><?= e($item['observaciones']??'') ?></textarea></div>

    <div style="margin-top:16px;display:flex;gap:10px;"><button class="btn" type="submit">Guardar reporte</button><a class="btn btn-outline" href="control-diario.php">Cancelar</a></div>
  </form>
</div>
<script>
(function(){
  const inputs=[...document.querySelectorAll('.rif-input')];
  const total=document.getElementById('rif_total');
  function calc(){ let s=0; inputs.forEach(i=>{s+=Math.max(0,parseInt(i.value||'0',10)||0)}); if(total) total.value=s; }
  inputs.forEach(i=>i.addEventListener('input',calc));
  calc();
})();
</script>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
