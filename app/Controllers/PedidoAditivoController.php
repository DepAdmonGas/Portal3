<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\PedidoAditivoService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PedidoAditivoController extends BaseController
{
    public function index()
    {
        $permisos = PedidoAditivoService::getPermisos();

        if (!$permisos['puedeAcceso']) {
            header('Location: /home');
            exit;
        }

        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        $title = 'Pedido de Aditivo';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoAditivoService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-aditivo/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoAditivoService::MODULE_KEY,
            'pendientesData'   => PedidoAditivoService::getPendingCountsFlat(),
            'puedeAcceso'      => $permisos['puedeAcceso'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'esEncargado'      => $permisos['esEncargado'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-aditivo.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-aditivo.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function pedido(int $id)
    {
        $solicitud = PedidoAditivoService::getSolicitud($id);
        if (!$solicitud) {
            http_response_code(404);
            echo 'Solicitud no encontrada';
            return;
        }

        $permisos = PedidoAditivoService::getPermisos();

        if (!$permisos['puedeAcceso']) {
            header('Location: /home');
            exit;
        }

        $title = 'Solicitud de aditivo (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Aditivo', '/departamento-operativo/comercializadora/pedido-aditivo');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoAditivoService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-aditivo/pedido', [
            'title'            => $title,
            'solicitud'        => $solicitud,
            'moduleStationKey' => PedidoAditivoService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'pendientesData'   => PedidoAditivoService::getPendingCountsFlat(),
            'puedeAcceso'      => $permisos['puedeAcceso'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'esEncargado'      => $permisos['esEncargado'],
            'idUsuario'        => $permisos['id_usuario'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-aditivo.pedido.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    private function resolverEstacion(): int
    {
        $idEstacion = (int)($_GET['id_estacion'] ?? ($_POST['id_estacion'] ?? 0));
        if ($idEstacion) {
            return $idEstacion;
        }
        $ctx = ModuleStationService::getContext(PedidoAditivoService::MODULE_KEY);
        return (int)($ctx['id_estacion'] ?? 0);
    }

    public function firma(int $id)
    {
        $solicitud = PedidoAditivoService::getSolicitud($id);
        if (!$solicitud) {
            http_response_code(404);
            echo 'Solicitud no encontrada';
            return;
        }

        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar Solicitud (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Aditivo', '/departamento-operativo/comercializadora/pedido-aditivo');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoAditivoService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-aditivo/firma', [
            'title'            => $title,
            'solicitud'        => $solicitud,
            'moduleStationKey' => PedidoAditivoService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-aditivo.firma.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    /* ================= SOLICITUDES ================= */

    public function getData()
    {
        $idEstacion = $this->resolverEstacion();
        $ids = $idEstacion ? [$idEstacion] : PedidoAditivoService::getAllowedStationIds();

        $data = [];
        foreach ($ids as $id) {
            $data = array_merge($data, PedidoAditivoService::getSolicitudes($id));
        }

        usort($data, fn($a, $b) => $b['id'] <=> $a['id']);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function getPendingCountsEndpoint()
    {
        $flat = PedidoAditivoService::getPendingCountsFlat();
        $flat['contexto'] = PedidoAditivoService::getPendingCountsActual();
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function getDetalle()
    {
        $id = (int)($_GET['id'] ?? 0);
        $solicitud = PedidoAditivoService::getSolicitud($id);

        if (!$solicitud) {
            JsonResponse::error('Solicitud no encontrada', 404);
        }

        $permisos = PedidoAditivoService::getPermisos();

        JsonResponse::custom([
            'success'  => true,
            'solicitud' => $solicitud,
            'permisos' => [
                'puedeEditar'     => $permisos['puedeEditar'],
                'puedeEliminar'   => $permisos['puedeEliminar'],
                'puedeFirmarVoBo' => $permisos['puedeFirmarVoBo'],
                'esEncargado'     => $permisos['esEncargado'],
            ],
        ]);
    }

    public function crear()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear solicitudes');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::crearSolicitud($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function guardarDatos()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar solicitudes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $fecha = (string)($_POST['fecha'] ?? '');
        $para = (string)($_POST['para'] ?? '');
        $fechaEntrega = (string)($_POST['fecha_entrega'] ?? '');
        $comentarios = (string)($_POST['comentarios'] ?? '');

        $result = PedidoAditivoService::guardarDatos($id, $fecha, $para, $fechaEntrega, $comentarios);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarTambo()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar solicitudes');
        }

        $idReporte = (int)($_POST['id_reporte'] ?? 0);
        $producto = (string)($_POST['producto'] ?? '');
        $cantidad = (int)($_POST['cantidad'] ?? 0);

        $result = PedidoAditivoService::agregarTambo($idReporte, $producto, $cantidad);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarTambo()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar solicitudes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $producto = (string)($_POST['producto'] ?? '');

        $result = PedidoAditivoService::editarTambo($id, $cantidad, $producto !== '' ? $producto : null);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarTambo()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar tambos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoAditivoService::eliminarTambo($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function finalizar()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['esEncargado'] && !$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar solicitudes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::finalizarSolicitud($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminar()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar solicitudes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::eliminarSolicitud($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public function crearToken()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            JsonResponse::forbidden('No tienes permiso para firmar el VoBo');
        }

        $id = (int)($_POST['id'] ?? 0);
        $via = (string)($_POST['via'] ?? 'telegram');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::crearToken($id, $idUsuario, $via);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmarVoBo()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            JsonResponse::forbidden('No tienes permiso para firmar el VoBo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $token = (string)($input['token'] ?? ($_POST['token'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::firmarVoBo($id, $idUsuario, $token);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= DOCUMENTOS / PAGOS ================= */

    public function getDocumentos()
    {
        $id = (int)($_GET['id'] ?? 0);

        JsonResponse::custom([
            'success'    => true,
            'documentos' => PedidoAditivoService::getDocumentos($id),
        ]);
    }

    public function subirDocumento()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para subir documentos');
        }

        $id = (int)($_POST['id'] ?? 0);
        $nombre = (string)($_POST['nombre'] ?? '');
        $file = $_FILES['documento'] ?? [];

        $result = PedidoAditivoService::subirDocumento($id, $nombre, $file);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarDocumento()
    {
        $permisos = PedidoAditivoService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar documentos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoAditivoService::eliminarDocumento($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= COMENTARIOS ================= */

    public function getComentarios()
    {
        $id = (int)($_GET['id'] ?? 0);

        JsonResponse::custom([
            'success' => true,
            'data'    => PedidoAditivoService::getComentarios($id),
        ]);
    }

    public function addComentario()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $comentario = (string)($input['comentario'] ?? ($_POST['comentario'] ?? ''));
        $idUsuario = (int)(\App\Core\Session::get('usuario')['id'] ?? 0);

        $result = PedidoAditivoService::addComentario($id, $comentario, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= PDF ================= */

    public function pdf(int $id)
    {
        $result = PedidoAditivoService::generarHtmlPdf($id);
        if (!$result['success']) {
            JsonResponse::error($result['message'] ?? 'No se pudo generar el PDF', 404);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($result['html']);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream($result['nombre'], ['Attachment' => true]);
    }
}