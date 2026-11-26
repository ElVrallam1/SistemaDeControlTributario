<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\InventarioSucesion;

class InventarioSucesionController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    private function normalizeDate(string $v): ?string {
        $v = sanitizeText($v, 20);
        if ($v === '') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    public function index(): void {
        $user = requireAuth();
        $filters = [
            'q' => sanitizeText($_GET['q'] ?? '', 120),
            'estatus' => sanitizeText($_GET['estatus'] ?? '', 60),
            'tipo_caso' => sanitizeText($_GET['tipo_caso'] ?? '', 30),
            'from' => sanitizeText($_GET['from'] ?? '', 20),
            'to' => sanitizeText($_GET['to'] ?? '', 20),
        ];
        foreach (['from','to'] as $k) if ($filters[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$k])) $filters[$k] = '';
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 20; $offset = ($page-1)*$limit;
        $res = InventarioSucesion::search($filters, $limit, $offset);
        $totalPages = max(1, (int)ceil($res['total']/$limit));
        view('inventario_sucesiones/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],
            'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,
            'statuses'=>InventarioSucesion::statuses(),'tipos'=>InventarioSucesion::tipos(),
            'pageTitle'=>'Inventario Sucesiones/Donaciones','pageSubtitle'=>'Inventario consolidado de expedientes','activeMenu'=>'inventario_sucesiones'
        ]);
    }

    public function form(?int $id=null): void {
        $user = requireAuth();
        $item = $id ? InventarioSucesion::find($id) : null;
        if ($id && !$item) { http_response_code(404); exit('No encontrado.'); }
        $errors = [];
        $old = $item ?: [
            'numero_expediente'=>'','razon_social'=>'','rif'=>'','unidad_adscripcion'=>'CENTRO AUXILIAR','tipo_caso'=>'SUCESION',
            'fecha_solicitud'=>date('Y-m-d'),'estatus'=>'EN REVISIÓN','observacion'=>'','fecha_acta_requerimiento'=>'',
            'fecha_notificacion_acto'=>'','numero_planilla_liquidacion'=>'','importe'=>'0','usuario_id'=>$user['id']
        ];
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[] = 'Sesión de seguridad inválida.';
            else {
                $old['numero_expediente'] = sanitizeText($_POST['numero_expediente'] ?? '', 80);
                $old['razon_social'] = sanitizeText($_POST['razon_social'] ?? '', 220);
                $old['rif'] = sanitizeText($_POST['rif'] ?? '', 30);
                $old['unidad_adscripcion'] = sanitizeText($_POST['unidad_adscripcion'] ?? 'CENTRO AUXILIAR', 120);
                $old['tipo_caso'] = strtoupper(sanitizeText($_POST['tipo_caso'] ?? 'SUCESION', 30));
                $old['fecha_solicitud'] = sanitizeText($_POST['fecha_solicitud'] ?? '', 20);
                $old['estatus'] = sanitizeText($_POST['estatus'] ?? 'EN REVISIÓN', 60);
                $old['observacion'] = sanitizeText($_POST['observacion'] ?? '', 2000);
                $old['fecha_acta_requerimiento'] = sanitizeText($_POST['fecha_acta_requerimiento'] ?? '', 20);
                $old['fecha_notificacion_acto'] = sanitizeText($_POST['fecha_notificacion_acto'] ?? '', 20);
                $old['numero_planilla_liquidacion'] = sanitizeText($_POST['numero_planilla_liquidacion'] ?? '', 80);
                $old['importe'] = (float)($_POST['importe'] ?? 0);
                $old['usuario_id'] = (int)$user['id'];

                if ($old['numero_expediente'] === '') $errors[] = 'N° de expediente obligatorio.';
                if ($old['razon_social'] === '') $errors[] = 'Razón social obligatoria.';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha_solicitud'])) $errors[] = 'Fecha de solicitud inválida.';
                if (!in_array($old['tipo_caso'], InventarioSucesion::tipos(), true)) $errors[] = 'Tipo de caso inválido.';
                if ($old['estatus'] === '') $errors[] = 'Estatus obligatorio.';
                if ($old['importe'] < 0) $errors[] = 'El importe no puede ser negativo.';
                foreach (['fecha_acta_requerimiento','fecha_notificacion_acto'] as $f) {
                    if ($old[$f] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old[$f])) $errors[] = "Fecha inválida: $f";
                    $old[$f] = $old[$f] !== '' ? $old[$f] : null;
                }
                if (!$errors) {
                    try {
                        if ($id) { InventarioSucesion::update($id,$old); auditLog((int)$user['id'],'inventario_sucesion.update','info','inventario_sucesion',(string)$id); }
                        else { $nid=InventarioSucesion::create($old); auditLog((int)$user['id'],'inventario_sucesion.create','info','inventario_sucesion',(string)$nid); }
                        redirect('inventario-sucesiones.php?ok=1');
                    } catch (\Throwable $e) { error_log($e->getMessage()); $errors[]='No se pudo guardar. Verifica que el N° de expediente no esté duplicado.'; }
                }
            }
        }
        view('inventario_sucesiones/form', [
            'app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,
            'statuses'=>InventarioSucesion::statuses(),'tipos'=>InventarioSucesion::tipos(),
            'pageTitle'=>$id?'Editar expediente':'Nuevo expediente de sucesión/donación','pageSubtitle'=>'Inventario consolidado','activeMenu'=>'inventario_sucesiones'
        ]);
    }

    public function delete(): void {
        $user = requireAnyRole(['admin','supervisor']);
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { InventarioSucesion::delete($id); auditLog((int)$user['id'],'inventario_sucesion.delete','warning','inventario_sucesion',(string)$id); }
        redirect('inventario-sucesiones.php?deleted=1');
    }

    public function export(): void {
        $user = requireAuth();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $filters = [
            'q'=>sanitizeText($_POST['q']??'',120), 'estatus'=>sanitizeText($_POST['estatus']??'',60),
            'tipo_caso'=>sanitizeText($_POST['tipo_caso']??'',30), 'from'=>sanitizeText($_POST['from']??'',20), 'to'=>sanitizeText($_POST['to']??'',20)
        ];
        foreach (['from','to'] as $k) if ($filters[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$k])) $filters[$k] = '';
        $rows = InventarioSucesion::forExport($filters);
        $headers = ['N° EXPEDIENTE','RAZÓN SOCIAL','RIF','UNIDAD DE ADSCRIPCIÓN','SUCESIÓN/DONACIÓN','FECHA DE LA SOLICITUD','ESTATUS','OBSERVACIÓN','FECHA ACTA REQUERIMIENTO','FECHA NOTIFICACIÓN ACTO ADMINISTRATIVO','N° PLANILLA DE LIQUIDACIÓN','IMPORTE'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (string)$r['numero_expediente'], (string)$r['razon_social'], (string)($r['rif']??''), (string)$r['unidad_adscripcion'], (string)$r['tipo_caso'],
                $r['fecha_solicitud'] ? date('d/m/Y', strtotime((string)$r['fecha_solicitud'])) : '',
                (string)$r['estatus'], (string)($r['observacion']??''),
                $r['fecha_acta_requerimiento'] ? date('d/m/Y', strtotime((string)$r['fecha_acta_requerimiento'])) : '',
                $r['fecha_notificacion_acto'] ? date('d/m/Y', strtotime((string)$r['fecha_notificacion_acto'])) : '',
                (string)($r['numero_planilla_liquidacion']??''), number_format((float)$r['importe'],2,'.','')
            ];
        }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'inventario_sucesion.export','info','inventario_sucesion',null,['rows'=>count($data)]);
        exportRowsToXlsx('inventario_sucesiones_'.date('Ymd_His').'.xlsx', $headers, $data);
    }

    public function template(): void {
        requireAuth(); require_once BASE_PATH.'/src/helpers/excel.php';
        exportRowsToXlsx('plantilla_inventario_sucesiones.xlsx',['NRO_EXPEDIENTE','RAZON_SOCIAL','RIF','UNIDAD_ADSCRIPCION','TIPO_CASO','FECHA_SOLICITUD','ESTATUS','OBSERVACION','FECHA_ACTA_REQUERIMIENTO','FECHA_NOTIFICACION_ACTO','NRO_PLANILLA_LIQUIDACION','IMPORTE'],[['80130377','EJEMPLO','J-00000000-0','CENTRO AUXILIAR','SUCESION','2026-10-01','EN REVISIÓN','','','','','0']]);
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
                            'numero_expediente'=>sanitizeText(importVal($row,['numero_expediente','nro_expediente','n_expediente','expediente']),80),
                            'razon_social'=>sanitizeText(importVal($row,['razon_social','nombre','contribuyente']),220),
                            'rif'=>sanitizeText(importVal($row,['rif']),30),
                            'unidad_adscripcion'=>sanitizeText(importVal($row,['unidad_adscripcion','unidad_de_adscripcion'],'CENTRO AUXILIAR'),120) ?: 'CENTRO AUXILIAR',
                            'tipo_caso'=>strtoupper(sanitizeText(importVal($row,['tipo_caso','sucesion_donacion','tipo'],'SUCESION'),30)),
                            'fecha_solicitud'=>importDate($row,['fecha_solicitud','fecha_de_la_solicitud','fecha']) ?? date('Y-m-d'),
                            'estatus'=>sanitizeText(importVal($row,['estatus','estado'],'EN REVISIÓN'),60),
                            'observacion'=>sanitizeText(importVal($row,['observacion','observaciones'],'[Importado]'),2000),
                            'fecha_acta_requerimiento'=>importDate($row,['fecha_acta_requerimiento','fecha_del_acta_de_requerimiento_si_aplica']),
                            'fecha_notificacion_acto'=>importDate($row,['fecha_notificacion_acto','fecha_de_notificacion_del_acto_administrativo_si_aplica','fecha_notificacion']),
                            'numero_planilla_liquidacion'=>sanitizeText(importVal($row,['numero_planilla_liquidacion','n_planilla_de_liquidacion_si_aplica','planilla']),80),
                            'importe'=>importFloat($row,['importe','importe_si_aplica','monto']),
                            'usuario_id'=>(int)$user['id'],
                        ];
                        if($d['numero_expediente']===''||$d['razon_social']===''){ $skipped++; $details[]='Fila '.$row['_row_num'].': expediente o razón social vacío.'; continue; }
                        if(!in_array($d['tipo_caso'],InventarioSucesion::tipos(),true)) $d['tipo_caso']='SUCESION';
                        try{ InventarioSucesion::create($d); $imported++; }
                        catch(\Throwable $e){ try{ $st=db()->prepare('SELECT id FROM inventario_sucesiones WHERE numero_expediente=:n LIMIT 1'); $st->execute(['n'=>$d['numero_expediente']]); $ex=$st->fetchColumn(); if($ex){ InventarioSucesion::update((int)$ex,$d); $imported++; } else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } } catch(\Throwable $e2){ $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; } }
                    }
                }
            }
        }
        if(isPost() && !$errors) auditLog((int)$user['id'],'inventario_sucesion.import','warning','inventario_sucesion',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('inventario_sucesiones/import',['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar inventario','pageSubtitle'=>'Carga histórica desde CSV','activeMenu'=>'inventario_sucesiones']);
    }
}
