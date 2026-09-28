<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\PedidoArticulosLimpiezaService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PedidoArticulosLimpiezaController extends BaseController
{
    public function index()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        $title = 'Pedido de Artículos de Limpieza';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoArticulosLimpiezaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-articulos-limpieza/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoArticulosLimpiezaService::MODULE_KEY,
            'pendientesData'   => PedidoArticulosLimpiezaService::getPendingCountsFlat(),
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeAcceso'      => $permisos['puedeAcceso'],
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    /* ================= VISTAS ================= */

    protected function vistarIndex(string $title, string $view, bool $showSelector = false, array $scripts = [], array $links = []): void
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Artículos de Limpieza', '/departamento-operativo/comercializadora/pedido-articulos-limpieza');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoArticulosLimpiezaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-articulos-limpieza/' . $view, [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoArticulosLimpiezaService::MODULE_KEY,
            'ocultarSelectorEstacion' => !$showSelector,
            'pendientesData'   => PedidoArticulosLimpiezaService::getPendingCountsFlat(),
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
            'scripts'          => $scripts,
            'links'            => $links,
        ], 'departamento-operativo');
    }

    public function catalogo()
    {
        $this->vistarIndex('Catálogo de artículos de limpieza', 'catalogo', false, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.catalogo.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function inventario()
    {
        $this->vistarIndex('Inventario de artículos de limpieza', 'inventario', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.inventario.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            '/assets/libs/select2/dist/css/select2.min.css',
            '/assets/css/select2-modal.css',
        ]);
    }

    public function reporte()
    {
        $this->vistarIndex('Reporte de artículos de limpieza', 'reporte', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.reporte.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function reporteDetalle(int $id)
    {
        $reporte = PedidoArticulosLimpiezaService::getReporte($id);
        if (!$reporte) {
            http_response_code(404);
            echo 'Reporte no encontrado';
            return;
        }

        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        $title = 'Reporte de artículos de limpieza (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Artículos de Limpieza', '/departamento-operativo/comercializadora/pedido-articulos-limpieza');
        Breadcrumb::add('Reporte de artículos de limpieza', '/departamento-operativo/comercializadora/pedido-articulos-limpieza/reporte');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoArticulosLimpiezaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-articulos-limpieza/reporte-detalle', [
            'title'            => $title,
            'reporte'          => $reporte,
            'moduleStationKey' => PedidoArticulosLimpiezaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeAcceso'      => $permisos['puedeAcceso'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'esEncargado'      => $permisos['esEncargado'],
            'idUsuario'        => $permisos['id_usuario'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.reporte-detalle.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function pedido(int $id)
    {
        $pedido = PedidoArticulosLimpiezaService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        $title = 'Pedido (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Artículos de Limpieza', '/departamento-operativo/comercializadora/pedido-articulos-limpieza');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoArticulosLimpiezaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-articulos-limpieza/pedido', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoArticulosLimpiezaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
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
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.pedido.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function firma(int $id)
    {
        $pedido = PedidoArticulosLimpiezaService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar Pedido (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Artículos de Limpieza', '/departamento-operativo/comercializadora/pedido-articulos-limpieza');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoArticulosLimpiezaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-articulos-limpieza/firma', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoArticulosLimpiezaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-articulos-limpieza.firma.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    private function resolverEstacion(): int
    {
        $idEstacion = (int)($_GET['id_estacion'] ?? ($_POST['id_estacion'] ?? 0));
        if ($idEstacion) {
            return $idEstacion;
        }
        $ctx = ModuleStationService::getContext(PedidoArticulosLimpiezaService::MODULE_KEY);
        return (int)($ctx['id_estacion'] ?? 0);
    }

    /* ================= PEDIDOS ================= */

    public function getData()
    {
        $idEstacion = $this->resolverEstacion();
        $ids = $idEstacion ? [$idEstacion] : PedidoArticulosLimpiezaService::getAllowedStationIds();

        $data = [];
        foreach ($ids as $id) {
            $data = array_merge($data, PedidoArticulosLimpiezaService::getPedidos($id));
        }

        usort($data, fn($a, $b) => $b['id'] <=> $a['id']);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function getPendingCountsEndpoint()
    {
        $flat = PedidoArticulosLimpiezaService::getPendingCountsFlat();
        $flat['contexto'] = PedidoArticulosLimpiezaService::getPendingCountsActual();
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function getDetalle()
    {
        $id = (int)($_GET['id'] ?? 0);
        $pedido = PedidoArticulosLimpiezaService::getPedido($id);

        if (!$pedido) {
            JsonResponse::error('Pedido no encontrado', 404);
        }

        $permisos = PedidoArticulosLimpiezaService::getPermisos();

        JsonResponse::custom([
            'success' => true,
            'pedido'  => $pedido,
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
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear pedidos');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::crearPedido($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProducto()
    {
        $idPedido = (int)($_POST['id_pedido'] ?? 0);
        $idProducto = (string)($_POST['id_producto'] ?? '');
        $otroProducto = (string)($_POST['otro_producto'] ?? '');
        $piezas = (int)($_POST['piezas'] ?? 0);
        $unidad = (string)($_POST['unidad'] ?? '');

        $result = PedidoArticulosLimpiezaService::agregarProducto($idPedido, $idProducto, $otroProducto, $piezas, $unidad);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarPiezas()
    {
        $id = (int)($_POST['id'] ?? 0);
        $piezas = (int)($_POST['piezas'] ?? 0);

        $result = PedidoArticulosLimpiezaService::editarPiezas($id, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarItem()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoArticulosLimpiezaService::eliminarItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function finalizar()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['esEncargado'] && !$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::finalizarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function entregar()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para entregar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::entregarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminar()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::eliminarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public function crearToken()
    {
        $id = (int)($_POST['id'] ?? 0);
        $via = (string)($_POST['via'] ?? 'telegram');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::crearToken($id, $idUsuario, $via);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmarVoBo()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            JsonResponse::forbidden('No tienes permiso para firmar el VoBo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $token = (string)($input['token'] ?? ($_POST['token'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::firmarVoBo($id, $idUsuario, $token);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= PDF ================= */

    public function pdf(int $id)
    {
        $result = PedidoArticulosLimpiezaService::generarHtmlPdf($id);
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

    /* ================= CATÁLOGO ================= */

    public function getCatalogos()
    {
        $soloActivos = (bool)($_GET['solo_activos'] ?? false);
        JsonResponse::custom([
            'success'   => true,
            'catalogos' => PedidoArticulosLimpiezaService::getCatalogos($soloActivos),
        ]);
    }

    public function guardarProducto()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para gestionar el catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $unidad = (string)($input['unidad'] ?? ($_POST['unidad'] ?? ''));
        $producto = (string)($input['producto'] ?? ($_POST['producto'] ?? ''));

        $result = PedidoArticulosLimpiezaService::guardarProducto($id, $unidad, $producto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProducto()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoArticulosLimpiezaService::eliminarProducto($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= INVENTARIO ================= */

    public function getInventario()
    {
        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'    => true,
            'inventario' => PedidoArticulosLimpiezaService::getInventario($idEstacion),
            'id_estacion' => $idEstacion,
        ]);
    }

    public function agregarInventario()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar el inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $piezas = (int)($input['piezas'] ?? ($_POST['piezas'] ?? 0));

        $result = PedidoArticulosLimpiezaService::agregarInventario($idEstacion, $idProducto, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarInventarioItem()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar del inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoArticulosLimpiezaService::eliminarInventarioItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= REPORTES ================= */

    public function getReportes()
    {
        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'  => true,
            'reportes' => PedidoArticulosLimpiezaService::getReportes($idEstacion),
        ]);
    }

    public function getReporte()
    {
        $id = (int)($_GET['id'] ?? 0);
        $reporte = PedidoArticulosLimpiezaService::getReporte($id);

        if (!$reporte) {
            JsonResponse::error('Reporte no encontrado', 404);
        }

        JsonResponse::custom(['success' => true, 'reporte' => $reporte]);
    }

    public function crearReporte()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear reportes');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? $this->resolverEstacion());
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoArticulosLimpiezaService::crearReporte($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function guardarReporteDatos()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $fecha = (string)($input['fecha'] ?? ($_POST['fecha'] ?? ''));
        $hora = (string)($input['hora'] ?? ($_POST['hora'] ?? ''));
        $detalle = (string)($input['detalle'] ?? ($_POST['detalle'] ?? ''));

        $result = PedidoArticulosLimpiezaService::guardarReporteDatos($id, $fecha, $hora, $detalle);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProductoReporte()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $unidad = (int)($input['unidad'] ?? ($_POST['unidad'] ?? 0));
        $observaciones = (string)($input['observaciones'] ?? ($_POST['observaciones'] ?? ''));

        $result = PedidoArticulosLimpiezaService::agregarProductoReporte($idReporte, $idEstacion, $idProducto, $unidad, $observaciones);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProductoReporte()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del reporte');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idItem = (int)($input['id_item'] ?? ($_POST['id_item'] ?? 0));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));

        $result = PedidoArticulosLimpiezaService::eliminarProductoReporte($idReporte, $idEstacion, $idItem, $idProducto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function aprobar()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para aprobar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoArticulosLimpiezaService::aprobarReporte($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarReporte()
    {
        $permisos = PedidoArticulosLimpiezaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));

        $result = PedidoArticulosLimpiezaService::eliminarReporte($id, $idEstacion);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }
}