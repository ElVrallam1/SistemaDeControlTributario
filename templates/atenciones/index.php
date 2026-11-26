<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card mb-24">
  <div class="toolbar">
    <div><h3 class="card-title" style="margin:0">Atenciones al Contribuyente</h3><p class="card-sub">Detalle por área con consolidación automática. Total: <?= (int)($total??0) ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;"><a class="btn btn-outline" href="atenciones.php?action=import">Importar CSV</a><a class="btn" href="atenciones.php?action=create">+ Nueva atención</a></div>
  </div>
  <?php if(isset($_GET['ok'])):?><div class="alert alert-success">Registro guardado.</div><?php endif; ?>
  <?php if(isset($_GET['deleted'])):?><div class="alert alert-success">Registro eliminado.</div><?php endif; ?>

  <div class="grid-2" style="gap:16px;margin-bottom:16px;">
    <div class="card" style="box-shadow:none;background:#fbfdff;margin:0;">
      <h4 style="margin:0 0 10px;font-size:.95rem;">Consolidado automático</h4>
      <div class="table-wrap"><table class="table" style="min-width:0"><thead><tr><th>Área</th><th>Atendidos</th><th>Monto total</th></tr></thead><tbody>
        <?php foreach(($consolidado??[]) as $c): ?>
          <tr style="<?= $c['area']==='TOTAL GENERAL'?'font-weight:700;background:#f8fafc':'' ?>">
            <td><?= e($c['area']) ?></td><td><?= (int)$c['total_atendidos'] ?></td><td><?= number_format((float)$c['total_monto'],2,',','.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <form method="post" action="atenciones.php?action=export_consolidado" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
        <?= csrfField($app) ?><input type="hidden" name="from" value="<?= e($filters['from']??'') ?>"><input type="hidden" name="to" value="<?= e($filters['to']??'') ?>">
        <button class="btn btn-outline btn-sm" type="submit">Exportar consolidado</button>
      </form>
    </div>
    <div class="card" style="box-shadow:none;background:#fbfdff;margin:0;">
      <h4 style="margin:0 0 10px;font-size:.95rem;">Exportar detalle</h4>
      <form method="post" action="atenciones.php?action=export" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
        <?= csrfField($app) ?>
        <input type="hidden" name="q" value="<?= e($filters['q']??'') ?>"><input type="hidden" name="area" value="<?= e($filters['area']??'') ?>">
        <input type="hidden" name="from" value="<?= e($filters['from']??'') ?>"><input type="hidden" name="to" value="<?= e($filters['to']??'') ?>">
        <button class="btn btn-outline" type="submit">Exportar detalle</button>
      </form>
      <p class="muted" style="margin:10px 0 0;font-size:.85rem;">El consolidado se calcula automáticamente desde los registros detallados según el rango de fechas filtrado.</p>
    </div>
  </div>

  <form method="get" action="atenciones.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
    <div class="field" style="margin:0;min-width:200px;"><label>Buscar</label><input name="q" value="<?= e($filters['q']??'') ?>" placeholder="Contribuyente, cédula/RIF, funcionario..."></div>
    <div class="field" style="margin:0;min-width:160px;"><label>Área</label><select name="area"><option value="">Todas</option><?php foreach($areas as $a): ?><option value="<?= e($a) ?>" <?= (($filters['area']??'')===$a)?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0;min-width:140px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from']??'') ?>"></div>
    <div class="field" style="margin:0;min-width:140px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to']??'') ?>"></div>
    <button class="btn" type="submit">Filtrar</button><a class="btn btn-outline" href="atenciones.php">Limpiar</a>
  </form>

  <div class="table-wrap"><table class="table">
    <thead><tr><th>Fecha</th><th>Área</th><th>Contribuyente</th><th>Cédula/RIF</th><th>Trámite</th><th>Funcionario</th><th>Monto</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php if(!$items): ?><tr><td colspan="8"><div class="empty">No hay atenciones registradas.</div></td></tr>
    <?php else: foreach($items as $it): ?>
      <tr>
        <td><strong><?= e(date('d/m/Y',strtotime((string)$it['fecha']))) ?></strong></td>
        <td><span class="badge badge-info"><?= e($it['area']) ?></span></td>
        <td><?= e($it['contribuyente']) ?></td>
        <td><?= e($it['cedula_rif']??'-') ?></td>
        <td><?= e($it['tramite']??'-') ?></td>
        <td><?= e($it['funcionario']??'-') ?></td>
        <td><?= number_format((float)$it['monto'],2,',','.') ?></td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn btn-outline btn-sm" href="atenciones.php?action=edit&id=<?= (int)$it['id'] ?>">Editar</a>
          <?php if(in_array(($user['role_name']??''),['admin','supervisor'],true)): ?>
          <form method="post" action="atenciones.php?action=delete" onsubmit="return confirm('¿Eliminar atención?');" style="margin:0"><?= csrfField($app) ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Eliminar</button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
  <?php if(($totalPages??1)>1): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;"><?php $qs=$_GET; for($p=1;$p<=$totalPages;$p++): $qs['page']=$p; ?><a class="btn btn-outline btn-sm" href="atenciones.php?<?= e(http_build_query($qs)) ?>" style="<?= $p===($page??1)?'background:var(--primary);color:#fff;border-color:var(--primary);':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
