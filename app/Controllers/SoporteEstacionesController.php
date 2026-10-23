<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\SoporteService;
use App\Services\SoporteEstacionesService;

/**
 * Soporte (Estaciones) — /sistemas/estaciones.
 *
 * Vista del área de Sistemas sobre los tickets de soporte de todas las
 * estaciones y departamentos. Reutiliza la lógica compartida de
 * SoporteService (comentarios, descargas, estados, notificaciones) y añade
 * el contexto de estación/departamento.
 */
class SoporteEstacionesController extends BaseController
{
    protected string $modulo = SoporteEstacionesService::MODULE_KEY;

    public function index()
    {
        $title = 'Soporte (Estaciones)';

        $permisos = SoporteEstacionesService::getPermisos();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        // Cierra los tickets que el solicitante no validó dentro de los
        // 3 días (mismo patrón "lazy" que el sistema anterior).
        SoporteService::procesarValidacionesVencidas();

        Breadcrumb::add('Home', '/home');
        // Breadcrumb::add('Sistemas', '/sistemas');
        Breadcrumb::add($title, '');

        $contexto = SoporteEstacionesService::getContexto();
        $pendientes = SoporteEstacionesService::getPendingCounts();

        // El filtro por responsable sólo lo ve el usuario principal (id 496).
        // Se imprime en el servidor para que no dependa del cliente.
        ob_start();
        if ($permisos['puede_filtrar_responsable']) {
            View::renderPartial('sistemas/partials/filtro-responsable', []);
        }
        $filtroResponsableHtml = ob_get_clean();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'contexto' => $contexto,
            'mostrarColumnaEstacion' => SoporteEstacionesService::mostrarColumnaEstacion(),
            'responsables' => SoporteEstacionesService::getResponsables(),
            'filtroResponsableHtml' => $filtroResponsableHtml,
            'pendientes' => $pendientes,
            'pendientesData' => SoporteEstacionesService::getPendientesMap(),
            'pendientesContexto' => SoporteEstacionesService::getPendientesContexto(),
            // El selector de estaciones y departamentos está disponible para
            // todo el equipo de Sistemas.
            'moduleStationKey' => SoporteEstacionesService::CONTEXT_KEY,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sistemas/soporte/estaciones.datatable.init.js?v=' . time(),
                '/js/sistemas/soporte/estaciones.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/soporte-estaciones', $data, 'main');
    }

    /**
     * Seguimiento del ticket: fechas, actividades y cierre del trabajo.
     */
    public function seguimiento(int $id)
    {
        $permisos = SoporteEstacionesService::getPermisos();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        $resultado = SoporteEstacionesService::show($id);

        if (empty($resultado['success'])) {
            header('Location: ' . SoporteEstacionesService::RUTA);
            exit;
        }

        $ticket = $resultado['data'];

        // El seguimiento exige responsable asignado.
        if (empty($ticket['tiene_responsable'])) {
            header('Location: ' . SoporteEstacionesService::RUTA);
            exit;
        }

        $title = 'Seguimiento del ticket (#0' . $id . ')';

        Breadcrumb::add('Home', '/home');
        //Breadcrumb::add('Sistemas', '/sistemas');
        Breadcrumb::add('Soporte (Estaciones)', SoporteEstacionesService::RUTA);
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'ticket' => $ticket,
            'responsables' => $ticket['responsables'],
            'fechasSugeridas' => SoporteEstacionesService::calcularFechasSugeridas(),
            // En el seguimiento no se muestra el selector de estaciones: el
            // badge indica la estación o departamento del ticket que se
            // está revisando.
            'moduleStationKey' => SoporteEstacionesService::CONTEXT_KEY,
            'ocultarSelectorEstacion' => true,
            'nombreBadgeEstacion' => (string)($ticket['estacion'] ?? ''),
            'endpoint' => SoporteEstacionesService::RUTA,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/js/sistemas/soporte/estaciones.seguimiento.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/soporte-estaciones-seguimiento', $data, 'main');
    }

    /* ------------------------------------------------------------------ */
    /* Listado y detalle                                                   */
    /* ------------------------------------------------------------------ */

    public function data()
    {
        if (!SoporteEstacionesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $estado = Request::input('estado');
        $estado = ($estado === null || $estado === '') ? null : (int)$estado;

        $responsable = Request::input('id_responsable');
        $responsable = ($responsable === null || $responsable === '') ? null : (int)$responsable;

        $texto = (string)(Request::input('texto') ?? '');

        JsonResponse::custom([
            'success' => true,
            'permisos' => SoporteEstacionesService::getPermisos(),
            'data' => SoporteEstacionesService::getData($estado, $responsable, null, $texto),
        ]);
    }

    /**
     * Actividades de un folio: las usa la vista de captura para refrescar
     * la tabla después de agregar o eliminar una actividad.
     */
    public function actividades()
    {
        if (!SoporteEstacionesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $resultado = SoporteEstacionesService::show((int)Request::input('id_ticket'));

        if (empty($resultado['success'])) {
            self::responder($resultado);
            return;
        }

        JsonResponse::custom([
            'success' => true,
            'data' => $resultado['data']['actividades'] ?? [],
        ]);
    }

    public function show()
    {
        if (!SoporteEstacionesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        self::responder(SoporteEstacionesService::show((int)Request::input('id_ticket')));
    }

    public function comentarios()
    {
        if (!SoporteEstacionesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        self::responder(SoporteEstacionesService::comentariosShow((int)Request::input('id_ticket')));
    }

    /* ------------------------------------------------------------------ */
    /* Acciones (POST)                                                     */
    /* ------------------------------------------------------------------ */

    public function guardarComentario()
    {
        self::responder(SoporteEstacionesService::guardarComentario(
            (int)Request::input('id_ticket'),
            (string)Request::input('comentario')
        ));
    }

    public function asignarResponsable()
    {
        self::responder(SoporteEstacionesService::asignarResponsable(
            (int)Request::input('id_ticket'),
            (int)Request::input('id_responsable')
        ));
    }

    public function editarRegistro()
    {
        self::responder(SoporteEstacionesService::editarRegistro(
            (int)Request::input('id_ticket'),
            (string)Request::input('detalle'),
            (int)Request::input('opcion')
        ));
    }

    public function editarActividad()
    {
        self::responder(SoporteEstacionesService::editarActividad(
            (int)Request::input('id_actividad'),
            (string)Request::input('detalle'),
            (int)Request::input('opcion')
        ));
    }

    public function guardarSeguimiento()
    {
        $actividades = Request::input('actividades', []);
        $actividades = is_array($actividades) ? $actividades : [];

        self::responder(SoporteEstacionesService::guardarSeguimiento(
            (int)Request::input('id_ticket'),
            (string)Request::input('fecha_inicio'),
            (string)Request::input('fecha_termino'),
            $actividades
        ));
    }

    public function finalizarSoporte()
    {
        self::responder(SoporteEstacionesService::finalizarSoporte(
            (int)Request::input('id_ticket'),
            (int)Request::input('finalizar', SoporteService::ESTADO_FINALIZADO)
        ));
    }

    public function cancelarTicket()
    {
        self::responder(SoporteEstacionesService::cancelarTicket((int)Request::input('id_ticket')));
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    private static function responder(array $resultado): void
    {
        if (!empty($resultado['success'])) {
            JsonResponse::success($resultado['message'] ?? 'OK', array_diff_key($resultado, ['success' => true, 'message' => true]));
        }

        JsonResponse::error($resultado['message'] ?? 'No se pudo completar la operación.');
    }
}
