<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
class Productividad {
    public static function all(): array { return db()->query('SELECT p.*, u.full_name AS usuario_nombre, e.numero_expediente FROM productividad_expedientes p LEFT JOIN users u ON u.id=p.usuario_id LEFT JOIN expedientes e ON e.id=p.expediente_id ORDER BY p.fecha DESC, p.id DESC')->fetchAll() ?: []; }
    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(u.full_name ILIKE :q OR e.numero_expediente ILIKE :q OR p.observaciones ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['from'])) { $where[]='p.fecha >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='p.fecha <= :to'; $params['to']=$f['to']; }
        $sql='FROM productividad_expedientes p LEFT JOIN users u ON u.id=p.usuario_id LEFT JOIN expedientes e ON e.id=p.expediente_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT p.*, u.full_name AS usuario_nombre, e.numero_expediente '.$sql.' ORDER BY p.fecha DESC, p.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }
    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM productividad_expedientes WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }
    public static function create(array $d): int { $st=db()->prepare('INSERT INTO productividad_expedientes (expediente_id,usuario_id,fecha,expedientes_procesados,expedientes_resueltos,expedientes_pendientes,observaciones) VALUES (:eid,:uid,:f,:p,:r,:pe,:o) RETURNING id'); $st->execute($d); return (int)$st->fetchColumn(); }
    public static function update(int $id,array $d): void { $d['id']=$id; $st=db()->prepare('UPDATE productividad_expedientes SET expediente_id=:eid,usuario_id=:uid,fecha=:f,expedientes_procesados=:p,expedientes_resueltos=:r,expedientes_pendientes=:pe,observaciones=:o WHERE id=:id'); $st->execute($d); }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM productividad_expedientes WHERE id=:id'); $st->execute(['id'=>$id]); }
}
