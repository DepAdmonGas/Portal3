<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Models\PolizaSeguro;
use App\Models\PolizaSeguroCobertura;
use App\Services\SeguroService;

class SeguroController extends BaseController
{
    private const MODULO = 'seguro';

    private function esAdministrador(): bool
    {
        return in_array((int) (Auth::user()?->id_puesto ?? 0), [25, 13], true);
    }

    private function exigirAdministrador(): void
    {
        if (!$this->esAdministrador()) {
            JsonResponse::forbidden('Solo los puestos 25 y 13 pueden administrar Seguro.');
        }
    }

    public function index(): void
    {
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Seguro', '');

        View::render('seguro/index', [
            'title' => 'Seguro',
            'modulo' => self::MODULO,
            'puedeAdministrarSeguro' => $this->esAdministrador(),
            'filtro_usuario' => $this->filtro_usuario,
            'links' => ['/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/seguro/seguro.datatable.init.js?v=' . time(),
                '/js/seguro/actions.init.js?v=' . time(),
            ],
            'help' => false,
        ], 'main');
    }

    public function datatablePolizaSeguro(): never
    {
        $admin = $this->esAdministrador();
        JsonResponse::custom([
            'data' => SeguroService::getRegistros(PolizaSeguro::class, !$admin),
            'permisos' => ['admin' => $admin, 'descargar' => true],
        ]);
    }

    public function datatablePolizaSeguroCobertura(): never
    {
        $admin = $this->esAdministrador();
        JsonResponse::custom([
            'data' => SeguroService::getRegistros(PolizaSeguroCobertura::class, !$admin),
            'permisos' => ['admin' => $admin, 'descargar' => true],
        ]);
    }

    public function createPolizaSeguro(): never
    {
        $this->exigirAdministrador();
        $this->crearRegistro(PolizaSeguro::class, $_FILES['poliza'] ?? null, 'Póliza de seguro');
    }

    public function createPolizaSeguroCobertura(): never
    {
        $this->exigirAdministrador();
        $this->crearRegistro(PolizaSeguroCobertura::class, $_FILES['cobertura'] ?? null, 'Cobertura de la póliza');
    }

    private function crearRegistro(string $modelo, ?array $archivo, string $tipo): never
    {
        $resultado = SeguroService::crear($modelo, $archivo);
        if (!$resultado['success']) {
            if (($resultado['status'] ?? 0) === 403) {
                JsonResponse::forbidden($resultado['message']);
            }
            if (($resultado['status'] ?? 0) === 422) {
                JsonResponse::validation($resultado['message']);
            }
            JsonResponse::error($resultado['message'], $resultado['status'] ?? 422);
        }

        SeguroService::notificarTelegram('agregar', $tipo, (int) (Auth::id() ?? 0));
        JsonResponse::success($tipo . ' registrada correctamente.');
    }

    public function deletePolizaSeguro(): never
    {
        $this->exigirAdministrador();
        $this->eliminarRegistro(PolizaSeguro::class, 'Póliza de seguro');
    }

    public function deletePolizaSeguroCobertura(): never
    {
        $this->exigirAdministrador();
        $this->eliminarRegistro(PolizaSeguroCobertura::class, 'Cobertura de la póliza');
    }

    private function eliminarRegistro(string $modelo, string $tipo): never
    {
        $id = filter_var(Request::jsonInput('id'), FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            JsonResponse::validation('Selecciona un registro válido.', ['id' => 'El identificador es obligatorio.']);
        }

        $resultado = SeguroService::eliminar($modelo, $id);
        if (!$resultado) {
            JsonResponse::notFound('El registro no existe o ya fue eliminado.');
        }

        SeguroService::notificarTelegram('eliminar', $tipo, (int) (Auth::id() ?? 0));
        JsonResponse::success($tipo . ' eliminada correctamente.');
    }
}
