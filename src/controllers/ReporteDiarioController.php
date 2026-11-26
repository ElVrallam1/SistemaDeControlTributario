<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\ReporteDiario;

class ReporteDiarioController {
    private array $app;
    public function __construct(array $app){ $this->app = $app; }

    public function index(): void {
        $user = requireAuth();
        $filters = [
            'q' => sanitizeText($_GET['q'] ?? '', 120),
            'from' => sanitizeText($_GET['from'] ?? '', 20),
            'to' => sanitizeText($_GET['to'] ?? '', 20),
            'department' => sanitizeText($_GET['department'] ?? '', 120),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 20; $offset = ($page - 1) * $limit;
        $res = ReporteDiario::search($filters, $limit, $offset);
        $totalPages = max(1, (int)ceil($res['total'] / $limit));
        $departments = db()->query("SELECT DISTINCT dependencia FROM reporte_diario WHERE dependencia IS NOT NULL AND dependencia <> '' ORDER BY dependencia")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        view('control_diario/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],
            'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'departments'=>$departments,
            'pageTitle'=>'Reporte diario','pageSubtitle'=>'Reporte diario operativo - Plaza Venezuela','activeMenu'=>'control_diario'
        ]);
    }

    public function form(?int $id=null): void {
        $user = requireAuth();
        $item = $id ? ReporteDiario::find($id) : null;
        if ($id && !$item) { http_response_code(404); exit('No encontrado.'); }
        $errors = [];
        $defaults = ['fecha'=>date('Y-m-d'),'usuario_id'=>$user['id'],'dependencia'=>$user['department'] ?? 'Plaza Venezuela','observaciones'=>''];
        foreach (ReporteDiario::numericFields() as $f) $defaults[$f] = 0;
        $old = $item ? array_merge($defaults, $item) : $defaults;
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[] = 'Sesión de seguridad inválida.';
            else {
                $old['fecha'] = sanitizeText($_POST['fecha'] ?? '', 20);
                $old['usuario_id'] = (int) $user['id'];
                $old['dependencia'] = sanitizeText($_POST['dependencia'] ?? 'Plaza Venezuela', 120) ?: 'Plaza Venezuela';
                $old['observaciones'] = sanitizeText($_POST['observaciones'] ?? '', 2000);
                foreach (ReporteDiario::numericFields() as $f) $old[$f] = max(0, (int)($_POST[$f] ?? 0));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha'])) $errors[] = 'Fecha inválida.';
                if ($old['dependencia'] === '') $errors[] = 'La dependencia es obligatoria.';
                // RIF total always recalculated server-side
                $old['rif_total_atendidos'] = ReporteDiario::calcRifTotal($old);
                if (!$errors) {
                    try {
                        if ($id) { ReporteDiario::update($id, $old); auditLog((int)$user['id'],'reporte_diario.update','info','reporte_diario',(string)$id); }
                        else { $nid = ReporteDiario::create($old); auditLog((int)$user['id'],'reporte_diario.create','info','reporte_diario',(string)$nid); }
                        redirect('control-diario.php?ok=1');
                    } catch (\Throwable $e) {
                        error_log($e->getMessage());
                        $errors[] = 'No se pudo guardar. Verifica que no exista ya un reporte para esa fecha y dependencia.';
                    }
                }
            }
        }
        $old['rif_total_atendidos'] = ReporteDiario::calcRifTotal($old);
        view('control_diario/form', [
            'app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,
            'pageTitle'=>$id?'Editar reporte diario':'Nuevo reporte diario','pageSubtitle'=>'Reporte diario operativo','activeMenu'=>'control_diario'
        ]);
    }

    public function delete(): void {
        $user = requireAnyRole(['admin','supervisor']);
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { ReporteDiario::delete($id); auditLog((int)$user['id'],'reporte_diario.delete','warning','reporte_diario',(string)$id); }
        redirect('control-diario.php?deleted=1');
    }

    public function export(): void {
        $user = requireAuth();
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $from = sanitizeText($_POST['from'] ?? '', 20);
        $to = sanitizeText($_POST['to'] ?? '', 20);
        $department = sanitizeText($_POST['department'] ?? '', 120);
        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';
        $rows = ReporteDiario::forExport($from ?: null, $to ?: null, $department ?: null);
        $headers = [
            'FECHA','DEPENDENCIA','USUARIO',
            'SOLICITUDES VIVIENDA RECIBIDAS','REGISTROS VIVIENDA ENTREGADOS','ORIENTACIONES','SOLICITUD COPIAS CERTIFICADAS','COPIAS CERTIFICADAS ENTREGADAS',
            'RIF CONTRIBUYENTES ATENDIDOS','INSC PN','ACT PN','INSC PJ','ACT PJ','INSC SUC','ACT SUC','INSC EMP','ACT EMP','INSC GOB','ACT GOB','INSC CC','ACT CC',
            'ASISTENCIA CONTRIBUYENTES','SUCESIONES ATENDIDOS','SUCESIONES INGRESADAS','SOLVENCIAS SUCESIONES ENTREGADAS','MULTAS NOTIFICADAS','PAPEL SEGURIDAD ANULADO','OBSERVACIONES'
        ];
        $data = [];
        $totals = array_fill(0, count($headers), '');
        $totals[0] = 'TOTALES';
        $sumIdx = range(3, 26); // numeric columns
        $sums = array_fill(0, count($headers), 0);
        foreach ($rows as $r) {
            $line = [
                date('d/m/Y', strtotime((string)$r['fecha'])),
                (string)($r['dependencia'] ?? ''),
                (string)($r['usuario'] ?? ''),
                (int)$r['vivienda_solicitudes_recibidas'], (int)$r['vivienda_registros_entregados'], (int)$r['orientaciones'], (int)$r['copias_solicitadas'], (int)$r['copias_entregadas'],
                (int)$r['rif_total_atendidos'], (int)$r['rif_insc_pn'], (int)$r['rif_act_pn'], (int)$r['rif_insc_pj'], (int)$r['rif_act_pj'], (int)$r['rif_insc_suc'], (int)$r['rif_act_suc'], (int)$r['rif_insc_emp'], (int)$r['rif_act_emp'], (int)$r['rif_insc_gob'], (int)$r['rif_act_gob'], (int)$r['rif_insc_cc'], (int)$r['rif_act_cc'],
                (int)$r['asistencia_contribuyentes'], (int)$r['sucesiones_atendidos'], (int)$r['sucesiones_ingresadas'], (int)$r['solvencias_sucesiones_entregadas'], (int)$r['multas_notificadas'], (int)$r['papel_seguridad_anulado'],
                (string)($r['observaciones'] ?? ''),
            ];
            foreach ($sumIdx as $i) $sums[$i] += (int)$line[$i];
            $data[] = array_map(fn($v) => is_int($v) ? (string)$v : (string)$v, $line);
        }
        foreach ($sumIdx as $i) $totals[$i] = (string)$sums[$i];
        if ($data) $data[] = $totals;
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'reporte_diario.export','info','reporte_diario',null,['from'=>$from,'to'=>$to,'rows'=>count($rows)]);
        exportRowsToXlsx('reporte_diario_' . date('Ymd_His') . '.xlsx', $headers, $data);
    }

    public function template(): void {
        requireAuth();
        require_once BASE_PATH . '/src/helpers/excel.php';
        $headers = ['FECHA','DEPENDENCIA','VIVIENDA_SOLICITUDES_RECIBIDAS','VIVIENDA_REGISTROS_ENTREGADOS','ORIENTACIONES','COPIAS_SOLICITADAS','COPIAS_ENTREGADAS','RIF_INSC_PN','RIF_ACT_PN','RIF_INSC_PJ','RIF_ACT_PJ','RIF_INSC_SUC','RIF_ACT_SUC','RIF_INSC_EMP','RIF_ACT_EMP','RIF_INSC_GOB','RIF_ACT_GOB','RIF_INSC_CC','RIF_ACT_CC','ASISTENCIA_CONTRIBUYENTES','SUCESIONES_ATENDIDOS','SUCESIONES_INGRESADAS','SOLVENCIAS_SUCESIONES_ENTREGADAS','MULTAS_NOTIFICADAS','PAPEL_SEGURIDAD_ANULADO','OBSERVACIONES'];
        exportRowsToXlsx('plantilla_reporte_diario.xlsx', $headers, [['2026-11-25','Plaza Venezuela','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','']]);
    }

    public function import(): void {
        $user = requireAnyRole(['admin','supervisor']);
        $errors=[]; $imported=0; $skipped=0; $details=[];
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[]='Sesión de seguridad inválida.';
            elseif (empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $errors[]='Selecciona un archivo CSV válido.';
            else {
                $name = (string)($_FILES['csv_file']['name'] ?? '');
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['csv','txt']; if (class_exists('ZipArchive')) $allowed[] = 'xlsx';
                if (!in_array($ext, $allowed, true)) $errors[]='Formato no soportado. Usa CSV' . (class_exists('ZipArchive') ? ' o XLSX' : '') . '.';
                elseif (($_FILES['csv_file']['size'] ?? 0) > 5*1024*1024) $errors[]='Archivo excede 5MB.';
                else {
                    require_once BASE_PATH . '/src/helpers/import.php';
                    $parsed = importReadFile((string)$_FILES['csv_file']['tmp_name']);
                    if (!$parsed['ok']) $errors[] = $parsed['error'];
                    else {
                        foreach ($parsed['rows'] as $row) {
                            $d = [];
                            $d['fecha'] = importDate($row, ['fecha','FECHA']);
                            $d['dependencia'] = sanitizeText(importVal($row,['dependencia','DEPENDENCIA'],'Plaza Venezuela'),120) ?: 'Plaza Venezuela';
                            $d['usuario_id'] = (int)$user['id'];
                            $d['observaciones'] = sanitizeText(importVal($row,['observaciones','OBSERVACIONES'],'[Importado]'),2000);
                            foreach (ReporteDiario::numericFields() as $f) $d[$f] = importInt($row, [$f, strtoupper($f)]);
                            $d['rif_total_atendidos'] = ReporteDiario::calcRifTotal($d);
                            if (!$d['fecha']) { $skipped++; $details[]='Fila '.$row['_row_num'].': fecha inválida.'; continue; }
                            try { ReporteDiario::create($d); $imported++; }
                            catch (\Throwable $e) {
                                // if duplicate, try update
                                try {
                                    $st=db()->prepare('SELECT id FROM reporte_diario WHERE fecha=:f AND dependencia=:d LIMIT 1');
                                    $st->execute(['f'=>$d['fecha'],'d'=>$d['dependencia']]); $existing=$st->fetchColumn();
                                    if ($existing) { ReporteDiario::update((int)$existing,$d); $imported++; }
                                    else { $skipped++; $details[]='Fila '.$row['_row_num'].': '.$e->getMessage(); }
                                } catch (\Throwable $e2) { $skipped++; $details[]='Fila '.$row['_row_num'].': no se pudo importar.'; }
                            }
                        }
                        auditLog((int)$user['id'],'reporte_diario.import','warning','reporte_diario',null,['imported'=>$imported,'skipped'=>$skipped]);
                    }
                }
            }
        }
        view('control_diario/import', ['app'=>$this->app,'user'=>$user,'errors'=>$errors,'imported'=>$imported,'skipped'=>$skipped,'details'=>array_slice($details,0,50),'pageTitle'=>'Importar reporte diario','pageSubtitle'=>'Carga histórica desde CSV','activeMenu'=>'control_diario']);
    }
}
