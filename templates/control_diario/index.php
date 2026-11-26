<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card mb-24">
  <div class="toolbar">
    <div><h3 class="card-title" style="margin:0">Reporte diario</h3><p class="card-sub">Formato operativo Plaza Venezuela (basado en reporte real). Total: <?= (int)($total??count($items)) ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;"><a class="btn btn-outline" href="control-diario.php?action=import">Importar CSV</a><a class="btn" href="control-diario.php?action=create">+ Nuevo reporte</a></div>
  </div>
  <?php if(isset($_GET['ok'])):?><div class="alert alert-success">Reporte guardado.</div><?php endif; ?>
  <?php if(isset($_GET['deleted'])):?><div class="alert alert-success">Reporte eliminado.</div><?php endif; ?>

  <div class="card" style="box-shadow:none;background:#fbfdff;margin-bottom:16px;">
    <h4 style="margin:0 0 10px;font-size:.95rem;">Exportar a Excel</h4>
    <form method="post" action="control-diario.php?action=export" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
      <?= csrfField($app) ?>
      <div class="field" style="min-width:160px;margin:0;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from']??'') ?>"></div>
      <div class="field" style="min-width:160px;margin:0;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to']??'') ?>"></div>
      <div class="field" style="min-width:180px;margin:0;"><label>Dependencia</label><select name="department"><option value="">Todas</option><?php foreach(($departments??[]) as $d): ?><option value="<?= e($d) ?>" <?= (($filters['department']??'')===$d)?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
      <button class="btn" type="submit">Descargar Excel</button>
    </form>
    <p class="muted" style="margin:10px 0 0;font-size:.85rem;">Exporta el formato real con columnas RIF desglosadas y fila de totales. <code>.xlsx</code> o <code>.csv</code> UTF-8 BOM si ZipArchive no está disponible.</p>
  </div>

  <form method="get" action="control-diario.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
    <div class="field" style="margin:0;min-width:200px;"><label>Buscar</label><input name="q" value="<?= e($filters['q']??'') ?>" placeholder="Usuario, dependencia u observación"></div>
    <div class="field" style="margin:0;min-width:150px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from']??'') ?>"></div>
    <div class="field" style="margin:0;min-width:150px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to']??'') ?>"></div>
    <div class="field" style="margin:0;min-width:180px;"><label>Dependencia</label><select name="department"><option value="">Todas</option><?php foreach(($departments??[]) as $d): ?><option value="<?= e($d) ?>" <?= (($filters['department']??'')===$d)?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
    <button class="btn" type="submit">Filtrar</button><a class="btn btn-outline" href="control-diario.php">Limpiar</a>
  </form>

  <div class="table-wrap"><table class="table">
    <thead><tr>
      <th>Fecha</th><th>Dependencia</th><th>Usuario</th>
      <th>RIF total</th><th>Asistencia</th><th>Sucesiones atend.</th><th>Sucesiones ing.</th><th>Solv. sucesiones</th><th>Multas notif.</th><th>Copias ent.</th><th>Acciones</th>
    </tr></thead>
    <tbody>
    <?php if(!$items): ?><tr><td colspan="11"><div class="empty">No hay reportes diarios.</div></td></tr>
    <?php else: foreach($items as $it): ?>
      <tr>
        <td><strong><?= e(date('d/m/Y', strtotime((string)$it['fecha']))) ?></strong></td>
        <td><?= e($it['dependencia'] ?? '-') ?></td>
        <td><?= e($it['usuario_nombre'] ?? '-') ?></td>
        <td><?= (int)$it['rif_total_atendidos'] ?></td>
        <td><?= (int)$it['asistencia_contribuyentes'] ?></td>
        <td><?= (int)$it['sucesiones_atendidos'] ?></td>
        <td><?= (int)$it['sucesiones_ingresadas'] ?></td>
        <td><?= (int)$it['solvencias_sucesiones_entregadas'] ?></td>
        <td><?= (int)$it['multas_notificadas'] ?></td>
        <td><?= (int)$it['copias_entregadas'] ?></td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn btn-outline btn-sm" href="control-diario.php?action=edit&id=<?= (int)$it['id'] ?>">Editar</a>
          <?php if (in_array(($user['role_name'] ?? ''), ['admin','supervisor'], true)): ?>
          <form method="post" action="control-diario.php?action=delete" onsubmit="return confirm('¿Eliminar reporte?');" style="margin:0"><?= csrfField($app) ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Eliminar</button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
  <?php if(($totalPages??1)>1): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;"><?php $qs=$_GET; for($p=1;$p<=$totalPages;$p++): $qs['page']=$p; ?><a class="btn btn-outline btn-sm" href="control-diario.php?<?= e(http_build_query($qs)) ?>" style="<?= $p===($page??1)?'background:var(--primary);color:#fff;border-color:var(--primary);':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
