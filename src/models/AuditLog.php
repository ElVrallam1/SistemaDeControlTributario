<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

class AuditLog {
    public static function search(array $filters, int $limit = 50, int $offset = 0): array {
        $where = []; $params = [];
        if (!empty($filters['action'])) { $where[] = 'a.action ILIKE :action'; $params['action'] = '%' . $filters['action'] . '%'; }
        if (!empty($filters['severity'])) { $where[] = 'a.severity = :severity'; $params['severity'] = $filters['severity']; }
        if (!empty($filters['user_id'])) { $where[] = 'a.user_id = :uid'; $params['uid'] = (int)$filters['user_id']; }
        if (!empty($filters['from'])) { $where[] = 'a.created_at >= :from'; $params['from'] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'a.created_at <= :to'; $params['to'] = $filters['to'] . ' 23:59:59'; }
        $sql = 'FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSt = db()->prepare('SELECT COUNT(*) ' . $sql);
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();
        $st = db()->prepare('SELECT a.*, u.username, u.full_name ' . $sql . ' ORDER BY a.created_at DESC, a.id DESC LIMIT :lim OFFSET :off');
        foreach ($params as $k => $v) $st->bindValue(':'.$k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll() ?: [], 'total' => $total];
    }
}
