<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
class Requerimiento {
    public static function all(): array { return db()->query('SELECT r.*, u.full_name AS responsable_nombre FROM requerimientos r LEFT JOIN users u ON u.id=r.responsable_id ORDER BY r.fecha_emision DESC, r.id DESC')->fetchAll() ?: []; }
    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(r.numero_requerimiento ILIKE :q OR r.contribuyente ILIKE :q OR r.rif ILIKE :q OR r.tipo ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estado'])) { $where[]='r.estado=:estado'; $params['estado']=$f['estado']; }
        $sql='FROM requerimientos r LEFT JOIN users u ON u.id=r.responsable_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT r.*, u.full_name AS responsable_nombre '.$sql.' ORDER BY r.fecha_emision DESC, r.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }
    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM requerimientos WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }
    public static function create(array $d): int { $st=db()->prepare('INSERT INTO requerimientos (numero_requerimiento,contribuyente,rif,tipo,estado,fecha_emision,fecha_limite,fecha_respuesta,responsable_id,descripcion) VALUES (:n,:c,:rif,:t,:e,:fe,:fl,:fr,:r,:dsc) RETURNING id'); $st->execute($d); return (int)$st->fetchColumn(); }
    public static function update(int $id,array $d): void { $d['id']=$id; $st=db()->prepare('UPDATE requerimientos SET numero_requerimiento=:n,contribuyente=:c,rif=:rif,tipo=:t,estado=:e,fecha_emision=:fe,fecha_limite=:fl,fecha_respuesta=:fr,responsable_id=:r,descripcion=:dsc,updated_at=NOW() WHERE id=:id'); $st->execute($d); }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM requerimientos WHERE id=:id'); $st->execute(['id'=>$id]); }
}
