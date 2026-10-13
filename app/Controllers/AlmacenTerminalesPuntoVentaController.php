<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenTerminalesPuntoVentaService as Service;
use App\Services\ModuleStationService;
use App\Services\MultiestacionService;

/**
 * Terminales Punto de Venta — Almacén
 *
 * CRUD del listado de terminales (migración del módulo legacy
 * "terminales-tpv" de departamento-operativo).
 */
class AlmacenTerminalesPuntoVentaController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/terminales-punto-venta';
    private const TITULO   = 'Terminales Punto de Venta';

    public function index(): void
    {
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

        if (!$permisos['puedeLeer']) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

$contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        // Misma regla que usa ModuleStationService::render() para decidir si
        // dibuja el <select>: sólo esos usuarios pueden cambiar de estación, y
        // por lo tanto son los únicos que ven el botón de "Fallas TPV".
        $stations     = ModuleStationService::getAvailableStations(Service::MODULE_KEY);
        $depts        = ModuleStationService::getAvailableDepartments(Service::MODULE_KEY);
        $hasChoices   = (count($stations) > 1) || (!empty($depts) && !empty($stations));
        $puedeUsarSelector = MultiestacionService::isEnabled() || $hasChoices;

         View::render('departamento-operativo/4-almacen/terminales-punto-venta/index', [
            'title'            => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija'     => $seleccion !== null,
            'estacionActual'   => $seleccion ?? 0,
            'esUsuarioEstacion' => $permisos['esUsuarioEstacion'],
            'puedeUsarSelector' => $puedeUsarSelector,
            'catalogosJson'    => json_encode(Service::getCatalogos(), JSON_UNESCAPED_UNICODE),
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeEliminarRegistro' => Service::puedeEliminarTerminal(),
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/terminales-punto-venta.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/terminales-punto-venta.actions.init.js?v=' . time(),
            ],
            'links'            => [
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

        $idEstacion = Request::input('id_estacion');
        $filtro = ($idEstacion !== null && $idEstacion !== '' && (int)$idEstacion > 0)
            ? (int)$idEstacion
            : null;

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getData($filtro),
        ]);
    }

    public function store(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar terminales.');
        }

        $idEstacion = (int)Request::input('id_estacion', 0);

        if ($idEstacion <= 0) {
            JsonResponse::validation('Selecciona la estación de la terminal.', [
                'id_estacion' => 'Selecciona la estación de la terminal.',
            ]);
        }

        $datos  = Service::normalizarEntrada($_POST);
        $errores = Service::validar($datos);

        if (!empty($errores)) {
            JsonResponse::validation('Revisa los campos obligatorios.', $errores);
        }

        $resultado = Service::store($idEstacion, $datos);

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function update(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar terminales.');
        }

        $id = (int)Request::input('id', 0);

        if ($id <= 0) {
            JsonResponse::notFound('Terminal no encontrada.');
        }

        $datos   = Service::normalizarEntrada($_POST);
        $errores = Service::validar($datos);

        if (!empty($errores)) {
            JsonResponse::validation('Revisa los campos obligatorios.', $errores);
        }

        $resultado = Service::update($id, $datos);

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function detalle(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)Request::input('id', 0);

        if ($id <= 0) {
            JsonResponse::notFound('Terminal no encontrada.');
        }

        $detalle = Service::getDetalle($id);

        if ($detalle === null) {
            JsonResponse::notFound('Terminal no encontrada.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => $detalle,
        ]);
    }

    public function destroy(): void
    {
        $permisos = Service::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar terminales.');
        }

        // Eliminar es exclusivo de los usuarios multiestación; el front sólo
        // deshabilita la opción, aquí se vuelve a validar.
        if (!Service::puedeEliminarTerminal()) {
            JsonResponse::forbidden('Sólo los usuarios de multiestación pueden eliminar terminales.');
        }

        $id = (int)Request::input('id', 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $resultado = Service::destroy($id);

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }
}
