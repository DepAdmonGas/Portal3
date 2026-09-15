<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\CuentaLitrosService;
use App\Services\DropdownYearMesService;
use App\Services\ModuleStationService;
 use App\Core\Session;

class CuentaLitrosController extends BaseController
{
    public function index()
    {
        $validated = DropdownYearMesService::validarYearMes(0, 0);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $this->renderIndex($idYear, $idMes);
    }

    public function filtrarPorAnioMes(int $year, int $mes)
    {
        $validated = DropdownYearMesService::validarYearMes($year, $mes);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $this->renderIndex($idYear, $idMes);
    }

    private function renderIndex(int $idYear, int $idMes): void
    {
        $permisos = CuentaLitrosService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Cuenta Litros (' . nombremes($idMes) . ' ' . $idYear . ')';

        $ctx = ModuleStationService::getContext(CuentaLitrosService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : 0;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, 2023), '');

        $yearMesTemplate = '/departamento-operativo/importacion/cuenta-litros/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/cuenta-litros/index', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idEstacion'       => $idEstacion,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'yearMesTemplate'  => $yearMesTemplate,
            'moduleStationKey' => CuentaLitrosService::MODULE_KEY,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/cuenta-litros.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/cuenta-litros.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        $input = Request::all();
        $year = !empty($input['year']) ? (int)$input['year'] : (int)date('Y');
        $mes = !empty($input['mes']) ? (int)$input['mes'] : (int)date('n');
        $idEstacion = isset($input['id_estacion']) && $input['id_estacion'] !== ''
            ? (int)$input['id_estacion']
            : CuentaLitrosService::getEstacionSeleccionada();

        JsonResponse::success('OK', [
            'data'     => CuentaLitrosService::getListado($year, $mes, $idEstacion),
            'permisos' => $permisos,
        ]);
    }

    public function crear()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permisos para crear registros.');
        }

        $idEstacion = CuentaLitrosService::getEstacionSeleccionada();
        if ($idEstacion === null) {
            JsonResponse::error('Selecciona una estación para registrar el formato.');
        }
        if (!CuentaLitrosService::estacionAutorizada($idEstacion)) {
            JsonResponse::error('No tienes acceso a la estación seleccionada.');
        }

        $fecha = trim((string)Request::input('fecha', ''));
        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            JsonResponse::error('Fecha inválida.');
        }

        $year = (int)$fechaObj->format('Y');
        $mes = (int)$fechaObj->format('n');

        try {
            $id = CuentaLitrosService::crear($idEstacion, $year, $mes, $fecha);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Registro creado exitosamente.', ['id' => $id]);
    }

    public function formato(int $id)
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        try {
            $vista = CuentaLitrosService::getVista($id, 'formato');
        } catch (\Throwable $e) {
            View::render('errors/404', ['message' => $e->getMessage()], 'departamento-operativo');
            return;
        }

        $title = 'Formato (' . $vista['fecha_larga'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Cuenta Litros', '/departamento-operativo/importacion/cuenta-litros');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/cuenta-litros/formato', array_merge([
            'title'     => $title,
            'breadcrumbExtra' => '',
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/cuenta-litros.formato.actions.init.js?v=' . time(),
            ],
        ], $vista), 'departamento-operativo');
    }

    public function detalle(int $id)
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        try {
            $vista = CuentaLitrosService::getVista($id, 'detalle');
        } catch (\Throwable $e) {
            View::render('errors/404', ['message' => $e->getMessage()], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(CuentaLitrosService::MODULE_KEY, 'Cuenta Litros', 'departamento-operativo')) {
        return;
        }

        $title = 'Detalle (' . $vista['fecha_larga'] . ')';  

        $contextoCtx = Session::get('module_context') ?? [];
        $contextoAnterior = $contextoCtx[CuentaLitrosService::MODULE_KEY] ?? null;

        ModuleStationService::setContext(CuentaLitrosService::MODULE_KEY, $vista['id_estacion']);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Cuenta Litros', '/departamento-operativo/importacion/cuenta-litros');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/cuenta-litros/detalle', array_merge([
            'title' => $title,
      'moduleStationKey' => CuentaLitrosService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/cuenta-litros.detalle.init.js?v=' . time(),
            ],
        ], $vista), 'departamento-operativo');


        $ctxRestaurado = Session::get('module_context') ?? [];
        if ($contextoAnterior === null) {
            unset($ctxRestaurado[CuentaLitrosService::MODULE_KEY]);
        } else {
            $ctxRestaurado[CuentaLitrosService::MODULE_KEY] = $contextoAnterior;
        }
        
       Session::set('module_context', $ctxRestaurado);

    }

    public function agregarDetalle()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para agregar descargas.');
        }

        $input = Request::all();
        $id = (int)($input['id_cuenta_litros'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID de registro inválido.');
        }

        $datos = $this->extraerDatosDescarga($input);
        $archivo = $_FILES['archivo'] ?? [];

        try {
            CuentaLitrosService::agregarDetalle($id, $datos, $archivo);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Descarga agregada exitosamente.');
    }

    public function editarDetalle()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para editar descargas.');
        }

        $input = Request::all();
        $idDetalle = (int)($input['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            JsonResponse::error('ID de descarga inválido.');
        }

        $datos = $this->extraerDatosDescarga($input);
        $archivo = $_FILES['archivo'] ?? [];

        try {
            CuentaLitrosService::editarDetalle($idDetalle, $datos, $archivo);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Descarga actualizada exitosamente.');
    }

    public function eliminarDetalle()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar descargas.');
        }

        $input = Request::all();
        $idDetalle = (int)($input['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            JsonResponse::error('ID de descarga inválido.');
        }

        try {
            CuentaLitrosService::eliminarDetalle($idDetalle);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Descarga eliminada.');
    }

    public function finalizar()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para finalizar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        try {
            CuentaLitrosService::finalizar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Registro finalizado exitosamente.');
    }

    public function habilitar()
    {
        $permisos = CuentaLitrosService::getPermisos();

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        try {
            CuentaLitrosService::habilitar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Registro habilitado.');
    }

    public function eliminar()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar registros.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        try {
            CuentaLitrosService::eliminar($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Registro eliminado.');
    }

    public function editarFecha()
    {
        $permisos = CuentaLitrosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para cambiar la fecha.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $fecha = trim((string)($input['fecha'] ?? ''));

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            JsonResponse::error('Fecha inválida.');
        }

        try {
            CuentaLitrosService::editarFecha($id, $fecha);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        JsonResponse::success('Fecha actualizada exitosamente.');
    }

    private function extraerDatosDescarga(array $input): array
    {
        $claves = [
            'hora',
            'embarque',
            'transporte',
            'producto',
            'tanque',
            'litros',
            'descarga_neto',
            'descarga_bruto',
            'litros_c',
            'tad',
            'unidad',
            'venta_momento',
            'folio_merma',
            'comentario',
        ];

        $datos = [];
        foreach ($claves as $clave) {
            $datos[$clave] = $input[$clave] ?? '';
        }

        return $datos;
    }
}