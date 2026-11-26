<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<?php $badgeMap = ['pendiente'=>'badge-warning','en_proceso'=>'badge-info','completado'=>'badge-success','detenido'=>'badge-danger','faltante'=>'badge-danger']; ?>
<div class="card">
  <div class="toolbar">
    <div>
      <h3 class="card-title" style="margin:0;">Listado de expedientes</h3>
      <p class="card-sub">Registra, actualiza y da seguimiento. Total: <?= (int)($total??count($items)) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;"><form method="post" action="expedientes.php?action=export" style="margin:0"><?= csrfField($app) ?><input type="hidden" name="q" value="<?= e($filters['q']??'') ?>"><input type="hidden" name="estado" value="<?= e($filters['estado']??'') ?>"><input type="hidden" name="prioridad" value="<?= e($filters['prioridad']??'') ?>"><button class="btn btn-outline" type="submit">Exportar Excel</button></form><a class="btn btn-outline" href="expedientes.php?action=import">Importar CSV</a><a class="btn" href="expedientes.php?action=create">+ Nuevo expediente</a></div>
  </div>
  <?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Expediente guardado correctamente.</div><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Expediente eliminado.</div><?php endif; ?>
  <form method="get" action="expedientes.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
    <div class="field" style="margin:0;min-width:220px;"><label>Buscar</label><input name="q" value="<?= e($filters['q']??'') ?>" placeholder="N°, contribuyente, RIF, trámite"></div>
    <div class="field" style="margin:0;min-width:160px;"><label>Estado</label><select name="estado"><option value="">Todos</option><?php foreach(($statuses??[]) as $s): ?><option value="<?= e($s) ?>" <?= (($filters['estado']??'')===$s)?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0;min-width:140px;"><label>Prioridad</label><select name="prioridad"><option value="">Todas</option><?php foreach(($priorities??[]) as $p): ?><option value="<?= e($p) ?>" <?= (($filters['prioridad']??'')===$p)?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
    <button class="btn" type="submit">Filtrar</button><a class="btn btn-outline" href="expedientes.php">Limpiar</a>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>N°</th><th>Contribuyente</th><th>Trámite</th><th>División</th><th>Estado</th><th>Prioridad</th><th>Apertura</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="8"><div class="empty">No hay expedientes registrados.</div></td></tr>
      <?php else: foreach ($items as $it): ?>
        <tr>
          <td><strong><?= e($it['numero_expediente']) ?></strong></td>
          <td><?= e($it['contribuyente']) ?><div class="muted" style="font-size:.8rem;"><?= e($it['rif'] ?? '') ?></div></td>
          <td><?= e($it['tipo_tramite']) ?></td>
          <td><?= e($it['division']) ?></td>
          <td><span class="badge <?= $badgeMap[$it['estado']] ?? 'badge-muted' ?>"><?= e($it['estado']) ?></span></td>
          <td><?= e($it['prioridad']) ?></td>
          <td><?= e(date('d/m/Y', strtotime((string)$it['fecha_apertura']))) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a class="btn btn-outline btn-sm" href="expedientes.php?action=edit&id=<?= (int)$it['id'] ?>">Editar</a>
              <form method="post" action="expedientes.php?action=delete" onsubmit="return confirm('¿Eliminar este expediente?');" style="margin:0;">
                <?= csrfField($app) ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit">Eliminar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(($totalPages??1)>1): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;"><?php $qs=$_GET; for($p=1;$p<=$totalPages;$p++): $qs['page']=$p; ?><a class="btn btn-outline btn-sm" href="expedientes.php?<?= e(http_build_query($qs)) ?>" style="<?= $p===($page??1)?'background:var(--primary);color:#fff;border-color:var(--primary);':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
