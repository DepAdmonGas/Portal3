<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\InventariosDiariosService;
use App\Services\DropdownYearMesService;

class InventariosDiariosController extends BaseController
{
    public function index()
    {
        $validated = DropdownYearMesService::validarYearMes(0, 0);
        $this->renderIndex($validated['idYear'], $validated['idMes']);
    }

    public function filtrarPorAnioMes(int $year, int $mes)
    {
        $validated = DropdownYearMesService::validarYearMes($year, $mes);
        $this->renderIndex($validated['idYear'], $validated['idMes']);
    }

    private function renderIndex(int $idYear, int $idMes): void
    {
        $permisos = InventariosDiariosService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $reportes = InventariosDiariosService::getListado($idYear, $idMes);

        Session::set('inventarios_diarios_context', ['year' => $idYear, 'mes' => $idMes]);

        $title = 'Inventarios diarios (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, 2021), '');

        $yearMesTemplate = '/departamento-operativo/importacion/inventarios-diarios/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/inventarios-diarios/index', [
            'title'           => $title,
            'idYear'          => $idYear,
            'idMes'           => $idMes,
            'reportes'        => $reportes,
            'puedeCrear'      => $permisos['puedeCrear'],
            'puedeEditar'     => $permisos['puedeEditar'],
            'puedeEliminar'   => $permisos['puedeEliminar'],
            'yearMesTemplate' => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/inventarios-diarios.init.js?v=' . time(),
            ],
            'links' => [],
        ], 'departamento-operativo');
    }

    public function reporte(int $id)
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $data = InventariosDiariosService::getReporte($id);
        if (!$data) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $contexto = Session::get('inventarios_diarios_context', []);
        $year = (int)($contexto['year'] ?? 0);
        $mes  = (int)($contexto['mes'] ?? 0);

        if ($year <= 0 || $mes <= 0) {
            $fecha = (string)($data['fecha'] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
                $year = (int)$m[1];
                $mes  = (int)$m[2];
            }
        }

        $validated = DropdownYearMesService::validarYearMes($year, $mes);
        $idYear = $validated['idYear'];
        $idMes  = $validated['idMes'];

        $title = 'Formulario Inventarios diarios (#' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Inventarios diarios (' . nombremes($idMes) . ' ' . $idYear . ')', '/departamento-operativo/importacion/inventarios-diarios/' . $idYear . '/' . $idMes);
        Breadcrumb::add($title, '');

        $yearMesTemplate = '';

        View::render('departamento-operativo/3-importacion/inventarios-diarios/reporte', [
            'title'           => $title,
            'reporte'         => $data,
            'puedeCrear'      => $permisos['puedeCrear'],
            'puedeEditar'     => $permisos['puedeEditar'],
            'yearMesTemplate' => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/inventarios-diarios.reporte.js?v=' . time(),
            ],
            'links' => [],
        ], 'departamento-operativo');
    }

    public function crear()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permisos para agregar.');
        }

        try {
            $id = InventariosDiariosService::crear();
        } catch (\Throwable $e) {
            JsonResponse::error('Error al crear el reporte.');
        }

        JsonResponse::success('Reporte creado', ['id' => $id]);
    }

    public function update()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para editar.');
        }

        $input = Request::all();
        $id    = (int)($input['id'] ?? 0);
        $tipo  = (int)($input['tipo'] ?? 0);
        $valor = (string)($input['valor'] ?? '');

        if ($id <= 0 || $tipo < 1 || $tipo > 4) {
            JsonResponse::error('Parámetros inválidos.');
        }

        $ok = InventariosDiariosService::updateDetalle($id, $tipo, $valor);
        JsonResponse::success($ok ? 'Actualizado' : 'Error al actualizar', ['ok' => $ok]);
    }

    public function agregarSucursal()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permisos para agregar.');
        }

        $input = Request::all();
        $idReporte = (int)($input['idReporte'] ?? 0);

        if ($idReporte <= 0 || trim((string)($input['Sucursal'] ?? '')) === '') {
            JsonResponse::error('La sucursal es obligatoria.');
        }

        $ok = InventariosDiariosService::agregarSucursal($idReporte, $input);
        JsonResponse::success($ok ? 'Sucursal agregada' : 'Error al agregar', ['ok' => $ok]);
    }

    public function eliminarDestino()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::error('Parámetros inválidos.');
        }

        $ok = InventariosDiariosService::eliminarDestino($id);
        JsonResponse::success($ok ? 'Destino eliminado' : 'Error al eliminar', ['ok' => $ok]);
    }

    public function finalizar()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para finalizar.');
        }

        $input = Request::all();
        $id    = (int)($input['id'] ?? 0);
        $fecha = trim((string)($input['fecha'] ?? ''));

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $resultado = InventariosDiariosService::finalizar($id, $fecha);
        if (!$resultado['ok']) {
            JsonResponse::error($resultado['message'] ?? 'Error al finalizar');
        }

        JsonResponse::success('Finalizado exitosamente', ['ok' => true]);
    }

    public function eliminar()
    {
        $permisos = InventariosDiariosService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permisos para eliminar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $ok = InventariosDiariosService::eliminarReporte($id);
        JsonResponse::success($ok ? 'Reporte eliminado' : 'Error al eliminar', ['ok' => $ok]);
    }
}