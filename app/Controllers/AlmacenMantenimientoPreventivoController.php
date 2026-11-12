<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenMantenimientoPreventivoService as Service;
use App\Services\DropdownYearMesService;
use App\Services\ModuleStationService;

/**
 * Mantenimiento Preventivo — Almacén
 *
 * Nombre deliberadamente distinto de MantenimientoPreventivoController, que pertenece
 * al módulo SASISOPA (programas de mantenimiento en la bitácora de equipos).
 */
class AlmacenMantenimientoPreventivoController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/mantenimiento-preventivo';
    private const TITULO   = 'Mantenimiento Preventivo';

    public function index(?int $idYear = null, ?int $idMes = null): void
    {
        $valid = DropdownYearMesService::validarYearMes($idYear, $idMes ?? (int)date('n'));
        $idYear = $valid['idYear'];
        $idMes  = $valid['idMes'];

        $title = self::TITULO;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = Service::getPermisos();

        // El legacy armaba el breadcrumb según el puesto: Encargado y Asistente
        // Administrativo llegaban desde Almacén; el resto, desde Mantenimiento.
        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add($title, '');

        // El legacy ponía el dropdown del año dentro del breadcrumb (for 2023 → actual).
        $yearMesTemplate = self::BASE_URL . '/{year}';
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, 2023, $yearMesTemplate), '');

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        $pendientesData   = Service::getPendientes($idYear);
        $pendientesActual = $seleccion !== null
            ? ($pendientesData['estacion_' . $seleccion] ?? 0)
            : $pendientesData['total'];

        View::render('departamento-operativo/4-almacen/mantenimiento-preventivo/index', [
            'title'             => $title,
            'moduleStationKey'  => Service::MODULE_KEY,
            'estacionFija'      => $seleccion !== null,
            'estacionActual'    => $seleccion ?? 0,
            'esUsuarioEstacion' => $permisos['esUsuarioEstacion'],
            'year'              => $idYear,
            'yearMesTemplate'   => $yearMesTemplate,
            'tipos'             => Service::getTipos(),
            'pendientesData'    => $pendientesData,
            'pendientesJson'    => json_encode($pendientesData, JSON_UNESCAPED_UNICODE),
            'pendientesActual'  => $pendientesActual,
            'puedeCrear'        => $permisos['puedeCrear'],
            'puedeEditar'       => $permisos['puedeEditar'],
            'puedeEliminar'     => $permisos['puedeEliminar'],
            'puedeDescargar'    => $permisos['puedeDescargar'],
            'scripts'           => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/libs/select2/dist/js/select2.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/mantenimiento-preventivo.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/mantenimiento-preventivo.actions.init.js?v=' . time(),
            ],
            'links'             => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function calendario(): void
    {
        $title = 'Calendario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = Service::getPermisos();

        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add('Mantenimiento Preventivo', '/departamento-operativo/almacen/mantenimiento-preventivo');

        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/mantenimiento-preventivo/calendario', [
            'title'            => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija'     => $seleccion !== null,
            'estacionActual'   => $seleccion ?? 0,
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/fullcalendar/index.global.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/mantenimiento-preventivo.calendario.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function calendarioEventos(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $idEstacion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        if ($idEstacion === null || $idEstacion <= 0) {
            JsonResponse::custom(['success' => false, 'eventos' => [], 'totales' => [], 'mensaje' => 'Selecciona una estación.']);
        }

        $inicio = (string)(Request::input('start') ?? '');
        $fin    = (string)(Request::input('end') ?? '');

        $resultado = Service::getCalendarioEventos($idEstacion, $inicio, $fin);

        JsonResponse::custom([
            'success' => true,
            'eventos' => $resultado['eventos'],
            'totales' => $resultado['totales'],
            'estacion' => $resultado['estacion'],
        ]);
    }

    public function calendarioDia(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $contexto   = ModuleStationService::getContext(Service::MODULE_KEY);
        $idEstacion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        if ($idEstacion === null || $idEstacion <= 0) {
            JsonResponse::custom(['success' => false, 'data' => []]);
        }

        $fecha = (string)(Request::input('fecha') ?? '');

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getCalendarioDia($idEstacion, $fecha),
        ]);
    }

    public function data(int $idYear): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idEstacion = (int)Request::input('id_estacion', 0);

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getData($idEstacion > 0 ? $idEstacion : null, $idYear),
        ]);
    }

    public function store(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar mantenimientos.');
        }

        $result = Service::store($_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function detalle(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id      = (int)(Request::input('id') ?? 0);
        $detalle = Service::getDetalle($id);

        if ($detalle === null) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        JsonResponse::custom(['success' => true, 'data' => $detalle]);
    }

    public function update(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar mantenimientos.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::update($id, $_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar mantenimientos.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::destroy($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function actualizarStatus(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para actualizar el estatus.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::actualizarStatus($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function encargados(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idEstacion = (int)(Request::input('id_estacion') ?? 0);

        if ($idEstacion <= 0) {
            JsonResponse::validation('Estación no válida.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getEncargados($idEstacion),
        ]);
    }

    public function tipos(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getTipos(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Prueba de Eficiencia                                                 */
    /* ------------------------------------------------------------------ */

    public function dataArchivosPrueba(int $idYear): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idEstacion = (int)(ModuleStationService::getContext(Service::MODULE_KEY)['id_estacion'] ?? 0);

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getDocumentos($idEstacion > 0 ? $idEstacion : null, $idYear),
        ]);
    }

    public function guardarArchivoPrueba(int $idYear): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para agregar pruebas de eficiencia.');
        }

        $result = Service::guardarDocumento($idYear, $_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function eliminarArchivoPrueba(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar pruebas de eficiencia.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Documento no encontrado.');
        }

        $result = Service::eliminarDocumento($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}