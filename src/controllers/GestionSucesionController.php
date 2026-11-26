<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\GestionSucesion;

class GestionSucesionController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    private function normDate(string $v): ?string { $v=sanitizeText($v,20); return ($v!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) ? $v : null; }

    public function index(): void {
        $user = requireAuth();
        $filters = ['q'=>sanitizeText($_GET['q']??'',120),'estatus'=>sanitizeText($_GET['estatus']??'',60),'gerencia_regional'=>sanitizeText($_GET['gerencia_regional']??'',120),'from'=>sanitizeText($_GET['from']??'',20),'to'=>sanitizeText($_GET['to']??'',20)];
        foreach(['from','to'] as $k) if($filters[$k]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$k])) $filters[$k]='';
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res = GestionSucesion::search($filters,$limit,$offset);
        view('gestion_sucesiones/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,
            'totalPages'=>max(1,(int)ceil($res['total']/$limit)),'filters'=>$filters,
            'statuses'=>GestionSucesion::statuses(),'gerencias'=>GestionSucesion::gerencias(),
            'pageTitle'=>'Gestión Sucesiones','pageSubtitle'=>'Seguimiento gerencial de expedientes de sucesiones (Formato Queli)','activeMenu'=>'gestion_sucesiones'
        ]);
    }

    public function form(?int $id=null): void {
        $user = requireAuth();
        $item = $id ? GestionSucesion::find($id) : null;
        if ($id && !$item) { http_response_code(404); exit('No encontrado.'); }
        $errors = [];
        $old = $item ?: ['gerencia_regional'=>'CENTRO AUXILIAR','numero_expediente'=>'','fecha_ingreso_coordinacion'=>'','estatus'=>'POR ASIGNAR','auto_reapertura'=>'NO','observacion'=>'','usuario_id'=>$user['id']];
        foreach (GestionSucesion::dateFields() as $f) if (!array_key_exists($f,$old)) $old[$f]='';
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['gerencia_regional']=sanitizeText($_POST['gerencia_regional']??'CENTRO AUXILIAR',120);
                $old['numero_expediente']=sanitizeText($_POST['numero_expediente']??'',80);
                $old['estatus']=sanitizeText($_POST['estatus']??'POR ASIGNAR',60);
                $old['auto_reapertura']=strtoupper(sanitizeText($_POST['auto_reapertura']??'NO',5))==='SI'?'SI':'NO';
                $old['observacion']=sanitizeText($_POST['observacion']??'',2000);
                $old['usuario_id']=(int)$user['id'];
                foreach (GestionSucesion::dateFields() as $f) $old[$f] = $this->normDate((string)($_POST[$f]??''));
                if ($old['numero_expediente']==='') $errors[]='N° de expediente obligatorio.';
                if ($old['gerencia_regional']==='') $errors[]='Gerencia/dependencia obligatoria.';
                if ($old['estatus']==='') $errors[]='Estatus obligatorio.';
                if (!$errors) {
                    try {
                        if ($id) { GestionSucesion::update($id,$old); auditLog((int)$user['id'],'gestion_sucesion.update','info','gestion_sucesion',(string)$id); }
                        else { $nid=GestionSucesion::create($old); auditLog((int)$user['id'],'gestion_sucesion.create','info','gestion_sucesion',(string)$nid); }
                        redirect('gestion-sucesiones.php?ok=1');
                    } catch (\Throwable $e) { error_log($e->getMessage()); $errors[]='No se pudo guardar. Verifica que el expediente no esté duplicado.'; }
                }
            }
        }
        view('gestion_sucesiones/form', [
            'app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,
            'statuses'=>GestionSucesion::statuses(),'gerencias'=>GestionSucesion::gerencias(),
            'pageTitle'=>$id?'Editar gestión de sucesión':'Nueva gestión de sucesión','pageSubtitle'=>'Detalle operativo formato Queli','activeMenu'=>'gestion_sucesiones'
        ]);
    }

    public function delete(): void {
        $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $id=(int)($_POST['id']??0); if($id>0){GestionSucesion::delete($id); auditLog((int)$user['id'],'gestion_sucesion.delete','warning','gestion_sucesion',(string)$id);} redirect('gestion-sucesiones.php?deleted=1');
    }

    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'estatus'=>sanitizeText($_POST['estatus']??'',60),'gerencia_regional'=>sanitizeText($_POST['gerencia_regional']??'',120),'from'=>sanitizeText($_POST['from']??'',20),'to'=>sanitizeText($_POST['to']??'',20)];
        foreach(['from','to'] as $k) if($filters[$k]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$k])) $filters[$k]='';
        $rows=GestionSucesion::forExport($filters);
        $headers=['N° EXP','GERENCIA REGIONAL','FECHA INGRESO COORDINACIÓN','ESTATUS','AUTO REAPERTURA','CONSIGNÓ RECAUDO','ASIGNADO ANALISTA','ACTA REQUERIMIENTO','CONSIGNÓ REQUERIMIENTO','SERIES ESPECIALES','TRAMITACIONES','CONSIGNÓ PAGO','CONTROL CALIDAD','ELABORACIÓN SOLVENCIA','ENTREGA SOLVENCIA','AUTO CIERRE EXPEDIENTE','ENVIADO ARCHIVO','OBSERVACIÓN'];
        $df=GestionSucesion::dateFields(); $data=[];
        foreach($rows as $r){
            $line=[(string)$r['numero_expediente'],(string)$r['gerencia_regional']];
            foreach($df as $f){ if($f==='fecha_ingreso_coordinacion'){ $line[]=$r[$f]?date('d/m/Y',strtotime((string)$r[$f])):''; } }
            $line[]=(string)$r['estatus']; $line[]=(string)$r['auto_reapertura'];
            foreach($df as $f){ if($f!=='fecha_ingreso_coordinacion'){ $line[]=$r[$f]?date('d/m/Y',strtotime((string)$r[$f])):''; } }
            $line[]=(string)($r['observacion']??'');
            $data[]=$line;
        }
        require_once BASE_PATH.'/src/helpers/excel.php';
        auditLog((int)$user['id'],'gestion_sucesion.export','info','gestion_sucesion',null,['rows'=>count($data)]);
        exportRowsToXlsx('gestion_sucesiones_'.date('Ymd_His').'.xlsx',$headers,$data);
    }

    public function exportResumen(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $from=sanitizeText($_POST['from']??date('Y-m-01'),20); $to=sanitizeText($_POST['to']??date('Y-m-t'),20);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to=date('Y-m-t');
        $rows=GestionSucesion::resumenMensual($from,$to);
        $gerencias=GestionSucesion::gerencias();
        $headers=array_merge(['ESTATUS'],$gerencias,['TOTAL']);
        $data=[]; foreach($rows as $r){ $line=[(string)$r['estatus']]; foreach($gerencias as $g) $line[]=(string)$r[$g]; $line[]=(string)$r['TOTAL']; $data[]=$line; }
        require_once BASE_PATH.'/src/helpers/excel.php';
        auditLog((int)$user['id'],'gestion_sucesion.export_resumen','info','gestion_sucesion',null,['from'=>$from,'to'=>$to]);
        exportRowsToXlsx('resumen_sucesiones_queli_'.date('Ymd_His').'.xlsx',$headers,$data);
    }

    public function template(): void {
        requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php';
        exportRowsToXlsx('plantilla_gestion_sucesiones.xlsx',['NUMERO_EXPEDIENTE','GERENCIA_REGIONAL','FECHA_INGRESO_COORDINACION','ESTATUS','AUTO_REAPERTURA','FECHA_CONSIGNO_RECAUDO','FECHA_ASIGNADO_ANALISTA','FECHA_ACTA_REQUERIMIENTO','FECHA_CONSIGNO_REQUERIMIENTO','FECHA_SERIES_ESPECIALES','FECHA_TRAMITACIONES','FECHA_CONSIGNO_PAGO','FECHA_CONTROL_CALIDAD','FECHA_ELABORACION_SOLVENCIA','FECHA_ENTREGA_SOLVENCIA','FECHA_AUTO_CIERRE_EXPEDIENTE','FECHA_ENVIADO_ARCHIVO','OBSERVACION'],[['81260723','CENTRO AUXILIAR','2026-10-01','POR ASIGNAR','NO','','','','','','','','','','','','','']]);
    }

    public function import(): void {
        $user=requireAnyRole(['admin','supervisor']); $errors=[]; $imported=0; $skipped=0; $details=[];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            elseif(empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) $errors[]='Selecciona un archivo CSV válido.';
            else {
                $ext=strtolower(pathinfo((string)$_FILES['csv_file']['name'],PATHINFO_EXTENSION));
                $allowed=['csv','txt']; if(class_exists('ZipArchive')) $allowed[]='xlsx';
                if(!in_array($ext,$allowed,true)) $errors[]='Formato no soportado. Usa CSV' . (class_exists('ZipArchive') ? ' o XLSX' : '') . '.';
                elseif(($_FILES['csv_file']['size']??0)>5*1024*1024) $errors[]='Archivo excede 5MB.';
                else {
                    require_once BASE_PATH.'/src/helpers/import.php';
                    $parsed=importReadFile((string)$_FILES['csv_file']['tmp_name']);
                    if(!$parsed['ok']) $errors[]=$parsed['error'];
                    else foreach($parsed['rows'] as $row){
                        $d=[
                            'gerencia_regional'=>sanitizeText(importVal($row,['gerencia_regional','gerencia','dependencia'],'CENTRO AUXILIAR'),120),
                            'numero_expediente'=>sanitizeText(importVal($row,['numero_expediente','n_expediente','nro_expediente','expediente']),80),
                            'estatus'=>sanitizeText(importVal($row,['estatus','estado'],'POR ASIGNAR'),60),
                            'auto_reapertura'=>strtoupper(sanitizeText(importVal($row,['auto_reapertura'],'NO'),5))==='SI'?'SI':'NO',
                            'observacion'=>sanitizeText(importVal($row,['observacion','observaciones'],'[Importado]'),2000),
                            'usuario_id'=>(int)$user['id'],
                        ];
                        foreach(GestionSucesion::dateFields() as $f) $d[$f]=importDate($row,[$f]);
                        if($d['numero_expediente']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': expediente vacío.'; continue; }
                        try{ GestionSucesion::create($d); $imported++; }
                        catch(\Throwable $e){ try{ $st=db()->prepare('SELECT id FROM gestion_sucesiones WHERE numero_expediente=:n LIMIT 1'); $st->execute(['n'=>$d['numero_expediente']]); $ex=$st->fetchColumn(); if($ex){ GestionSucesion::update((int)$ex,$d); $imported++; } else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } } catch(\Throwable $e2){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'gestion_sucesion.import','warning','gestion_sucesion',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('gestion_sucesiones/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar gestión sucesiones','pageSubtitle'=>'Carga histórica desde CSV','activeMenu'=>'gestion_sucesiones']);
    }
}
