<?php include BASE_PATH . '/templates/layout_start.php'; ?>
<div class="card">
  <div class="card-header"><div><h3 class="card-title">Importar expedientes (CSV/XLSX)</h3><p class="card-sub">Carga histórica desde archivo CSV UTF-8 o XLSX (si el servidor lo soporta). Máx. 10.000 filas.</p></div><a class="btn btn-outline" href="expedientes.php">Volver</a></div>
  <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if($imported>0 || $skipped>0): ?><div class="alert alert-success">Importación finalizada. Importados/actualizados: <strong><?= (int)$imported ?></strong>. Omitidos: <strong><?= (int)$skipped ?></strong>.</div><?php endif; ?>
  <?php if(!empty($details)): ?><div class="alert alert-warning"><strong>Detalles:</strong><br><?php foreach($details as $d): ?><?= e($d) ?><br><?php endforeach; ?></div><?php endif; ?>
  <div class="card" style="box-shadow:none;background:#fbfdff;margin-bottom:16px;">
    <p style="margin:0 0 8px;"><strong>Formato esperado:</strong> encabezados en primera fila. Puedes descargar la plantilla.</p>
    <p class="muted" style="margin:0;font-size:.85rem;">Columnas: NUMERO_EXPEDIENTE, CONTRIBUYENTE, RIF, TIPO_TRAMITE, DIVISION, ESTADO, PRIORIDAD, DESCRIPCION, FECHA_APERTURA, FECHA_CIERRE. Fechas: YYYY-MM-DD o DD/MM/YYYY. Si existe el número de expediente, se actualiza.</p>
    <div style="margin-top:10px;"><a class="btn btn-outline btn-sm" href="expedientes.php?action=template">Descargar plantilla</a></div>
  </div>
  <form method="post" enctype="multipart/form-data"><?= csrfField($app) ?>
    <div class="field"><label>Archivo CSV/XLSX</label><input type="file" name="csv_file" accept=".csv,.txt,.xlsx,text/csv" required></div>
    <div style="display:flex;gap:10px;"><button class="btn" type="submit">Importar</button><a class="btn btn-outline" href="expedientes.php">Cancelar</a></div>
  </form>
</div>
<?php include BASE_PATH . '/templates/layout_end.php'; ?>
