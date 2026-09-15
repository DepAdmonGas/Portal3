<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\PreciosCombustibleService;
use App\Services\DropdownYearMesService;

class PreciosCombustibleController extends BaseController
{
    public function index()
    {
        $validated = DropdownYearMesService::validarYearMes(0, 0);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $this->renderIndex($idYear, $idMes);
    }

    public function filtrarPorAnioMes(int $year, int $mes)
    {
        $validated = DropdownYearMesService::validarYearMes($year, $mes);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $this->renderIndex($idYear, $idMes);
    }

    private function renderIndex(int $idYear, int $idMes): void
    {
        $permisos = PreciosCombustibleService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Precios diarios de combustible (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, 2023), '');

        $yearMesTemplate = '/departamento-operativo/importacion/precios-diarios-combustible/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/precios-diarios-combustible/index', [
            'title'            => $title,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'yearMesTemplate'  => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/3-importacion/precios-diarios-combustible.datatable.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        $input = Request::all();
        $year = !empty($input['year']) ? (int)$input['year'] : (int)date('Y');
        $mes = !empty($input['mes']) ? (int)$input['mes'] : (int)date('n');

        JsonResponse::success('OK', [
            'data'     => PreciosCombustibleService::getData($year, $mes),
            'permisos' => $permisos,
        ]);
    }

    public function agregar()
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permisos para agregar.');
        }

        $fecha = trim((string)Request::input('fecha', ''));
        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            JsonResponse::error('Fecha inválida.');
        }

        $year = (int)$fechaObj->format('Y');
        $mes = (int)$fechaObj->format('n');

        $idFormato = PreciosCombustibleService::crear($year, $mes, $fecha);

        JsonResponse::success('Registro creado', ['id' => $idFormato]);
    }

    public function formulario(int $id)
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $data = PreciosCombustibleService::getFormato($id);
        if (!$data) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $formato = $data['formato'];
        $transporte = $data['transporte'];
        $detalle = $data['detalle'];
        $fecha = $formato->fecha;
        $estatus = (int)$formato->estatus;
        $ocultarVopak = PreciosCombustibleService::isVopakHidden($fecha);

        $transporteTarifas = [];
        foreach ($transporte as $t) {
            $precio = (float)$t->precio;
            $iva = number_format($precio * 0.16, 4);
            $retencion = number_format($precio * 0.04, 4);
            $tarifa = number_format($precio + $iva - $retencion, 4);
            $transporteTarifas[] = [
                'id'        => $t->id,
                'detalle'   => $t->detalle,
                'precio'    => $precio,
                'iva'       => $iva,
                'retencion' => $retencion,
                'tarifa'    => $tarifa,
            ];
        }

        $detalleConTarifas = [];
        foreach ($detalle as $d) {
            $tuxpanTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Tuxpan');
            $vopakTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Vopak');
            $tizayucaTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Tizayuca');
            $pueblaTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Puebla');

            $pemex = (float)$d->pemex;
            $pickup_vopak = (float)$d->pickup_vopak;
            $pickup_tuxpan = (float)$d->pickup_tuxpan;
            $pickup_montera = (float)$d->pickup_montera;
            $pickup_tizayuca = (float)$d->pickup_tizayuca;
            $pickup_puebla = (float)$d->pickup_puebla;

            $delivery_montera = (float)$d->delivery_montera;
            $delivery_tuxpan = (float)$d->delivery_tuxpan;
            $delivery_vopak = (float)$d->delivery_vopak;

            $detalleConTarifas[] = [
                'id'                => $d->id,
                'producto'          => $d->producto,
                'pemex'             => $pemex,
                'delivery_montera'  => $delivery_montera,
                'delivery_tuxpan'   => $delivery_tuxpan,
                'delivery_vopak'    => $delivery_vopak,
                'pickup_vopak'      => $pickup_vopak,
                'pickup_tuxpan'     => $pickup_tuxpan,
                'pickup_montera'    => $pickup_montera,
                'pickup_tizayuca'   => $pickup_tizayuca,
                'pickup_puebla'     => $pickup_puebla,
                'tarifa_tuxpan'     => $tuxpanTarifa,
                'tarifa_vopak'      => $vopakTarifa,
                'tarifa_tizayuca'   => $tizayucaTarifa,
                'tarifa_puebla'     => $pueblaTarifa,
                'dif_delivery_montera' => $delivery_montera - $pemex,
                'dif_delivery_tuxpan'  => $delivery_tuxpan - $pemex,
                'dif_delivery_vopak'   => $delivery_vopak - $pemex,
                'dif_pickup_vopak'     => ($vopakTarifa + $pickup_vopak) - $pemex,
                'dif_pickup_tuxpan'    => ($tuxpanTarifa + $pickup_tuxpan) - $pemex,
                'dif_pickup_montera'   => ($tuxpanTarifa + $pickup_montera) - $pemex,
                'dif_pickup_tizayuca'  => ($tizayucaTarifa + $pickup_tizayuca) - $pemex,
                'dif_pickup_puebla'    => ($pueblaTarifa + $pickup_puebla) - $pemex,
            ];
        }

        $editable = $estatus === 0 && $permisos['puedeEditar'];

        $tablas = PreciosCombustibleService::buildTablasHtml(
            $transporteTarifas,
            $detalleConTarifas,
            'editar',
            $editable,
            $ocultarVopak
        ); 

        $accionesHeader = '';
        if ($editable) {
            $accionesHeader = '<div class="col-12 mt-2">'
                . '<button type="button" class="btn bg-success text-white float-end mt-4" id="btnFinalizar"><i class="ti ti-check me-1"></i> Finalizar</button>'
                . '</div>';
        }

        $title = 'Formulario (' . formatearFecha($fecha) . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Precios diarios de combustible', '/departamento-operativo/importacion/precios-diarios-combustible');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/precios-diarios-combustible/editar', [
            'title'          => $title,
            'fecha'          => $fecha,
            'accionesHeader' => $accionesHeader,
            'tablas'         => $tablas,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/precios-diarios-combustible.editar.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function detalle(int $id)
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeDetalle']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $data = PreciosCombustibleService::getFormato($id);
        if (!$data) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $formato = $data['formato'];
        $transporte = $data['transporte'];
        $detalle = $data['detalle'];
        $fecha = $formato->fecha;
        $ocultarVopak = PreciosCombustibleService::isVopakHidden($fecha);

        $transporteTarifas = [];
        foreach ($transporte as $t) {
            $precio = (float)$t->precio;
            $iva = $precio * 0.16;
            $retencion = $precio * 0.04;
            $tarifa = $precio + $iva - $retencion;
            $transporteTarifas[] = [
                'detalle'   => $t->detalle,
                'precio'    => $precio,
                'iva'       => $iva,
                'retencion' => $retencion,
                'tarifa'    => $tarifa,
            ];
        }

        $tuxpanTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Tuxpan');
        $vopakTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Vopak');
        $tizayucaTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Tizayuca');
        $pueblaTarifa = PreciosCombustibleService::getTransporteTarifa($id, 'Puebla');

        $detalleConTarifas = [];
        foreach ($detalle as $d) {
            $pemex = (float)$d->pemex;
            $pickup_vopak = (float)$d->pickup_vopak + $vopakTarifa;
            $pickup_tuxpan = (float)$d->pickup_tuxpan + $tuxpanTarifa;
            $pickup_montera = (float)$d->pickup_montera + $tuxpanTarifa;
            $pickup_tizayuca = (float)$d->pickup_tizayuca + $tizayucaTarifa;
            $pickup_puebla = (float)$d->pickup_puebla + $pueblaTarifa;

            $precioArray = $ocultarVopak
                ? [$d->delivery_montera, $d->delivery_tuxpan, $pickup_tuxpan, $pickup_montera, $pickup_tizayuca, $pickup_puebla, $pemex]
                : [$d->delivery_montera, $d->delivery_vopak, $d->delivery_tuxpan, $pickup_vopak, $pickup_tuxpan, $pickup_montera, $pickup_tizayuca, $pickup_puebla, $pemex];

            $minPrecio = min($precioArray);

            $detalleConTarifas[] = [
                'id'               => $d->id,
                'producto'         => $d->producto,
                'pemex'            => $pemex,
                'delivery_montera' => (float)$d->delivery_montera,
                'delivery_vopak'   => (float)$d->delivery_vopak,
                'delivery_tuxpan'  => (float)$d->delivery_tuxpan,
                'pickup_vopak'     => $pickup_vopak,
                'pickup_tuxpan'    => $pickup_tuxpan,
                'pickup_montera'   => $pickup_montera,
                'pickup_tizayuca'  => $pickup_tizayuca,
                'pickup_puebla'    => $pickup_puebla,
                'dif_delivery_montera' => (float)$d->delivery_montera - $pemex,
                'dif_delivery_vopak'   => (float)$d->delivery_vopak - $pemex,
                'dif_delivery_tuxpan'  => (float)$d->delivery_tuxpan - $pemex,
                'dif_pickup_vopak'     => $pickup_vopak - $pemex,
                'dif_pickup_tuxpan'    => $pickup_tuxpan - $pemex,
                'dif_pickup_montera'   => $pickup_montera - $pemex,
                'dif_pickup_tizayuca'  => $pickup_tizayuca - $pemex,
                'dif_pickup_puebla'    => $pickup_puebla - $pemex,
                'min_precio'           => $minPrecio,
            ];
        }

        $verTodo = $permisos['esPuesto13'];

        $tablas = ['transporte' => '', 'detalle' => ''];
        if ($verTodo) {
            $tablas = PreciosCombustibleService::buildTablasHtml(
                $transporteTarifas,
                $detalleConTarifas,
                'detalle',
                false,
                $ocultarVopak
            );
        }

        $title = 'Detalle (' . formatearFecha($fecha) . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Precios diarios de combustible', '/departamento-operativo/importacion/precios-diarios-combustible');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/precios-diarios-combustible/detalle', [
            'title'       => $title,
            'tablas'      => $tablas,
            'reporteHtml' => PreciosCombustibleService::buildReporteHtml($id, $verTodo),
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/precios-diarios-combustible.detalle.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function update()
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para editar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);
        $valor = $input['valor'] ?? '';
        $num = (int)($input['num'] ?? -1);

        if ($id <= 0 || $num < 0) {
            JsonResponse::error('Parámetros inválidos.');
        }

        $ok = PreciosCombustibleService::updateField($id, $valor, $num);
        JsonResponse::success($ok ? 'Actualizado' : 'Error al actualizar', ['ok' => $ok]);
    }

    public function finalizar()
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permisos para finalizar.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::error('ID inválido.');
        }

        $ok = PreciosCombustibleService::finalizar($id);
        JsonResponse::success($ok ? 'Finalizado exitosamente' : 'Error al finalizar', ['ok' => $ok]);
    }

    public function togglePrecioBajo()
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['esPuesto13']) {
            JsonResponse::forbidden('No tienes permisos.');
        }

        $input = Request::all();
        $idPrecio = (int)($input['idPrecio'] ?? 0);
        $valCheck = (int)($input['valCheck'] ?? 0);
        $num = (int)($input['num'] ?? 0);
        $producto = $input['producto'] ?? '';

        if ($idPrecio <= 0 || $num <= 0 || $num > 10 || empty($producto)) {
            JsonResponse::error('Parámetros inválidos.');
        }

        $ok = PreciosCombustibleService::togglePrecioBajo($idPrecio, $valCheck, $num, $producto);
        JsonResponse::success($ok ? 'Actualizado' : 'Error', ['ok' => $ok]);
    }

    public function reporte(int $id)
    {
        $permisos = PreciosCombustibleService::getPermisos();
        if (!$permisos['puedeDetalle']) {
            JsonResponse::forbidden('No tienes acceso.');
        }

        $data = PreciosCombustibleService::getReporte($id);
        if (empty($data)) {
            JsonResponse::error('No encontrado.', [], 404);
        }

        View::renderPartial('departamento-operativo/3-importacion/precios-diarios-combustible/partials/reporte', [
            'reporteHtml' => PreciosCombustibleService::buildReporteHtml($id, $permisos['esPuesto13']),
        ]);
    }
}
