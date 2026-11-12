<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\SoporteService;

/**
 * Soporte del Departamento de Sistemas (/sistemas/soporte).
 *
 * Toda la lógica de negocio vive en SoporteService. El controlador sólo
 * resuelve la petición, valida permisos y entrega JSON (JsonResponse) que
 * consume la interfaz Alpine, siguiendo el patrón del resto de Portal3.
 */
class SoporteController extends BaseController
{
    protected string $modulo = SoporteService::MODULE_KEY;

    public function index()
    {
        $title = 'Soporte (Sistemas)';

        $permisos = SoporteService::getPermisos();

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // Cierra los tickets que el solicitante no validó dentro de los
        // 3 días (mismo patrón "lazy" que el sistema anterior).
        SoporteService::procesarValidacionesVencidas();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }


        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sistemas/soporte/index.datatable.init.js?v=' . time(),
                '/js/sistemas/soporte/index.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/soporte', $data, 'main');
    }

    /**
     * Vista dedicada para crear/editar un registro (ticket en estado Creando).
     * Sustituye al modal de alta del listado, siguiendo el flujo heredado
     * (/departamento-sistemas/nuevo-registro/{id}).
     */
    public function formulario(int $id)
    {
        $permisos = SoporteService::getPermisos();

        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        $ticket = SoporteService::detalleTicket($id);

        // Solo se puede abrir el formulario de un ticket propio en creación.
        if ($ticket === null || (int)$ticket['estado'] !== SoporteService::ESTADO_CREANDO) {
            header('Location: /sistemas/soporte');
            exit;
        }

        $title = 'Formulario Ticket (#0' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Soporte (Sistemas)', '/sistemas/soporte');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'ticket' => $ticket,
            'endpoint' => '/sistemas/soporte',
            'responsables' => SoporteService::getResponsables(),
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/js/sistemas/soporte/formulario.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('sistemas/soporte-formulario', $data, 'main');
    }

    /* ------------------------------------------------------------------ */
    /* Listado y detalle                                                   */
    /* ------------------------------------------------------------------ */

    public function data()
    {
        if (!SoporteService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $estado = Request::input('estado');
        $estado = ($estado === null || $estado === '') ? null : (int)$estado;

        JsonResponse::custom([
            'success' => true,
            'permisos' => SoporteService::getPermisos(),
            'data' => SoporteService::getData($estado),
        ]);
    }

    public function show()
    {
        if (!SoporteService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $resultado = SoporteService::show((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    public function comentarios()
    {
        if (!SoporteService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $resultado = SoporteService::comentariosShow((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    public function actividades()
    {
        if (!SoporteService::puede('leer')) {
            JsonResponse::forbidden('No tienes permiso para consultar el módulo.');
        }

        $resultado = SoporteService::actividadesShow((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    /* ------------------------------------------------------------------ */
    /* Acciones (POST)                                                     */
    /* ------------------------------------------------------------------ */

    public function nuevoFolio()
    {
        $resultado = SoporteService::nuevoTicket((string)Request::input('categoria', SoporteService::CATEGORIA_ACTIVIDAD));

        self::responder($resultado);
    }

    public function editarPrioridad()
    {
        $resultado = SoporteService::editarPrioridad(
            (int)Request::input('id_ticket'),
            (string)Request::input('prioridad')
        );

        self::responder($resultado);
    }

    public function editarDescripcion()
    {
        $resultado = SoporteService::editarDescripcion(
            (int)Request::input('id_ticket'),
            (string)Request::input('descripcion')
        );

        self::responder($resultado);
    }

    public function agregarActividad()
    {
        $resultado = SoporteService::agregarActividad(
            (int)Request::input('id_ticket'),
            (string)Request::input('descripcion'),
            $_FILES['archivo'] ?? null
        );

        self::responder($resultado);
    }

    public function agregarEvidencia()
    {
        $resultado = SoporteService::agregarEvidencia(
            (int)Request::input('id_ticket'),
            (string)Request::input('descripcion'),
            $_FILES['archivo'] ?? null
        );

        self::responder($resultado);
    }

    public function eliminarActividad()
    {
        $resultado = SoporteService::eliminarActividad((int)Request::input('id_actividad'));

        self::responder($resultado);
    }

    public function eliminarEvidencia()
    {
        $resultado = SoporteService::eliminarEvidencia((int)Request::input('id_evidencia'));

        self::responder($resultado);
    }

    public function editarActividad()
    {
        $resultado = SoporteService::editarActividad(
            (int)Request::input('id_actividad'),
            (string)Request::input('detalle'),
            (int)Request::input('opcion')
        );

        self::responder($resultado);
    }

    public function editarRegistro()
    {
        $resultado = SoporteService::editarRegistro(
            (int)Request::input('id_ticket'),
            (string)Request::input('detalle'),
            (int)Request::input('opcion')
        );

        self::responder($resultado);
    }

    public function finalizarRegistro()
    {
        $resultado = SoporteService::finalizarRegistro((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    public function finalizarSoporte()
    {
        $resultado = SoporteService::finalizarSoporte(
            (int)Request::input('id_ticket'),
            (int)Request::input('finalizar', SoporteService::ESTADO_FINALIZADO)
        );

        self::responder($resultado);
    }

    public function cancelarTicket()
    {
        $resultado = SoporteService::cancelarTicket((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    public function guardarComentario()
    {
        $resultado = SoporteService::guardarComentario(
            (int)Request::input('id_ticket'),
            (string)Request::input('comentario'),
            (int)Request::input('opcion', 1)
        );

        self::responder($resultado);
    }

    /**
     * El solicitante valida el trabajo realizado por el área de Sistemas.
     * Cierra el ticket dentro de la ventana de 3 días.
     */
    public function validarSolicitud()
    {
        $resultado = SoporteService::validarSolicitud((int)Request::input('id_ticket'));

        self::responder($resultado);
    }

    /**
     * El solicitante devuelve la solicitud al área de Sistemas porque el
     * resultado no es el esperado o sigue inconclusa.
     */
    public function solicitarRevision()
    {
        $resultado = SoporteService::solicitarRevision(
            (int)Request::input('id_ticket'),
            (string)Request::input('motivo', '')
        );

        self::responder($resultado);
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
