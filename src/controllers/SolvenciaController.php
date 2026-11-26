<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Solvencia;
class SolvenciaController {
    private array $app; public function __construct(array $app){$this->app=$app;}
    private function statuses(): array { return ['pendiente','emitida','vencida','rechazada']; }
    public function index(): void {
        $user=requireAuth();
        $filters=['q'=>sanitizeText($_GET['q']??'',120),'estado'=>sanitizeText($_GET['estado']??'',40)];
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res=Solvencia::search($filters,$limit,$offset); $totalPages=max(1,(int)ceil($res['total']/$limit));
        view('solvencias/index',['app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'statuses'=>$this->statuses(),'pageTitle'=>'Solvencia','pageSubtitle'=>'Control de solvencias tributarias','activeMenu'=>'solvencia']);
    }
    public function form(?int $id=null): void {
        $user=requireAuth(); $item=$id?Solvencia::find($id):null; if($id&&!$item){http_response_code(404);exit('No encontrado.');}
        $errors=[]; $old=$item ?: ['numero_solvencia'=>'','contribuyente'=>'','rif'=>'','tipo'=>'','estado'=>'pendiente','monto'=>'0','fecha_emision'=>'','fecha_vencimiento'=>'','responsable_id'=>$user['id'],'observaciones'=>''];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['numero_solvencia']=sanitizeText($_POST['numero_solvencia']??'',80);
                $old['contribuyente']=sanitizeText($_POST['contribuyente']??'',200);
                $old['rif']=sanitizeText($_POST['rif']??'',30);
                $old['tipo']=sanitizeText($_POST['tipo']??'',100);
                $old['estado']=sanitizeText($_POST['estado']??'pendiente',40);
                $old['monto']=(float)($_POST['monto']??0);
                $old['fecha_emision']=sanitizeText($_POST['fecha_emision']??'',20);
                $old['fecha_vencimiento']=sanitizeText($_POST['fecha_vencimiento']??'',20);
                $old['observaciones']=sanitizeText($_POST['observaciones']??'',2000);
                $old['responsable_id']=(int)$user['id'];
                if($old['numero_solvencia']==='') $errors[]='Número de solvencia obligatorio.';
                if($old['contribuyente']==='') $errors[]='Contribuyente obligatorio.';
                if($old['tipo']==='') $errors[]='Tipo obligatorio.';
                if(!in_array($old['estado'],$this->statuses(),true)) $errors[]='Estado inválido.';
                if($old['monto']<0) $errors[]='Monto inválido.';
                foreach(['fecha_emision','fecha_vencimiento'] as $f){ if($old[$f]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$old[$f])) $errors[]="Fecha inválida: $f"; $old[$f]=$old[$f]!==''?$old[$f]:null; } if($old['fecha_emision'] && $old['fecha_vencimiento'] && $old['fecha_vencimiento']<$old['fecha_emision']) $errors[]='La fecha de vencimiento no puede ser anterior a la emisión.';
                if(!$errors){ try{ if($id){Solvencia::update($id,$old); auditLog((int)$user['id'],'solvencia.update','info','solvencia',(string)$id);} else {$nid=Solvencia::create($old); auditLog((int)$user['id'],'solvencia.create','info','solvencia',(string)$nid);} redirect('solvencia.php?ok=1'); }catch(\Throwable $e){error_log($e->getMessage()); $errors[]='No se pudo guardar (verifica duplicados).';} }
            }
        }
        view('solvencias/form',['app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'statuses'=>$this->statuses(),'pageTitle'=>$id?'Editar solvencia':'Nueva solvencia','pageSubtitle'=>'Registro de solvencias','activeMenu'=>'solvencia']);
    }
    public function delete(): void { $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');} $id=(int)($_POST['id']??0); if($id>0){Solvencia::delete($id); auditLog((int)$user['id'],'solvencia.delete','warning','solvencia',(string)$id);} redirect('solvencia.php?deleted=1'); }
    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'estado'=>sanitizeText($_POST['estado']??'',40)];
        $rows=Solvencia::search($filters, 5000, 0)['items'];
        $data=[]; foreach($rows as $r){ $data[]=[(string)$r['numero_solvencia'],(string)$r['contribuyente'],(string)($r['rif']??''),(string)$r['tipo'],(string)$r['estado'],number_format((float)$r['monto'],2,'.',''),(string)($r['fecha_emision']??''),(string)($r['fecha_vencimiento']??''),(string)($r['observaciones']??'')]; }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'solvencia.export','info','solvencia',null,['rows'=>count($data)]);
        exportRowsToXlsx('solvencias_'.date('Ymd_His').'.xlsx',['N° Solvencia','Contribuyente','RIF','Tipo','Estado','Monto','Fecha emisión','Fecha vencimiento','Observaciones'],$data);
    }

    public function template(): void { requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php'; exportRowsToXlsx('plantilla_solvencias.xlsx',['NUMERO_SOLVENCIA','CONTRIBUYENTE','RIF','TIPO','ESTADO','MONTO','FECHA_EMISION','FECHA_VENCIMIENTO','OBSERVACIONES'],[['SOLV-001','CONTRIBUYENTE EJEMPLO','J-00000000-0','TRIBUTARIA','pendiente','0','2026-01-01','','']]); }
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
                        $d=['n'=>sanitizeText(importVal($row,['numero_solvencia','n_solvencia','solvencia']),80),'c'=>sanitizeText(importVal($row,['contribuyente','razon_social']),200),'rif'=>sanitizeText(importVal($row,['rif']),30),'t'=>sanitizeText(importVal($row,['tipo','tipo_solvencia']),100),'e'=>sanitizeText(importVal($row,['estado','estatus'],'pendiente'),40),'m'=>importFloat($row,['monto','importe']),'fe'=>importDate($row,['fecha_emision','emision']),'fv'=>importDate($row,['fecha_vencimiento','vencimiento']),'r'=>(int)$user['id'],'o'=>sanitizeText(importVal($row,['observaciones','observacion'],'[Importado]'),2000)];
                        if($d['n']===''||$d['c']===''||$d['t']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': faltan campos obligatorios.'; continue; }
                        if(!in_array($d['e'],$this->statuses(),true)) $d['e']='pendiente';
                        try{ Solvencia::create($d); $imported++; } catch(\Throwable $e){ try{ $st=db()->prepare('SELECT id FROM solvencias WHERE numero_solvencia=:n LIMIT 1'); $st->execute(['n'=>$d['n']]); $ex=$st->fetchColumn(); if($ex){ Solvencia::update((int)$ex,$d); $imported++; } else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } } catch(\Throwable $e2){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'solvencia.import','warning','solvencia',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('solvencias/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar solvencias','pageSubtitle'=>'Carga histórica desde CSV/XLSX','activeMenu'=>'solvencia']);
    }
}
