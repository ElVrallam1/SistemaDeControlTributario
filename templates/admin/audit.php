<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card mb-24">
  <div class="toolbar">
    <div><h3 class="card-title" style="margin:0">Auditoría</h3><p class="card-sub">Eventos de sistema y seguridad. Total: <?= (int)$total ?></p></div>
    <a class="btn btn-outline" href="admin.php">Volver</a>
  </div>
  <form method="get" action="admin.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
    <input type="hidden" name="action" value="audit">
    <div class="field" style="margin:0;min-width:180px;"><label>Acción</label><input name="q" value="<?= e($filters['action']) ?>" placeholder="login.failed, admin..."></div>
    <div class="field" style="margin:0;min-width:150px;"><label>Severidad</label>
      <select name="severity"><option value="">Todas</option><?php foreach(['info','warning','critical'] as $s): ?><option value="<?= $s ?>" <?= $filters['severity']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
    </div>
    <div class="field" style="margin:0;min-width:150px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="field" style="margin:0;min-width:150px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn btn-outline" href="admin.php?action=audit">Limpiar</a>
  </form>

  <div class="table-wrap"><table class="table">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Severidad</th><th>Entidad</th><th>IP</th><th>Detalle</th></tr></thead>
    <tbody>
    <?php if (!$items): ?><tr><td colspan="7"><div class="empty">No hay registros de auditoría.</div></td></tr>
    <?php else: foreach ($items as $it): $details = (string)($it['details'] ?? ''); ?>
      <tr>
        <td style="white-space:nowrap"><?= e(date('d/m/Y H:i:s', strtotime((string)$it['created_at']))) ?></td>
        <td><?= e($it['full_name'] ?: ($it['username'] ?: 'Sistema/Anónimo')) ?></td>
        <td><code style="font-size:.8rem"><?= e($it['action']) ?></code></td>
        <td><?php $sev=$it['severity']; $cls=$sev==='critical'?'badge-danger':($sev==='warning'?'badge-warning':'badge-info'); ?><span class="badge <?= $cls ?>"><?= e($sev) ?></span></td>
        <td><?= e(trim(($it['entity_type'] ?? '') . ' ' . ($it['entity_id'] ?? ''))) ?: '-' ?></td>
        <td><?= e($it['ip_address'] ?? '-') ?></td>
        <td style="max-width:320px;word-break:break-word;font-size:.82rem;color:var(--muted)"><?= e(mb_substr($details, 0, 180)) ?><?= mb_strlen($details)>180?'…':'' ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center;">
      <?php $qs = $_GET; for ($p=1; $p <= $totalPages; $p++): $qs['page']=$p; $href='admin.php?'.http_build_query($qs); ?>
        <a class="btn btn-outline btn-sm" href="<?= e($href) ?>" style="<?= $p===$page?'background:var(--primary);color:#fff;border-color:var(--primary);':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
