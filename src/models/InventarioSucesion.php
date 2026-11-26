<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

class InventarioSucesion {
    public static function statuses(): array {
        return [
            'EN REVISIÓN','FALTA DE DOCUMENTACIÓN','REQUERIMIENTOS CONSIGNADOS','POR NOTIFICAR','NOTIFICADO',
            'POR LIQUIDAR','SANCIÓN','POR ENTREGAR SOLVENCIA','INCIDENCIA EN EL SISTEMA',
            'DECLARACIÓN SUSTITUTIVA','TRÁMITE LEGAL','ARCHIVO DEL EXPEDIENTE','MULTA','MULTA NOTIFICADA'
        ];
    }
    public static function tipos(): array { return ['SUCESION','DONACION']; }

    public static function search(array $f, int $limit=20, int $offset=0): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(i.numero_expediente ILIKE :q OR i.razon_social ILIKE :q OR i.rif ILIKE :q OR i.observacion ILIKE :q OR i.numero_planilla_liquidacion ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estatus'])) { $where[]='i.estatus=:estatus'; $params['estatus']=$f['estatus']; }
        if (!empty($f['tipo_caso'])) { $where[]='i.tipo_caso=:tipo_caso'; $params['tipo_caso']=$f['tipo_caso']; }
        if (!empty($f['from'])) { $where[]='i.fecha_solicitud >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='i.fecha_solicitud <= :to'; $params['to']=$f['to']; }
        $sql='FROM inventario_sucesiones i LEFT JOIN users u ON u.id=i.usuario_id' . ($where?' WHERE '.implode(' AND ',$where):'');
        $st=db()->prepare('SELECT COUNT(*) '.$sql); $st->execute($params); $total=(int)$st->fetchColumn();
        $st=db()->prepare('SELECT i.*, u.full_name AS usuario_nombre '.$sql.' ORDER BY i.fecha_solicitud DESC, i.id DESC LIMIT :lim OFFSET :off');
        foreach($params as $k=>$v) $st->bindValue(':'.$k,$v);
        $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute();
        return ['items'=>$st->fetchAll()?:[], 'total'=>$total];
    }

    public static function find(int $id): ?array { $st=db()->prepare('SELECT * FROM inventario_sucesiones WHERE id=:id'); $st->execute(['id'=>$id]); $r=$st->fetch(); return $r?:null; }

    public static function create(array $d): int {
        $st=db()->prepare('INSERT INTO inventario_sucesiones (numero_expediente,razon_social,rif,unidad_adscripcion,tipo_caso,fecha_solicitud,estatus,observacion,fecha_acta_requerimiento,fecha_notificacion_acto,numero_planilla_liquidacion,importe,usuario_id) VALUES (:numero_expediente,:razon_social,:rif,:unidad_adscripcion,:tipo_caso,:fecha_solicitud,:estatus,:observacion,:fecha_acta_requerimiento,:fecha_notificacion_acto,:numero_planilla_liquidacion,:importe,:usuario_id) RETURNING id');
        $st->execute($d); return (int)$st->fetchColumn();
    }
    public static function update(int $id, array $d): void {
        $d['id']=$id;
        $st=db()->prepare('UPDATE inventario_sucesiones SET numero_expediente=:numero_expediente,razon_social=:razon_social,rif=:rif,unidad_adscripcion=:unidad_adscripcion,tipo_caso=:tipo_caso,fecha_solicitud=:fecha_solicitud,estatus=:estatus,observacion=:observacion,fecha_acta_requerimiento=:fecha_acta_requerimiento,fecha_notificacion_acto=:fecha_notificacion_acto,numero_planilla_liquidacion=:numero_planilla_liquidacion,importe=:importe,usuario_id=:usuario_id,updated_at=NOW() WHERE id=:id');
        $st->execute($d);
    }
    public static function delete(int $id): void { $st=db()->prepare('DELETE FROM inventario_sucesiones WHERE id=:id'); $st->execute(['id'=>$id]); }

    public static function forExport(array $f): array {
        $where=[]; $params=[];
        if (!empty($f['q'])) { $where[]='(i.numero_expediente ILIKE :q OR i.razon_social ILIKE :q OR i.rif ILIKE :q OR i.observacion ILIKE :q OR i.numero_planilla_liquidacion ILIKE :q)'; $params['q']='%'.$f['q'].'%'; }
        if (!empty($f['estatus'])) { $where[]='i.estatus=:estatus'; $params['estatus']=$f['estatus']; }
        if (!empty($f['tipo_caso'])) { $where[]='i.tipo_caso=:tipo_caso'; $params['tipo_caso']=$f['tipo_caso']; }
        if (!empty($f['from'])) { $where[]='i.fecha_solicitud >= :from'; $params['from']=$f['from']; }
        if (!empty($f['to'])) { $where[]='i.fecha_solicitud <= :to'; $params['to']=$f['to']; }
        $sql='SELECT i.numero_expediente,i.razon_social,i.rif,i.unidad_adscripcion,i.tipo_caso,i.fecha_solicitud,i.estatus,i.observacion,i.fecha_acta_requerimiento,i.fecha_notificacion_acto,i.numero_planilla_liquidacion,i.importe FROM inventario_sucesiones i' . ($where?' WHERE '.implode(' AND ',$where):'') . ' ORDER BY i.fecha_solicitud DESC, i.id DESC';
        $st=db()->prepare($sql); $st->execute($params); return $st->fetchAll()?:[];
    }
}
