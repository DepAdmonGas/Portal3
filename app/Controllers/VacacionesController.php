<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\FormatosService;
use App\Services\ModuleStationService;
use App\Services\VacacionesService;

class VacacionesController extends BaseController
{
    public function index($idYear = null)
    {
        $permisos = VacacionesService::getPermisos();
        if (!$permisos['puede_leer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $year = $this->validarYear($idYear);
        $counts = VacacionesService::getConteosPendientes($year);
        $countsSelector = $this->conteosParaSelector($counts);
        $currentContext = VacacionesService::getContexto();
        $title = 'Vacaciones (' . $year . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Vacaciones', '/departamento-operativo/recursos-humanos/vacaciones');
        Breadcrumb::add($this->dropdownYear($year), '');

        $localidadesPermitidas = VacacionesService::getLocalidadesPermitidas();
        $contextoSeleccionado = $currentContext['id_depto'] ?? $currentContext['id_estacion'] ?? null;
        $usuarioSesion = Session::get('usuario') ?? [];
        $estacionesDisponibles = ModuleStationService::getAvailableStations(VacacionesService::MODULE_KEY);
        $departamentosDisponibles = ModuleStationService::getAvailableDepartments(VacacionesService::MODULE_KEY);
        $haySelectorContexto = !empty($usuarioSesion['multiestacion'])
            || count($estacionesDisponibles) > 1
            || ($estacionesDisponibles !== [] && $departamentosDisponibles !== []);
        if ($contextoSeleccionado === null && count($localidadesPermitidas) === 1 && !$haySelectorContexto) {
            $contextoSeleccionado = (int)$localidadesPermitidas[0]['id'];
        }

        View::render('departamento-operativo/2-recursos-humanos/vacaciones/index', [
            'title' => $title,
            'year' => $year,
            'puedeCrear' => $permisos['puede_crear'],
            'puedeEditar' => $permisos['puede_editar'],
            'puedeEliminar' => $permisos['puede_eliminar'],
            'puedeDescargar' => $permisos['puede_descargar'],
            'puedeFirmar' => $permisos['puede_firmar'],
            'idUsuario' => $permisos['id_usuario'],
            'mostrarEstacion' => VacacionesService::mostrarColumnaEstacion(),
            'contextoSeleccionado' => (int)($contextoSeleccionado ?? 0),
            'pendientesActuales' => (int)($counts['contexto'] ?? 0),
            'moduleStationKey' => VacacionesService::MODULE_KEY,
            'pendientesData' => $countsSelector,
            'contexto' => $currentContext,
            'help' => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/vacaciones.datatable.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function data($idYear)
    {
        if (!VacacionesService::getPermisos()['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Vacaciones.');
        }

        $year = $this->validarYear($idYear);
        JsonResponse::custom([
            'success' => true,
            'data' => VacacionesService::getLista($year),
            'mostrar_estacion' => VacacionesService::mostrarColumnaEstacion(),
        ]);
    }

    public function pendientes($idYear)
    {
        if (!VacacionesService::getPermisos()['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Vacaciones.');
        }
        JsonResponse::custom(VacacionesService::getConteosPendientes($this->validarYear($idYear)));
    }

    public function detalle()
    {
        if (!VacacionesService::getPermisos()['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Vacaciones.');
        }
        $id = (int)Request::input('id', 0);
        $detalle = VacacionesService::getSolicitud($id);
        if (!$detalle) JsonResponse::notFound('La solicitud no existe o no pertenece a tu alcance.');
        JsonResponse::custom(['success' => true, 'detalle' => $detalle]);
    }

    public function historial()
    {
        if (!VacacionesService::getPermisos()['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Vacaciones.');
        }

        $idPersonal = (int)Request::input('id_personal', 0);
        $year = $this->validarYear(Request::input('year'));
        $historial = VacacionesService::getHistorialPersonal($idPersonal, $year);
        if ($historial === null) JsonResponse::notFound('El personal no pertenece al contexto seleccionado.');

        JsonResponse::custom(['success' => true, 'historial' => $historial]);
    }

    public function comentarios()
    {
        if (!VacacionesService::getPermisos()['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Vacaciones.');
        }

        $idPersonal = (int)Request::input('id_personal', 0);
        $year = $this->validarYear(Request::input('year'));
        if (!VacacionesService::puedePersonalEnContexto($idPersonal)) {
            JsonResponse::notFound('El personal no pertenece al contexto seleccionado.');
        }

        $empleado = VacacionesService::getDetallePersonal($idPersonal, $year);
        JsonResponse::custom([
            'success' => true,
            'comentarios' => VacacionesService::getComentarios($idPersonal, $year),
            'detalle' => $empleado,
        ]);
    }

    public function guardarComentario()
    {
        $permisos = VacacionesService::getPermisos();
        if (!$permisos['puede_leer']) {
            JsonResponse::forbidden('No tienes permiso para agregar comentarios.');
        }

        $idPersonal = (int)Request::input('id_personal', 0);
        $year = $this->validarYear(Request::input('year'));
        $comentario = (string)Request::input('comentario', '');
        $idUsuario = (int)$permisos['id_usuario'];

        $result = VacacionesService::guardarComentario($idPersonal, $year, $idUsuario, $comentario);
        JsonResponse::custom($result, $result['success'] ? 200 : 422);
    }

    public function nuevo($idLocalidad)
    {
        $permisos = VacacionesService::getPermisos();
        $idLocalidad = (int)$idLocalidad;
        if (!$permisos['puede_crear'] || !VacacionesService::puedeRegistroEnContexto($idLocalidad)) {
            http_response_code(403);
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $estaciones = array_map('intval', array_column(ModuleStationService::getAvailableStations(FormatosService::MODULE_KEY), 'id'));
        $departamentos = array_map('intval', array_column(ModuleStationService::getAvailableDepartments(FormatosService::MODULE_KEY), 'id'));
        if (in_array($idLocalidad, $estaciones, true)) {
            ModuleStationService::setContext(FormatosService::MODULE_KEY, $idLocalidad, null);
        } elseif (in_array($idLocalidad, $departamentos, true)) {
            ModuleStationService::setContext(FormatosService::MODULE_KEY, null, $idLocalidad);
        } else {
            http_response_code(403);
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        header('Location: /departamento-operativo/recursos-humanos/formatos-formulario/6/' . $idLocalidad);
        exit;
    }

    public function editar($idFormulario)
    {
        $permisos = VacacionesService::getPermisos();
        $detalle = VacacionesService::getSolicitud((int)$idFormulario);
        if (!$permisos['puede_editar'] || !$detalle || (int)$detalle['status'] !== 0) {
            http_response_code(403);
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        header('Location: /departamento-operativo/recursos-humanos/formatos-editar/' . (int)$idFormulario);
        exit;
    }

    public function eliminar()
    {
        $permisos = VacacionesService::getPermisos();
        if (!$permisos['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar solicitudes.');
        }

        $id = (int)Request::input('id', 0);
        $detalle = VacacionesService::getSolicitud($id);
        if (!$detalle) JsonResponse::notFound('La solicitud no existe o no pertenece a tu contexto.');
        if ((int)$detalle['status'] >= 3) {
            JsonResponse::conflict('No se puede eliminar una solicitud finalizada.');
        }

        $result = FormatosService::eliminarFormato($id, (int)$permisos['id_usuario']);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    private function conteosParaSelector(array $counts): array
    {
        $result = ['total' => (int)($counts['total'] ?? 0)];
        foreach (ModuleStationService::getAvailableStations(VacacionesService::MODULE_KEY) as $row) {
            $id = (int)$row['id'];
            $result['estacion_' . $id] = (int)($counts['localidad_' . $id] ?? 0);
        }
        foreach (ModuleStationService::getAvailableDepartments(VacacionesService::MODULE_KEY) as $row) {
            $id = (int)$row['id'];
            $result['depto_' . $id] = (int)($counts['localidad_' . $id] ?? 0);
        }
        return $result;
    }

    private function validarYear($year): int
    {
        $actual = (int)date('Y');
        $year = filter_var($year, FILTER_VALIDATE_INT);
        return $year !== false && $year >= 2020 && $year <= $actual + 1 ? (int)$year : $actual;
    }

    private function dropdownYear(int $selectedYear): string
    {
        $currentYear = (int)date('Y');
        $html = '<a class="dropdown-toggle breadcrumb-item active pointer" role="button" data-bs-toggle="dropdown" aria-expanded="false">'
            . '<i class="ti ti-calendar"></i> <span class="ms-1">' . $selectedYear . '</span></a>'
            . '<ul class="dropdown-menu animated rubberBand">';

        for ($year = $currentYear; $year >= 2020; $year--) {
            $url = '/departamento-operativo/recursos-humanos/vacaciones/' . $year;
            $html .= '<li><a class="dropdown-item" href="' . $url . '">'
                . '<i class="ti ti-calendar"></i> <span class="ms-1">' . $year . '</span></a></li>';
        }

        return $html . '</ul>';
    }
}
