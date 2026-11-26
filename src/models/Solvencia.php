<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
class Solvencia {
    public static function all(): array { return db()->query('SELECT s.*, u.full_name AS responsable_nombre FROM solvencias s LEFT JOIN users u ON u.id=s.responsable_id ORDER BY s.created_at DESC, s.id DESC')->fetchAll() ?: []; }
    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(s.numero_solvencia ILIKE :q OR s.contribuyente ILIKE :q OR s.rif ILIKE :q OR s.tipo ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estado'])) { $where[]='s.estado=:estado'; $params['estado']=$f['estado']; }
        $sql='FROM solvencias s LEFT JOIN users u ON u.id=s.responsable_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT s.*, u.full_name AS responsable_nombre '.$sql.' ORDER BY s.created_at DESC, s.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }
    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM solvencias WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }
    public static function create(array $d): int { $st=db()->prepare('INSERT INTO solvencias (numero_solvencia,contribuyente,rif,tipo,estado,monto,fecha_emision,fecha_vencimiento,responsable_id,observaciones) VALUES (:n,:c,:rif,:t,:e,:m,:fe,:fv,:r,:o) RETURNING id'); $st->execute($d); return (int)$st->fetchColumn(); }
    public static function update(int $id,array $d): void { $d['id']=$id; $st=db()->prepare('UPDATE solvencias SET numero_solvencia=:n,contribuyente=:c,rif=:rif,tipo=:t,estado=:e,monto=:m,fecha_emision=:fe,fecha_vencimiento=:fv,responsable_id=:r,observaciones=:o,updated_at=NOW() WHERE id=:id'); $st->execute($d); }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM solvencias WHERE id=:id'); $st->execute(['id'=>$id]); }
}
