<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\MedicionesService;
use App\Services\ModuleStationService;

class MedicionesController extends BaseController
{
    public function index()
    {
        $permisos = MedicionesService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(MedicionesService::MODULE_KEY, 'Mediciones', 'departamento-operativo')) {
            return;
        }

        $title = 'Mediciones';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/mediciones/index', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idEstacion'       => $permisos['id_estacion'],
            'moduleStationKey' => MedicionesService::MODULE_KEY,
            'multiestacion'    => $permisos['multiestacion'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/mediciones.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/mediciones.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = MedicionesService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        JsonResponse::success('OK', [
            'data'     => MedicionesService::getData(),
            'permisos' => $permisos,
        ]);
    }

    public function store()
    {
        $permisos = MedicionesService::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para agregar mediciones.');
        }

        $ctx = ModuleStationService::getContext(MedicionesService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;

        if (!$idEstacion) {
            JsonResponse::error('Selecciona una estación para registrar la medición.');
        }

        if (!MedicionesService::puedeEstacion($idEstacion)) {
            JsonResponse::error('No tienes acceso a la estación seleccionada.');
        }

        $input = Request::all();

        $fecha = trim((string)($input['fecha'] ?? ''));
        $factura = trim((string)($input['factura'] ?? ''));
        $neto = $input['neto'] ?? '';
        $bruto = $input['bruto'] ?? '';
        $cuentaLitros = $input['cuenta_litros'] ?? '';
        $proveedor = trim((string)($input['proveedor'] ?? ''));

        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            JsonResponse::error('La fecha es obligatoria.');
        }
        if ($factura === '') {
            JsonResponse::error('La factura es obligatoria.');
        }
        if ($neto === '' || !is_numeric($neto)) {
            JsonResponse::error('El neto es obligatorio.');
        }
        if ($bruto === '' || !is_numeric($bruto)) {
            JsonResponse::error('El bruto es obligatorio.');
        }
        if ($cuentaLitros === '' || !is_numeric($cuentaLitros)) {
            JsonResponse::error('La cuenta de litros es obligatoria.');
        }
        if (!in_array($proveedor, MedicionesService::PROVEEDORES, true)) {
            JsonResponse::error('Selecciona un proveedor válido.');
        }

        try {
            $id = MedicionesService::crear(
                $idEstacion,
                $fecha,
                $factura,
                (float)$neto,
                (float)$bruto,
                (float)$cuentaLitros,
                $proveedor
            );

            if (!$id) {
                JsonResponse::error('Error al registrar la medición.');
            }

            MedicionesService::notificarCreacion($id, $permisos['id_usuario']);

            JsonResponse::success('Medición registrada exitosamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al registrar: ' . $e->getMessage());
        }
    }

    public function delete()
    {
        $permisos = MedicionesService::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar mediciones.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $datos = MedicionesService::getDatosMedicion($id);
        if (!$datos) {
            JsonResponse::error('Registro no encontrado.');
        }

        if (!MedicionesService::puedeEstacion($datos['id_estacion'])) {
            JsonResponse::error('No tienes acceso a la estación de este registro.');
        }

        try {
            $ok = MedicionesService::eliminar($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar la medición.');
            }

            MedicionesService::notificarEliminacion($id, $permisos['id_usuario']);

            JsonResponse::success('Medición eliminada exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }
}