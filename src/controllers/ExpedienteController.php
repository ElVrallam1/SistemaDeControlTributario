<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expediente;

class ExpedienteController
{
    private array $app;
    public function __construct(array $app) { $this->app = $app; }

    private function statuses(): array { return ['pendiente','en_proceso','completado','detenido','faltante']; }
    private function priorities(): array { return ['baja','media','alta']; }

    public function index(): void
    {
        $user = requireAuth();
        $filters = [
            'q' => sanitizeText($_GET['q'] ?? '', 120),
            'estado' => sanitizeText($_GET['estado'] ?? '', 40),
            'prioridad' => sanitizeText($_GET['prioridad'] ?? '', 20),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 20; $offset = ($page - 1) * $limit;
        $res = Expediente::search($filters, $limit, $offset);
        $totalPages = max(1, (int)ceil($res['total'] / $limit));
        view('expedientes/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],
            'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,
            'statuses'=>$this->statuses(),'priorities'=>$this->priorities(),
            'pageTitle'=>'Expedientes','pageSubtitle'=>'Gestión y seguimiento de expedientes','activeMenu'=>'expedientes'
        ]);
    }

    public function form(?int $id = null): void
    {
        $user = requireAuth();
        $item = $id ? Expediente::find($id) : null;
        if ($id && !$item) { http_response_code(404); exit('Expediente no encontrado.'); }
        $errors = [];
        $old = $item ?: [
            'numero_expediente'=>'','contribuyente'=>'','rif'=>'','tipo_tramite'=>'','division'=>'','estado'=>'pendiente',
            'prioridad'=>'media','descripcion'=>'','fecha_apertura'=>date('Y-m-d'),'fecha_cierre'=>'','responsable_id'=>$user['id']
        ];

        if (isPost()) {
            if (!verifyCsrf($this->app)) { $errors[] = 'Sesión de seguridad inválida.'; }
            else {
                $old['numero_expediente'] = sanitizeText($_POST['numero_expediente'] ?? '', 80);
                $old['contribuyente'] = sanitizeText($_POST['contribuyente'] ?? '', 200);
                $old['rif'] = sanitizeText($_POST['rif'] ?? '', 30);
                $old['tipo_tramite'] = sanitizeText($_POST['tipo_tramite'] ?? '', 120);
                $old['division'] = sanitizeText($_POST['division'] ?? '', 120);
                $old['estado'] = sanitizeText($_POST['estado'] ?? 'pendiente', 40);
                $old['prioridad'] = sanitizeText($_POST['prioridad'] ?? 'media', 20);
                $old['descripcion'] = sanitizeText($_POST['descripcion'] ?? '', 2000);
                $old['fecha_apertura'] = sanitizeText($_POST['fecha_apertura'] ?? '', 20);
                $old['fecha_cierre'] = sanitizeText($_POST['fecha_cierre'] ?? '', 20);
                $old['responsable_id'] = (int) $user['id'];

                if ($old['numero_expediente']==='') $errors[]='El número de expediente es obligatorio.';
                if ($old['contribuyente']==='') $errors[]='El contribuyente es obligatorio.';
                if ($old['tipo_tramite']==='') $errors[]='El tipo de trámite es obligatorio.';
                if ($old['division']==='' || !in_array($old['division'], AuthController::departments(), true)) $errors[]='Selecciona una división válida.';
                if (!in_array($old['estado'], $this->statuses(), true)) $errors[]='Estado inválido.';
                if (!in_array($old['prioridad'], $this->priorities(), true)) $errors[]='Prioridad inválida.';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha_apertura'])) $errors[]='Fecha de apertura inválida.';
                if ($old['fecha_cierre'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha_cierre'])) $errors[]='Fecha de cierre inválida.';
                if ($old['fecha_cierre'] !== '' && $old['fecha_cierre'] < $old['fecha_apertura']) $errors[]='La fecha de cierre no puede ser anterior a la apertura.';

                $old['fecha_cierre'] = $old['fecha_cierre'] !== '' ? $old['fecha_cierre'] : null;

                if (!$errors) {
                    try {
                        if ($id) {
                            Expediente::update($id, $old);
                            auditLog((int)$user['id'], 'expediente.update', 'info', 'expediente', (string)$id);
                        } else {
                            $newId = Expediente::create($old + ['created_by' => (int)$user['id']]);
                            auditLog((int)$user['id'], 'expediente.create', 'info', 'expediente', (string)$newId);
                        }
                        redirect('expedientes.php?ok=1');
                    } catch (\Throwable $e) {
                        error_log($e->getMessage());
                        $errors[] = 'No se pudo guardar. Verifica que el número de expediente no esté duplicado.';
                    }
                }
            }
        }

        view('expedientes/form', [
            'app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,'id'=>$id,
            'statuses'=>$this->statuses(),'priorities'=>$this->priorities(),'departments'=>AuthController::departments(),
            'pageTitle'=>$id?'Editar expediente':'Nuevo expediente','pageSubtitle'=>'Registro y actualización de expedientes','activeMenu'=>'expedientes'
        ]);
    }

    public function delete(): void
    {
        $user = requireAnyRole(['admin','supervisor']);
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Expediente::delete($id);
            auditLog((int)$user['id'], 'expediente.delete', 'warning', 'expediente', (string)$id);
        }
        redirect('expedientes.php?deleted=1');
    }

    public function export(): void
    {
        $user = requireAuth();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $filters = [
            'q' => sanitizeText($_POST['q'] ?? '', 120),
            'estado' => sanitizeText($_POST['estado'] ?? '', 40),
            'prioridad' => sanitizeText($_POST['prioridad'] ?? '', 20),
        ];
        $rows = Expediente::search($filters, 5000, 0)['items'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [(string)$r['numero_expediente'],(string)$r['contribuyente'],(string)($r['rif']??''),(string)$r['tipo_tramite'],(string)$r['division'],(string)$r['estado'],(string)$r['prioridad'],(string)$r['fecha_apertura'],(string)($r['fecha_cierre']??''),(string)($r['descripcion']??'')];
        }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'], 'expediente.export', 'info', 'expediente', null, ['rows'=>count($data)]);
        exportRowsToXlsx('expedientes_'.date('Ymd_His').'.xlsx', ['N° Expediente','Contribuyente','RIF','Tipo trámite','División','Estado','Prioridad','Fecha apertura','Fecha cierre','Descripción'], $data);
    }

    public function template(): void {
        requireAuth(); require_once BASE_PATH . '/src/helpers/excel.php';
        exportRowsToXlsx('plantilla_expedientes.xlsx', ['NUMERO_EXPEDIENTE','CONTRIBUYENTE','RIF','TIPO_TRAMITE','DIVISION','ESTADO','PRIORIDAD','DESCRIPCION','FECHA_APERTURA','FECHA_CIERRE'], [['EXP-001','CONTRIBUYENTE EJEMPLO','J-00000000-0','TRÁMITE','SUCESIONES','pendiente','media','','2026-01-01','']]);
    }

    public function import(): void {
        $user = requireAnyRole(['admin','supervisor']);
        $errors=[]; $imported=0; $skipped=0; $details=[];
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            elseif (empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $errors[]='Selecciona un archivo CSV/XLSX válido.';
            else {
                $ext = strtolower(pathinfo((string)$_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                $allowed=['csv','txt']; if (class_exists('ZipArchive')) $allowed[]='xlsx';
                if (!in_array($ext,$allowed,true)) $errors[]='Formato no soportado. Usa CSV' . (class_exists('ZipArchive')?' o XLSX':'') . '.';
                elseif (($_FILES['csv_file']['size'] ?? 0) > 5*1024*1024) $errors[]='Archivo excede 5MB.';
                else {
                    require_once BASE_PATH . '/src/helpers/import.php';
                    $parsed = importReadFile((string)$_FILES['csv_file']['tmp_name']);
                    if (!$parsed['ok']) $errors[]=$parsed['error'];
                    else foreach ($parsed['rows'] as $row) {
                        $d = [
                            'numero_expediente'=>sanitizeText(importVal($row,['numero_expediente','n_expediente','expediente','nro_expediente']),80),
                            'contribuyente'=>sanitizeText(importVal($row,['contribuyente','razon_social','nombre']),200),
                            'rif'=>sanitizeText(importVal($row,['rif','cedula_rif']),30),
                            'tipo_tramite'=>sanitizeText(importVal($row,['tipo_tramite','tramite','tipo']),120),
                            'division'=>sanitizeText(importVal($row,['division','gerencia','dependencia']),120),
                            'estado'=>sanitizeText(importVal($row,['estado','estatus'],'pendiente'),40),
                            'prioridad'=>sanitizeText(importVal($row,['prioridad'],'media'),20),
                            'descripcion'=>sanitizeText(importVal($row,['descripcion','observaciones','observacion'],'[Importado]'),2000),
                            'fecha_apertura'=>importDate($row,['fecha_apertura','fecha']) ?? date('Y-m-d'),
                            'fecha_cierre'=>importDate($row,['fecha_cierre']),
                            'responsable_id'=>(int)$user['id'],
                            'created_by'=>(int)$user['id'],
                        ];
                        if ($d['numero_expediente']==='' || $d['contribuyente']==='' || $d['tipo_tramite']==='') { $skipped++; $details[]='Fila '.$row['_row_num'].': faltan campos obligatorios.'; continue; }
                        if (!in_array($d['division'], AuthController::departments(), true)) $d['division'] = AuthController::departments()[0] ?? 'OTROS';
                        if (!in_array($d['estado'], $this->statuses(), true)) $d['estado']='pendiente';
                        if (!in_array($d['prioridad'], $this->priorities(), true)) $d['prioridad']='media';
                        try { Expediente::create($d); $imported++; }
                        catch (\Throwable $e) {
                            try {
                                $st=db()->prepare('SELECT id FROM expedientes WHERE numero_expediente=:n LIMIT 1'); $st->execute(['n'=>$d['numero_expediente']]); $ex=$st->fetchColumn();
                                if ($ex) { unset($d['created_by']); Expediente::update((int)$ex,$d); $imported++; }
                                else { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; }
                            } catch (\Throwable $e2) { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; }
                        }
                    }
                }
            }
        }
        if (isPost() && !$errors) auditLog((int)$user['id'],'expediente.import','warning','expediente',null,['imported'=>$imported,'skipped'=>$skipped]);
        view('expedientes/import', ['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar expedientes','pageSubtitle'=>'Carga histórica desde CSV/XLSX','activeMenu'=>'expedientes']);
    }
}
