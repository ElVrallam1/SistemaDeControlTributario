<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\AtencionContribuyente;

class AtencionContribuyenteController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    public function index(): void {
        $user = requireAuth();
        $filters = [
            'q'=>sanitizeText($_GET['q']??'',120),'area'=>sanitizeText($_GET['area']??'',40),
            'from'=>sanitizeText($_GET['from']??'',20),'to'=>sanitizeText($_GET['to']??'',20)
        ];
        foreach(['from','to'] as $k) if($filters[$k]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$k])) $filters[$k]='';
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res=AtencionContribuyente::search($filters,$limit,$offset);
        $consolidado = AtencionContribuyente::consolidado($filters['from']?:null, $filters['to']?:null);
        view('atenciones/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,
            'totalPages'=>max(1,(int)ceil($res['total']/$limit)),'filters'=>$filters,'areas'=>AtencionContribuyente::areas(),
            'consolidado'=>$consolidado,
            'pageTitle'=>'Atenciones al Contribuyente','pageSubtitle'=>'Registro detallado y consolidación automática por área','activeMenu'=>'atenciones'
        ]);
    }

    public function form(?int $id=null): void {
        $user=requireAuth();
        $item=$id?AtencionContribuyente::find($id):null; if($id&&!$item){http_response_code(404);exit('No encontrado.');}
        $errors=[]; $old=$item ?: ['fecha'=>date('Y-m-d'),'area'=>'ASISTENCIA','contribuyente'=>'','cedula_rif'=>'','tipo_persona'=>'NATURAL','tramite'=>'','orientado'=>0,'usuario_clave'=>0,'funcionario'=>'','n_declaracion'=>'','monto'=>'0','n_aprobacion'=>'','telefono'=>'','observaciones'=>'','usuario_id'=>$user['id']];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['fecha']=sanitizeText($_POST['fecha']??'',20);
                $old['area']=strtoupper(sanitizeText($_POST['area']??'ASISTENCIA',40));
                $old['contribuyente']=sanitizeText($_POST['contribuyente']??'',220);
                $old['cedula_rif']=sanitizeText($_POST['cedula_rif']??'',40);
                $old['tipo_persona']=sanitizeText($_POST['tipo_persona']??'',20);
                $old['tramite']=sanitizeText($_POST['tramite']??'',160);
                $old['orientado']=isset($_POST['orientado'])?1:0;
                $old['usuario_clave']=isset($_POST['usuario_clave'])?1:0;
                $old['funcionario']=sanitizeText($_POST['funcionario']??'',150);
                $old['n_declaracion']=sanitizeText($_POST['n_declaracion']??'',80);
                $old['monto']=(float)($_POST['monto']??0);
                $old['n_aprobacion']=sanitizeText($_POST['n_aprobacion']??'',80);
                $old['telefono']=sanitizeText($_POST['telefono']??'',60);
                $old['observaciones']=sanitizeText($_POST['observaciones']??'',2000);
                $old['usuario_id']=(int)$user['id'];
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$old['fecha'])) $errors[]='Fecha inválida.';
                if(!in_array($old['area'],AtencionContribuyente::areas(),true)) $errors[]='Área inválida.';
                if($old['contribuyente']==='') $errors[]='Contribuyente obligatorio.';
                if($old['monto']<0) $errors[]='Monto inválido.';
                if(!$errors){
                    try{
                        if($id){AtencionContribuyente::update($id,$old); auditLog((int)$user['id'],'atencion.update','info','atencion',(string)$id);}
                        else{$nid=AtencionContribuyente::create($old); auditLog((int)$user['id'],'atencion.create','info','atencion',(string)$nid);}
                        redirect('atenciones.php?ok=1');
                    }catch(\Throwable $e){error_log($e->getMessage()); $errors[]='No se pudo guardar el registro.';}
                }
            }
        }
        view('atenciones/form',['app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'areas'=>AtencionContribuyente::areas(),'pageTitle'=>$id?'Editar atención':'Nueva atención','pageSubtitle'=>'Registro de atención al contribuyente','activeMenu'=>'atenciones']);
    }

    public function delete(): void {
        $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $id=(int)($_POST['id']??0); if($id>0){AtencionContribuyente::delete($id); auditLog((int)$user['id'],'atencion.delete','warning','atencion',(string)$id);} redirect('atenciones.php?deleted=1');
    }

    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'area'=>sanitizeText($_POST['area']??'',40),'from'=>sanitizeText($_POST['from']??'',20),'to'=>sanitizeText($_POST['to']??'',20)];
        foreach(['from','to'] as $k) if($filters[$k]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$k])) $filters[$k]='';
        $rows=AtencionContribuyente::forExport($filters);
        $headers=['FECHA','ÁREA','CONTRIBUYENTE','CÉDULA/RIF','TIPO PERSONA','TRÁMITE','ORIENTADO','USUARIO Y CLAVE','FUNCIONARIO','N° DECLARACIÓN','MONTO','N° APROBACIÓN','TELÉFONO','OBSERVACIONES'];
        $data=[]; foreach($rows as $r){ $data[]=[date('d/m/Y',strtotime((string)$r['fecha'])),(string)$r['area'],(string)$r['contribuyente'],(string)($r['cedula_rif']??''),(string)($r['tipo_persona']??''),(string)($r['tramite']??''),$r['orientado']?'SI':'NO',$r['usuario_clave']?'SI':'NO',(string)($r['funcionario']??''),(string)($r['n_declaracion']??''),number_format((float)$r['monto'],2,'.',''),(string)($r['n_aprobacion']??''),(string)($r['telefono']??''),(string)($r['observaciones']??'')]; }
        require_once BASE_PATH.'/src/helpers/excel.php';
        auditLog((int)$user['id'],'atencion.export','info','atencion',null,['rows'=>count($data)]);
        exportRowsToXlsx('atenciones_'.date('Ymd_His').'.xlsx',$headers,$data);
    }

    public function exportConsolidado(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $from=sanitizeText($_POST['from']??'',20); $to=sanitizeText($_POST['to']??'',20);
        if($from!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from='';
        if($to!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to='';
        $rows=AtencionContribuyente::consolidado($from?:null,$to?:null);
        $headers=['ÁREA','CONTRIBUYENTES ATENDIDOS','MONTO TOTAL'];
        $data=[]; foreach($rows as $r){ $data[]=[(string)$r['area'],(string)$r['total_atendidos'],number_format((float)$r['total_monto'],2,'.','')]; }
        require_once BASE_PATH.'/src/helpers/excel.php';
        auditLog((int)$user['id'],'atencion.export_consolidado','info','atencion',null,['from'=>$from,'to'=>$to]);
        exportRowsToXlsx('consolidado_atenciones_'.date('Ymd_His').'.xlsx',$headers,$data);
    }

    public function template(): void {
        requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php';
        exportRowsToXlsx('plantilla_atenciones.xlsx',['FECHA','AREA','CONTRIBUYENTE','CEDULA_RIF','TIPO_PERSONA','TRAMITE','ORIENTADO','USUARIO_CLAVE','FUNCIONARIO','N_DECLARACION','MONTO','N_APROBACION','TELEFONO','OBSERVACIONES'],[['2026-11-11','ASISTENCIA','NOMBRE EJEMPLO','V00000000','NATURAL','ORIENTACION','SI','NO','FUNCIONARIO','','0','','','']]);
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
                        $area=strtoupper(sanitizeText(importVal($row,['area','tipo','seccion'],'ASISTENCIA'),40));
                        if(!in_array($area,AtencionContribuyente::areas(),true)) $area='ASISTENCIA';
                        $orientado=strtoupper(importVal($row,['orientado'],'NO')); $uc=strtoupper(importVal($row,['usuario_clave','usuario_y_clave'],'NO'));
                        $d=[
                            'fecha'=>importDate($row,['fecha']) ?? date('Y-m-d'),
                            'area'=>$area,
                            'contribuyente'=>sanitizeText(importVal($row,['contribuyente','nombre','nombre_y_apellido','razon_social']),220),
                            'cedula_rif'=>sanitizeText(importVal($row,['cedula_rif','cedula','rif','cedula_rif']),40),
                            'tipo_persona'=>sanitizeText(importVal($row,['tipo_persona','tipo_contribuyente'],'NATURAL'),20),
                            'tramite'=>sanitizeText(importVal($row,['tramite']),160),
                            'orientado'=>(in_array($orientado,['SI','X','1','YES','TRUE'],true)?1:0),
                            'usuario_clave'=>(in_array($uc,['SI','X','1','YES','TRUE'],true)?1:0),
                            'funcionario'=>sanitizeText(importVal($row,['funcionario']),150),
                            'n_declaracion'=>sanitizeText(importVal($row,['n_declaracion','nro_declaracion','numero_declaracion']),80),
                            'monto'=>importFloat($row,['monto','importe','total_recaudados']),
                            'n_aprobacion'=>sanitizeText(importVal($row,['n_aprobacion','nro_aprobacion','numero_aprobacion']),80),
                            'telefono'=>sanitizeText(importVal($row,['telefono','n_telf_del_pagador','telf']),60),
                            'observaciones'=>sanitizeText(importVal($row,['observaciones','observacion'],'[Importado]'),2000),
                            'usuario_id'=>(int)$user['id'],
                        ];
                        if($d['contribuyente']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': contribuyente vacío.'; continue; }
                        try{ AtencionContribuyente::create($d); $imported++; }
                        catch(\Throwable $e){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'atencion.import','warning','atencion',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('atenciones/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar atenciones','pageSubtitle'=>'Carga histórica desde CSV','activeMenu'=>'atenciones']);
    }
}
