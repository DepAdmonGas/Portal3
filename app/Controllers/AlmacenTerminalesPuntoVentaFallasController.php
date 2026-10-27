<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenTerminalesPuntoVentaFallasService as Service;
use App\Services\ModuleStationService;

/**
 * Fallas de Terminales Punto de Venta — Almacén
 *
 * Dos vistas, igual que el legacy:
 *  - /almacen/terminales-punto-venta-fallas/{id}  → fallas de UNA terminal
 *  - /almacen/terminales-punto-venta-fallas         → fallas de la estación del
 *    contexto del módulo (multiestación).
 */
class AlmacenTerminalesPuntoVentaFallasController extends BaseController
{
    public function index(): void
    {
        $this->renderVista(null);
    }

    /** Fallas de una terminal concreta: el id viaja en la ruta. */
    public function terminal(string $id): void
    {
        $this->renderVista((int)$id);
    }

    private function renderVista(?int $idTpv): void
    {
        
    $title = 'Fallas Terminales Punto de Venta' . ($idTpv !== null ? " (#{$idTpv})" : '');
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

        $permisos = Service::getPermisos();

        if (!$permisos['puedeLeer']) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Terminales Punto de Venta', 'departamento-operativo')) {
            return;
        }

        // Con id: la estación es la de la terminal. Sin id: la del contexto.
        $terminal = null;
        $estacionId = null;
        $estacionFija = false;
        $estacionActual = 0;

        if ($idTpv !== null) {
            $terminal = Service::getTerminal($idTpv);

            if ($terminal === null) {
                JsonResponse::notFound('Terminal no encontrada.');
                return;
            }

            $estacionId = (int)$terminal['id_estacion'];
            $estacionFija = true;
            $estacionActual = $estacionId;
        } else {
            $contexto  = ModuleStationService::getContext(Service::MODULE_KEY);
            $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

            $estacionId = $seleccion;
            $estacionFija = $seleccion !== null;
            $estacionActual = $seleccion ?? 0;
        }

        View::render('departamento-operativo/4-almacen/terminales-punto-venta-fallas/index', [
            'title'            => $title,
            'moduleStationKey' => Service::MODULE_KEY,
            'terminal'         => $terminal,
            'estacionId'       => $estacionId,
            'estacionFija'     => $estacionFija,
            'estacionActual'   => $estacionActual,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeOperar'      => Service::puedeOperar(),
            // Reportar fallas: sólo usuarios con id_gas, y sólo en la vista de
            // una terminal (que ya es de su estación).
            'puedeReportar'    => Service::puedeReportarFalla($estacionId),
            // Con terminal el contexto ya está resuelto: el selector estorba.
            'ocultarSelectorEstacion' => $idTpv !== null,
            'catalogosJson'    => json_encode(Service::getCatalogos(), JSON_UNESCAPED_UNICODE),
            'scripts'          => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/libs/select2/dist/js/select2.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/terminales-punto-venta-fallas.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/terminales-punto-venta-fallas.actions.init.js?v=' . time(),
            ],
            'links'            => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    public function data(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idTpv = (int)Request::input('id_tpv', 0);

        if ($idTpv > 0) {
            $data = Service::getDataFallas($idTpv);

            if (empty($data) && Service::getTerminal($idTpv) === null) {
                JsonResponse::notFound('Terminal no encontrada.');
                return;
            }
        } else {
            $idEstacion = Request::input('id_estacion');
            $filtro = ($idEstacion !== null && $idEstacion !== '' && (int)$idEstacion > 0)
                ? (int)$idEstacion
                : null;

            $data = Service::getDataFallasEstacion($filtro);
        }

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function detalle(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $detalle = Service::getDetalle((int)Request::input('id', 0));

        if ($detalle === null) {
            JsonResponse::notFound('Falla no encontrada.');
            return;
        }

        JsonResponse::custom(['success' => true, 'data' => $detalle]);
    }

    public function comentarios(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getComentarios((int)Request::input('id', 0)),
            'detalle' => Service::getDetalle((int)Request::input('id', 0)),
        ]);
    }

    public function anexos(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getAnexos((int)Request::input('id', 0)),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    public function store(): void
    {
        if (!Service::getPermisos()['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para registrar fallas.');
        }

        $idTpv = (int)Request::input('id_tpv', 0);
        $terminal = Service::getTerminal($idTpv);

        if ($terminal === null) {
            JsonResponse::notFound('Terminal no encontrada.');
            return;
        }

        // La regla se revalida en el backend: reportar fallas es exclusivo de
        // los usuarios con id_gas sobre su propia estación.
        if (!Service::puedeReportarFalla((int)$terminal['id_estacion'])) {
            JsonResponse::forbidden('Sólo los usuarios de la estación pueden registrar fallas de esta terminal.');
            return;
        }

        $resultado = Service::storeFalla($idTpv, (string)Request::input('falla', ''));

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function update(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar fallas.');
        }

        $accion = (string)Request::input('accion', 'editar');

        $resultado = Service::updateFalla(
            (int)Request::input('id', 0),
            $_POST,
            $_FILES,
            $accion === 'finalizar' ? 'finalizar' : 'editar'
        );

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    /**
     * Cierra una falla "En reparación" con una confirmación previa, sin volver
     * a abrir el modal de captura.
     */
    public function finalizar(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar fallas.');
        }

        $resultado = Service::finalizarFalla((int)Request::input('id', 0));

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function destroy(): void
    {
        if (!Service::getPermisos()['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar fallas.');
        }

        $resultado = Service::destroyFalla((int)Request::input('id', 0));

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function documentacion(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para actualizar la documentación.');
        }

        $resultado = Service::actualizarDocumentacion((int)Request::input('id', 0), $_FILES);

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function storeAnexo(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para agregar anexos.');
        }

        $resultado = Service::storeAnexo(
            (int)Request::input('id', 0),
            (string)Request::input('descripcion', ''),
            $_FILES['archivo'] ?? []
        );

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function destroyAnexo(): void
    {
        if (!Service::getPermisos()['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar anexos.');
        }

        $resultado = Service::destroyAnexo((int)Request::input('id', 0));

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }

    public function storeComentario(): void
    {
        if (!Service::getPermisos()['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $resultado = Service::storeComentario(
            (int)Request::input('id', 0),
            (string)Request::input('comentario', '')
        );

        JsonResponse::custom($resultado, $resultado['code'] ?? 200);
    }
}
