<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AlmacenMaquinariaEquiposService;
use App\Services\AlmacenMaquinariaEquiposBitacoraService as Service;
use App\Services\ModuleStationService;

/**
 * Bitácora de Maquinaria y Equipos — Almacén
 *
 * Pantalla calendario + listado del día + acciones del flujo (crear, checklist,
 * firmas, evidencias, comentarios). Todas las respuestas de este controlador son
 * JSON: el HTML lo pinta Alpine.
 */
class AlmacenMaquinariaEquiposBitacoraController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/maquinaria-equipos';
    private const BASE_BITACORA = '/departamento-operativo/almacen/maquinaria-equipos-bitacora';
    private const TITULO   = 'Bitácora de Maquinaria y Equipos';

    /* ------------------------------------------------------------------ */
    /* Vista                                                               */
    /* ------------------------------------------------------------------ */

    public function index(int $idEquipo): void
    {
        $title = self::TITULO;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = AlmacenMaquinariaEquiposService::getPermisos();

        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add('Maquinaria y Equipos', self::BASE_URL);
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(AlmacenMaquinariaEquiposService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }
/*
        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }
*/
        $equipo = Service::equipoInfo($idEquipo);

        if ($equipo === null) {
            JsonResponse::notFound('El equipo no existe o no está disponible.');
        }

        $contexto  = ModuleStationService::getContext(AlmacenMaquinariaEquiposService::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/maquinaria-equipos/bitacora', [
            'title'          => $title,
            'idEquipo'       => $idEquipo,
            'equipo'         => $equipo,
            'moduleStationKey' => AlmacenMaquinariaEquiposService::MODULE_KEY,
            'estacionFija'   => $seleccion !== null,
            'estacionActual' => $seleccion ?? 0,
            'puedeCrear'     => $permisos['puedeCrear'],
            'puedeEditar'    => $permisos['puedeEditar'],
            'puedeEliminar'  => $permisos['puedeEliminar'],
            'puedeDescargar' => $permisos['puedeDescargar'],
            'esUsuarioEstacion' => $permisos['esUsuarioEstacion'],
            // El equipo viene fijado por la URL ({idEquipo}), así que el selector
            // de estación/departamento no aplica en esta pantalla.
            'ocultarSelectorEstacion' => true,
            'scripts'        => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/fullcalendar/index.global.min.js',
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/bitacora.calendario.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/bitacora.actions.js?v=' . time(),
            ],
            'links'          => [],
        ], 'departamento-operativo');
    }

    /* ------------------------------------------------------------------ */
    /* Pantallas completas                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Nuevo mantenimiento (pantalla completa, igual que el legacy
     * maquinaria-equipos-nuevo/{idEquipo}). Las frecuencias ofrecidas dependen
     * del tipo de maquinaria: Hidrolavadora Diario/Semanal/Por horas, Planta de
     * emergencia Diario/Semanal/Mensual, el resto no genera checklist.
     */
    public function nuevoPage(int $idEquipo): void
    {
        $title = 'Nuevo mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = AlmacenMaquinariaEquiposService::getPermisos();

        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add('Maquinaria y Equipos', self::BASE_URL);
        Breadcrumb::add('Bitácora', self::BASE_BITACORA . '/' . $idEquipo);
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(AlmacenMaquinariaEquiposService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        /*
        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }
*/
        if (!$permisos['esUsuarioEstacion']) {
            JsonResponse::forbidden('Tu puesto no está habilitado para crear mantenimientos.');
        }

        $equipo = Service::equipoInfo($idEquipo);

        if ($equipo === null) {
            JsonResponse::notFound('El equipo no existe o no está disponible.');
        }

        $contexto  = ModuleStationService::getContext(AlmacenMaquinariaEquiposService::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/maquinaria-equipos/bitacora-nuevo', [
            'title'                => $title,
            'idEquipo'             => $idEquipo,
            'equipo'               => $equipo,
            'moduleStationKey'     => AlmacenMaquinariaEquiposService::MODULE_KEY,
            'estacionFija'         => $seleccion !== null,
            'estacionActual'       => $seleccion ?? 0,
            'esUsuarioEstacion'    => $permisos['esUsuarioEstacion'],
            'usaFrecuencia'        => Service::usaFrecuencia((string)$equipo['maquinaria'], Service::TIPO_PREVENTIVO),
            'frecuencias'          => Service::frecuenciasDisponibles((string)$equipo['maquinaria'], Service::TIPO_PREVENTIVO),
            // El equipo viene fijado por la URL: el selector de estación no aplica.
            'ocultarSelectorEstacion' => true,
            'scripts'              => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/bitacora.nuevo.js?v=' . time(),
            ],
            'links'                => [],
        ], 'departamento-operativo');
    }

    /**
     * Pantallas del registro. Cada una tiene su propia ruta (como el legacy) y
     * comparte la misma vista: el modo decide qué secciones se pintan y qué se
     * puede tocar (los permisos llegan del backend en el payload).
     *
     *   detalle       → solo lectura (info + checklist + firmas)
     *   editar        → costo del registro (los bloqueos del legacy)
     *   mantenimiento → checklist editable + evidencias
     *   firma         → checklist de consulta + firmas (pad A / B / tokens)
     */
    public function detallePage(int $idOcurrencia): void
    {
        $this->renderRegistro($idOcurrencia, 'detalle');
    }

    public function mantenimientoPage(int $idOcurrencia): void
    {
        $this->renderRegistro($idOcurrencia, 'mantenimiento');
    }

    public function firmaPage(int $idOcurrencia): void
    {
        $this->renderRegistro($idOcurrencia, 'firma');
    }

    private function renderRegistro(int $idOcurrencia, string $modo): void
    {
        $titulos = [
            'detalle'       => 'Detalle de mantenimiento',
            'editar'        => 'Editar registro',
            'mantenimiento' => 'Mantenimiento',
            'firma'         => 'Firmar mantenimiento',
        ];

        $title = $titulos[$modo] ?? 'Registro';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');

        $permisos = AlmacenMaquinariaEquiposService::getPermisos();

        if ($permisos['origenMantenimiento']) {
            Breadcrumb::add('Mantenimiento', '');
        }

        Breadcrumb::add('Maquinaria y Equipos', self::BASE_URL);

        $registro = Service::getRegistro($idOcurrencia);

        if ($registro === null) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        $idEquipo = (int)$registro['id_equipo'];

        Breadcrumb::add('Bitácora', self::BASE_BITACORA . '/' . $idEquipo);
        Breadcrumb::add($title . ' (No. ' . ($registro['orden_label'] ?? '') . ')', '');

        if (!$this->guardModuleAccess(AlmacenMaquinariaEquiposService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        /*
        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }
*/
        $contexto  = ModuleStationService::getContext(AlmacenMaquinariaEquiposService::MODULE_KEY);
        $seleccion = $contexto['id_estacion'] !== null ? (int)$contexto['id_estacion'] : null;

        View::render('departamento-operativo/4-almacen/maquinaria-equipos/bitacora-registro', [
            'title'                => $title,
            'idEquipo'             => $idEquipo,
            'registro'             => $registro,
            'modo'                 => $modo,
            'moduleStationKey'     => AlmacenMaquinariaEquiposService::MODULE_KEY,
            'estacionFija'         => $seleccion !== null,
            'estacionActual'       => $seleccion ?? 0,
            'esUsuarioEstacion'    => $permisos['esUsuarioEstacion'],
            'ocultarSelectorEstacion' => true,
            'scripts'              => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/bitacora.registro.js?v=' . time(),
            ],
            'links'                => [],
        ], 'departamento-operativo');
    }
    /* ------------------------------------------------------------------ */
    /* Lectura: calendario + día + registro                                */
    /* ------------------------------------------------------------------ */

    public function calendario(): void
    {
        $this->requerirLectura();

        $idEquipo = (int)Request::input('idEquipo', 0);
        $this->equipoValido($idEquipo);

        // FullCalendar manda el rango visible (start/end); al ser dayGridMonth
        // incluye también días de los meses vecinos, por eso se consulta por
        // rango y no por mes (si no, los meses anterior/siguiente salen vacíos).
        $inicio = substr(trim((string)Request::input('start', '')), 0, 10);
        $fin    = substr(trim((string)Request::input('end', '')), 0, 10);

        if ($inicio === '' || $fin === '') {
            $mes    = (int)Request::input('mes', (int)date('n'));
            $year   = (int)Request::input('year', (int)date('Y'));
            $inicio = sprintf('%04d-%02d-01', $year, max(1, min(12, $mes)));
            $fin    = date('Y-m-t', strtotime($inicio));
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getCalendarioRango($idEquipo, $inicio, $fin),
        ]);
    }

    public function actividades(): void
    {
        $this->requerirLectura();

        $idEquipo = (int)Request::input('idEquipo', 0);
        $this->equipoValido($idEquipo);

        $fecha = substr(trim((string)Request::input('fecha', date('Y-m-d'))), 0, 10);

        JsonResponse::custom([
            'success' => true,
            'data'    => Service::getDia($idEquipo, $fecha),
        ]);
    }

    public function detalleRegistro(): void
    {
        $this->requerirLectura();

        $registro = Service::getRegistro((int)Request::input('id', 0));

        if ($registro === null) {
            JsonResponse::notFound('Registro no encontrado.');
        }

        JsonResponse::custom(['success' => true, 'data' => $registro]);
    }

    /** Preview del checklist para el modal "Nuevo mantenimiento". */
    public function checklistPreview(): void
    {
        $this->requerirLectura();

        $idEquipo = (int)Request::input('idEquipo', 0);
        $equipo   = Service::equipo($idEquipo);

        if (!$equipo) {
            JsonResponse::notFound('El equipo no existe o no está disponible.');
        }

        $tipoMantenimiento = (int)Request::input('tipoMantenimiento', 0);
        $frecuenciaRaw     = Request::input('frecuencia', null);
        $frecuencia        = ($frecuenciaRaw !== null && $frecuenciaRaw !== '') ? (int)$frecuenciaRaw : null;

        JsonResponse::custom([
            'success'   => true,
            'filas'     => Service::precargarChecklist(trim((string)$equipo->maquinaria), $tipoMantenimiento, $frecuencia),
            'usuarios'  => Service::usuariosEstacion((int)$equipo->id_estacion),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Mantenimiento: crear / costo / completar / eliminar                 */
    /* ------------------------------------------------------------------ */

    public function crearMantenimiento(): void
    {
        $this->requerirLectura();

        $result = Service::crearMantenimiento(Request::all());
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function editarCosto(): void
    {
        $idOcurrencia = (int)Request::input('id', 0);
        $costo        = (float)Request::input('costo', 0);

        $result = Service::editarCostoOcurrencia($idOcurrencia, $costo);
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function completar(): void
    {
        $result = Service::completarOcurrencia((int)Request::input('id', 0));
        $http   = (int)($result['code'] ?? 200);

        JsonResponse::custom($result, ($http < 100 || $http > 599) ? 200 : $http);
    }

    public function actualizarEstatus(): void
    {
        $result = Service::actualizarEstatusOcurrencia(
            (int)Request::input('id', 0),
            (int)Request::input('estatus', 0)
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function eliminarRegistro(): void
    {
        $result = Service::eliminarRegistro((int)Request::input('id', 0));
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function eliminarMantenimiento(): void
    {
        $result = Service::eliminarMantenimiento((int)Request::input('id', 0));
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    /* ------------------------------------------------------------------ */
    /* Checklist                                                           */
    /* ------------------------------------------------------------------ */

    public function guardarActividad(): void
    {
        $result = Service::actualizarActividad(Request::all());
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    /* ------------------------------------------------------------------ */
    /* Firmas: A (dibujo), B (dibujo/token), C (token)                     */
    /* ------------------------------------------------------------------ */

    public function firmarElaboro(): void
    {
        $result = Service::firmaElaboroOcurrencia(
            (int)Request::input('id', 0),
            (string)Request::input('firma', '')
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function firmarVoboSignature(): void
    {
        $result = Service::firmaVobo(
            (int)Request::input('id', 0),
            (string)Request::input('firma', '')
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function solicitarToken(): void
    {
        $result = Service::solicitarTokenOcurrencia(
            (int)Request::input('id', 0),
            (string)Request::input('via', 'telegram'),
            (string)Request::input('tipoFirma', 'B')
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function validarToken(): void
    {
        $result = Service::validarTokenFirmaOcurrencia(
            (int)Request::input('id', 0),
            (string)Request::input('tipoFirma', 'B'),
            (int)Request::input('token', 0)
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    /* ------------------------------------------------------------------ */
    /* Evidencias                                                          */
    /* ------------------------------------------------------------------ */

    public function evidenciaSubir(): void
    {
        // El front envía el archivo como "evidencia" (se acepta también
        // "archivo" por compatibilidad con el nombre del legacy).
        $file = $_FILES['evidencia'] ?? $_FILES['archivo'] ?? [];

        $result = Service::subirEvidenciaOcurrencia(
            (int)Request::input('id', 0),
            $file
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    public function evidenciaEliminar(): void
    {
        $result = Service::eliminarEvidencia((int)Request::input('id', 0));
        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios                                                         */
    /* ------------------------------------------------------------------ */

    public function comentarios(): void
    {
        $this->requerirLectura();

        JsonResponse::custom([
            'success'    => true,
            'comentarios' => Service::getComentarios((int)Request::input('id', 0)),
        ]);
    }

    public function comentarioAgregar(): void
    {
        $result = Service::agregarComentarioOcurrencia(
            (int)Request::input('id', 0),
            Request::all()
        );

        JsonResponse::custom($result, (int)($result['code'] ?? 200));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function requerirLectura(): void
    {
        $permisos = Service::getPermisos();
/*
        if (!$permisos['puedeLeer']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }
            */
    }

    /** El equipo debe existir y pertenecer a una localidad autorizada del usuario. */
    private function equipoValido(int $idEquipo): void
    {
        if ($idEquipo <= 0 || Service::equipo($idEquipo) === null) {
            JsonResponse::notFound('El equipo no existe o no está disponible.');
        }
    }
}
