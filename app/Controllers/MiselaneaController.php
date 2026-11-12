<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\DropdownYearMesService;
use App\Services\MiselaneaService;
use App\Services\ModuleStationService;

class MiselaneaController extends BaseController
{
    /** Clave del módulo para el selector de estación compartido. */
    protected string $modulo = MiselaneaService::MODULE_KEY;

    /** Redirige al ejercicio por defecto cuando no viene en la URL. */
    public function redirect(): void
    {
        header('Location: ' . MiselaneaService::RUTA . '/' . date('Y'));
        exit;
    }

    public function index(int $year = 0): void
    {
        $title = 'Miselanea 30, 31';

        $permisos = MiselaneaService::getPermisos();

        $year = MiselaneaService::validarYear($year);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($year, 1, MiselaneaService::ANIO_INICIO), '');

        // El acceso se resuelve con la entrada del menú del Home.
        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        if (!$this->guardModuleAccess(MiselaneaService::MODULE_KEY, $title, 'main')) {
            return;
        }

        $idEstacion = MiselaneaService::estacionContextual();

        $data = [
            'title' => $title . ' (Periodo ' . $year . ')',
            'year' => $year,
            'idEstacion' => $idEstacion,
            'estacion' => $idEstacion ? MiselaneaService::nombreEstacion($idEstacion) : '',
            'contenido' => MiselaneaService::contenidoIndex($idEstacion, $year),
            'moduleStationKey' => MiselaneaService::MODULE_KEY,
            'yearMesTemplate' => MiselaneaService::RUTA . '/{year}',
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/js/core/module-station-selector.js',
                '/js/miselanea-30-31/miselanea-30-31.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('miselanea-30-31/index', $data, 'main');
    }

    /**
     * Sub-página de una etapa navegable (documental o certificación), con
     * su tabla de documentos y el modal de gestión de archivos.
     */
    public function etapa(string $slug, int $year = 0): void
    {
        $etapa = MiselaneaService::etapaPorSlug($slug);

        if ($etapa === null) {
            View::render('errors/404', [], 'main');
            return;
        }

        $title = $etapa['titulo'];

        $permisos = MiselaneaService::getPermisos();

        $year = MiselaneaService::validarYear($year);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Miselanea 30, 31', MiselaneaService::RUTA . '/' . $year);
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($year, 1, MiselaneaService::ANIO_INICIO), '');

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        if (!$this->guardModuleAccess(MiselaneaService::MODULE_KEY, $title, 'main')) {
            return;
        }

        $idEstacion = MiselaneaService::estacionContextual();

        $data = [
            'title' => $title . ' (Periodo ' . $year . ')',
            'year' => $year,
            'etapa' => $etapa,
            'permisos' => $permisos,
            'idEstacion' => $idEstacion,
            'estacion' => $idEstacion ? MiselaneaService::nombreEstacion($idEstacion) : '',
            'moduleStationKey' => MiselaneaService::MODULE_KEY,
            'yearMesTemplate' => MiselaneaService::RUTA . '/' . $slug . '/{year}',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js',
                '/js/miselanea-30-31/miselanea-30-31.datatable.init.js?v=' . time(),
                '/js/miselanea-30-31/miselanea-30-31.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('miselanea-30-31/etapa', $data, 'main');
    }

    /** Catálogo de documentos (etapas) con el conteo de archivos. */
    public function data(): void
    {
        if (!MiselaneaService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        $year = MiselaneaService::validarYear((int)Request::input('year'));

        JsonResponse::custom([
            'success' => true,
            'data' => [
                'documental' => MiselaneaService::documentosEtapa(
                    MiselaneaService::ETAPA_DOCUMENTAL,
                    $idEstacion,
                    $year
                ),
                'certificacion' => MiselaneaService::documentosEtapa(
                    MiselaneaService::ETAPA_CERTIFICACION,
                    $idEstacion,
                    $year
                ),
            ],
        ]);
    }

    /** Archivos de un documento (modal de gestión). */
    public function documentos(): void
    {
        if (!MiselaneaService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        $idDocumento = (int)Request::input('id_documento');
        $documento = MiselaneaService::documento($idDocumento);

        if (!$documento) {
            JsonResponse::notFound('El documento seleccionado no existe.');
            return;
        }

        $year = MiselaneaService::validarYear((int)Request::input('year'));

        JsonResponse::custom([
            'success' => true,
            'documento' => [
                'id' => (int)$documento->id,
                'id_lista' => (string)$documento->id_lista,
                'documento' => (string)$documento->documento,
            ],
            'data' => MiselaneaService::archivosDocumento($idDocumento, $idEstacion, $year),
        ]);
    }

    /** Agrega un archivo a un documento (multipart). */
    public function agregar(): void
    {
        if (!MiselaneaService::getPermisos()['puede_agregar']) {
            JsonResponse::forbidden('No tienes permiso para agregar archivos.');
        }

        $resultado = MiselaneaService::agregarArchivo(Request::post(), $_FILES);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Elimina un archivo de un documento. */
    public function eliminar(): void
    {
        if (!MiselaneaService::getPermisos()['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar archivos.');
        }

        $id = (int)Request::input('id');
        $resultado = MiselaneaService::eliminarArchivo($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    //----------------------------------------------------------------------
    // VALIDACIONES DE BACKEND
    //----------------------------------------------------------------------

    /**
     * Devuelve la estación validada o responde 403.
     * Sin esto, ocultar las tarjetas en el frontend no protegería los datos.
     */
    private function estacionValida(): ?int
    {
        $idEstacion = MiselaneaService::estacionContextual();

        if ($idEstacion === null) {
            JsonResponse::forbidden('Debes seleccionar una estación del menú superior para visualizar MISELANEA 30, 31.');
            return null;
        }

        return $idEstacion;
    }
}
