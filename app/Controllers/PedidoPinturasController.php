<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\PedidoPinturasService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PedidoPinturasController extends BaseController
{
    public function index()
    {
        $permisos = PedidoPinturasService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        $title = 'Pedido de Pinturas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPinturasService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-pinturas/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoPinturasService::MODULE_KEY,
            'pendientesData'   => PedidoPinturasService::getPendingCountsFlat(),
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    /* ================= VISTAS ================= */

    protected function vistarIndex(string $title, string $view, bool $showSelector = false, array $scripts = [], array $links = []): void
    {
        $permisos = PedidoPinturasService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Pinturas', '/departamento-operativo/comercializadora/pedido-pinturas');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPinturasService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-pinturas/' . $view, [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoPinturasService::MODULE_KEY,
            'ocultarSelectorEstacion' => !$showSelector,
            'pendientesData'   => PedidoPinturasService::getPendingCountsFlat(),
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
        $this->vistarIndex('Catálogo de pinturas', 'catalogo', false, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.catalogo.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function inventario()
    {
        $this->vistarIndex('Inventario de pinturas', 'inventario', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.inventario.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            '/assets/libs/select2/dist/css/select2.min.css',
            '/assets/css/select2-modal.css',
        ]);
    }

    public function reporte()
    {
        $this->vistarIndex('Reporte de pinturas', 'reporte', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.reporte.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function reporteDetalle(int $id)
    {
        $reporte = PedidoPinturasService::getReporte($id);
        if (!$reporte) {
            http_response_code(404);
            echo 'Reporte no encontrado';
            return;
        }

        $permisos = PedidoPinturasService::getPermisos();
        $title = 'Reporte de pinturas (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Pinturas', '/departamento-operativo/comercializadora/pedido-pinturas');
        Breadcrumb::add('Reporte de pinturas', '/departamento-operativo/comercializadora/pedido-pinturas/reporte');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPinturasService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-pinturas/reporte-detalle', [
            'title'            => $title,
            'reporte'          => $reporte,
            'moduleStationKey' => PedidoPinturasService::MODULE_KEY,
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.reporte-detalle.init.js?v=' . time(),
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
        $pedido = PedidoPinturasService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoPinturasService::getPermisos();
        $title = 'Pedido (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Pinturas', '/departamento-operativo/comercializadora/pedido-pinturas');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPinturasService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-pinturas/pedido', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoPinturasService::MODULE_KEY,
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
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.pedido.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function firma(int $id)
    {
        $pedido = PedidoPinturasService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar VoBo #00' . $id;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Pinturas', '/departamento-operativo/comercializadora/pedido-pinturas');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPinturasService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-pinturas/firma', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoPinturasService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.firma.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    private function resolverEstacion(): int
    {
        $idEstacion = (int)($_GET['id_estacion'] ?? ($_POST['id_estacion'] ?? 0));
        if ($idEstacion) {
            return $idEstacion;
        }
        $ctx = ModuleStationService::getContext(PedidoPinturasService::MODULE_KEY);
        return (int)($ctx['id_estacion'] ?? 0);
    }

    /* ================= PEDIDOS ================= */

    public function getData()
    {
        $idEstacion = $this->resolverEstacion();
        $ids = $idEstacion ? [$idEstacion] : PedidoPinturasService::getAllowedStationIds();

        $data = [];
        foreach ($ids as $id) {
            $data = array_merge($data, PedidoPinturasService::getPedidos($id));
        }

        usort($data, fn($a, $b) => $b['id'] <=> $a['id']);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function getPendingCountsEndpoint()
    {
        $flat = PedidoPinturasService::getPendingCountsFlat();
        $flat['contexto'] = PedidoPinturasService::getPendingCountsActual();
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function getDetalle()
    {
        $id = (int)($_GET['id'] ?? 0);
        $pedido = PedidoPinturasService::getPedido($id);

        if (!$pedido) {
            JsonResponse::error('Pedido no encontrado', 404);
        }

        $permisos = PedidoPinturasService::getPermisos();

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
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear pedidos');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::crearPedido($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProducto()
    {
        $idPedido = (int)($_POST['id_pedido'] ?? 0);
        $idProducto = (string)($_POST['id_producto'] ?? '');
        $otroProducto = (string)($_POST['otro_producto'] ?? '');
        $piezas = (int)($_POST['piezas'] ?? 0);
        $paraQue = (string)($_POST['para_que'] ?? '');

        $result = PedidoPinturasService::agregarProducto($idPedido, $idProducto, $otroProducto, $piezas, $paraQue);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarPiezas()
    {
        $id = (int)($_POST['id'] ?? 0);
        $piezas = (int)($_POST['piezas'] ?? 0);

        $result = PedidoPinturasService::editarPiezas($id, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarDetalle()
    {
        $id = (int)($_POST['id'] ?? 0);
        $detalle = (string)($_POST['detalle'] ?? '');

        $result = PedidoPinturasService::editarDetalle($id, $detalle);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarObservaciones()
    {
        $id = (int)($_POST['id'] ?? 0);
        $observaciones = (string)($_POST['observaciones'] ?? '');

        $result = PedidoPinturasService::editarObservaciones($id, $observaciones);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarItem()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPinturasService::eliminarItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function finalizar()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['esEncargado'] && !$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $firma = (string)($input['firma'] ?? ($_POST['firma'] ?? ''));
        $observaciones = (string)($input['observaciones'] ?? ($_POST['observaciones'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::finalizarPedido($id, $idUsuario, $firma, $observaciones);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminar()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::eliminarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public function crearToken()
    {
        $id = (int)($_POST['id'] ?? 0);
        $via = (string)($_POST['via'] ?? 'telegram');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::crearToken($id, $idUsuario, $via);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmarVoBo()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            JsonResponse::forbidden('No tienes permiso para firmar el VoBo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $token = (string)($input['token'] ?? ($_POST['token'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::firmarVoBo($id, $idUsuario, $token);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= PDF ================= */

    public function pdf(int $id)
    {
        $result = PedidoPinturasService::generarHtmlPdf($id);
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
            'catalogos' => PedidoPinturasService::getCatalogos($soloActivos),
        ]);
    }

    public function guardarProducto()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para gestionar el catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $unidad = (string)($input['unidad'] ?? ($_POST['unidad'] ?? ''));
        $producto = (string)($input['producto'] ?? ($_POST['producto'] ?? ''));

        $result = PedidoPinturasService::guardarProducto($id, $unidad, $producto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProducto()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPinturasService::eliminarProducto($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= INVENTARIO ================= */

    public function getInventario()
    {
        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'    => true,
            'inventario' => PedidoPinturasService::getInventario($idEstacion),
            'id_estacion' => $idEstacion,
        ]);
    }

    public function agregarInventario()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar el inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $piezas = (int)($input['piezas'] ?? ($_POST['piezas'] ?? 0));

        $result = PedidoPinturasService::agregarInventario($idEstacion, $idProducto, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarInventarioItem()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar del inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPinturasService::eliminarInventarioItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= REPORTES ================= */

    public function getReportes()
    {
        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'  => true,
            'reportes' => PedidoPinturasService::getReportes($idEstacion),
        ]);
    }

    public function getReporte()
    {
        $id = (int)($_GET['id'] ?? 0);
        $reporte = PedidoPinturasService::getReporte($id);

        if (!$reporte) {
            JsonResponse::error('Reporte no encontrado', 404);
        }

        JsonResponse::custom(['success' => true, 'reporte' => $reporte]);
    }

    public function crearReporte()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear reportes');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? $this->resolverEstacion());
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPinturasService::crearReporte($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function guardarReporteDatos()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $fecha = (string)($input['fecha'] ?? ($_POST['fecha'] ?? ''));
        $hora = (string)($input['hora'] ?? ($_POST['hora'] ?? ''));
        $detalle = (string)($input['detalle'] ?? ($_POST['detalle'] ?? ''));

        $result = PedidoPinturasService::guardarReporteDatos($id, $fecha, $hora, $detalle);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProductoReporte()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $unidad = (int)($input['unidad'] ?? ($_POST['unidad'] ?? 0));
        $observaciones = (string)($input['observaciones'] ?? ($_POST['observaciones'] ?? ''));

        $result = PedidoPinturasService::agregarProductoReporte($idReporte, $idEstacion, $idProducto, $unidad, $observaciones);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProductoReporte()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del reporte');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));
        $idItem = (int)($input['id_item'] ?? ($_POST['id_item'] ?? 0));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));

        $result = PedidoPinturasService::eliminarProductoReporte($idReporte, $idEstacion, $idItem, $idProducto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function aprobar()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para aprobar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPinturasService::aprobarReporte($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarReporte()
    {
        $permisos = PedidoPinturasService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idEstacion = (int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? $this->resolverEstacion()));

        $result = PedidoPinturasService::eliminarReporte($id, $idEstacion);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }
}