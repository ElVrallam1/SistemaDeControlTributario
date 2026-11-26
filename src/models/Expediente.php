<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Expediente
{
    public static function all(): array
    {
        $stmt = db()->query('SELECT e.*, u.full_name AS responsable_nombre FROM expedientes e LEFT JOIN users u ON u.id = e.responsable_id ORDER BY e.fecha_apertura DESC, e.id DESC');
        return $stmt->fetchAll() ?: [];
    }

    public static function search(array $f, int $limit = 20, int $offset = 0): array
    {
        $where = []; $params = [];
        if (!empty($f['q'])) { $where[] = '(e.numero_expediente ILIKE :q OR e.contribuyente ILIKE :q OR e.rif ILIKE :q OR e.tipo_tramite ILIKE :q)'; $params['q'] = '%'.$f['q'].'%'; }
        if (!empty($f['estado'])) { $where[] = 'e.estado = :estado'; $params['estado'] = $f['estado']; }
        if (!empty($f['prioridad'])) { $where[] = 'e.prioridad = :prioridad'; $params['prioridad'] = $f['prioridad']; }
        $sql = 'FROM expedientes e LEFT JOIN users u ON u.id = e.responsable_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $st = db()->prepare('SELECT COUNT(*) ' . $sql); $st->execute($params); $total = (int)$st->fetchColumn();
        $st = db()->prepare('SELECT e.*, u.full_name AS responsable_nombre ' . $sql . ' ORDER BY e.fecha_apertura DESC, e.id DESC LIMIT :lim OFFSET :off');
        foreach ($params as $k => $v) $st->bindValue(':'.$k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT); $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll() ?: [], 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM expedientes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $d): int
    {
        $stmt = db()->prepare('INSERT INTO expedientes (numero_expediente, contribuyente, rif, tipo_tramite, division, estado, prioridad, descripcion, fecha_apertura, fecha_cierre, responsable_id, created_by) VALUES (:n,:c,:rif,:t,:div,:e,:p,:desc,:fa,:fc,:resp,:cb) RETURNING id');
        $stmt->execute([
            'n'=>$d['numero_expediente'],'c'=>$d['contribuyente'],'rif'=>$d['rif'],'t'=>$d['tipo_tramite'],'div'=>$d['division'],
            'e'=>$d['estado'],'p'=>$d['prioridad'],'desc'=>$d['descripcion'],'fa'=>$d['fecha_apertura'],'fc'=>$d['fecha_cierre'],
            'resp'=>$d['responsable_id'],'cb'=>$d['created_by']
        ]);
        return (int) $stmt->fetchColumn();
    }

    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE expedientes SET numero_expediente=:n, contribuyente=:c, rif=:rif, tipo_tramite=:t, division=:div, estado=:e, prioridad=:p, descripcion=:desc, fecha_apertura=:fa, fecha_cierre=:fc, responsable_id=:resp, updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            'id'=>$id,'n'=>$d['numero_expediente'],'c'=>$d['contribuyente'],'rif'=>$d['rif'],'t'=>$d['tipo_tramite'],'div'=>$d['division'],
            'e'=>$d['estado'],'p'=>$d['prioridad'],'desc'=>$d['descripcion'],'fa'=>$d['fecha_apertura'],'fc'=>$d['fecha_cierre'],'resp'=>$d['responsable_id']
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM expedientes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
