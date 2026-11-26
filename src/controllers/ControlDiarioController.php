<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\ControlDiario;

class ControlDiarioController {
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
        $res = ControlDiario::search($filters, $limit, $offset);
        $totalPages = max(1, (int)ceil($res['total'] / $limit));
        $departments = db()->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        view('control_diario/index', [
            'app'=>$this->app,'user'=>$user,'items'=>$res['items'],'total'=>$res['total'],
            'page'=>$page,'totalPages'=>$totalPages,'filters'=>$filters,'departments'=>$departments,
            'pageTitle'=>'Control diario','pageSubtitle'=>'Registro diario y exportación a Excel','activeMenu'=>'control_diario'
        ]);
    }

    public function form(?int $id=null): void {
        $user = requireAuth();
        $item = $id ? ControlDiario::find($id) : null;
        if ($id && !$item) { http_response_code(404); exit('No encontrado.'); }
        $errors = [];
        $old = $item ?: [
            'fecha'=>date('Y-m-d'),'usuario_id'=>$user['id'],'expedientes_ingresados'=>0,'expedientes_procesados'=>0,
            'solvencias_emitidas'=>0,'multas_registradas'=>0,'requerimientos_atendidos'=>0,'observaciones'=>''
        ];
        if (isPost()) {
            if (!verifyCsrf($this->app)) $errors[] = 'Sesión de seguridad inválida.';
            else {
                $old['fecha'] = sanitizeText($_POST['fecha'] ?? '', 20);
                $old['usuario_id'] = (int) $user['id'];
                $old['expedientes_ingresados'] = max(0, (int)($_POST['expedientes_ingresados'] ?? 0));
                $old['expedientes_procesados'] = max(0, (int)($_POST['expedientes_procesados'] ?? 0));
                $old['solvencias_emitidas'] = max(0, (int)($_POST['solvencias_emitidas'] ?? 0));
                $old['multas_registradas'] = max(0, (int)($_POST['multas_registradas'] ?? 0));
                $old['requerimientos_atendidos'] = max(0, (int)($_POST['requerimientos_atendidos'] ?? 0));
                $old['observaciones'] = sanitizeText($_POST['observaciones'] ?? '', 2000);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha'])) $errors[] = 'Fecha inválida.';
                if (!$errors) {
                    try {
                        if ($id) { ControlDiario::update($id, $old); auditLog((int)$user['id'],'control_diario.update','info','control_diario',(string)$id); }
                        else { $nid = ControlDiario::create($old); auditLog((int)$user['id'],'control_diario.create','info','control_diario',(string)$nid); }
                        redirect('control-diario.php?ok=1');
                    } catch (\Throwable $e) {
                        error_log($e->getMessage());
                        $errors[] = 'No se pudo guardar. Verifica que no exista ya un registro para esa fecha y usuario.';
                    }
                }
            }
        }
        view('control_diario/form', [
            'app'=>$this->app,'user'=>$user,'item'=>$old,'errors'=>$errors,'isEdit'=>(bool)$id,
            'pageTitle'=>$id?'Editar control diario':'Nuevo control diario','pageSubtitle'=>'Registro diario operativo','activeMenu'=>'control_diario'
        ]);
    }

    public function delete(): void {
        $user = requireAnyRole(['admin','supervisor']);
        if (!isPost() || !verifyCsrf($this->app)) { http_response_code(400); exit('Solicitud inválida.'); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { ControlDiario::delete($id); auditLog((int)$user['id'],'control_diario.delete','warning','control_diario',(string)$id); }
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
        $rows = ControlDiario::forExport($from ?: null, $to ?: null, $department ?: null);
        $headers = ['Fecha','Usuario','Departamento','Expedientes ingresados','Expedientes procesados','Solvencias emitidas','Multas registradas','Requerimientos atendidos','Observaciones'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                date('d/m/Y', strtotime((string)$r['fecha'])),
                (string)($r['usuario'] ?? ''),
                (string)($r['departamento'] ?? ''),
                (string)$r['expedientes_ingresados'],
                (string)$r['expedientes_procesados'],
                (string)$r['solvencias_emitidas'],
                (string)$r['multas_registradas'],
                (string)$r['requerimientos_atendidos'],
                (string)($r['observaciones'] ?? ''),
            ];
        }
        require_once BASE_PATH . '/src/helpers/excel.php';
        auditLog((int)$user['id'],'control_diario.export','info','control_diario',null,['from'=>$from,'to'=>$to,'rows'=>count($data)]);
        exportRowsToXlsx('control_diario_' . date('Ymd_His') . '.xlsx', $headers, $data);
    }
}
