<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\Embarque;
use App\Models\Operativo\Medicion;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;

class AnalisisCompraImportacionService
{
public const MODULE_KEY = 'analisis-compra';

public const PRODUCTOS = ['G SUPER', 'G PREMIUM', 'G DIESEL'];

public const TIPOS_EMBARQUE = ['Pemex', 'Delivery', 'Pick Up'];

public const INVENTARIO = [
'G SUPER'   => 4920,
'G PREMIUM' => 4190,
'G DIESEL'  => 903,
];

public const COLORES_PRODUCTO = [
'G SUPER'   => '#76bd1d',
'G PREMIUM' => '#e21683',
'G DIESEL'  => '#5e0f8e',
];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

$allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
$permisosDb = $allPerms['importacion'] ?? [];

$tieneSubmenu = false;
foreach ($permisosDb['submenus'] ?? [] as $sm) {
if (($sm['clave'] ?? '') === self::MODULE_KEY) {
$tieneSubmenu = true;
break;
}
}

$leer = !empty($permisosDb['leer']);
$editar = !empty($permisosDb['editar']);

$esDireccionOperaciones = $nombrePuesto === 'Dirección de operaciones';
$esContabilidad = $nombrePuesto === 'Contabilidad';
$esComercializadora = $nombrePuesto === 'Comercializadora';
$esPuestoAutorizado = $esDireccionOperaciones || $esContabilidad || $esComercializadora;

return [
'id_usuario'               => $idUsuario,
'id_estacion'              => $idEstacion,
'nombre_puesto'            => $nombrePuesto,
'multiestacion'            => $multiestacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad'          => $esContabilidad,
'es_comercializadora'      => $esComercializadora,
'puedeVer'                 => $tieneSubmenu && $leer,
'puedeEditar'              => $tieneSubmenu && ($editar || $esPuestoAutorizado),
];
}

public static function getEstacionesPermitidas(): array
{
$stations = ModuleStationService::getAvailableStations(self::MODULE_KEY);
return array_values(array_column($stations, 'id'));
}

public static function getEstaciones(): array
{
return ModuleStationService::getAvailableStations(self::MODULE_KEY);
}

public static function puedeEstacion(int $idEstacion): bool
{
return in_array($idEstacion, self::getEstacionesPermitidas(), true);
}

public static function getEstacionContexto(): ?int
{
$ctx = ModuleStationService::getContext(self::MODULE_KEY);
return $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;
}

public static function getEstacionNombre(int $idEstacion): string
{
$estacion = Estacion::find($idEstacion);
return $estacion ? (string)$estacion->nombre : 'Estación #' . $idEstacion;
}

public static function resolverCorte(int $idEstacion, int $idYear, int $idMes): ?int
{
$corteYear = CorteYear::where('id_estacion', $idEstacion)
->where('year', $idYear)
->first();

if (!$corteYear) {
return null;
}

$corteMes = CorteMes::where('id_year', $corteYear->id)
->where('mes', $idMes)
->first();

return $corteMes ? (int)$corteMes->id : null;
}

public static function validarEmbarques(int $idEstacion, int $idReporte): void
{
$embarques = Embarque::where('id_mes', $idReporte)
->where(function ($q) {
$q->where('bruto', 0)
->orWhere('neto', 0);
})
->get();

foreach ($embarques as $embarque) {
$datos = self::buscarBrutoNeto($idEstacion, $embarque->fecha->format('Y-m-d'), (float)$embarque->importef);

$embarque->bruto = (int)$datos['bruto'];
$embarque->neto = (int)$datos['neto'];
$embarque->save();
}
}

public static function buscarBrutoNeto(int $idEstacion, string $fecha, float $importef): array
{
$medicion = Medicion::where('id_estacion', $idEstacion)
->where('fecha', $fecha)
->where('factura', (string)$importef)
->orderByDesc('id')
->first();

if (!$medicion) {
return ['bruto' => 0, 'neto' => 0];
}

return [
'bruto' => (float)$medicion->bruto,
'neto'  => (float)$medicion->neto,
];
}

public static function getDatos(int $idEstacion, int $idYear, int $idMes, bool $validar = true): array
{
    $idReporte = self::resolverCorte($idEstacion, $idYear, $idMes);

    if (!$idReporte) {
        return ['idReporte' => null, 'productos' => []];
    }

    if ($validar) {
        self::validarEmbarques($idEstacion, $idReporte);
    }

$productos = [];

foreach (self::PRODUCTOS as $producto) {
$embarques = Embarque::where('id_mes', $idReporte)
->where('producto', $producto)
->orderBy('fecha', 'asc')
->get();

$columnas = [];
$totales = [
'lts_factura'    => 0,
'bruto'          => 0,
'dif1'           => 0,
'neto'           => 0,
'dif2'           => 0,
'metro_contador' => 0,
'dif3'           => 0,
];

foreach ($embarques as $embarque) {
$importef = (float)$embarque->importef;
$bruto = (float)$embarque->bruto;
$neto = (float)$embarque->neto;

$dif1 = $bruto - $importef;
$dif2 = $neto - $importef;
$mcpo = round($importef * 0.5 / 100);
$metroContador = $importef - $mcpo;
$dif3 = $metroContador - $importef;

$fecha = $embarque->fecha ? $embarque->fecha->format('Y-m-d') : '';
$dia = $fecha ? (int)$embarque->fecha->format('d') : 0;

$columnas[] = [
'id'             => (int)$embarque->id,
'fecha'          => $fecha,
'dia'            => $dia,
'dia_nombre'     => get_nombre_dia($fecha),
'lts_factura'    => $importef,
'tipo'           => (string)$embarque->embarque,
'bruto'          => $bruto,
'dif1'           => $dif1,
'neto'           => $neto,
'dif2'           => $dif2,
'metro_contador' => $metroContador,
'dif3'           => $dif3,
];

$totales['lts_factura'] += $importef;
$totales['bruto'] += $bruto;
$totales['dif1'] += $dif1;
$totales['neto'] += $neto;
$totales['dif2'] += $dif2;
$totales['metro_contador'] += $metroContador;
$totales['dif3'] += $dif3;
}

$productos[] = [
'producto' => $producto,
'columnas' => $columnas,
'totales'  => $totales,
];
}

return [
'idReporte' => $idReporte,
'estacion'  => self::getEstacionNombre($idEstacion),
'productos' => $productos,
];
}

public static function getSubtotales(int $idEstacion, int $idYear, int $idMes): array
{
$idReporte = self::resolverCorte($idEstacion, $idYear, $idMes);

if (!$idReporte) {
return [];
}

$resultado = [];

foreach (self::PRODUCTOS as $producto) {
$embarques = Embarque::where('id_mes', $idReporte)
->where('producto', $producto)
->get();

$toImporteF = 0;
$toBruto = 0;
$toNeto = 0;
$toMetroContador = 0;

foreach ($embarques as $embarque) {
$importef = (float)$embarque->importef;
$mcpo = round($importef * 0.5 / 100);
$metroContador = $importef - $mcpo;

$toImporteF += $importef;
$toBruto += (float)$embarque->bruto;
$toNeto += (float)$embarque->neto;
$toMetroContador += $metroContador;
}

$mermaBruto = $toImporteF - $toBruto;
$mermaNeto = $toImporteF - $toNeto;
$mermaMetroContador = $toImporteF - $toMetroContador;
$inventario = self::INVENTARIO[$producto] ?? 0;

if ($toImporteF != 0) {
$pMermaBruto = $mermaBruto / $toImporteF;
$pMermaNeto = $mermaNeto / $toImporteF;
$pMermaMetroContador = $mermaMetroContador / $toImporteF;
$pInventario = $inventario / $toImporteF;
} else {
$pMermaBruto = 0;
$pMermaNeto = 0;
$pMermaMetroContador = 0;
$pInventario = 0;
}

$resultado[] = [
'producto'              => $producto,
'inventario'            => $inventario,
'factura'               => $toImporteF,
'bruto'                 => $toBruto,
'merma_bruto'           => $mermaBruto,
'p_merma_bruto'         => $pMermaBruto,
'neto'                  => $toNeto,
'merma_neto'            => $mermaNeto,
'p_merma_neto'          => $pMermaNeto,
'metro_contador'        => $toMetroContador,
'merma_metro_contador'  => $mermaMetroContador,
'p_merma_metro_contador' => $pMermaMetroContador,
'p_inventario'          => $pInventario,
];
}

return $resultado;
}

public static function getTotales(int $idEstacion, int $idYear, int $idMes): array
{
$idReporte = self::resolverCorte($idEstacion, $idYear, $idMes);

if (!$idReporte) {
return self::matrizVacia();
}

return self::construirMatriz([$idReporte]);
}

public static function getTotalesGeneral(int $idYear, int $idMes, array $estaciones = []): array
{
if (empty($estaciones)) {
$estaciones = self::getEstacionesPermitidas();
}

$idReportes = [];

foreach ($estaciones as $idEstacion) {
$idReporte = self::resolverCorte((int)$idEstacion, $idYear, $idMes);
if ($idReporte) {
$idReportes[] = $idReporte;
}
}

if (empty($idReportes)) {
return self::matrizVacia();
}

return self::construirMatriz($idReportes);
}

private static function construirMatriz(array $idReportes): array
{
$importes = [];

foreach (self::PRODUCTOS as $producto) {
foreach (self::TIPOS_EMBARQUE as $tipo) {
$importes[$producto][$tipo] = 0;
}
}

$embarques = Embarque::whereIn('id_mes', $idReportes)
->whereIn('producto', self::PRODUCTOS)
->whereIn('embarque', self::TIPOS_EMBARQUE)
->get(['producto', 'embarque', 'importef']);

foreach ($embarques as $embarque) {
$producto = (string)$embarque->producto;
$tipo = (string)$embarque->embarque;
if (!isset($importes[$producto][$tipo])) {
continue;
}
$importes[$producto][$tipo] += (float)$embarque->importef;
}

$productos = [];
$totalPemex = 0;
$totalDelivery = 0;
$totalPickUp = 0;
$granTotal = 0;

foreach (self::PRODUCTOS as $producto) {
$pemex = $importes[$producto]['Pemex'];
$delivery = $importes[$producto]['Delivery'];
$pickUp = $importes[$producto]['Pick Up'];
$total = $pemex + $delivery + $pickUp;

$totalPemex += $pemex;
$totalDelivery += $delivery;
$totalPickUp += $pickUp;
$granTotal += $total;

$productos[$producto] = [
'pemex'    => $pemex,
'delivery' => $delivery,
'pickup'   => $pickUp,
'total'    => $total,
];
}

foreach ($productos as $producto => &$fila) {
$fila['porcentaje'] = $granTotal != 0 ? ($fila['total'] / $granTotal) * 100 : 0;
}
unset($fila);

return [
'productos'  => $productos,
'total'      => [
'pemex'    => $totalPemex,
'delivery' => $totalDelivery,
'pickup'   => $totalPickUp,
'total'    => $granTotal,
],
'porcentaje' => [
'pemex'    => $granTotal != 0 ? ($totalPemex / $granTotal) * 100 : 0,
'delivery' => $granTotal != 0 ? ($totalDelivery / $granTotal) * 100 : 0,
'pickup'   => $granTotal != 0 ? ($totalPickUp / $granTotal) * 100 : 0,
],
];
}

private static function matrizVacia(): array
{
$productos = [];
foreach (self::PRODUCTOS as $producto) {
$productos[$producto] = [
'pemex'      => 0,
'delivery'   => 0,
'pickup'     => 0,
'total'      => 0,
'porcentaje' => 0,
];
}

return [
'productos'  => $productos,
'total'      => ['pemex' => 0, 'delivery' => 0, 'pickup' => 0, 'total' => 0],
'porcentaje' => ['pemex' => 0, 'delivery' => 0, 'pickup' => 0],
];
}

public static function getEmbarqueEstacionId(int $idEmbarque): ?int
{
$embarque = Embarque::find($idEmbarque);

if (!$embarque || !$embarque->id_mes) {
return null;
}

$corteMes = CorteMes::find($embarque->id_mes);

if (!$corteMes || !$corteMes->id_year) {
return null;
}

$corteYear = CorteYear::find($corteMes->id_year);

return $corteYear ? (int)$corteYear->id_estacion : null;
}

public static function getYearMesDeEmbarque(int $id): array
{
    $embarque = Embarque::find($id);

    if (!$embarque || !$embarque->id_mes) {
        return ['year' => 0, 'mes' => 0];
    }

    $corteMes = CorteMes::find($embarque->id_mes);

    if (!$corteMes || !$corteMes->id_year) {
        return ['year' => 0, 'mes' => 0];
    }

    $corteYear = CorteYear::find($corteMes->id_year);

    return $corteYear ? ['year' => (int)$corteYear->year, 'mes' => (int)$corteMes->mes] : ['year' => 0, 'mes' => 0];
}

public static function getProductoDeEmbarque(int $id): ?string
{
    $embarque = Embarque::find($id);

    return $embarque && $embarque->producto ? (string)$embarque->producto : null;
}

public static function actualizarBrutoNeto(int $id, int $opcion, $valor): array
{
$embarque = Embarque::find($id);

if (!$embarque) {
return ['success' => false, 'message' => 'El registro no existe.'];
}

if (!in_array($opcion, [1, 2], true)) {
return ['success' => false, 'message' => 'Opción no válida.'];
}

if (!is_numeric($valor)) {
return ['success' => false, 'message' => 'El valor debe ser numérico.'];
}

if ($opcion === 1) {
$embarque->bruto = (int)$valor;
} else {
$embarque->neto = (int)$valor;
}

$embarque->save();

return ['success' => true];
}

public static function buildAnalisisHtml(
array $datos,
array $subtotales,
?array $totales,
?array $totalesGeneral,
bool $puedeEditar
): string {
$html = self::buildDetalleProductosHtml($datos['productos'] ?? [], $puedeEditar);

if (!empty($subtotales)) {
$html .= '<h5 class="fw-semibold mt-2 mb-3">Subtotales del Análisis de Compra</h5>';
$html .= self::buildSubtotalesHtml($subtotales);
}

if (!empty($totales)) {
$html .= self::buildTotalesHtml($totales,'Total del Análisis de Compra');
}

if (!empty($totalesGeneral)) {
$html .= self::buildTotalesHtml($totalesGeneral,'Total General');
}

return $html;
}

public static function buildDetalleProductosHtml(array $productos, bool $puedeEditar): string
{
$html = '';

$html .= '<div class="row">';

foreach ($productos as $bloque) {
$nombreProducto = $bloque['producto'];
$color = self::COLORES_PRODUCTO[$nombreProducto] ?? '#0d6efd';
$columnas = $bloque['columnas'];
$totalesProducto = $bloque['totales'];
$nombre = htmlspecialchars($nombreProducto, ENT_QUOTES, 'UTF-8');

if (empty($columnas)) {
$html .= '<div class="col-12" data-producto="' . $nombre . '">';
$html .= '<div class="alert mb-3 text-center text-white" role="alert" style="background: ' . $color . ';">';
$html .= 'No se encontró información para mostrar (' . $nombre . ')';
$html .= '</div>';
$html .= '</div>';
continue;
}  

$html .= '<div class="col-12" data-producto="' . $nombre . '">';
$html .= '<div class="card">';
$html .= '<div class="card-header card-colored-header text-white" style="background: ' . $color . ';"><h4 class="card-title text-white mb-0"><i class="ti ti-gas-station"></i> ' . $nombre . ' </h4></div>';

$html .= '<div class="card-body p-0">';

$html .= '<div class="table-responsive">';
$html .= '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">';
$html .= '<thead>';
$html .= '<tr><th class="text-center align-middle" ></th>';
foreach ($columnas as $columna) {
$html .= '<th class="text-center align-middle fw-semibold">' . (int)$columna['dia'] . '</th>';
}
$html .= '<th class="text-center fw-semibold">Total</th></tr>';
$html .= '</thead>';
$html .= '<tbody>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Día</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle">' . htmlspecialchars($columna['dia_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
}
$html .= '<td></td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Lts. Factura</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle">' . formatearCantidad($columna['lts_factura']) . '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['lts_factura']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Tipo</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle">' . htmlspecialchars($columna['tipo'], ENT_QUOTES, 'UTF-8') . '</td>';
}
$html .= '<td></td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Bruto</th>';
foreach ($columnas as $columna) {
$html .= '<td class="p-0 text-center align-middle">';
if ($puedeEditar) {
$html .= '<input type="number" min="0" step="1" class="border-0 p-3 text-center w-100 bg-transparent" value="' . (int)$columna['bruto'] . '" data-embarque-id="' . (int)$columna['id'] . '" data-opcion="1" data-lts-factura="' . (float)$columna['lts_factura'] . '" x-on:input="actualizarVistaLocal($event, ' . (int)$columna['id'] . ', 1)" x-on:change="actualizarBrutoNeto($event, ' . (int)$columna['id'] . ', 1)">';} else {
$html .= '<div class="text-center">' . formatearCantidad($columna['bruto']) . '</div>';
}
$html .= '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['bruto']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Dif1</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle" data-embarque-id="' . (int)$columna['id'] . '" data-campo="dif1">' . formatearCantidad($columna['dif1']) . '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['dif1']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Neto</th>';
foreach ($columnas as $columna) {
$html .= '<td class="p-0 text-center align-middle">';
if ($puedeEditar) {
$html .= '<input type="number" min="0" step="1" class="border-0 p-3 text-center w-100 bg-transparent" value="' . (int)$columna['neto'] . '" data-embarque-id="' . (int)$columna['id'] . '" data-opcion="2" data-lts-factura="' . (float)$columna['lts_factura'] . '" x-on:input="actualizarVistaLocal($event, ' . (int)$columna['id'] . ', 2)" x-on:change="actualizarBrutoNeto($event, ' . (int)$columna['id'] . ', 2)">';} else {
$html .= '<div class="text-center">' . formatearCantidad($columna['neto']) . '</div>';
}
$html .= '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['neto']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Dif2</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle" data-embarque-id="' . (int)$columna['id'] . '" data-campo="dif2">' . formatearCantidad($columna['dif2']) . '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['dif2']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Metro Contador</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle">' . formatearCantidad($columna['metro_contador']) . '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['metro_contador']) . '</td></tr>';

$html .= '<tr><th class="text-center align-middle fw-semibold">Dif3</th>';
foreach ($columnas as $columna) {
$html .= '<td class="text-center align-middle">' . formatearCantidad($columna['dif3']) . '</td>';
}
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totalesProducto['dif3']) . '</td></tr>';

$html .= '</tbody></table></div>';

$html .= '</div>';

$html .= '</div>';
$html .= '</div>';

}

$html .= '</div>';
return $html;
}

public static function buildSubtotalesHtml(array $subtotales): string
{
$html = '<div class="row">';

foreach ($subtotales as $subtotal) {
$nombreProducto = $subtotal['producto'];
$color = self::COLORES_PRODUCTO[$nombreProducto] ?? '#0d6efd';
$nombre = htmlspecialchars($nombreProducto, ENT_QUOTES, 'UTF-8');

$html .= '<div class="col-xl-4 col-lg-4 col-md-12 mb-3" data-producto="' . $nombre . '">';
$html .= '<div class="card">';
$html .= '<div class="card-header card-colored-header text-white" style="background: ' . $color . ';"><h4 class="card-title text-white mb-0"><i class="ti ti-gas-station"></i> ' . $nombre . ' </h4></div>';
$html .= '<div class="card-body p-0">';
$html .= '<div class="table-responsive">';
$html .= '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">';
$html .= '<thead><tr>';
$html .= '<th class="text-center align-middle" colspan="2">Totales</th>';
$html .= '<th class="text-center align-middle">Mermas</th>';
$html .= '<th class="text-center align-middle">Porcentaje</th>';
$html .= '</tr></thead>';
$html .= '<tbody>';
$html .= '<tr><th class="text-center fw-semibold">Factura</th><td class="text-center">' . formatearCantidad($subtotal['factura']) . '</td><td class="text-center">0</td><td class="text-center"></td></tr>';
$html .= '<tr><th class="text-center fw-semibold">Bruto</th><td class="text-center">' . formatearCantidad($subtotal['bruto']) . '</td><td class="text-center">' . formatearCantidad($subtotal['merma_bruto']) . '</td><td class="text-center">' . formatearCantidad($subtotal['p_merma_bruto']) . '%</td></tr>';
$html .= '<tr><th class="text-center fw-semibold">Neto</th><td class="text-center">' . formatearCantidad($subtotal['neto']) . '</td><td class="text-center">' . formatearCantidad($subtotal['merma_neto']) . '</td><td class="text-center">' . formatearCantidad($subtotal['p_merma_neto']) . '%</td></tr>';
$html .= '<tr><th class="text-center fw-semibold">Metro contador</th><td class="text-center">' . formatearCantidad($subtotal['metro_contador']) . '</td><td class="text-center">' . formatearCantidad($subtotal['merma_metro_contador']) . '</td><td class="text-center">' . formatearCantidad($subtotal['p_merma_metro_contador']) . '%</td></tr>';
$html .= '<tr><th class="text-center fw-semibold">Inventario</th><td class="text-center"></td><td class="text-center">' . formatearCantidad($subtotal['inventario']) . '</td><td class="text-center">' . formatearCantidad($subtotal['p_inventario']) . '%</td></tr>';
$html .= '</tbody></table></div></div></div></div>';
}

$html .= '</div>';

return $html;
}

private static function buildTotalesHtml(array $totales, $titulos): string
{

$html = '<div class="card">';

$html .= '<div class="card-header card-colored-header bg-primary text-white"><h4 class="card-title text-white mb-0"><i class="ti ti-receipt-2"></i> '.$titulos.' </h4></div>';

$html .= '<div class="card-body p-0">';
$html .= '<div class="table-responsive">';
$html .= '<table class="table table-bordered mb-0 text-nowrap align-middle">';
$html .= '<thead><tr>';
$html .= '<th></th>';
$html .= '<th class="text-center align-middle" style="background: #CEEDFB; color: #000;">PEMEX</th>';
$html .= '<th class="text-center align-middle" style="background: #FAFBCE; color: #000;">DELIVERY</th>';
$html .= '<th class="text-center align-middle" style="background: #DEDEDE;">PICK UP</th>';
$html .= '<th class="text-center align-middle">TOTAL</th>';
$html .= '<th class="text-center align-middle">%</th>';
$html .= '</tr></thead>';
$html .= '<tbody>';

foreach ($totales['productos'] as $nombreProducto => $fila) {
$color = self::COLORES_PRODUCTO[$nombreProducto] ?? '#0d6efd';
$nombre = htmlspecialchars($nombreProducto, ENT_QUOTES, 'UTF-8');

$html .= '<tr>';
$html .= '<th class="fw-semibold text-white align-middle" style="background: ' . $color . ';">' . $nombre . '</th>';
$html .= '<td class="text-center align-middle">' . formatearCantidad($fila['pemex']) . '</td>';
$html .= '<td class="text-center align-middle">' . formatearCantidad($fila['delivery']) . '</td>';
$html .= '<td class="text-center align-middle">' . formatearCantidad($fila['pickup']) . '</td>';
$html .= '<td class="text-center align-middle">' . formatearCantidad($fila['total']) . '</td>';
$html .= '<td class="text-center align-middle text-white" style="background: ' . $color . ';">' . number_format($fila['porcentaje']) . '%</td>';
$html .= '</tr>';
}

$html .= '<tr>';
$html .= '<th class="fw-semibold align-middle">TOTAL</th>';
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totales['total']['pemex']) . '</td>';
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totales['total']['delivery']) . '</td>';
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totales['total']['pickup']) . '</td>';
$html .= '<td class="text-center align-middle fw-semibold">' . formatearCantidad($totales['total']['total']) . '</td>';
$html .= '<td></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<th class="fw-semibold align-middle">%</th>';
$html .= '<td class="text-center align-middle" style="background: #CEEDFB; color: #000;">' . number_format($totales['porcentaje']['pemex']) . '%</td>';
$html .= '<td class="text-center align-middle" style="background: #FAFBCE; color: #000;">' . number_format($totales['porcentaje']['delivery']) . '%</td>';
$html .= '<td class="text-center align-middle" style="background: #DEDEDE;">' . number_format($totales['porcentaje']['pickup']) . '%</td>';
$html .= '<td class="text-center align-middle fw-semibold">100%</td>';
$html .= '<td></td>';
$html .= '</tr>';

$html .= '</tbody></table></div>';
$html .= '</div>';
$html .= '</div>';

return $html;
}
}
