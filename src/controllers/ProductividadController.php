<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Productividad;
use App\Models\Expediente;
class ProductividadController {
    private array $app; public function __construct(array $app){$this->app=$app;}
    public function index(): void {
        $user=requireAuth();
        $filters=['q'=>sanitizeText($_GET['q']??'',120),'from'=>sanitizeText($_GET['from']??'',20),'to'=>sanitizeText($_GET['to']??'',20)];
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res=Productividad::search($filters,$limit,$offset); $totalPages=max(1,(int)ceil($res['total']/$limit));
        view('productividad/index',['app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'pageTitle'=>'Productividad','pageSubtitle'=>'Productividad de expedientes por usuario y fecha','activeMenu'=>'productividad']);
    }
    public function form(?int $id=null): void {
        $user=requireAuth(); $item=$id?Productividad::find($id):null; if($id&&!$item){http_response_code(404);exit('No encontrado.');}
        $errors=[]; $old=$item ?: ['expediente_id'=>'','usuario_id'=>$user['id'],'fecha'=>date('Y-m-d'),'expedientes_procesados'=>0,'expedientes_resueltos'=>0,'expedientes_pendientes'=>0,'observaciones'=>''];
        $expedientes = Expediente::all();
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['expediente_id'] = ($_POST['expediente_id'] ?? '') !== '' ? (int)$_POST['expediente_id'] : null;
                $old['usuario_id'] = (int)$user['id'];
                $old['fecha'] = sanitizeText($_POST['fecha'] ?? '', 20);
                $old['expedientes_procesados'] = max(0, (int)($_POST['expedientes_procesados'] ?? 0));
                $old['expedientes_resueltos'] = max(0, (int)($_POST['expedientes_resueltos'] ?? 0));
                $old['expedientes_pendientes'] = max(0, (int)($_POST['expedientes_pendientes'] ?? 0));
                $old['observaciones'] = sanitizeText($_POST['observaciones'] ?? '', 2000);
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$old['fecha'])) $errors[]='Fecha inválida.';
                if(!$errors){ try{ if($id){Productividad::update($id,$old); auditLog((int)$user['id'],'productividad.update','info','productividad',(string)$id);} else {$nid=Productividad::create($old); auditLog((int)$user['id'],'productividad.create','info','productividad',(string)$nid);} redirect('productividad.php?ok=1'); }catch(\Throwable $e){error_log($e->getMessage()); $errors[]='No se pudo guardar.';} }
            }
        }
        view('productividad/form',['app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'expedientes'=>$expedientes,'pageTitle'=>$id?'Editar productividad':'Nueva productividad','pageSubtitle'=>'Registro de productividad','activeMenu'=>'productividad']);
    }
    public function delete(): void { $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');} $id=(int)($_POST['id']??0); if($id>0){Productividad::delete($id); auditLog((int)$user['id'],'productividad.delete','warning','productividad',(string)$id);} redirect('productividad.php?deleted=1'); }
    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'from'=>sanitizeText($_POST['from']??'',20),'to'=>sanitizeText($_POST['to']??'',20)];
        $rows=Productividad::search($filters, 5000, 0)['items'];
        $data=[]; foreach($rows as $r){ $data[]=[(string)$r['fecha'],(string)($r['usuario_nombre']??''),(string)($r['numero_expediente']??'General'),(string)$r['expedientes_procesados'],(string)$r['expedientes_resueltos'],(string)$r['expedientes_pendientes'],(string)($r['observaciones']??'')]; }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'productividad.export','info','productividad',null,['rows'=>count($data)]);
        exportRowsToXlsx('productividad_'.date('Ymd_His').'.xlsx',['Fecha','Usuario','Expediente','Procesados','Resueltos','Pendientes','Observaciones'],$data);
    }

    public function template(): void { requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php'; exportRowsToXlsx('plantilla_productividad.xlsx',['FECHA','NUMERO_EXPEDIENTE','EXPEDIENTES_PROCESADOS','EXPEDIENTES_RESUELTOS','EXPEDIENTES_PENDIENTES','OBSERVACIONES'],[['2026-01-01','','0','0','0','']]); }
    public function import(): void {
        $user=requireAnyRole(['admin','supervisor']); $errors=[]; $imported=0; $skipped=0; $details=[];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            elseif(empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) $errors[]='Selecciona un archivo CSV/XLSX válido.';
            else {
                $ext=strtolower(pathinfo((string)$_FILES['csv_file']['name'],PATHINFO_EXTENSION)); $allowed=['csv','txt']; if(class_exists('ZipArchive')) $allowed[]='xlsx';
                if(!in_array($ext,$allowed,true)) $errors[]='Formato no soportado. Usa CSV' . (class_exists('ZipArchive')?' o XLSX':'') . '.';
                elseif(($_FILES['csv_file']['size']??0)>5*1024*1024) $errors[]='Archivo excede 5MB.';
                else { require_once BASE_PATH.'/src/helpers/import.php'; $parsed=importReadFile((string)$_FILES['csv_file']['tmp_name']);
                    if(!$parsed['ok']) $errors[]=$parsed['error'];
                    else foreach($parsed['rows'] as $row){
                        $numExp = sanitizeText(importVal($row,['numero_expediente','expediente','n_expediente']),80);
                        $expId = null;
                        if ($numExp !== '') { $st=db()->prepare('SELECT id FROM expedientes WHERE numero_expediente=:n LIMIT 1'); $st->execute(['n'=>$numExp]); $ex=$st->fetchColumn(); if($ex) $expId=(int)$ex; }
                        $d=['expediente_id'=>$expId,'usuario_id'=>(int)$user['id'],'fecha'=>importDate($row,['fecha']) ?? date('Y-m-d'),'expedientes_procesados'=>importInt($row,['expedientes_procesados','procesados']),'expedientes_resueltos'=>importInt($row,['expedientes_resueltos','resueltos']),'expedientes_pendientes'=>importInt($row,['expedientes_pendientes','pendientes']),'observaciones'=>sanitizeText(importVal($row,['observaciones','observacion'],'[Importado]'),2000)];
                        if(!$d['fecha']) { $skipped++; $details[]='Fila '.$row['_row_num'].': fecha inválida.'; continue; }
                        try{ Productividad::create($d); $imported++; } catch(\Throwable $e){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'productividad.import','warning','productividad',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('productividad/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar productividad','pageSubtitle'=>'Carga histórica desde CSV/XLSX','activeMenu'=>'productividad']);
    }
}
