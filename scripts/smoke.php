<?php
declare(strict_types=1);
// Smoke test básico: php scripts/smoke.php
$base = dirname(__DIR__);
$errors = [];
$required = [
    '.env.example','public/index.php','public/login.php','public/dashboard.php','public/control-diario.php','public/inventario-sucesiones.php','public/gestion-sucesiones.php','public/atenciones.php',
    'src/bootstrap.php','src/helpers/security.php','src/helpers/auth.php','src/helpers/excel.php','src/helpers/import.php',
    'database/schema.sql','templates/layout_start.php'
];
foreach ($required as $f) { if (!file_exists($base.'/'.$f)) $errors[] = "Falta: $f"; }
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)) continue;
    $phpBin = PHP_BINARY ?: 'php';
    $out=[]; $code=0; exec(escapeshellarg($phpBin).' -l '.escapeshellarg($path).' 2>&1', $out, $code);
    if ($code !== 0) $errors[] = "Lint: $path\n".implode("\n",$out);
}
if ($errors) { echo "SMOKE FAIL\n".implode("\n---\n",$errors)."\n"; exit(1); }
echo "SMOKE OK\n";