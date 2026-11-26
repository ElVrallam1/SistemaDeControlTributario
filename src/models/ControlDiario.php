<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
class ControlDiario {
    public static function all(): array {
        return db()->query('SELECT c.*, u.full_name AS usuario_nombre FROM control_diario c LEFT JOIN users u ON u.id=c.usuario_id ORDER BY c.fecha DESC, c.id DESC')->fetchAll() ?: [];
    }
    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['from'])) { $where[]='c.fecha >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='c.fecha <= :to'; $params['to']=$f['to']; }
        if (!empty($f['q'])) { $where[]='(u.full_name ILIKE :q OR c.observaciones ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['department'])) { $where[]='u.department = :department'; $params['department']=$f['department']; }
        $sql='FROM control_diario c LEFT JOIN users u ON u.id=c.usuario_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT c.*, u.full_name AS usuario_nombre '.$sql.' ORDER BY c.fecha DESC, c.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }
    public static function find(int $id): ?array {
        $st = db()->prepare('SELECT * FROM control_diario WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null;
    }
    public static function create(array $d): int {
        $st = db()->prepare('INSERT INTO control_diario (fecha,usuario_id,expedientes_ingresados,expedientes_procesados,solvencias_emitidas,multas_registradas,requerimientos_atendidos,observaciones) VALUES (:f,:uid,:ei,:ep,:se,:mr,:ra,:o) RETURNING id');
        $st->execute($d); return (int)$st->fetchColumn();
    }
    public static function update(int $id, array $d): void {
        $d['id']=$id;
        $st = db()->prepare('UPDATE control_diario SET fecha=:f,usuario_id=:uid,expedientes_ingresados=:ei,expedientes_procesados=:ep,solvencias_emitidas=:se,multas_registradas=:mr,requerimientos_atendidos=:ra,observaciones=:o,updated_at=NOW() WHERE id=:id');
        $st->execute($d);
    }
    public static function delete(int $id): void {
        $st = db()->prepare('DELETE FROM control_diario WHERE id=:id'); $st->execute(['id'=>$id]);
    }
    public static function forExport(?string $from=null, ?string $to=null, ?string $department=null): array {
        $sql = 'SELECT c.fecha, u.full_name AS usuario, u.department AS departamento, c.expedientes_ingresados, c.expedientes_procesados, c.solvencias_emitidas, c.multas_registradas, c.requerimientos_atendidos, c.observaciones FROM control_diario c LEFT JOIN users u ON u.id=c.usuario_id';
        $where=[]; $params=[];
        if ($from) { $where[]='c.fecha >= :from'; $params['from']=$from; }
        if ($to) { $where[]='c.fecha <= :to'; $params['to']=$to; }
        if ($department) { $where[]='u.department = :department'; $params['department']=$department; }
        if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
        $sql .= ' ORDER BY c.fecha DESC, c.id DESC';
        $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll() ?: [];
    }
}
