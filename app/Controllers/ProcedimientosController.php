<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\ProcedimientosService;

final class ProcedimientosController extends BaseController
{
    public function index(): void
    {
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Procedimientos', '');

        View::render('procedimientos/index', [
            'title' => 'Procedimientos',
            'modulo' => 'procedimientos',
            'filtro_usuario' => $this->filtro_usuario,
            'puedeAdministrarProcedimientos' => ProcedimientosService::puedeAdministrar(),
            'links' => ['/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/procedimientos/procedimientos.datatable.init.js?v=' . time(),
            ],
            'help' => false,
        ], 'main');
    }

    public function datatableActividadesTec(): never
    {
        $this->responderTabla('actividades-tecnicas');
    }

    public function datatableVisitaES(): never
    {
        $this->responderTabla('visita-estacion');
    }

    public function create(): never
    {
        $this->validarAdministrador('agregar');
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(ProcedimientosService::crear(
            (string) Request::input('tipo', ''),
            Request::post(),
            $_FILES['archivo'] ?? null
        ));
    }

    public function update(): never
    {
        $this->validarAdministrador('editar');
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(ProcedimientosService::actualizar(
            (string) Request::input('tipo', ''),
            (int) Request::input('id', 0),
            Request::post(),
            $_FILES['archivo'] ?? null
        ));
    }

    public function delete(): never
    {
        $this->validarAdministrador('eliminar');
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(ProcedimientosService::eliminar(
            (string) Request::input('tipo', ''),
            (int) Request::input('id', 0)
        ));
    }

    private function responderTabla(string $tipo): never
    {
        if (!ProcedimientosService::puedeConsultar()) {
            JsonResponse::unauthorized();
        }

        JsonResponse::custom([
            'data' => ProcedimientosService::listar($tipo),
            'permisos' => [
                'descargar' => ProcedimientosService::puedeDescargar(),
                'administrar' => ProcedimientosService::puedeAdministrar(),
            ],
        ]);
    }

    private function validarAdministrador(string $accion): void
    {
        if (!ProcedimientosService::puedeAdministrar()) {
            JsonResponse::forbidden("Solo los usuarios del puesto 25 pueden {$accion} procedimientos.");
        }
    }

    private function responderResultado(array $resultado): never
    {
        if ($resultado['ok']) {
            JsonResponse::success($resultado['message']);
        }

        if (($resultado['status'] ?? 500) === 422) {
            JsonResponse::validation($resultado['message'], $resultado['errors'] ?? []);
        }
        if (($resultado['status'] ?? 500) === 404) {
            JsonResponse::notFound($resultado['message']);
        }
        JsonResponse::error($resultado['message'], (int) ($resultado['status'] ?? 500));
    }
}
