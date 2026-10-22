<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\SoporteService;
use App\Services\SoporteActividadesService;

/**
 * Actividades (Sistemas) — /sistemas/actividades.
 *
 * Listado y seguimiento de las actividades internas solicitadas por el
 * personal del "Departamento Sistemas". Reutiliza la estructura del módulo
 * de Soporte (Estaciones): mismos componentes visuales, mismos endpoints
 * JSON y mismos mecanismos globales.
 */
class SoporteActividadesController extends BaseController
{
    protected string $modulo = SoporteActividadesService::MODULE_KEY;

    public function index()
    {
        $title = 'Actividades (Sistemas)';

        $permisos = SoporteActividadesService::getPermisos();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        Breadcrumb::add('Home', '/home');
        //Breadcrumb::add('Sistemas', '/sistemas');
        Breadcrumb::add($title, '');

        // El filtro por responsable lo imprime el Controller sólo para el
        // usuario 496 (mismo criterio que Soporte (Estaciones)).
        ob_start();
        if ($permisos['puede_filtrar_responsable']) {
            View::renderPartial('sistemas/partials/filtro-responsable', []);
        }
        $filtroResponsableHtml = ob_get_clean();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'responsables' => SoporteActividadesService::getResponsables(),
            'pendientesPorResponsable' => SoporteActividadesService::getPendientesPorResponsable(),
            'totalPendientes' => SoporteActividadesService::getPendientes(),
            'pendientesContexto' => SoporteActividadesService::getPendientes(),
            'filtroResponsableHtml' => $filtroResponsableHtml,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sistemas/soporte/estaciones.datatable.init.js?v=' . time(),
                '/js/sistemas/soporte/estaciones.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/actividades', $data, 'main');
    }

    /**
     * Captura de una actividad (folio en estado "Creando").
     */
    public function nueva(int $id)
    {
        $permisos = SoporteActividadesService::getPermisos();

        if (!$permisos['puede_agregar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        $resultado = SoporteActividadesService::show($id);

        if (empty($resultado['success'])) {
            header('Location: ' . SoporteActividadesService::RUTA);
            exit;
        }

        $ticket = $resultado['data'];

        // Sólo se puede capturar una actividad que siga en estado "Creando".
        if ((int)$ticket['estado'] !== SoporteService::ESTADO_CREANDO) {
            header('Location: ' . SoporteActividadesService::RUTA);
            exit;
        }

        $title = 'Nueva actividad';

        Breadcrumb::add('Home', '/home');
        //Breadcrumb::add('Sistemas', '/sistemas');
        Breadcrumb::add('Actividades (Sistemas)', SoporteActividadesService::RUTA);
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'ticket' => $ticket,
            'responsables' => SoporteActividadesService::getResponsables(),
            'endpoint' => SoporteActividadesService::RUTA,
            'textoAlerta' => 'Crea tu actividad para el área de Sistemas, es importante que detalles la descripción o las actividades a realizar.',
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/js/sistemas/soporte/formulario.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/soporte-formulario', $data, 'main');
    }

    /**
     * Seguimiento de la actividad (fechas, actividades y cierre).
     */
    public function seguimiento(int $id)
    {
        $permisos = SoporteActividadesService::getPermisos();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        $resultado = SoporteActividadesService::show($id);

        if (empty($resultado['success'])) {
            header('Location: ' . SoporteActividadesService::RUTA);
            exit;
        }

        $ticket = $resultado['data'];

        $title = 'Seguimiento de la actividad';

        Breadcrumb::add('Home', '/home');
        //Breadcrumb::add('Sistemas', '/sistemas');
        Breadcrumb::add('Actividades (Sistemas)', SoporteActividadesService::RUTA);
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'ticket' => $ticket,
            'responsables' => SoporteActividadesService::getResponsables(),
            'endpoint' => SoporteActividadesService::RUTA,
            'fechasSugeridas' => SoporteService::calcularFechasSugeridas(),
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
        if (!SoporteActividadesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $idResponsable = Request::input('id_responsable');
        $idResponsable = ($idResponsable === null || $idResponsable === '') ? null : (int)$idResponsable;

        $estado = Request::input('estado');
        $estado = ($estado === null || $estado === '') ? null : (int)$estado;

        $texto = (string)(Request::input('texto') ?? '');

        JsonResponse::custom([
            'success' => true,
            'permisos' => SoporteActividadesService::getPermisos(),
            'data' => SoporteActividadesService::getData($idResponsable, $texto, $estado),
        ]);
    }

    /**
     * Actividades de un folio: las usa la vista de captura para refrescar
     * la tabla después de agregar o eliminar una actividad.
     */
    public function actividades()
    {
        if (!SoporteActividadesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $resultado = SoporteActividadesService::show((int)Request::input('id_ticket'));

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
        if (!SoporteActividadesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        self::responder(SoporteActividadesService::show((int)Request::input('id_ticket')));
    }

    public function comentarios()
    {
        if (!SoporteActividadesService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        self::responder(SoporteActividadesService::comentariosShow((int)Request::input('id_ticket')));
    }

    /* ------------------------------------------------------------------ */
    /* Acciones (POST)                                                     */
    /* ------------------------------------------------------------------ */

    public function nuevoFolio()
    {
        self::responder(SoporteActividadesService::nuevoFolio());
    }

    public function editarDescripcion()
    {
        self::responder(SoporteActividadesService::editarDescripcion(
            (int)Request::input('id_ticket'),
            (string)Request::input('descripcion')
        ));
    }

    public function editarPrioridad()
    {
        self::responder(SoporteActividadesService::editarPrioridad(
            (int)Request::input('id_ticket'),
            (string)Request::input('prioridad')
        ));
    }

    public function asignarResponsable()
    {
        self::responder(SoporteActividadesService::asignarResponsable(
            (int)Request::input('id_ticket'),
            (int)Request::input('id_responsable')
        ));
    }

    public function agregarActividad()
    {
        self::responder(SoporteActividadesService::agregarActividad(
            (int)Request::input('id_ticket'),
            (string)Request::input('descripcion'),
            $_FILES['archivo'] ?? null
        ));
    }

    public function eliminarActividad()
    {
        self::responder(SoporteActividadesService::eliminarActividad(
            (int)Request::input('id_actividad')
        ));
    }

    public function finalizarRegistro()
    {
        self::responder(SoporteActividadesService::finalizarRegistro(
            (int)Request::input('id_ticket')
        ));
    }

    public function guardarSeguimiento()
    {
        $actividades = Request::input('actividades', []);
        $actividades = is_array($actividades) ? $actividades : [];

        self::responder(SoporteActividadesService::guardarSeguimiento(
            (int)Request::input('id_ticket'),
            (string)Request::input('fecha_inicio'),
            (string)Request::input('fecha_termino'),
            $actividades
        ));
    }

    public function editarActividad()
    {
        self::responder(SoporteActividadesService::editarActividad(
            (int)Request::input('id_actividad'),
            (string)Request::input('detalle'),
            (int)Request::input('opcion')
        ));
    }

    public function finalizarSoporte()
    {
        self::responder(SoporteActividadesService::finalizarSoporte(
            (int)Request::input('id_ticket')
        ));
    }

    /**
     * El solicitante devuelve la actividad porque el resultado no es el
     * esperado o sigue inconclusa.
     */
    public function solicitarRevision()
    {
        self::responder(SoporteActividadesService::solicitarRevision(
            (int)Request::input('id_ticket'),
            (string)Request::input('motivo', '')
        ));
    }

    public function guardarComentario()
    {
        self::responder(SoporteActividadesService::guardarComentario(
            (int)Request::input('id_ticket'),
            (string)Request::input('comentario')
        ));
    }

    public function cancelarTicket()
    {
        self::responder(SoporteActividadesService::cancelarTicket(
            (int)Request::input('id_ticket')
        ));
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
