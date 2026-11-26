<?php
declare(strict_types=1);
// Limpieza básica: php scripts/cleanup.php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$pdo = db();
$daysAttempts = 30;
$daysAudit = 180;
$daysRecovery = 7;
$st = $pdo->prepare("DELETE FROM login_attempts WHERE created_at < NOW() - (:d * INTERVAL '1 day')");
$st->execute(['d'=>$daysAttempts]);
$a1 = $st->rowCount();
$st = $pdo->prepare("DELETE FROM password_recovery_requests WHERE created_at < NOW() - (:d * INTERVAL '1 day')");
$st->execute(['d'=>$daysRecovery]);
$a2 = $st->rowCount();
$st = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < NOW() - (:d * INTERVAL '1 day') AND severity = 'info'");
$st->execute(['d'=>$daysAudit]);
$a3 = $st->rowCount();
echo "Cleanup OK. login_attempts=$a1 recovery=$a2 audit_info=$a3\n";