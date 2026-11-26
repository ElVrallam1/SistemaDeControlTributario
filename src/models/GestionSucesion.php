<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

class GestionSucesion {
    public static function statuses(): array {
        return ['POR ASIGNAR','EN ANALISIS','SERIES ESPECIALES','TRAMITACIONES','PENDIENTE DE PAGO','ELABORACION DE SOLVENCIA','POR ENTREGAR SOLVENCIA','CON ACTA DE REQUERIMIENTO','SOLVENCIA ENTREGADA','REMITIDA AL ARCHIVO'];
    }
    public static function gerencias(): array {
        return ['LIBERTADOR','CENTRO AUXILIAR','SECTOR LA GUAIRA','CARAYACA','COLONIA TOVAR'];
    }
    public static function dateFields(): array {
        return ['fecha_ingreso_coordinacion','fecha_consigno_recaudo','fecha_asignado_analista','fecha_acta_requerimiento','fecha_consigno_requerimiento','fecha_series_especiales','fecha_tramitaciones','fecha_consigno_pago','fecha_control_calidad','fecha_elaboracion_solvencia','fecha_entrega_solvencia','fecha_auto_cierre_expediente','fecha_enviado_archivo'];
    }

    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(g.numero_expediente ILIKE :q OR g.observacion ILIKE :q OR g.gerencia_regional ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estatus'])) { $where[]='g.estatus=:estatus'; $params['estatus']=$f['estatus']; }
        if (!empty($f['gerencia_regional'])) { $where[]='g.gerencia_regional=:gerencia_regional'; $params['gerencia_regional']=$f['gerencia_regional']; }
        if (!empty($f['from'])) { $where[]='g.fecha_ingreso_coordinacion >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='g.fecha_ingreso_coordinacion <= :to'; $params['to']=$f['to']; }
        $sql='FROM gestion_sucesiones g LEFT JOIN users u ON u.id=g.usuario_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT g.*, u.full_name AS usuario_nombre '.$sql.' ORDER BY g.fecha_ingreso_coordinacion DESC NULLS LAST, g.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }

    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM gestion_sucesiones WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }

    public static function create(array $d): int {
        $cols = array_merge(['gerencia_regional','numero_expediente','estatus','auto_reapertura','observacion','usuario_id'], self::dateFields());
        $sql = 'INSERT INTO gestion_sucesiones ('.implode(',',$cols).') VALUES ('.implode(',',array_map(fn($c)=>':'.$c,$cols)).') RETURNING id';
        $st=db()->prepare($sql); foreach($cols as $c) $st->bindValue(':'.$c,$d[$c] ?? null); $st->execute(); return (int)$st->fetchColumn();
    }
    public static function update(int $id, array $d): void {
        $cols = array_merge(['gerencia_regional','numero_expediente','estatus','auto_reapertura','observacion','usuario_id'], self::dateFields());
        $sql = 'UPDATE gestion_sucesiones SET '.implode(', ',array_map(fn($c)=>"$c=:$c",$cols)).', updated_at=NOW() WHERE id=:id';
        $st=db()->prepare($sql); foreach($cols as $c) $st->bindValue(':'.$c,$d[$c] ?? null); $st->bindValue(':id',$id,PDO::PARAM_INT); $st->execute();
    }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM gestion_sucesiones WHERE id=:id'); $st->execute(['id'=>$id]); }

    public static function forExport(array $f): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(g.numero_expediente ILIKE :q OR g.observacion ILIKE :q OR g.gerencia_regional ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estatus'])) { $where[]='g.estatus=:estatus'; $params['estatus']=$f['estatus']; }
        if (!empty($f['gerencia_regional'])) { $where[]='g.gerencia_regional=:gerencia_regional'; $params['gerencia_regional']=$f['gerencia_regional']; }
        if (!empty($f['from'])) { $where[]='g.fecha_ingreso_coordinacion >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='g.fecha_ingreso_coordinacion <= :to'; $params['to']=$f['to']; }
        $sql='SELECT g.* FROM gestion_sucesiones g' . ($where?' WHERE '.implode(' AND ',$where):'') . ' ORDER BY g.fecha_ingreso_coordinacion DESC NULLS LAST, g.id DESC';
        $st=db()->prepare($sql); $st->execute($params); return $st->fetchAll()?:[];
    }

    public static function resumenMensual(string $from, string $to): array {
        $statuses = self::statuses();
        $gerencias = self::gerencias();
        $sql = "SELECT estatus, gerencia_regional, COUNT(*)::int AS total FROM gestion_sucesiones WHERE fecha_ingreso_coordinacion >= :from AND fecha_ingreso_coordinacion <= :to GROUP BY estatus, gerencia_regional";
        $st=db()->prepare($sql); $st->execute(['from'=>$from,'to'=>$to]);
        $map = [];
        foreach ($st->fetchAll()?:[] as $r) { $map[$r['estatus']][$r['gerencia_regional']] = (int)$r['total']; }
        $rows = [];
        foreach ($statuses as $s) {
            $row = ['estatus'=>$s]; $sum=0;
            foreach ($gerencias as $g) { $v = (int)($map[$s][$g] ?? 0); $row[$g]=$v; $sum+=$v; }
            $row['TOTAL']=$sum; $rows[]=$row;
        }
        $totals = ['estatus'=>'TOTAL']; $grand=0;
        foreach ($gerencias as $g) { $t=0; foreach($rows as $r) $t += (int)$r[$g]; $totals[$g]=$t; $grand+=$t; }
        $totals['TOTAL']=$grand; $rows[]=$totals;
        return $rows;
    }
}
