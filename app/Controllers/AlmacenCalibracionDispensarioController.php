<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenCalibracionDispensarioService as Service;
use App\Services\ModuleStationService;

/**
 * Calibración de Dispensarios — Almacén
 *
 * Nombre deliberadamente distinto de CalibracionDispensarioController, que pertenece
 * al módulo SASISOPA (bitácora de calibración de equipos).
 */
class AlmacenCalibracionDispensarioController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/calibracion-dispensarios';
    private const TITULO   = 'Calibración Dispensarios';

    public function index(): void
    {
        $title = self::TITULO;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = Service::getPermisos();

        // El legacy.armaba el breadcrumb según el puesto: Encargado y Asistente
        // Administrativo llegaban desde Almacén; el resto, desde Mantenimiento.
        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/calibracion-dispensarios/index', [
            'title'           => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija'    => $seleccion !== null,
            'periodos'        => Service::PERIODOS,
            'puedeCrear'      => $permisos['puedeCrear'],
            'puedeEditar'     => $permisos['puedeEditar'],
            'puedeEliminar'   => $permisos['puedeEliminar'],
            'puedeDescargar'  => $permisos['puedeDescargar'],
            'scripts'         => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/calibracion-dispensario.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/calibracion-dispensario.actions.init.js?v=' . time(),
            ],
            'links'           => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function data(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        // Request::input() fusiona GET + POST + JSON, que es lo que envía DataTable.
        // Request::query() no acepta argumentos: devuelve el array completo de $_GET.
        $idEstacion = (int)Request::input('id_estacion', 0);

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getData($idEstacion > 0 ? $idEstacion : null),
        ]);
    }

    public function store(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar calibraciones.');
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
            JsonResponse::forbidden('No tienes permiso para editar calibraciones.');
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
            JsonResponse::forbidden('No tienes permiso para eliminar calibraciones.');
        }

        $id = (int)(Request::input('id') ?? 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::destroy($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}
