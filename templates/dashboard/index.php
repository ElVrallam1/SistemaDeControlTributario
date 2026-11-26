<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<?php
$kpis = $summary['kpis'] ?? [];
$balance3 = $summary['balance3'] ?? ['labels'=>[], 'values'=>[]];
$statusChart = $summary['statusChart'] ?? ['labels'=>[], 'completado'=>[], 'proceso'=>[], 'pendiente'=>[]];
$operativo = $summary['operativo'] ?? [];
$progress = $summary['progress'] ?? [];
$recent = $summary['recent'] ?? [];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<style>.chart-box{position:relative;height:290px}</style>

<?php if (isset($_GET['password_changed'])): ?>
  <div class="alert alert-success">Contraseña actualizada correctamente.</div>
<?php endif; ?>

<div class="card mb-24">
  <form method="get" action="dashboard.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
    <div class="field" style="margin:0;min-width:170px;"><label>Desde</label><input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>"></div>
    <div class="field" style="margin:0;min-width:170px;"><label>Hasta</label><input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>"></div>
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn btn-outline" href="dashboard.php">Limpiar</a>
    <?php if (!empty($filters['from']) || !empty($filters['to'])): ?><span class="muted" style="font-size:.85rem;">Mostrando datos filtrados por fecha.</span><?php endif; ?>
  </form>
</div>
<?php
$alerts = [];
$falta = $operativo['falta'] ?? [];
if (($falta['Solvencias vencidas'] ?? 0) > 0) $alerts[] = ['type'=>'danger','msg'=>'Hay '.$falta['Solvencias vencidas'].' solvencia(s) vencida(s).'];
if (($falta['Req. vencidos/pendientes'] ?? 0) > 0) $alerts[] = ['type'=>'warning','msg'=>'Hay '.$falta['Req. vencidos/pendientes'].' requerimiento(s) vencido(s)/pendiente(s).'];
if (($falta['Multas pendientes'] ?? 0) > 0) $alerts[] = ['type'=>'warning','msg'=>'Hay '.$falta['Multas pendientes'].' multa(s) pendiente(s).'];
foreach ($alerts as $a): ?>
  <div class="alert alert-<?= e($a['type']) ?> mb-24" style="margin-bottom:12px;"><?= e($a['msg']) ?></div>
<?php endforeach; ?>

<div class="grid grid-4 mb-24">
  <?php foreach ($kpis as $kpi): ?>
    <div class="card metric">
      <div class="label"><?= e($kpi['label']) ?></div>
      <div class="value"><?= e($kpi['value']) ?></div>
      <span class="delta <?= e($kpi['direction']) ?>">
        <?= $kpi['direction'] === 'up' ? '▲' : ($kpi['direction'] === 'down' ? '▼' : '■') ?>
        <?= e($kpi['delta']) ?>
      </span>
      <div class="meta"><?= e($kpi['meta']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-4 mb-24">
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Qué se tiene</h3><p class="card-sub">Inventario operativo actual</p></div><span class="badge badge-info">Total</span></div>
    <div class="kpi-row">
      <?php foreach (($operativo['se_tiene'] ?? []) as $k => $v): ?>
        <div class="kpi-box"><div class="k"><?= e($k) ?></div><div class="v"><?= e((string)$v) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Qué se está haciendo</h3><p class="card-sub">En proceso / gestión activa</p></div><span class="badge badge-warning">Proceso</span></div>
    <div class="kpi-row">
      <?php foreach (($operativo['se_hace'] ?? []) as $k => $v): ?>
        <div class="kpi-box"><div class="k"><?= e($k) ?></div><div class="v"><?= e((string)$v) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Qué no se está haciendo</h3><p class="card-sub">Detenido / anulado / cancelado</p></div><span class="badge badge-muted">Inactivo</span></div>
    <div class="kpi-row">
      <?php foreach (($operativo['no_se_hace'] ?? []) as $k => $v): ?>
        <div class="kpi-box"><div class="k"><?= e($k) ?></div><div class="v"><?= e((string)$v) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Qué hace falta</h3><p class="card-sub">Pendientes / vencidos / faltantes</p></div><span class="badge badge-danger">Atención</span></div>
    <div class="kpi-row">
      <?php foreach (($operativo['falta'] ?? []) as $k => $v): ?>
        <div class="kpi-box"><div class="k"><?= e($k) ?></div><div class="v"><?= e((string)$v) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid grid-2 mb-24">
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Balance de 3 estadísticas</h3><p class="card-sub">Expedientes completados, solvencias emitidas y requerimientos atendidos</p></div></div>
    <div class="chart-box"><canvas id="balanceChart"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Estado operativo por área</h3><p class="card-sub">Completado, en proceso y pendiente/falta</p></div></div>
    <div class="chart-box"><canvas id="statusChart"></canvas></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Avance por gestión</h3><p class="card-sub">Porcentajes de cumplimiento</p></div></div>
    <div style="display:grid;gap:14px;">
      <?php foreach ($progress as $p): ?>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:.9rem;margin-bottom:6px;">
            <span><?= e($p['label']) ?></span><strong><?= e((string)$p['pct']) ?>%</strong>
          </div>
          <div class="progress"><span style="width:<?= (int)$p['pct'] ?>%;background:<?= e($p['color']) ?>"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3 class="card-title">Control diario reciente</h3><p class="card-sub">Últimos registros operativos</p></div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Área</th><th>Movimiento</th><th>Estado</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="4"><div class="empty">No hay registros recientes.</div></td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime((string)$r['fecha']))) ?></td>
            <td><?= e($r['area']) ?></td>
            <td><?= e($r['movimiento']) ?></td>
            <td>
              <?php $badge = $r['estado'] === 'Completado' ? 'badge-success' : 'badge-warning'; ?>
              <span class="badge <?= $badge ?>"><?= e($r['estado']) ?></span>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$dashboardPayload = json_encode([
  'balance3' => $balance3,
  'statusChart' => $statusChart,
], $jsonFlags);
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
  . '<script type="application/json" id="dashboard-data">' . $dashboardPayload . '</script>'
  . '<script src="assets/js/dashboard.js"></script>';
include BASE_PATH . '/templates/layout_end.php';
?>
