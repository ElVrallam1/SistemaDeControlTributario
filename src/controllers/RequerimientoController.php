<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Requerimiento;
class RequerimientoController {
    private array $app; public function __construct(array $app){$this->app=$app;}
    private function statuses(): array { return ['pendiente','enviado','atendido','vencido','cancelado']; }
    public function index(): void {
        $user=requireAuth();
        $filters=['q'=>sanitizeText($_GET['q']??'',120),'estado'=>sanitizeText($_GET['estado']??'',40)];
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res=Requerimiento::search($filters,$limit,$offset); $totalPages=max(1,(int)ceil($res['total']/$limit));
        view('requerimientos/index',['app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'statuses'=>$this->statuses(),'pageTitle'=>'Requerimientos','pageSubtitle'=>'Seguimiento de requerimientos emitidos y atendidos','activeMenu'=>'requerimientos']);
    }
    public function form(?int $id=null): void {
        $user=requireAuth(); $item=$id?Requerimiento::find($id):null; if($id&&!$item){http_response_code(404);exit('No encontrado.');}
        $errors=[]; $old=$item ?: ['numero_requerimiento'=>'','contribuyente'=>'','rif'=>'','tipo'=>'','estado'=>'pendiente','fecha_emision'=>date('Y-m-d'),'fecha_limite'=>'','fecha_respuesta'=>'','responsable_id'=>$user['id'],'descripcion'=>''];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['numero_requerimiento']=sanitizeText($_POST['numero_requerimiento']??'',80);
                $old['contribuyente']=sanitizeText($_POST['contribuyente']??'',200);
                $old['rif']=sanitizeText($_POST['rif']??'',30);
                $old['tipo']=sanitizeText($_POST['tipo']??'',120);
                $old['estado']=sanitizeText($_POST['estado']??'pendiente',40);
                $old['fecha_emision']=sanitizeText($_POST['fecha_emision']??'',20);
                $old['fecha_limite']=sanitizeText($_POST['fecha_limite']??'',20);
                $old['fecha_respuesta']=sanitizeText($_POST['fecha_respuesta']??'',20);
                $old['descripcion']=sanitizeText($_POST['descripcion']??'',2000);
                $old['responsable_id']=(int)$user['id'];
                if($old['numero_requerimiento']==='') $errors[]='Número de requerimiento obligatorio.';
                if($old['contribuyente']==='') $errors[]='Contribuyente obligatorio.';
                if($old['tipo']==='') $errors[]='Tipo obligatorio.';
                if(!in_array($old['estado'],$this->statuses(),true)) $errors[]='Estado inválido.';
                foreach(['fecha_emision','fecha_limite','fecha_respuesta'] as $f){ if($old[$f]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$old[$f])) $errors[]="Fecha inválida: $f"; $old[$f]=$old[$f]!==''?$old[$f]:null; }
                if($old['fecha_emision'] && $old['fecha_limite'] && $old['fecha_limite'] < $old['fecha_emision']) $errors[]='La fecha límite no puede ser anterior a la emisión.'; if($old['fecha_emision'] && $old['fecha_respuesta'] && $old['fecha_respuesta'] < $old['fecha_emision']) $errors[]='La fecha de respuesta no puede ser anterior a la emisión.'; if($old['fecha_emision']===null) $errors[]='Fecha de emisión obligatoria.';
                if(!$errors){ try{ if($id){Requerimiento::update($id,$old); auditLog((int)$user['id'],'requerimiento.update','info','requerimiento',(string)$id);} else {$nid=Requerimiento::create($old); auditLog((int)$user['id'],'requerimiento.create','info','requerimiento',(string)$nid);} redirect('requerimientos.php?ok=1'); }catch(\Throwable $e){error_log($e->getMessage()); $errors[]='No se pudo guardar (verifica duplicados).';} }
            }
        }
        view('requerimientos/form',['app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'statuses'=>$this->statuses(),'pageTitle'=>$id?'Editar requerimiento':'Nuevo requerimiento','pageSubtitle'=>'Registro de requerimientos','activeMenu'=>'requerimientos']);
    }
    public function delete(): void { $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');} $id=(int)($_POST['id']??0); if($id>0){Requerimiento::delete($id); auditLog((int)$user['id'],'requerimiento.delete','warning','requerimiento',(string)$id);} redirect('requerimientos.php?deleted=1'); }
    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'estado'=>sanitizeText($_POST['estado']??'',40)];
        $rows=Requerimiento::search($filters, 5000, 0)['items'];
        $data=[]; foreach($rows as $r){ $data[]=[(string)$r['numero_requerimiento'],(string)$r['contribuyente'],(string)($r['rif']??''),(string)$r['tipo'],(string)$r['estado'],(string)$r['fecha_emision'],(string)($r['fecha_limite']??''),(string)($r['fecha_respuesta']??''),(string)($r['descripcion']??'')]; }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'requerimiento.export','info','requerimiento',null,['rows'=>count($data)]);
        exportRowsToXlsx('requerimientos_'.date('Ymd_His').'.xlsx',['N° Requerimiento','Contribuyente','RIF','Tipo','Estado','Fecha emisión','Fecha límite','Fecha respuesta','Descripción'],$data);
    }

    public function template(): void { requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php'; exportRowsToXlsx('plantilla_requerimientos.xlsx',['NUMERO_REQUERIMIENTO','CONTRIBUYENTE','RIF','TIPO','ESTADO','FECHA_EMISION','FECHA_LIMITE','FECHA_RESPUESTA','DESCRIPCION'],[['REQ-001','CONTRIBUYENTE EJEMPLO','J-00000000-0','INFORMACIÓN','pendiente','2026-01-01','','','']]); }
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
                        $d=['n'=>sanitizeText(importVal($row,['numero_requerimiento','n_requerimiento','requerimiento']),80),'c'=>sanitizeText(importVal($row,['contribuyente','razon_social']),200),'rif'=>sanitizeText(importVal($row,['rif']),30),'t'=>sanitizeText(importVal($row,['tipo']),120),'e'=>sanitizeText(importVal($row,['estado','estatus'],'pendiente'),40),'fe'=>importDate($row,['fecha_emision','emision']) ?? date('Y-m-d'),'fl'=>importDate($row,['fecha_limite','limite']),'fr'=>importDate($row,['fecha_respuesta','respuesta']),'r'=>(int)$user['id'],'dsc'=>sanitizeText(importVal($row,['descripcion','observaciones','observacion'],'[Importado]'),2000)];
                        if($d['n']===''||$d['c']===''||$d['t']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': faltan campos obligatorios.'; continue; }
                        if(!in_array($d['e'],$this->statuses(),true)) $d['e']='pendiente';
                        try{ Requerimiento::create($d); $imported++; } catch(\Throwable $e){ try{ $st=db()->prepare('SELECT id FROM requerimientos WHERE numero_requerimiento=:n LIMIT 1'); $st->execute(['n'=>$d['n']]); $ex=$st->fetchColumn(); if($ex){ Requerimiento::update((int)$ex,$d); $imported++; } else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } } catch(\Throwable $e2){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'requerimiento.import','warning','requerimiento',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('requerimientos/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar requerimientos','pageSubtitle'=>'Carga histórica desde CSV/XLSX','activeMenu'=>'requerimientos']);
    }
}
