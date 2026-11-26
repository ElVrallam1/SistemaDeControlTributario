<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Multa;
class MultaController {
    private array $app; public function __construct(array $app){$this->app=$app;}
    private function statuses(): array { return ['pendiente','notificada','pagada','anulada','en_recurso']; }
    public function index(): void {
        $user=requireAuth();
        $filters=['q'=>sanitizeText($_GET['q']??'',120),'estado'=>sanitizeText($_GET['estado']??'',40)];
        $page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
        $res=Multa::search($filters,$limit,$offset); $totalPages=max(1,(int)ceil($res['total']/$limit));
        view('multas/index',['app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'statuses'=>$this->statuses(),'pageTitle'=>'Multas','pageSubtitle'=>'Registro y control de multas tributarias','activeMenu'=>'multas']);
    }
    public function form(?int $id=null): void {
        $user=requireAuth(); $item=$id?Multa::find($id):null; if($id&&!$item){http_response_code(404);exit('No encontrado.');}
        $errors=[]; $old=$item ?: ['numero_multa'=>'','contribuyente'=>'','rif'=>'','motivo'=>'','estado'=>'pendiente','monto'=>'0','fecha_registro'=>date('Y-m-d'),'fecha_pago'=>'','responsable_id'=>$user['id'],'observaciones'=>''];
        if(isPost()){
            if(!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            else {
                $old['numero_multa']=sanitizeText($_POST['numero_multa']??'',80);
                $old['contribuyente']=sanitizeText($_POST['contribuyente']??'',200);
                $old['rif']=sanitizeText($_POST['rif']??'',30);
                $old['motivo']=sanitizeText($_POST['motivo']??'',2000);
                $old['estado']=sanitizeText($_POST['estado']??'pendiente',40);
                $old['monto']=(float)($_POST['monto']??0);
                $old['fecha_registro']=sanitizeText($_POST['fecha_registro']??'',20);
                $old['fecha_pago']=sanitizeText($_POST['fecha_pago']??'',20);
                $old['observaciones']=sanitizeText($_POST['observaciones']??'',2000);
                $old['responsable_id']=(int)$user['id'];
                if($old['numero_multa']==='') $errors[]='Número de multa obligatorio.';
                if($old['contribuyente']==='') $errors[]='Contribuyente obligatorio.';
                if($old['motivo']==='') $errors[]='Motivo obligatorio.';
                if(!in_array($old['estado'],$this->statuses(),true)) $errors[]='Estado inválido.';
                if($old['monto']<0) $errors[]='Monto inválido.';
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$old['fecha_registro'])) $errors[]='Fecha de registro inválida.';
                if($old['fecha_pago']!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$old['fecha_pago'])) $errors[]='Fecha de pago inválida.'; if($old['fecha_pago']!=='' && $old['fecha_pago'] < $old['fecha_registro']) $errors[]='La fecha de pago no puede ser anterior al registro.';
                $old['fecha_pago']=$old['fecha_pago']!==''?$old['fecha_pago']:null;
                if(!$errors){ try{ if($id){Multa::update($id,$old); auditLog((int)$user['id'],'multa.update','info','multa',(string)$id);} else {$nid=Multa::create($old); auditLog((int)$user['id'],'multa.create','info','multa',(string)$nid);} redirect('multas.php?ok=1'); }catch(\Throwable $e){error_log($e->getMessage()); $errors[]='No se pudo guardar (verifica duplicados).';} }
            }
        }
        view('multas/form',['app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'statuses'=>$this->statuses(),'pageTitle'=>$id?'Editar multa':'Nueva multa','pageSubtitle'=>'Registro de multas','activeMenu'=>'multas']);
    }
    public function delete(): void { $user=requireAnyRole(['admin','supervisor']); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');} $id=(int)($_POST['id']??0); if($id>0){Multa::delete($id); auditLog((int)$user['id'],'multa.delete','warning','multa',(string)$id);} redirect('multas.php?deleted=1'); }
    public function export(): void {
        $user=requireAuth(); if(!isPost()||!verifyCsrf($this->app)){http_response_code(400);exit('Solicitud inválida.');}
        $filters=['q'=>sanitizeText($_POST['q']??'',120),'estado'=>sanitizeText($_POST['estado']??'',40)];
        $rows=Multa::search($filters, 5000, 0)['items'];
        $data=[]; foreach($rows as $r){ $data[]=[(string)$r['numero_multa'],(string)$r['contribuyente'],(string)($r['rif']??''),(string)$r['motivo'],(string)$r['estado'],number_format((float)$r['monto'],2,'.',''),(string)$r['fecha_registro'],(string)($r['fecha_pago']??''),(string)($r['observaciones']??'')]; }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'multa.export','info','multa',null,['rows'=>count($data)]);
        exportRowsToXlsx('multas_'.date('Ymd_His').'.xlsx',['N° Multa','Contribuyente','RIF','Motivo','Estado','Monto','Fecha registro','Fecha pago','Observaciones'],$data);
    }

    public function template(): void { requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php'; exportRowsToXlsx('plantilla_multas.xlsx',['NUMERO_MULTA','CONTRIBUYENTE','RIF','MOTIVO','ESTADO','MONTO','FECHA_REGISTRO','FECHA_PAGO','OBSERVACIONES'],[['MULTA-001','CONTRIBUYENTE EJEMPLO','J-00000000-0','MOTIVO','pendiente','0','2026-01-01','','']]); }
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
                        $d=['n'=>sanitizeText(importVal($row,['numero_multa','n_multa','multa']),80),'c'=>sanitizeText(importVal($row,['contribuyente','razon_social']),200),'rif'=>sanitizeText(importVal($row,['rif']),30),'mo'=>sanitizeText(importVal($row,['motivo','descripcion']),2000),'e'=>sanitizeText(importVal($row,['estado','estatus'],'pendiente'),40),'m'=>importFloat($row,['monto','importe']),'fr'=>importDate($row,['fecha_registro','fecha']) ?? date('Y-m-d'),'fp'=>importDate($row,['fecha_pago']),'r'=>(int)$user['id'],'o'=>sanitizeText(importVal($row,['observaciones','observacion'],'[Importado]'),2000)];
                        if($d['n']===''||$d['c']===''||$d['mo']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': faltan campos obligatorios.'; continue; }
                        if(!in_array($d['e'],$this->statuses(),true)) $d['e']='pendiente';
                        try{ Multa::create($d); $imported++; } catch(\Throwable $e){ try{ $st=db()->prepare('SELECT id FROM multas WHERE numero_multa=:n LIMIT 1'); $st->execute(['n'=>$d['n']]); $ex=$st->fetchColumn(); if($ex){ Multa::update((int)$ex,$d); $imported++; } else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } } catch(\Throwable $e2){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'multa.import','warning','multa',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('multas/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar multas','pageSubtitle'=>'Carga histórica desde CSV/XLSX','activeMenu'=>'multas']);
    }
}
