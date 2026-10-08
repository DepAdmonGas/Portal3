<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\View;
use App\Services\ProveedoresService;

class ProveedoresController extends BaseController
{
    public function index(): void
    {
        $title = 'Proveedores';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/proveedores/index', [
            'title'   => $title,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/4-almacen/proveedores.init.js?v=' . time(),
            ],
            'links'   => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function data(): void
    {
        $data = ProveedoresService::getData();
        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function crear(): void
    {
        $title = 'Formulario Proveedor';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add('Proveedores', '/departamento-operativo/almacen/proveedores');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/proveedores/crear', [
            'title'   => $title,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/proveedores-crear.js?v=' . time(),
            ],
            'links'   => [],
        ], 'departamento-operativo');
    }

    public function store(): void
    {
        $result = ProveedoresService::store($_POST, $_FILES);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function editar(int $id): void
    {
        $title = 'Editar Proveedor';
        $proveedor = ProveedoresService::getDetalle($id);

        if (!$proveedor) {
            header('Location: /departamento-operativo/almacen/proveedores');
            exit;
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add('Proveedores', '/departamento-operativo/almacen/proveedores');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/proveedores/editar', [
            'title'     => $title,
            'proveedor' => $proveedor,
            'scripts'   => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/proveedores-editar.js?v=' . time(),
            ],
            'links'     => [],
        ], 'departamento-operativo');
    }

    public function update(int $id): void
    {
        $result = ProveedoresService::update($id, $_POST);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function detalle(): void
    {
        // Lectura universal compatible con JSON y Form-Data
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['idProveedor'] ?? $_GET['idProveedor'] ?? 0);

        $detalle = ProveedoresService::getDetalle($id);

        if (!$detalle) {
            JsonResponse::custom(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
            return;
        }

        JsonResponse::custom(['success' => true, 'data' => $detalle]);
    }

    public function actualizarArchivo(): void
    {
        $idProveedor = (int)($_POST['idProveedor'] ?? 0);
        $tipoArchivo = trim((string)($_POST['TipoArchivo'] ?? ''));
        $fechaDoc = trim((string)($_POST['FechaDocumentacion'] ?? ''));
        $file = $_FILES['Archivo_file'] ?? [];

        $result = ProveedoresService::actualizarArchivo($idProveedor, $tipoArchivo, $fechaDoc, $file);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function destroy(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? $input['idProveedor'] ?? 0);

        $result = ProveedoresService::eliminar($id);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }
}