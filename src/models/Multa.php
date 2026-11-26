<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
class Multa {
    public static function all(): array { return db()->query('SELECT m.*, u.full_name AS responsable_nombre FROM multas m LEFT JOIN users u ON u.id=m.responsable_id ORDER BY m.fecha_registro DESC, m.id DESC')->fetchAll() ?: []; }
    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(m.numero_multa ILIKE :q OR m.contribuyente ILIKE :q OR m.rif ILIKE :q OR m.motivo ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estado'])) { $where[]='m.estado=:estado'; $params['estado']=$f['estado']; }
        $sql='FROM multas m LEFT JOIN users u ON u.id=m.responsable_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT m.*, u.full_name AS responsable_nombre '.$sql.' ORDER BY m.fecha_registro DESC, m.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }
    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM multas WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }
    public static function create(array $d): int { $st=db()->prepare('INSERT INTO multas (numero_multa,contribuyente,rif,motivo,estado,monto,fecha_registro,fecha_pago,responsable_id,observaciones) VALUES (:n,:c,:rif,:mo,:e,:m,:fr,:fp,:r,:o) RETURNING id'); $st->execute($d); return (int)$st->fetchColumn(); }
    public static function update(int $id,array $d): void { $d['id']=$id; $st=db()->prepare('UPDATE multas SET numero_multa=:n,contribuyente=:c,rif=:rif,motivo=:mo,estado=:e,monto=:m,fecha_registro=:fr,fecha_pago=:fp,responsable_id=:r,observaciones=:o,updated_at=NOW() WHERE id=:id'); $st->execute($d); }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM multas WHERE id=:id'); $st->execute(['id'=>$id]); }
}
