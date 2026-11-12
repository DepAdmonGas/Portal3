<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenMedicionNivelExplosividadService as Service;
use App\Services\ModuleStationService;

/**
 * Medición Nivel de Explosividad — Almacén
 *
 * Un único conjunto de rutas para todos los puestos: lo que cambia son los
 * permisos que devuelve el Service (C1), no la vista.
 *
 * El legacy no tenía edición de registros finalizados: estado 1 es de sólo
 * lectura y estado 0 es el borrador editable/eliminable.
 */
class AlmacenMedicionNivelExplosividadController extends BaseController
{
    private const BASE_URL       = '/departamento-operativo/almacen/medicion-nivel-explosividad';
    private const FORM_BASE_URL  = '/departamento-operativo/almacen/medicion-nivel-explosividad-formulario';
    private const DETALLE_BASE_URL = '/departamento-operativo/almacen/medicion-nivel-explosividad-detalle';
    private const TITULO         = 'Medición Nivel de Explosividad';

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

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/medicion-nivel-explosividad/index', [
            'title'            => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'estacionFija'     => $seleccion !== null,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/medicion-nivel-explosividad.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/medicion-nivel-explosividad.actions.init.js?v=' . time(),
            ],
            'links'            => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function data(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        // Request::input() fusiona GET + POST + JSON, que es lo que envía DataTable.
        $idEstacion = (int)Request::input('id_estacion', 0);

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getData($idEstacion > 0 ? $idEstacion : null),
        ]);
    }

    /**
     * GET /nuevo — crea el borrador y abre el formulario.
     *
     * El legacy hacía POST a agregar-nivel-explosividad.php y usaba el id devuelto
     * para navegar al formulario: sin ese paso no existiría la fila rosa que permite
     * retomar el registro si el usuario abandona.
     */
    public function crear(): void
    {
        $title = self::TITULO;

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $permisos = Service::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar mediciones.');
        }

        $resultado = Service::crearBorrador();

        if (!($resultado['success'] ?? false)) {
            JsonResponse::custom($resultado, $resultado['code'] ?? 400);
        }

        header('Location: ' . self::FORM_BASE_URL . '/' . $resultado['id']);
        exit;
    }

    /**
     * GET /formulario/{id} — formulario de captura, sólo con borradores.
     */
    public function editar(int $id): void
    {
        $title = self::TITULO;

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $permisos = Service::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar mediciones.');
        }

        $reporte = Service::getRegistro($id);

        if (!$reporte) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        if ((int)$reporte->estado !== 0) {
            JsonResponse::custom([
                'success' => false,
                'message' => 'El registro ya está finalizado y sólo puede consultarse.',
                'code'    => 409,
            ], 409);
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add($title, self::BASE_URL);
        Breadcrumb::add('Formulario' . ' (#00' . $reporte->folio . ')', '');

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        $idEstacion = (int)$reporte->id_estacion;

        View::render('departamento-operativo/4-almacen/medicion-nivel-explosividad/formulario', [
            'title'            => $title . ' (#00' . $reporte->folio . ')',
            'moduleStationKey' => Service::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'modo'             => 'editar',
            'idReporte'        => $id,
            'folio'            => '00' . $reporte->folio,
            'idEstacion'       => $idEstacion,
            'estacionFija'     => $seleccion !== null,
            'etiquetas'        => Service::ETIQUETAS,
            'datos'            => Service::getCamposFormulario($id),
            'pozos'            => Service::getPozos($id),
            'encargados'       => Service::getEncargados($idEstacion),
            'opcionesPozo'     => Service::getOpcionesPozo($idEstacion),
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/medicion-nivel-explosividad.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    /**
     * GET /detalle/{id} — página de detalle, sólo con registros finalizados.
     */
    public function detalle(int $id): void
    {
        $title = self::TITULO;

        if (!$this->guardModuleAccess(Service::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $detalle = Service::getDetalle($id);

        if ($detalle === null) {
            JsonResponse::notFound('Registro no encontrado. Los borradores no tienen detalle.');
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        if (Service::getPermisos()['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add($title, self::BASE_URL);
        Breadcrumb::add('Detalle', '');

        $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        // El layout muestra un badge con $detalle['estacion_nombre']; el selector
        // de estación del módulo ya muestra el mismo dato, así se evita duplicarlo.
        unset($detalle['estacion_nombre']);

        View::render('departamento-operativo/4-almacen/medicion-nivel-explosividad/detalle', [
            'title'            => $title . '(#' . $id . ')',
            'moduleStationKey' => Service::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'detalle'          => $detalle,
            'estacionFija'     => $seleccion !== null,
            'etiquetas'        => Service::ETIQUETAS,
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/medicion-nivel-explosividad.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    /**
     * POST /guardar — finaliza un borrador.
     */
    public function store(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para registrar mediciones.');
        }

        $result = Service::guardar(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        if (!Service::getPermisos()['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar registros.');
        }

        $id = (int)Request::input('id', 0);

        if ($id <= 0) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $result = Service::destroy($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function agregarPozo(): void
    {
        $result = Service::agregarPozo(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function eliminarPozo(): void
    {
        $result = Service::eliminarPozo(
            (int)Request::input('idNivel', 0),
            (int)Request::input('idReporte', 0)
        );
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}
