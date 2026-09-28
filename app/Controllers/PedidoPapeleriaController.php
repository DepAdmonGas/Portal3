<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\PedidoPapeleriaService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PedidoPapeleriaController extends BaseController
{
    public function index()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];

        $idDepto = 0;
        if ($permisos['esGestoriaOficina']) {
            $esMultiestacion = false;
            $idEstacion = 0;
            $idDepto = PedidoPapeleriaService::ID_DEPTO_GESTION;
            $ocultarSelectorEstacion = true;
            ModuleStationService::setContext(PedidoPapeleriaService::MODULE_KEY, null, $idDepto);
        } else {
            $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];
            $ocultarSelectorEstacion = !$esMultiestacion && (int)$idEstacion === PedidoPapeleriaService::ID_ESTACION_PALO_SOLO;
        }

        $title = 'Pedido de Papelería';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPapeleriaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-papeleria/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'idDepto'          => $idDepto,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoPapeleriaService::MODULE_KEY,
            'ocultarSelectorEstacion' => $ocultarSelectorEstacion,
            'esGestoriaOficina'  => $permisos['esGestoriaOficina'],
            'esDireccionOficina' => $permisos['esDireccionOficina'],
            'pendientesData'   => PedidoPapeleriaService::getPendingCountsFlat(),
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    /* ================= VISTAS ================= */

    protected function vistarIndex(string $title, string $view, bool $showSelector = false, array $scripts = [], array $links = []): void
    {
        $permisos = PedidoPapeleriaService::getPermisos();

        if ($permisos['esGestoriaOficina']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];
        $ocultarSelectorEstacion = !$esMultiestacion && (int)$idEstacion === PedidoPapeleriaService::ID_ESTACION_PALO_SOLO;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Papelería', '/departamento-operativo/comercializadora/pedido-papeleria');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPapeleriaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-papeleria/' . $view, [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'idDepto'          => 0,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => PedidoPapeleriaService::MODULE_KEY,
            'ocultarSelectorEstacion' => !$showSelector || $ocultarSelectorEstacion,
            'esGestoriaOficina'  => false,
            'esDireccionOficina' => $permisos['esDireccionOficina'],
            'pendientesData'   => PedidoPapeleriaService::getPendingCountsFlat(),
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
        $this->vistarIndex('Catálogo de papelería', 'catalogo', false, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.catalogo.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function inventario()
    {
        $this->vistarIndex('Inventario de papelería', 'inventario', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.inventario.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            '/assets/libs/select2/dist/css/select2.min.css',
            '/assets/css/select2-modal.css',
        ]);
    }

    public function reporte()
    {
        $this->vistarIndex('Reporte de papelería', 'reporte', true, [
            '/assets/js/vendor.min.js?v=' . time(),
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/core/module-station-selector.js?v=' . time(),
            '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.reporte.init.js?v=' . time(),
        ], [
            '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        ]);
    }

    public function reporteDetalle(int $id)
    {
        $reporte = PedidoPapeleriaService::getReporte($id);
        if (!$reporte) {
            http_response_code(404);
            echo 'Reporte no encontrado';
            return;
        }

        $permisos = PedidoPapeleriaService::getPermisos();

        if ($permisos['esGestoriaOficina']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Reporte de papelería (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Papelería', '/departamento-operativo/comercializadora/pedido-papeleria');
        Breadcrumb::add('Reporte de papelería', '/departamento-operativo/comercializadora/pedido-papeleria/reporte');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPapeleriaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-papeleria/reporte-detalle', [
            'title'            => $title,
            'reporte'          => $reporte,
            'moduleStationKey' => PedidoPapeleriaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'esGestoriaOficina'  => false,
            'esDireccionOficina' => $permisos['esDireccionOficina'],
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.reporte-detalle.init.js?v=' . time(),
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
        $pedido = PedidoPapeleriaService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        $title = 'Pedido (#00' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Papelería', '/departamento-operativo/comercializadora/pedido-papeleria');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPapeleriaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-papeleria/pedido', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoPapeleriaService::MODULE_KEY,
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
                '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.pedido.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
        ], 'departamento-operativo');
    }

    public function firma(int $id)
    {
        $pedido = PedidoPapeleriaService::getPedido($id);
        if (!$pedido) {
            http_response_code(404);
            echo 'Pedido no encontrado';
            return;
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar VoBo #00' . $id;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Comercializadora', '/departamento-operativo/comercializadora');
        Breadcrumb::add('Pedido de Papelería', '/departamento-operativo/comercializadora/pedido-papeleria');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PedidoPapeleriaService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/5-comercializadora/pedido-papeleria/firma', [
            'title'            => $title,
            'pedido'           => $pedido,
            'moduleStationKey' => PedidoPapeleriaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeFirmarVoBo'  => $permisos['puedeFirmarVoBo'],
            'idUsuario'        => $permisos['id_usuario'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/5-comercializadora/pedido-papeleria.firma.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    private function resolverFiltro(): array
    {
        $idDepto = (int)($_GET['id_depto'] ?? ($_POST['id_depto'] ?? 0));
        if ($idDepto) {
            return ['id_estacion' => 0, 'id_depto' => $idDepto];
        }

        $idEstacion = (int)($_GET['id_estacion'] ?? ($_POST['id_estacion'] ?? 0));
        if ($idEstacion) {
            $converted = PedidoPapeleriaService::estacionDesdeLocalidad($idEstacion);
            return ['id_estacion' => $converted ?? 0, 'id_depto' => 0];
        }

        $ctx = PedidoPapeleriaService::getContextoEfectivo();
        if (!empty($ctx['id_depto'])) {
            return ['id_estacion' => 0, 'id_depto' => (int)$ctx['id_depto']];
        }
        if (!empty($ctx['id_estacion'])) {
            $converted = PedidoPapeleriaService::estacionDesdeLocalidad((int)$ctx['id_estacion']);
            return ['id_estacion' => $converted ?? 0, 'id_depto' => 0];
        }

        return ['id_estacion' => 0, 'id_depto' => 0];
    }

    private function resolverEstacion(): int
    {
        $idEstacion = (int)($_GET['id_estacion'] ?? ($_POST['id_estacion'] ?? 0));
        if ($idEstacion) {
            return PedidoPapeleriaService::estacionDesdeLocalidad($idEstacion) ?? $idEstacion;
        }

        $idDepto = (int)($_GET['id_depto'] ?? ($_POST['id_depto'] ?? 0));
        if ($idDepto) {
            return PedidoPapeleriaService::ID_ESTACION_DEPARTAMENTOS;
        }

        $ctx = PedidoPapeleriaService::getContextoEfectivo();
        if (!empty($ctx['id_depto'])) {
            return PedidoPapeleriaService::ID_ESTACION_DEPARTAMENTOS;
        }
        if (!empty($ctx['id_estacion'])) {
            $converted = PedidoPapeleriaService::estacionDesdeLocalidad((int)$ctx['id_estacion']);
            return $converted ?? 0;
        }

        return 0;
    }

    private function esGestoriaOficinaEnSesion(): bool
    {
        return !empty(PedidoPapeleriaService::getPermisos()['esGestoriaOficina']);
    }

    /* ================= PEDIDOS ================= */

    public function getData()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        $filtro = $this->resolverFiltro();

        if ($permisos['esGestoriaOficina']
            && ($filtro['id_depto'] !== PedidoPapeleriaService::ID_DEPTO_GESTION || $filtro['id_estacion'] !== 0)) {
            JsonResponse::forbidden('No tienes permiso para consultar esta información');
        }

        $data = PedidoPapeleriaService::getPedidosFiltro($filtro);

        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function getPendingCountsEndpoint()
    {
        $flat = PedidoPapeleriaService::getPendingCountsFlat();
        $flat['contexto'] = PedidoPapeleriaService::getPendingCountsActual();
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function getDetalle()
    {
        $id = (int)($_GET['id'] ?? 0);
        $pedido = PedidoPapeleriaService::getPedido($id);

        if (!$pedido) {
            JsonResponse::error('Pedido no encontrado', 404);
        }

        $permisos = PedidoPapeleriaService::getPermisos();

        if ($permisos['esGestoriaOficina']
            && ((int)$pedido['id_estacion'] !== PedidoPapeleriaService::ID_ESTACION_DEPARTAMENTOS
                || (int)$pedido['depto'] !== PedidoPapeleriaService::ID_DEPTO_GESTION)) {
            JsonResponse::forbidden('No tienes permiso para consultar esta información');
        }

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
        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear pedidos');
        }

        $idEstacion = (int)($_POST['id_estacion'] ?? 0);
        $idDepto = (int)($_POST['id_depto'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($idDepto > 0) {
            $permitido = ($permisos['esGestoriaOficina'] && $idDepto === PedidoPapeleriaService::ID_DEPTO_GESTION)
                || ($permisos['esDireccionOficina'] && $idDepto === PedidoPapeleriaService::ID_DEPTO_DIRECCION_OPERACIONES);
            if (!$permitido) {
                JsonResponse::forbidden('No tienes permiso para crear pedidos en este departamento');
            }
        } elseif ($permisos['esGestoriaOficina'] || $permisos['esDireccionOficina']) {
            JsonResponse::forbidden('No tienes permiso para crear pedidos en estaciones');
        }

        $result = PedidoPapeleriaService::crearPedido($idEstacion, $idUsuario, $idDepto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProducto()
    {
        $idPedido = (int)($_POST['id_pedido'] ?? 0);
        $idProducto = (string)($_POST['id_producto'] ?? '');
        $otroProducto = (string)($_POST['otro_producto'] ?? '');
        $piezas = (int)($_POST['piezas'] ?? 0);

        $result = PedidoPapeleriaService::agregarProducto($idPedido, $idProducto, $otroProducto, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function editarPiezas()
    {
        $id = (int)($_POST['id'] ?? 0);
        $piezas = (int)($_POST['piezas'] ?? 0);

        $result = PedidoPapeleriaService::editarPiezas($id, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarItem()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPapeleriaService::eliminarItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function finalizar()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['esEncargado'] && !$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPapeleriaService::finalizarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminar()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar pedidos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPapeleriaService::eliminarPedido($id, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public function crearToken()
    {
        $id = (int)($_POST['id'] ?? 0);
        $via = (string)($_POST['via'] ?? 'telegram');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPapeleriaService::crearToken($id, $idUsuario, $via);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmarVoBo()
    {
        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeFirmarVoBo']) {
            JsonResponse::forbidden('No tienes permiso para firmar el VoBo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $token = (string)($input['token'] ?? ($_POST['token'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPapeleriaService::firmarVoBo($id, $idUsuario, $token);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= PDF ================= */

    public function pdf(int $id)
    {
        $result = PedidoPapeleriaService::generarHtmlPdf($id);
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
            'catalogos' => PedidoPapeleriaService::getCatalogos($soloActivos),
        ]);
    }

    public function guardarProducto()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para gestionar el catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $producto = (string)($input['producto'] ?? ($_POST['producto'] ?? ''));

        $result = PedidoPapeleriaService::guardarProducto($id, $producto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProducto()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del catálogo');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPapeleriaService::eliminarProducto($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= INVENTARIO ================= */

    public function getInventario()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'    => true,
            'inventario' => PedidoPapeleriaService::getInventario($idEstacion),
            'id_estacion' => $idEstacion,
        ]);
    }

    public function agregarInventario()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar el inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idEstacion = PedidoPapeleriaService::estacionDesdeLocalidad((int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? 0)))
            ?? $this->resolverEstacion();
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $piezas = (int)($input['piezas'] ?? ($_POST['piezas'] ?? 0));

        $result = PedidoPapeleriaService::agregarInventario($idEstacion, $idProducto, $piezas);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarInventarioItem()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar del inventario');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPapeleriaService::eliminarInventarioItem($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    /* ================= REPORTES ================= */

    public function getReportes()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $idEstacion = $this->resolverEstacion();
        JsonResponse::custom([
            'success'  => true,
            'reportes' => PedidoPapeleriaService::getReportes($idEstacion),
        ]);
    }

    public function getReporte()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $id = (int)($_GET['id'] ?? 0);
        $reporte = PedidoPapeleriaService::getReporte($id);

        if (!$reporte) {
            JsonResponse::error('Reporte no encontrado', 404);
        }

        JsonResponse::custom(['success' => true, 'reporte' => $reporte]);
    }

    public function crearReporte()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear reportes');
        }

        $idEstacion = $this->resolverEstacion();
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $result = PedidoPapeleriaService::crearReporte($idEstacion, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function guardarReporteDatos()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $fecha = (string)($input['fecha'] ?? ($_POST['fecha'] ?? ''));
        $hora = (string)($input['hora'] ?? ($_POST['hora'] ?? ''));
        $detalle = (string)($input['detalle'] ?? ($_POST['detalle'] ?? ''));

        $result = PedidoPapeleriaService::guardarReporteDatos($id, $fecha, $hora, $detalle);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function agregarProductoReporte()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = PedidoPapeleriaService::estacionDesdeLocalidad((int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? 0)))
            ?? $this->resolverEstacion();
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));
        $unidad = (int)($input['unidad'] ?? ($_POST['unidad'] ?? 0));
        $observaciones = (string)($input['observaciones'] ?? ($_POST['observaciones'] ?? ''));

        $result = PedidoPapeleriaService::agregarProductoReporte($idReporte, $idEstacion, $idProducto, $unidad, $observaciones);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarProductoReporte()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar productos del reporte');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($input['id_reporte'] ?? ($_POST['id_reporte'] ?? 0));
        $idEstacion = PedidoPapeleriaService::estacionDesdeLocalidad((int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? 0)))
            ?? $this->resolverEstacion();
        $idItem = (int)($input['id_item'] ?? ($_POST['id_item'] ?? 0));
        $idProducto = (int)($input['id_producto'] ?? ($_POST['id_producto'] ?? 0));

        $result = PedidoPapeleriaService::eliminarProductoReporte($idReporte, $idEstacion, $idItem, $idProducto);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function aprobar()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para aprobar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));

        $result = PedidoPapeleriaService::aprobarReporte($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function eliminarReporte()
    {
        if ($this->esGestoriaOficinaEnSesion()) {
            JsonResponse::forbidden('No tienes permiso para esta sección');
        }

        $permisos = PedidoPapeleriaService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar reportes');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        $idEstacion = PedidoPapeleriaService::estacionDesdeLocalidad((int)($input['id_estacion'] ?? ($_POST['id_estacion'] ?? 0)))
            ?? $this->resolverEstacion();

        $result = PedidoPapeleriaService::eliminarReporte($id, $idEstacion);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }
}