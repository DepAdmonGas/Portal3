<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AnalisisCompraImportacionService;
use App\Services\DropdownYearMesService;

class AnalisisCompraImportacionController extends BaseController
{
    public function index($idYear = null, $idMes = null)
    {
        $permisos = AnalisisCompraImportacionService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(AnalisisCompraImportacionService::MODULE_KEY, 'Análisis de Compra', 'departamento-operativo')) {
            return;
        }

        $validated = DropdownYearMesService::validarYearMes((int)$idYear, (int)$idMes);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $idEstacion = AnalisisCompraImportacionService::getEstacionContexto();

        if ($idEstacion !== null && !AnalisisCompraImportacionService::puedeEstacion($idEstacion)) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $nombreEstacion = '';
        $analisisHtml = '';

        if ($idEstacion !== null) {
            $datos = AnalisisCompraImportacionService::getDatos($idEstacion, $idYear, $idMes);
            $subtotales = AnalisisCompraImportacionService::getSubtotales($idEstacion, $idYear, $idMes);
            $totales = AnalisisCompraImportacionService::getTotales($idEstacion, $idYear, $idMes);
            $totalesGeneral = AnalisisCompraImportacionService::getTotalesGeneral($idYear, $idMes);
            $nombreEstacion = AnalisisCompraImportacionService::getEstacionNombre($idEstacion);
            $analisisHtml = AnalisisCompraImportacionService::buildAnalisisHtml($datos, $subtotales, $totales, $totalesGeneral, $permisos['puedeEditar']);
        }

        $title = 'Análisis de Compra (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

        $yearMesTemplate = '/departamento-operativo/importacion/analisis-compra/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/analisis-compra/index', [
            'title'               => $title,
            'idUsuario'           => $permisos['id_usuario'],
            'idEstacion'          => $idEstacion,
            'nombreEstacion'      => $nombreEstacion,
            'moduleStationKey'    => AnalisisCompraImportacionService::MODULE_KEY,
            'multiestacion'       => false,
            'puedeEditar'         => $permisos['puedeEditar'],
            'idYear'              => $idYear,
            'idMes'               => $idMes,
            'yearMesTemplate'     => $yearMesTemplate,
            'analisisHtml'        => $analisisHtml,
            'help'                => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/analisis-compra.init.js?v=' . time(),
            ],
            'links' => [],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = AnalisisCompraImportacionService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        $validated = DropdownYearMesService::validarYearMes(
            (int)Request::input('year', 0),
            (int)Request::input('mes', 0)
        );
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $idEstacion = AnalisisCompraImportacionService::getEstacionContexto();

        if ($idEstacion === null || !AnalisisCompraImportacionService::puedeEstacion($idEstacion)) {
            JsonResponse::forbidden('No tienes acceso a la estación seleccionada.');
        }

        JsonResponse::success('OK', [
            'idEstacion'     => $idEstacion,
            'nombreEstacion' => AnalisisCompraImportacionService::getEstacionNombre($idEstacion),
            'idYear'         => $idYear,
            'idMes'          => $idMes,
            'datos'          => AnalisisCompraImportacionService::getDatos($idEstacion, $idYear, $idMes),
            'subtotales'     => AnalisisCompraImportacionService::getSubtotales($idEstacion, $idYear, $idMes),
            'totales'        => AnalisisCompraImportacionService::getTotales($idEstacion, $idYear, $idMes),
            'totalesGeneral' => AnalisisCompraImportacionService::getTotalesGeneral($idYear, $idMes),
            'permisos'       => $permisos,
        ]);
    }

    public function update()
    {
        $permisos = AnalisisCompraImportacionService::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar este módulo.');
        }

        $id = (int)Request::input('id', 0);
        $opcion = (int)Request::input('opcion', 0);
        $valor = Request::input('valor');

        if ($id <= 0) {
            JsonResponse::validation('El registro es obligatorio.');
        }

        if (!in_array($opcion, [1, 2], true)) {
            JsonResponse::validation('La opción enviada no es válida.');
        }

        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            JsonResponse::validation('El valor debe ser numérico.');
        }

        $idEstacion = AnalisisCompraImportacionService::getEmbarqueEstacionId($id);

        if ($idEstacion === null) {
            JsonResponse::notFound('El registro no existe.');
        }

        if (!AnalisisCompraImportacionService::puedeEstacion($idEstacion)) {
            JsonResponse::forbidden('No tienes acceso a la estación de este registro.');
        }

        $resultado = AnalisisCompraImportacionService::actualizarBrutoNeto($id, $opcion, $valor);

        if (!$resultado['success']) {
            JsonResponse::error($resultado['message'] ?? 'No fue posible actualizar el registro.');
        }

        $yearMes = AnalisisCompraImportacionService::getYearMesDeEmbarque($id);

        if ($yearMes['year'] === 0 || $yearMes['mes'] === 0) {
            JsonResponse::error('No fue posible localizar el periodo del registro.');
        }

        $producto = AnalisisCompraImportacionService::getProductoDeEmbarque($id);

        if ($producto === null) {
            JsonResponse::error('No fue posible localizar el producto del registro.');
        }

        $datos = AnalisisCompraImportacionService::getDatos($idEstacion, $yearMes['year'], $yearMes['mes'], false);
        $subtotales = AnalisisCompraImportacionService::getSubtotales($idEstacion, $yearMes['year'], $yearMes['mes']);

        $detalleHtml = '';
        $subtotalHtml = '';

        try {
            foreach ($datos['productos'] as $p) {
                if (($p['producto'] ?? '') === $producto) {
                    $detalleHtml = AnalisisCompraImportacionService::buildDetalleProductosHtml([$p], $permisos['puedeEditar']);
                    break;
                }
            }

            foreach ($subtotales as $s) {
                if (($s['producto'] ?? '') === $producto) {
                    $subtotalHtml = AnalisisCompraImportacionService::buildSubtotalesHtml([$s]);
                    break;
                }
            }
        } catch (\Throwable $e) {
            $detalleHtml = '';
            $subtotalHtml = '';
        }

        JsonResponse::success('Registro actualizado correctamente.', [
            'id'       => $id,
            'opcion'   => $opcion,
            'valor'    => (int)$valor,
            'producto' => $producto,
            'detalle'  => $detalleHtml,
            'subtotal' => $subtotalHtml,
        ]);
    }
}
