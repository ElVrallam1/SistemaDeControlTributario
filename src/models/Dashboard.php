<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Dashboard
{
    public static function summary(array $filters = []): array
    {
        $pdo = db();
        $from = !empty($filters['from']) ? $filters['from'] : null;
        $to = !empty($filters['to']) ? $filters['to'] : null;

        $exp = self::statusCounts('expedientes', ['pendiente', 'en_proceso', 'completado', 'detenido', 'faltante'], 'fecha_apertura', $from, $to);
        $sol = self::statusCounts('solvencias', ['pendiente', 'emitida', 'vencida', 'rechazada'], 'created_at', $from, $to);
        $mul = self::statusCounts('multas', ['pendiente', 'notificada', 'pagada', 'anulada', 'en_recurso'], 'fecha_registro', $from, $to);
        $req = self::statusCounts('requerimientos', ['pendiente', 'enviado', 'atendido', 'vencido', 'cancelado'], 'fecha_emision', $from, $to);

        $totals = [
            'expedientes' => array_sum($exp),
            'solvencias' => array_sum($sol),
            'multas' => array_sum($mul),
            'requerimientos' => array_sum($req),
        ];

        $kpis = self::kpis($pdo, $totals, $exp, $mul, $req);
        $balance3 = [
            'labels' => ['Expedientes completados', 'Solvencias emitidas', 'Requerimientos atendidos'],
            'values' => [
                (int) ($exp['completado'] ?? 0),
                (int) ($sol['emitida'] ?? 0),
                (int) ($req['atendido'] ?? 0),
            ],
        ];

        $statusChart = [
            'labels' => ['Expedientes', 'Solvencias', 'Multas', 'Requerimientos'],
            'completado' => [
                (int) ($exp['completado'] ?? 0),
                (int) ($sol['emitida'] ?? 0),
                (int) (($mul['pagada'] ?? 0)),
                (int) ($req['atendido'] ?? 0),
            ],
            'proceso' => [
                (int) ($exp['en_proceso'] ?? 0),
                (int) ($sol['pendiente'] ?? 0),
                (int) (($mul['notificada'] ?? 0) + ($mul['en_recurso'] ?? 0)),
                (int) (($req['enviado'] ?? 0) + ($req['pendiente'] ?? 0)),
            ],
            'pendiente' => [
                (int) (($exp['pendiente'] ?? 0) + ($exp['detenido'] ?? 0) + ($exp['faltante'] ?? 0)),
                (int) (($sol['vencida'] ?? 0) + ($sol['rechazada'] ?? 0)),
                (int) (($mul['pendiente'] ?? 0) + ($mul['anulada'] ?? 0)),
                (int) (($req['vencido'] ?? 0) + ($req['cancelado'] ?? 0)),
            ],
        ];

        $operativo = [
            'se_tiene' => [
                'Expedientes' => $totals['expedientes'],
                'Solvencias' => $totals['solvencias'],
                'Multas' => $totals['multas'],
                'Requerimientos' => $totals['requerimientos'],
            ],
            'se_hace' => [
                'Expedientes' => (int) ($exp['en_proceso'] ?? 0),
                'Solvencias' => (int) ($sol['pendiente'] ?? 0),
                'Multas' => (int) (($mul['notificada'] ?? 0) + ($mul['en_recurso'] ?? 0)),
                'Requerimientos' => (int) (($req['enviado'] ?? 0)),
            ],
            'no_se_hace' => [
                'Expedientes detenidos' => (int) ($exp['detenido'] ?? 0),
                'Multas anuladas' => (int) ($mul['anulada'] ?? 0),
                'Req. cancelados' => (int) ($req['cancelado'] ?? 0),
                'Solvencias rechazadas' => (int) ($sol['rechazada'] ?? 0),
            ],
            'falta' => [
                'Expedientes pendientes/faltantes' => (int) (($exp['pendiente'] ?? 0) + ($exp['faltante'] ?? 0)),
                'Solvencias vencidas' => (int) ($sol['vencida'] ?? 0),
                'Multas pendientes' => (int) ($mul['pendiente'] ?? 0),
                'Req. vencidos/pendientes' => (int) (($req['vencido'] ?? 0) + ($req['pendiente'] ?? 0)),
            ],
        ];

        $progress = [
            ['label' => 'Expedientes resueltos', 'pct' => self::pct($exp['completado'] ?? 0, $totals['expedientes']), 'color' => 'linear-gradient(90deg,#8b1e1e,#d14b4b)'],
            ['label' => 'Solvencias emitidas', 'pct' => self::pct($sol['emitida'] ?? 0, $totals['solvencias']), 'color' => 'linear-gradient(90deg,#2563eb,#60a5fa)'],
            ['label' => 'Requerimientos atendidos', 'pct' => self::pct($req['atendido'] ?? 0, $totals['requerimientos']), 'color' => 'linear-gradient(90deg,#059669,#34d399)'],
            ['label' => 'Multas gestionadas/pagadas', 'pct' => self::pct(($mul['pagada'] ?? 0) + ($mul['en_recurso'] ?? 0) + ($mul['notificada'] ?? 0), $totals['multas']), 'color' => 'linear-gradient(90deg,#d97706,#fbbf24)'],
        ];

        $recent = self::recentActivity($pdo, $from, $to);

        return compact('kpis', 'balance3', 'statusChart', 'operativo', 'progress', 'recent', 'totals', 'filters');
    }

    private static function statusCounts(string $table, array $statuses, string $dateCol = '', ?string $from = null, ?string $to = null): array
    {
        $allowed = ['expedientes','solvencias','multas','requerimientos'];
        $allowedCols = ['fecha_apertura','fecha_registro','fecha_emision','created_at'];
        if (!in_array($table, $allowed, true)) return [];
        $where = []; $params = [];
        if ($dateCol && in_array($dateCol, $allowedCols, true)) {
            if ($from) { $where[] = "{$dateCol}::date >= :from"; $params['from'] = $from; }
            if ($to) { $where[] = "{$dateCol}::date <= :to"; $params['to'] = $to; }
        }
        $sql = "SELECT estado, COUNT(*)::int AS total FROM {$table}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " GROUP BY estado";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach ($statuses as $s) $out[$s] = (int) ($rows[$s] ?? 0);
        return $out;
    }

    private static function pct(int|float $part, int|float $total): int
    {
        if ($total <= 0) return 0;
        return (int) round(($part / $total) * 100);
    }

    private static function kpis(PDO $pdo, array $totals, array $exp, array $mul, array $req): array
    {
        // Comparación últimos 7 días vs 7 días anteriores (por fecha de creación/registro)
        $expTrend = self::periodTrend('expedientes', 'fecha_apertura');
        $mulPendTrend = self::periodTrend('multas', 'fecha_registro', "estado = 'pendiente'");
        $reqVencTrend = self::periodTrend('requerimientos', 'fecha_emision', "estado = 'vencido'");

        $resueltos = (int) ($exp['completado'] ?? 0);
        $expTotal = (int) ($totals['expedientes'] ?? 0);
        $prodPct = self::pct($resueltos, $expTotal);

        return [
            [
                'label' => 'Expedientes totales',
                'value' => (string) $expTotal,
                'delta' => $expTrend['delta'],
                'direction' => $expTrend['direction'],
                'meta' => $expTrend['meta'],
            ],
            [
                'label' => 'Productividad resuelta',
                'value' => $prodPct . '%',
                'delta' => $resueltos . ' de ' . $expTotal,
                'direction' => $prodPct >= 60 ? 'up' : ($prodPct >= 40 ? 'flat' : 'down'),
                'meta' => 'Expedientes completados sobre el total',
            ],
            [
                'label' => 'Multas pendientes',
                'value' => (string) ((int) ($mul['pendiente'] ?? 0)),
                'delta' => $mulPendTrend['delta'],
                'direction' => $mulPendTrend['inverse'] ?? $mulPendTrend['direction'],
                'meta' => $mulPendTrend['meta'],
            ],
            [
                'label' => 'Requerimientos vencidos',
                'value' => (string) ((int) ($req['vencido'] ?? 0)),
                'delta' => $reqVencTrend['delta'],
                'direction' => $reqVencTrend['inverse'] ?? $reqVencTrend['direction'],
                'meta' => $reqVencTrend['meta'],
            ],
        ];
    }

    private static function periodTrend(string $table, string $dateCol, string $where = ''): array
    {
        $allowedTables = ['expedientes','multas','requerimientos','solvencias'];
        $allowedCols = ['fecha_apertura','fecha_registro','fecha_emision','created_at'];
        if (!in_array($table, $allowedTables, true) || !in_array($dateCol, $allowedCols, true)) {
            return ['delta' => 'Sin datos', 'direction' => 'flat', 'meta' => ''];
        }
        $extra = $where ? " AND ($where)" : '';
        $sql = "SELECT
            COUNT(*) FILTER (WHERE {$dateCol} >= CURRENT_DATE - INTERVAL '7 days' {$extra})::int AS current_p,
            COUNT(*) FILTER (WHERE {$dateCol} < CURRENT_DATE - INTERVAL '7 days' AND {$dateCol} >= CURRENT_DATE - INTERVAL '14 days' {$extra})::int AS prev_p
            FROM {$table} WHERE 1=1 {$extra}";
        $row = db()->query($sql)->fetch() ?: ['current_p' => 0, 'prev_p' => 0];
        $cur = (int) $row['current_p'];
        $prev = (int) $row['prev_p'];
        if ($prev === 0 && $cur === 0) return ['delta' => 'Sin cambio', 'direction' => 'flat', 'meta' => 'Últimos 7 días vs 7 anteriores', 'inverse' => 'flat'];
        if ($prev === 0) return ['delta' => "+{$cur} nuevos", 'direction' => 'up', 'meta' => 'Últimos 7 días vs 7 anteriores', 'inverse' => 'down'];
        $diff = $cur - $prev;
        $pct = (int) round(($diff / max($prev, 1)) * 100);
        $sign = $pct > 0 ? '+' : '';
        $dir = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
        $inv = $pct > 0 ? 'down' : ($pct < 0 ? 'up' : 'flat'); // para métricas donde subir es malo
        return ['delta' => "{$sign}{$pct}% vs periodo anterior", 'direction' => $dir, 'meta' => 'Últimos 7 días vs 7 anteriores', 'inverse' => $inv];
    }

    private static function recentActivity(PDO $pdo, ?string $from = null, ?string $to = null): array
    {
        $where = []; $params = [];
        if ($from) { $where[] = 'r.fecha >= :from'; $params['from'] = $from; }
        if ($to) { $where[] = 'r.fecha <= :to'; $params['to'] = $to; }
        $sql = "SELECT r.fecha, 'Reporte diario' AS area,
            CONCAT('RIF atendidos: ', r.rif_total_atendidos, ', Asistencia: ', r.asistencia_contribuyentes, ', Sucesiones: ', r.sucesiones_atendidos) AS movimiento,
            CASE WHEN r.rif_total_atendidos > 0 OR r.asistencia_contribuyentes > 0 OR r.sucesiones_atendidos > 0 THEN 'Completado' ELSE 'En proceso' END AS estado
            FROM reporte_diario r" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY r.fecha DESC, r.id DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
