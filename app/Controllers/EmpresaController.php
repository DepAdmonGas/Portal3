<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\EmpresaService;

final class EmpresaController extends BaseController
{
    public function index(): void
    {
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Empresa', '');

        View::render('empresa/index', [
            'title' => 'Empresa',
            'modulo' => 'empresa',
            'filtro_usuario' => $this->filtro_usuario,
            'puedeAdministrarEmpresa' => EmpresaService::puedeAdministrar(),
            'links' => ['/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/empresa/empresa.datatable.init.js?v=' . time(),
            ],
            'help' => false,
        ], 'main');
    }

    public function datatableEmpresa(): never
    {
        if (!EmpresaService::puedeConsultar()) {
            JsonResponse::unauthorized();
        }

        JsonResponse::custom([
            'data' => EmpresaService::listar(),
            'tipos' => EmpresaService::tipos(),
            'permisos' => [
                'administrar' => EmpresaService::puedeAdministrar(),
                'descargar' => true,
            ],
        ]);
    }

    public function create(): never
    {
        $this->validarAdministrador();
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(EmpresaService::crear(Request::post(), $_FILES['archivo'] ?? null));
    }

    public function update(): never
    {
        $this->validarAdministrador();
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(EmpresaService::actualizar(
            (int) Request::input('id', 0),
            Request::post(),
            $_FILES['archivo'] ?? null
        ));
    }

    public function delete(): never
    {
        $this->validarAdministrador();
        if (!Request::isPost()) {
            JsonResponse::error('Método no permitido.', 405);
        }

        $this->responderResultado(EmpresaService::eliminar((int) Request::input('id', 0)));
    }

    private function validarAdministrador(): void
    {
        if (!EmpresaService::puedeAdministrar()) {
            JsonResponse::forbidden('Solo los usuarios del puesto 25 pueden administrar la información de Empresa.');
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
