<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

class AtencionContribuyente {
    public static function areas(): array { return ['ASISTENCIA','VIVIENDA','RIF','SUCESIONES','COBRANZA']; }

    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(a.contribuyente ILIKE :q OR a.cedula_rif ILIKE :q OR a.funcionario ILIKE :q OR a.tramite ILIKE :q OR a.n_declaracion ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['area'])) { $where[]='a.area=:area'; $params['area']=$f['area']; }
        if (!empty($f['from'])) { $where[]='a.fecha >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='a.fecha <= :to'; $params['to']=$f['to']; }
        $sql='FROM atenciones_contribuyentes a LEFT JOIN users u ON u.id=a.usuario_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT a.*, u.full_name AS usuario_nombre '.$sql.' ORDER BY a.fecha DESC, a.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }

    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM atenciones_contribuyentes WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }

    public static function create(array $d): int {
        $st=db()->prepare('INSERT INTO atenciones_contribuyentes (fecha,area,contribuyente,cedula_rif,tipo_persona,tramite,orientado,usuario_clave,funcionario,n_declaracion,monto,n_aprobacion,telefono,observaciones,usuario_id) VALUES (:fecha,:area,:contribuyente,:cedula_rif,:tipo_persona,:tramite,:orientado,:usuario_clave,:funcionario,:n_declaracion,:monto,:n_aprobacion,:telefono,:observaciones,:usuario_id) RETURNING id');
        $st->execute($d); return (int)$st->fetchColumn();
    }
    public static function update(int $id, array $d): void {
        $d['id']=$id;
        $st=db()->prepare('UPDATE atenciones_contribuyentes SET fecha=:fecha,area=:area,contribuyente=:contribuyente,cedula_rif=:cedula_rif,tipo_persona=:tipo_persona,tramite=:tramite,orientado=:orientado,usuario_clave=:usuario_clave,funcionario=:funcionario,n_declaracion=:n_declaracion,monto=:monto,n_aprobacion=:n_aprobacion,telefono=:telefono,observaciones=:observaciones,usuario_id=:usuario_id,updated_at=NOW() WHERE id=:id');
        $st->execute($d);
    }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM atenciones_contribuyentes WHERE id=:id'); $st->execute(['id'=>$id]); }

    public static function forExport(array $f): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(a.contribuyente ILIKE :q OR a.cedula_rif ILIKE :q OR a.funcionario ILIKE :q OR a.tramite ILIKE :q OR a.n_declaracion ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['area'])) { $where[]='a.area=:area'; $params['area']=$f['area']; }
        if (!empty($f['from'])) { $where[]='a.fecha >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='a.fecha <= :to'; $params['to']=$f['to']; }
        $sql='SELECT a.* FROM atenciones_contribuyentes a' . ($where?' WHERE '.implode(' AND ',$where):'') . ' ORDER BY a.fecha DESC, a.id DESC';
        $st=db()->prepare($sql); $st->execute($params); return $st->fetchAll()?:[];
    }

    public static function consolidado(?string $from=null, ?string $to=null): array {
        $where=[]; $params=[];
        if ($from) { $where[]='fecha >= :from'; $params['from']=$from; }
        if ($to) { $where[]='fecha <= :to'; $params['to']=$to; }
        $sql='SELECT area, COUNT(*)::int AS total_atendidos, COALESCE(SUM(monto),0)::numeric(14,2) AS total_monto FROM atenciones_contribuyentes' . ($where?' WHERE '.implode(' AND ',$where):'') . ' GROUP BY area';
        $st=db()->prepare($sql); $st->execute($params);
        $rows=$st->fetchAll()?:[];
        $map=[]; foreach($rows as $r) $map[$r['area']]=$r;
        $out=[]; $grand=0; $grandMonto=0.0;
        foreach(self::areas() as $a){ $t=(int)($map[$a]['total_atendidos']??0); $m=(float)($map[$a]['total_monto']??0); $out[]=['area'=>$a,'total_atendidos'=>$t,'total_monto'=>$m]; $grand+=$t; $grandMonto+=$m; }
        $out[]=['area'=>'TOTAL GENERAL','total_atendidos'=>$grand,'total_monto'=>$grandMonto];
        return $out;
    }
}
