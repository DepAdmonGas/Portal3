<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\JsonResponse;
use App\Services\ProgramarHorarioService;
use App\Models\Operativo\RhPersonalHorarioProgramar;

class ProgramarHorarioController extends BaseController
{
    public function index()
    {
        $permisos = ProgramarHorarioService::getPermisos();
        $ctx = ProgramarHorarioService::getContexto();

        $title = 'Programar Horario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(ProgramarHorarioService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $sessionUsuario = Session::get('usuario');
        $idGas = (int)($sessionUsuario['id_estacion'] ?? 0);

        View::render('departamento-operativo/2-recursos-humanos/programar-horario/index', [
            'title'            => $title,
            'ctx'              => $ctx,
            'multiestacion'    => $permisos['multiestacion'],
            'moduleStationKey' => ProgramarHorarioService::MODULE_KEY,
            'puedeCrear'       => $permisos['puedeCrear'],
            'idGas'            => $idGas,
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/programar-horario.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/programar-horario.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $datos = ProgramarHorarioService::getReportes();
        JsonResponse::custom([
            'success'  => true,
            'data'     => $datos['rows'],
            'contexto' => $datos['contexto'],
        ]);
    }

    public function nuevo()
    {
        $idEstacion = ProgramarHorarioService::resolverIdEstacionContexto();
        if (!$idEstacion) {
            header('Location: /departamento-operativo/recursos-humanos/programar-horario');
            exit;
        }

        $id = ProgramarHorarioService::crearReporteInicial($idEstacion);
        header('Location: /departamento-operativo/recursos-humanos/programar-horario-formulario/' . $id);
        exit;
    }

    public function formulario($id = null)
    {
        $idReporte = (int)($id ?? 0);
$title = "Programar Horario (# $idReporte)";

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Programar Horario', '/departamento-operativo/recursos-humanos/programar-horario');

            Breadcrumb::add($title, '');
  

        View::render('departamento-operativo/2-recursos-humanos/programar-horario/formulario', [
            'title'                   => $title,
            'idReporte'               => $idReporte,
            'ctx'                     => ProgramarHorarioService::getContexto(),
            'moduleStationKey'        => ProgramarHorarioService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'help'                    => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/programar-horario.nuevo.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getDetalle()
    {
        $idReporte = (int)($_GET['id'] ?? 0);

        try {
            $detalle = ProgramarHorarioService::getDetalle($idReporte);
            JsonResponse::custom([
                'success'  => true,
                'data'     => $detalle['rows'],
                'reporte'  => $detalle['reporte'],
            ]);
        } catch (\Throwable $e) {
            JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function detalle($id = null)
    {
        $idReporte = (int)($id ?? $_GET['id'] ?? 0);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Programar Horario', '/departamento-operativo/recursos-humanos/programar-horario');

        if (!$idReporte) {
            View::render('partials/_module-blocked', [
                'title' => 'Programar Horario - Reporte no encontrado',
            ], 'departamento-operativo');
            return;
        }

        Breadcrumb::add('Detalle Reporte #' . $idReporte, '');

        View::render('departamento-operativo/2-recursos-humanos/programar-horario/detalle', [
            'title'                   => 'Detalle Programar Horario',
            'idReporte'               => $idReporte,
            'ctx'                     => ProgramarHorarioService::getContexto(),
            'moduleStationKey'        => ProgramarHorarioService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'help'                    => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/programar-horario.detalle.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function editarTurno()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $idPersonal = (int)($data['id_personal'] ?? 0);
        $dia = (int)($data['dia'] ?? 0);
        $horario = trim((string)($data['horario'] ?? ''));
        $idEstacion = (int)($data['id_estacion'] ?? 0);

        if (!$idPersonal || !$dia || !$idEstacion) {
            JsonResponse::custom(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        try {
            $celda = ProgramarHorarioService::editarTurno($idReporte, $idPersonal, $dia, $horario, $idEstacion);
            JsonResponse::custom(['success' => true, 'message' => 'Turno actualizado.', 'celda' => $celda]);
        } catch (\Throwable $e) {
            JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function actualizarFecha()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $fecha = trim((string)($data['fecha'] ?? ''));

        if (!$idReporte || $fecha === '') {
            JsonResponse::custom(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        try {
            ProgramarHorarioService::actualizarFecha($idReporte, $fecha);
            JsonResponse::custom(['success' => true, 'message' => 'Fecha actualizada correctamente.']);
        } catch (\Throwable $e) {
            JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function guardar()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $fecha = trim((string)($data['fecha'] ?? ''));

        if ($fecha === '') {
            JsonResponse::custom(['success' => false, 'message' => 'Datos incompletos. Se requiere fecha.']);
            return;
        }

        try {
            if ($idReporte > 0) {
                $idGuardado = ProgramarHorarioService::guardarReporte($idReporte, $fecha);
            } else {
                $detalles = $data['detalles'] ?? [];
                if (empty($detalles)) {
                    JsonResponse::custom(['success' => false, 'message' => 'Debe programar al menos un turno antes de guardar.']);
                    return;
                }
                $idEstacion = ProgramarHorarioService::resolverIdEstacionContexto();
                if (!$idEstacion) {
                    JsonResponse::custom(['success' => false, 'message' => 'No se pudo determinar la estación.']);
                    return;
                }
                $idGuardado = ProgramarHorarioService::guardarReporte(0, $fecha, $idEstacion, $detalles);
            }
            JsonResponse::custom([
                'success' => true,
                'message' => $idReporte > 0 ? 'Horario actualizado correctamente.' : 'Horario guardado y programado correctamente.',
                'id'      => $idGuardado,
            ]);
        } catch (\Throwable $e) {
            JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function eliminar()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id'] ?? 0);

        if (!$idReporte) {
            JsonResponse::custom(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        try {
            ProgramarHorarioService::validarEliminar($idReporte);
            ProgramarHorarioService::eliminarReporte($idReporte);
            JsonResponse::custom(['success' => true, 'message' => 'Reporte eliminado correctamente.']);
        } catch (\Throwable $e) {
            JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
