<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\OrdenCompraService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\RhLocalidad;
use App\Models\Estacion;

class OrdenCompraController extends BaseController
{
public function index(int $idYear, int $idMes): void
    {
        $valid = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = $valid['idYear'];
        $idMes = $valid['idMes'];

        $title = 'Orden de Compra (' . nombremes($idMes) . ' ' . $idYear . ')';

        // Plantilla para que DropdownYearMesService construya las URLs de navegación
        $yearMesTemplate = '/departamento-operativo/almacen/orden-compra/{year}/{mes}';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes, $yearMesTemplate), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, 2023, $yearMesTemplate), '');

        View::render('departamento-operativo/4-almacen/orden-compra/index', [
            'title'           => $title,
            'idYear'          => $idYear,
            'idMes'           => $idMes,
            'yearMesTemplate' => $yearMesTemplate,
            'scripts'         => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/4-almacen/orden-compra.init.js?v=' . time(),
            ],
            'links'           => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function data(int $idYear, int $idMes): void
    {
        $data = OrdenCompraService::getLista($idYear, $idMes);
        JsonResponse::custom(['success' => true, 'data' => $data]);
    }

    public function crearOrden(int $idYear, int $idMes): void
    {
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);
        $result = OrdenCompraService::crearReporte($idYear, $idMes, $idUsuario);
        JsonResponse::custom($result);
    }

public function formulario(int $id): void
    {
        $detalle = OrdenCompraService::getDetalleCompleto($id);
        if (!$detalle) {
            header('Location: /departamento-operativo/almacen');
            exit;
        }

        $permisos = OrdenCompraService::getPermisos();
        $title = 'Formulario Orden de Compra';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add($title, '');

        // Catálogo de estaciones asignables a la Orden
        $estacionesCatalogo = RhLocalidad::where('numlista', '<=', 8)
            ->orWhereBetween('numlista', [22, 23])
            ->get(['id', 'localidad']);

        // Catálogo EXACTO de estaciones para prorrateo (numlista <= 8 de op_rh_localidades)
        $estacionesProrrateo = RhLocalidad::where('numlista', '<=', 8)
            ->orderBy('numlista', 'asc')
            ->get(['id', 'localidad as nombre']);

        View::render('departamento-operativo/4-almacen/orden-compra/formulario', [
            'title'               => $title,
            'idReporte'           => $id,
            'detalle'             => $detalle,
            'permisos'            => $permisos,
            'estacionesCatalogo'  => $estacionesCatalogo,
            'estacionesProrrateo' => $estacionesProrrateo,
            'scripts'             => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/departamento-operativo/4-almacen/orden-compra-form.js?v=' . time(),
            ],
            'links'               => [],
        ], 'departamento-operativo');
    }

    public function detalle(int $id): void
    {
        $detalle = OrdenCompraService::getDetalleCompleto($id);
        if (!$detalle) {
            header('Location: /departamento-operativo/almacen');
            exit;
        }

        $title = 'Detalle Orden de Compra';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/orden-compra/detalle', [
            'title'     => $title,
            'detalle'   => $detalle,
            'scripts'   => ['/assets/js/vendor.min.js?v=' . time()],
            'links'     => [],
        ], 'departamento-operativo');
    }

    public function firmarView(int $id): void
    {
        $detalle = OrdenCompraService::getDetalleCompleto($id);
        $permisos = OrdenCompraService::getPermisos();

        $title = 'Firmar Orden de Compra';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/orden-compra/firmar', [
            'title'     => $title,
            'idReporte' => $id,
            'detalle'   => $detalle,
            'permisos'  => $permisos,
            'scripts'   => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/4-almacen/orden-compra-firmar.js?v=' . time(),
            ],
            'links'     => [],
        ], 'departamento-operativo');
    }

    public function guardarEstacion(): void
    {
        $idReporte = (int)($_POST['idReporte'] ?? 0);
        $idEstacion = (int)($_POST['idEstacion'] ?? 0);
        $ok = OrdenCompraService::guardarEstacion($idReporte, $idEstacion);
        JsonResponse::custom(['success' => $ok]);
    }

    public function editarFormato(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $campo = (int)($_POST['num'] ?? 0);
        $valor = (string)($_POST['valor'] ?? '');

        $ok = OrdenCompraService::editarFormato($id, $campo, $valor);
        JsonResponse::custom(['success' => $ok]);
    }

    public function agregarProveedor(): void
    {
        $idReporte = (int)($_POST['idReporte'] ?? 0);
        $res = OrdenCompraService::agregarProveedor($idReporte, [
            'razon_social' => trim($_POST['RazonSocial'] ?? ''),
            'direccion'    => trim($_POST['Direccion'] ?? ''),
            'contacto'     => trim($_POST['Contacto'] ?? ''),
            'email'        => trim($_POST['Email'] ?? '')
        ]);
        JsonResponse::custom($res);
    }

    public function editarProveedor(): void
    {
        $idProveedor = (int)($_POST['idProveedor'] ?? 0);
        $res = OrdenCompraService::editarProveedor($idProveedor, [
            'razon_social' => trim($_POST['RazonSocial'] ?? ''),
            'direccion'    => trim($_POST['Direccion'] ?? ''),
            'contacto'     => trim($_POST['Contacto'] ?? ''),
            'email'        => trim($_POST['Email'] ?? '')
        ]);
        JsonResponse::custom($res);
    }

public function eliminarProveedor(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idProveedor = (int)($input['id'] ?? $input['idProveedor'] ?? 0);

        if ($idProveedor <= 0) {
            JsonResponse::custom(['success' => false, 'message' => 'ID de proveedor no válido.'], 400);
            return;
        }

        $res = OrdenCompraService::eliminarProveedor($idProveedor);
        JsonResponse::custom($res, $res['success'] ? 200 : 400);
    }

    public function seleccionarProveedor(): void
    {
        $idReporte = (int)($_POST['idReporte'] ?? 0);
        $idProveedor = (int)($_POST['idProveedor'] ?? 0);
        $valor = (int)($_POST['valor'] ?? 1);

        $ok = OrdenCompraService::seleccionarProveedor($idReporte, $idProveedor, $valor);
        JsonResponse::custom(['success' => $ok]);
    }

    public function actualizarCostosProveedor(): void
    {
        $idProveedor = (int)($_POST['idProveedor'] ?? 0);
        $tipo = (int)($_POST['tipo'] ?? 1); // 1: Descuento, 2: Envío
        $valor = (float)($_POST['valor'] ?? 0);

        $ok = OrdenCompraService::actualizarCostosProveedor($idProveedor, $tipo, $valor);
        JsonResponse::custom(['success' => $ok]);
    }

    public function agregarArticulo(): void
    {
        $idReporte = (int)($_POST['idReporte'] ?? 0);
        $idProveedor = (int)($_POST['id_proveedor'] ?? $_POST['Proveedor'] ?? 0);

        $res = OrdenCompraService::agregarArticulo($idReporte, [
            'id_proveedor'    => $idProveedor,
            'concepto'        => (string)($_POST['Concepto'] ?? ''),
            'unidades'        => (float)($_POST['Unidades'] ?? 0),
            'estatus_r'       => (string)($_POST['EstatusR'] ?? ''),
            'precio_unitario' => (float)($_POST['PrecioUnitario'] ?? 0)
        ]);
        JsonResponse::custom($res);
    }

public function eliminarArticulo(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::custom(['success' => false, 'message' => 'ID de artículo no válido.'], 400);
            return;
        }

        $ok = OrdenCompraService::eliminarArticulo($id);
        JsonResponse::custom([
            'success' => $ok,
            'message' => $ok ? 'Artículo eliminado exitosamente.' : 'Error al eliminar el artículo.'
        ], $ok ? 200 : 400);
    }

    public function agregarRefacturacion(): void
    {
        $idReporte = (int)($_POST['idReporte'] ?? 0);
        $res = OrdenCompraService::agregarRefacturacion($idReporte, [
            'id_estacion' => (int)($_POST['Estacion'] ?? 0),
            'descripcion' => (string)($_POST['Descripcion'] ?? ''),
            'cantidad'    => (float)($_POST['Cantidad'] ?? 0),
            'importe'     => (float)($_POST['Importe'] ?? 0),
            'porcentaje'  => (float)($_POST['Porcentaje'] ?? 0),
            'cantidadES'  => (float)($_POST['CantidadES'] ?? 0),
            'cantidadAl'  => (float)($_POST['CantidadAl'] ?? 0)
        ]);
        JsonResponse::custom($res);
    }

public function eliminarRefacturacion(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::custom(['success' => false, 'message' => 'ID de refacturación no válido.'], 400);
            return;
        }

        $ok = OrdenCompraService::eliminarRefacturacion($id);
        JsonResponse::custom([
            'success' => $ok,
            'message' => $ok ? 'Registro eliminado exitosamente.' : 'Error al eliminar la refacturación.'
        ], $ok ? 200 : 400);
    }

    public function finalizarPad(): void
    {
        $id = (int)($_POST['idReporte'] ?? 0);
        $base64 = (string)($_POST['base64'] ?? '');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $res = OrdenCompraService::guardarFirmaPad($id, $idUsuario, $base64);
        JsonResponse::custom($res);
    }

    public function crearToken(): void
    {
        $id = (int)($_POST['idReporte'] ?? 0);
        $via = (int)($_POST['idVal'] ?? 1);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $res = OrdenCompraService::generarToken($id, $idUsuario, $via);
        JsonResponse::custom($res);
    }

    public function firmarToken(): void
    {
        $id = (int)($_POST['idReporte'] ?? 0);
        $token = trim((string)($_POST['TokenValidacion'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $res = OrdenCompraService::firmarToken($id, $idUsuario, $token);
        JsonResponse::custom($res);
    }

    public function destroy(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? $input['idCompra'] ?? 0);

        $res = OrdenCompraService::eliminarOrden($id);
        JsonResponse::custom($res, $res['code'] ?? 200);
    }

public function getDetalleData(int $id): void
    {
        $detalle = OrdenCompraService::getDetalleCompleto($id);
        if (!$detalle) {
            JsonResponse::custom(['success' => false, 'message' => 'No encontrado'], 404);
            return;
        }

        JsonResponse::custom(['success' => true, 'data' => $detalle]);
    }

public function downloadPdf(int $id): void
    {
        $detalle = OrdenCompraService::getDetalleCompleto($id);
        if (!$detalle) {
            http_response_code(404);
            echo 'Orden de compra no encontrada';
            return;
        }

        // Cargar logotipo en base64
        $logoPath = dirname(__DIR__, 2) . '/public/assets/images/logos/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
        }

        // Convertir la firma de elaboración (Tipo A) a base64
        if (!empty($detalle['firmas'])) {
            foreach ($detalle['firmas'] as &$f) {
                if ($f['tipo_firma'] === 'A' && !empty($f['firma'])) {
                    $firmaPath = dirname(__DIR__, 2) . '/public/uploads/firmas/orden-compra/' . $f['firma'];
                    if (file_exists($firmaPath)) {
                        $imgData = file_get_contents($firmaPath);
                        $type = pathinfo($firmaPath, PATHINFO_EXTENSION);
                        $f['base64_firma'] = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
                    }
                }
            }
        }
        unset($f);

        $titulo = 'Orden de Compra ' . $detalle['no_control'];

        ob_start();
        extract([
            'detalle'    => $detalle,
            'titulo'     => $titulo,
            'logoBase64' => $logoBase64
        ], EXTR_SKIP);

        // Ruta corregida a app/Views
        require __DIR__ . '/../Views/departamento-operativo/4-almacen/orden-compra/pdf.php';
        $html = ob_get_clean();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Necesario para cargar fuentes externas en Dompdf
        $options->set('defaultFont', 'Montserrat');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Numeración de páginas en el pie
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(520, 815, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.3, 0.3, 0.3]);

        $nombreArchivo = 'Reporte_Orden_Compra_' . $detalle['no_control'] . '.pdf';
        $dompdf->stream($nombreArchivo, ['Attachment' => false]);
    }

}