<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\ProcedimientosDoService;

class ProcedimientosDoController extends BaseController
{
    public function index()
    {
        if (!ProcedimientosDoService::autorizado()) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Procedimientos');

        View::render('departamento-operativo/procedimientos/index', [
            'title' => 'Procedimientos',
            'scripts' => [
                '/assets/js/departamento-operativo/procedimientos/procedimientos-do.sidebar.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function modulo($modulo)
    {
        if (!ProcedimientosDoService::autorizado()) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $modulo = (string) $modulo;

        if (!ProcedimientosDoService::esModuloValido($modulo)) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $nombreModulo = strip_tags(ProcedimientosDoService::nombreModulo($modulo));

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Procedimientos', '/departamento-operativo/procedimientos');
        Breadcrumb::add($nombreModulo);

        View::render('departamento-operativo/procedimientos/modulo', [
            'title' => $nombreModulo,
            'modulo' => $modulo,
            'moduloNombre' => $nombreModulo,
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/procedimientos/procedimientos-do.sidebar.init.js?v=' . time(),
                '/assets/js/departamento-operativo/procedimientos/procedimientos-do.actions.init.js?v=' . time(),
                '/assets/js/departamento-operativo/procedimientos/procedimientos-do.datatable.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function data($modulo)
    {
        if (!ProcedimientosDoService::autorizado()) {
            JsonResponse::forbidden('No tienes permiso para consultar los procedimientos.');
        }

        $modulo = (string) $modulo;

        if (!ProcedimientosDoService::esModuloValido($modulo)) {
            JsonResponse::notFound('El módulo de procedimientos no existe.');
        }

        JsonResponse::success('OK', [
            'data' => ProcedimientosDoService::listar($modulo),
        ]);
    }

    public function create($modulo)
    {
        if (!ProcedimientosDoService::autorizado()) {
            JsonResponse::forbidden('No tienes permiso para agregar procedimientos.');
        }

        $modulo = (string) $modulo;

        if (!ProcedimientosDoService::esModuloValido($modulo)) {
            JsonResponse::notFound('El módulo de procedimientos no existe.');
        }

        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $resultado = ProcedimientosDoService::crear(
            $modulo,
            [
                'fecha' => Request::input('fecha'),
                'titulo' => Request::input('titulo'),
            ],
            $_FILES['documento'] ?? null
        );

        if ($resultado['ok']) {
            JsonResponse::success($resultado['message'], ['data' => $resultado['data']]);
        }

        if ($resultado['status'] === 422) {
            JsonResponse::validation($resultado['message'], $resultado['errors']);
        }

        JsonResponse::error($resultado['message'], $resultado['status']);
    }

    public function delete()
    {
        if (!ProcedimientosDoService::autorizado()) {
            JsonResponse::forbidden('No tienes permiso para eliminar procedimientos.');
        }

        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $id = (int) Request::input('id', 0);

        if ($id <= 0) {
            JsonResponse::validation('El procedimiento no es válido.', [
                'id' => 'El identificador del procedimiento es obligatorio.',
            ]);
        }

        $resultado = ProcedimientosDoService::eliminar($id);

        if ($resultado['ok']) {
            JsonResponse::success($resultado['message']);
        }

        if ($resultado['status'] === 404) {
            JsonResponse::notFound($resultado['message']);
        }

        JsonResponse::error($resultado['message'], $resultado['status']);
    }
}
