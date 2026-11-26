<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

class ReporteDiario {
    /** @return string[] */
    public static function rifFields(): array {
        return ['rif_insc_pn','rif_act_pn','rif_insc_pj','rif_act_pj','rif_insc_suc','rif_act_suc','rif_insc_emp','rif_act_emp','rif_insc_gob','rif_act_gob','rif_insc_cc','rif_act_cc'];
    }
    /** @return string[] */
    public static function numericFields(): array {
        return array_merge(
            ['vivienda_solicitudes_recibidas','vivienda_registros_entregados','orientaciones','copias_solicitadas','copias_entregadas'],
            self::rifFields(),
            ['asistencia_contribuyentes','sucesiones_atendidos','sucesiones_ingresadas','solvencias_sucesiones_entregadas','multas_notificadas','papel_seguridad_anulado']
        );
    }
    public static function calcRifTotal(array $d): int {
        $sum = 0;
        foreach (self::rifFields() as $f) $sum += max(0, (int)($d[$f] ?? 0));
        return $sum;
    }

    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['from'])) { $where[]='r.fecha >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='r.fecha <= :to'; $params['to']=$f['to']; }
        if (!empty($f['q'])) { $where[]='(u.full_name ILIKE :q OR r.observaciones ILIKE :q OR r.dependencia ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['department'])) { $where[]='r.dependencia = :department'; $params['department']=$f['department']; }
        $sql='FROM reporte_diario r LEFT JOIN users u ON u.id=r.usuario_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT r.*, u.full_name AS usuario_nombre '.$sql.' ORDER BY r.fecha DESC, r.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }

    public static function find(int $id): ?array {
        $st = db()->prepare('SELECT * FROM reporte_diario WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null;
    }

    private static function bindFields(\PDOStatement $st, array $d): void {
        $st->bindValue(':fecha', $d['fecha']);
        $st->bindValue(':usuario_id', $d['usuario_id'], PDO::PARAM_INT);
        $st->bindValue(':dependencia', $d['dependencia']);
        foreach (self::numericFields() as $f) $st->bindValue(':'.$f, max(0,(int)($d[$f]??0)), PDO::PARAM_INT);
        $st->bindValue(':rif_total_atendidos', self::calcRifTotal($d), PDO::PARAM_INT);
        $st->bindValue(':observaciones', $d['observaciones'] !== '' ? $d['observaciones'] : null);
    }

    public static function create(array $d): int {
        $cols = ['fecha','usuario_id','dependencia'];
        $cols = array_merge($cols, self::numericFields(), ['rif_total_atendidos','observaciones']);
        $placeholders = array_map(fn($c)=>':'.$c, $cols);
        $sql = 'INSERT INTO reporte_diario ('.implode(',',$cols).') VALUES ('.implode(',',$placeholders).') RETURNING id';
        $st = db()->prepare($sql);
        self::bindFields($st, $d);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public static function update(int $id, array $d): void {
        $cols = array_merge(['fecha','usuario_id','dependencia'], self::numericFields(), ['rif_total_atendidos','observaciones']);
        $sets = array_map(fn($c)=>$c.'=:'.$c, $cols);
        $sql = 'UPDATE reporte_diario SET '.implode(', ',$sets).', updated_at=NOW() WHERE id=:id';
        $st = db()->prepare($sql);
        self::bindFields($st, $d);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
    }

    public static function delete(int $id): void {
        $st = db()->prepare('DELETE FROM reporte_diario WHERE id=:id'); $st->execute(['id'=>$id]);
    }

    public static function forExport(?string $from=null, ?string $to=null, ?string $department=null): array {
        $where=[]; $params=[];
        if ($from) { $where[]='r.fecha >= :from'; $params['from']=$from; }
        if ($to) { $where[]='r.fecha <= :to'; $params['to']=$to; }
        if ($department) { $where[]='r.dependencia = :department'; $params['department']=$department; }
        $cols = ['r.fecha','r.dependencia','u.full_name AS usuario',
            'r.vivienda_solicitudes_recibidas','r.vivienda_registros_entregados','r.orientaciones','r.copias_solicitadas','r.copias_entregadas',
            'r.rif_total_atendidos','r.rif_insc_pn','r.rif_act_pn','r.rif_insc_pj','r.rif_act_pj','r.rif_insc_suc','r.rif_act_suc','r.rif_insc_emp','r.rif_act_emp','r.rif_insc_gob','r.rif_act_gob','r.rif_insc_cc','r.rif_act_cc',
            'r.asistencia_contribuyentes','r.sucesiones_atendidos','r.sucesiones_ingresadas','r.solvencias_sucesiones_entregadas','r.multas_notificadas','r.papel_seguridad_anulado','r.observaciones'];
        $sql = 'SELECT '.implode(', ',$cols).' FROM reporte_diario r LEFT JOIN users u ON u.id=r.usuario_id';
        if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
        $sql .= ' ORDER BY r.fecha ASC, r.id ASC';
        $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll() ?: [];
    }
}
