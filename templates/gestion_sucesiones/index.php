<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card mb-24">
  <div class="toolbar">
    <div><h3 class="card-title" style="margin:0">Gestión de Sucesiones (Queli)</h3><p class="card-sub">Seguimiento detallado y resumen gerencial. Total: <?= (int)($total??0) ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;"><a class="btn btn-outline" href="gestion-sucesiones.php?action=import">Importar CSV</a><a class="btn" href="gestion-sucesiones.php?action=create">+ Nuevo seguimiento</a></div>
  </div>
  <?php if(isset($_GET['ok'])):?><div class="alert alert-success">Registro guardado.</div><?php endif; ?>
  <?php if(isset($_GET['deleted'])):?><div class="alert alert-success">Registro eliminado.</div><?php endif; ?>

  <div class="card" style="box-shadow:none;background:#fbfdff;margin-bottom:16px;">
    <h4 style="margin:0 0 10px;font-size:.95rem;">Exportaciones</h4>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
      <form method="post" action="gestion-sucesiones.php?action=export" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:0;">
        <?= csrfField($app) ?>
        <input type="hidden" name="q" value="<?= e($filters['q']??'') ?>"><input type="hidden" name="estatus" value="<?= e($filters['estatus']??'') ?>">
        <input type="hidden" name="gerencia_regional" value="<?= e($filters['gerencia_regional']??'') ?>"><input type="hidden" name="from" value="<?= e($filters['from']??'') ?>"><input type="hidden" name="to" value="<?= e($filters['to']??'') ?>">
        <button class="btn btn-outline" type="submit">Exportar detalle</button>
      </form>
      <form method="post" action="gestion-sucesiones.php?action=export_resumen" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:0;">
        <?= csrfField($app) ?>
        <div class="field" style="margin:0;min-width:140px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from'] ?: date('Y-m-01')) ?>"></div>
        <div class="field" style="margin:0;min-width:140px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to'] ?: date('Y-m-t')) ?>"></div>
        <button class="btn" type="submit">Exportar resumen Queli</button>
      </form>
    </div>
  </div>

  <form method="get" action="gestion-sucesiones.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
    <div class="field" style="margin:0;min-width:180px;"><label>Buscar</label><input name="q" value="<?= e($filters['q']??'') ?>" placeholder="Expediente u observación"></div>
    <div class="field" style="margin:0;min-width:180px;"><label>Estatus</label><select name="estatus"><option value="">Todos</option><?php foreach($statuses as $s): ?><option value="<?= e($s) ?>" <?= (($filters['estatus']??'')===$s)?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0;min-width:180px;"><label>Gerencia/Dependencia</label><select name="gerencia_regional"><option value="">Todas</option><?php foreach($gerencias as $g): ?><option value="<?= e($g) ?>" <?= (($filters['gerencia_regional']??'')===$g)?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0;min-width:140px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from']??'') ?>"></div>
    <div class="field" style="margin:0;min-width:140px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to']??'') ?>"></div>
    <button class="btn" type="submit">Filtrar</button><a class="btn btn-outline" href="gestion-sucesiones.php">Limpiar</a>
  </form>

  <div class="table-wrap"><table class="table">
    <thead><tr><th>Expediente</th><th>Gerencia</th><th>Ingreso coord.</th><th>Estatus</th><th>Reapertura</th><th>Asignado analista</th><th>Acta req.</th><th>Entrega solv.</th><th>Archivo</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php if(!$items): ?><tr><td colspan="10"><div class="empty">No hay registros de gestión de sucesiones.</div></td></tr>
    <?php else: foreach($items as $it): $d = fn($k)=> !empty($it[$k]) ? e(date('d/m/Y',strtotime((string)$it[$k]))) : '-'; ?>
      <tr>
        <td><strong><?= e($it['numero_expediente']) ?></strong></td>
        <td><?= e($it['gerencia_regional']) ?></td>
        <td><?= $d('fecha_ingreso_coordinacion') ?></td>
        <td><span class="badge badge-muted"><?= e($it['estatus']) ?></span></td>
        <td><?= e($it['auto_reapertura']) ?></td>
        <td><?= $d('fecha_asignado_analista') ?></td>
        <td><?= $d('fecha_acta_requerimiento') ?></td>
        <td><?= $d('fecha_entrega_solvencia') ?></td>
        <td><?= $d('fecha_enviado_archivo') ?></td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn btn-outline btn-sm" href="gestion-sucesiones.php?action=edit&id=<?= (int)$it['id'] ?>">Editar</a>
          <?php if(in_array(($user['role_name']??''),['admin','supervisor'],true)): ?>
          <form method="post" action="gestion-sucesiones.php?action=delete" onsubmit="return confirm('¿Eliminar registro?');" style="margin:0"><?= csrfField($app) ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Eliminar</button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
  <?php if(($totalPages??1)>1): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;"><?php $qs=$_GET; for($p=1;$p<=$totalPages;$p++): $qs['page']=$p; ?><a class="btn btn-outline btn-sm" href="gestion-sucesiones.php?<?= e(http_build_query($qs)) ?>" style="<?= $p===($page??1)?'background:var(--primary);color:#fff;border-color:var(--primary);':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
