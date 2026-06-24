<?php

namespace App\Services;

use App\Models\Estacion;
use App\Models\Operativo\ControlVolumetrico;
use App\Models\Operativo\ControlVolumetricoResumen;
use App\Models\Operativo\ControlVolumetricoResumenAceite;
use App\Models\Operativo\ControlVolumetricoPrefijo;
use App\Models\Operativo\ControlVolumetricoPrefijoFinalizar;
use App\Models\Operativo\ControlVolumetricoComentario;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\VentasDia;
use App\Models\Operativo\DespachoFactura;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\AceiteLubricanteReporte;
use App\Models\Operativo\IngresosFacturacionContabilidad;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\ReporteCreProducto;
use App\Models\Sasisopa\ReporteCrePipa;
use App\Services\TelegramService;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;

class ControlVolumetricoService
{
public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
return CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->value('id');
}

public static function getEstado(int $idMes): int
{
$row = ControlVolumetricoPrefijoFinalizar::where('id_mes', $idMes)->first();
return $row ? (int) $row->estado : 0;
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $sessionUsuario['id'] ?? 0,
];
}

// ================================================================
// AUTO-CALCULATION & SEEDING (matches original PHP logic)
// ================================================================

public static function asegurarRegistros(int $idMes, int $idEstacion, int $idYear, int $idMesNum): void
{
$estacion = Estacion::find($idEstacion);
if (!$estacion) return;

$productos = [$estacion->producto_uno, $estacion->producto_dos];
if (!empty($estacion->producto_tres)) {
$productos[] = $estacion->producto_tres;
}

foreach ($productos as $producto) {
self::calcularYActualizarResumen($idMes, $idEstacion, $idYear, $idMesNum, $producto);
}

self::asegurarPrefijos($idMes, $idEstacion);
self::asegurarResumenAceite($idMes);
self::asegurarIngresosFacturacion($idMes, $idEstacion, $idYear, $idMesNum);
}

private static function calcularYActualizarResumen(int $idMes, int $idEstacion, int $idYear, int $idMesNum, string $producto): void
{
$volumen = 0;
$importetotal = 0;

$creMes = ReporteCreMes::where('id_estacion', $idEstacion)
->where('mes', $idMesNum)
->where('year', $idYear)
->first();

if ($creMes) {
$creProductos = ReporteCreProducto::where('id_re_mes', $creMes->id)
->where('producto', $producto)
->get();

foreach ($creProductos as $cp) {
$pipas = ReporteCrePipa::where('id_re_producto', $cp->id)->get();
foreach ($pipas as $pipa) {
$volumen += $pipa->volumen;
$importetotal += $pipa->importe_total;
}
}
}

$totalLitros = 0;
$grantotal = 0;

$corteIds = CorteDia::where('id_mes', $idMes)->pluck('id');

if ($corteIds->isNotEmpty()) {
$ventas = VentasDia::whereIn('idreporte_dia', $corteIds)
->where('producto', $producto)
->get();

foreach ($ventas as $v) {
$litrosV = $v->litros;
$preciolitroV = $v->precio_litro;
$total = $litrosV * $preciolitroV;

$totalLitros += $litrosV;
$grantotal += $total;
}
}

$dato12TB = 0;
$dato14TB = 0;

$diasIds = CorteDia::where('id_mes', $idMes)->pluck('id');
if ($diasIds->isNotEmpty()) {
$despachos = DespachoFactura::whereIn('id_dia', $diasIds)->get();

foreach ($despachos as $d) {
if ($producto === 'G SUPER') {
$dato12TB += $d->litros_producto_uno;
$dato14TB += $d->pesos_producto_uno;
} elseif ($producto === 'G PREMIUM') {
$dato12TB += $d->litros_producto_dos;
$dato14TB += $d->pesos_producto_dos;
} elseif ($producto === 'G DIESEL') {
$dato12TB += $d->litros_producto_tres;
$dato14TB += $d->pesos_producto_tres;
}
}
}

$existing = ControlVolumetricoResumen::where('id_mes', $idMes)
->where('producto', $producto)
->first();

if ($existing) {
$existing->update([
'dato4' => $volumen,
'dato6' => $importetotal,
'dato8' => $totalLitros,
'dato10' => $grantotal,
'dato12' => $dato12TB,
'dato14' => $dato14TB,
]);
} else {
ControlVolumetricoResumen::create([
'id_mes' => $idMes,
'producto' => $producto,
'dato1' => 0,
'dato2' => 0,
'dato3' => 0,
'dato4' => $volumen,
'dato5' => 0,
'dato6' => $importetotal,
'dato7' => 0,
'dato8' => $totalLitros,
'dato9' => 0,
'dato10' => $grantotal,
'dato11' => 0,
'dato12' => $dato12TB,
'dato13' => 0,
'dato14' => $dato14TB,
'comentario' => '',
]);
}
}

private static function asegurarPrefijos(int $idMes, int $idEstacion): void
{
$isFinalized = ControlVolumetricoPrefijoFinalizar::where('id_mes', $idMes)
->where('estado', 1)
->exists();

if ($isFinalized) return;

$prefijosPorEstacion = [
1 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
2 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['AL', 'AUTOLAVADO'],
['VA', 'VENTA MOSTRADOR AUTOLAVADO'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
3 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
4 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
5 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
6 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['ERP', 'PUBLICIDAD'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
7 => [
['PG', 'PUBLICO EN GENERAL'],
['VM', 'VENTA MOSTRADOR'],
['FEVM (WEB)', 'FACTURACION EN LINEA'],
['FGVM (WEB)', 'FACTURACION EN LA APP'],
['EDI', 'MONEDEROS EXTERNOS'],
['T', 'TPV'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
14 => [
['PG', 'PUBLICO EN GENERAL'],
['T', 'TPV'],
['FEVM', 'WEB'],
['CC', 'CLIENTES DE CREDITO'],
['CD', 'CLIENTES DE DEBITO'],
['CA', 'CLIENTES ANTICIPO'],
['VM', 'VENTA MOSTRADOR'],
['EDI', 'MONEDEROS'],
['FA', 'FACTURA DE ACEITES'],
['RL', 'RENTAS'],
['S', 'SODEXO'],
['K', 'Notas de credito'],
['CP', 'Complemento de pago'],
],
];

$lista = $prefijosPorEstacion[$idEstacion] ?? $prefijosPorEstacion[1];

foreach ($lista as $item) {
$exists = ControlVolumetricoPrefijo::where('id_mes', $idMes)
->where('serie', $item[0])
->where('descripcion', $item[1])
->exists();

if (!$exists) {
ControlVolumetricoPrefijo::create([
'id_mes' => $idMes,
'serie' => $item[0],
'descripcion' => $item[1],
'total' => 0,
]);
}
}

$finalizarExists = ControlVolumetricoPrefijoFinalizar::where('id_mes', $idMes)
->where('estado', 1)
->exists();

if (!$finalizarExists) {
ControlVolumetricoPrefijoFinalizar::create([
'id_mes' => $idMes,
'estado' => 1,
]);
}
}

private static function asegurarResumenAceite(int $idMes): void
{
$exists = ControlVolumetricoResumenAceite::where('id_mes', $idMes)->exists();
if (!$exists) {
ControlVolumetricoResumenAceite::create([
'id_mes' => $idMes,
'piezas' => 0,
'volumetrico' => 0,
'contables' => 0,
]);
}
}

private static function asegurarIngresosFacturacion(int $idMes, int $idEstacion, int $idYear, int $idMesNum): void
{
$idYearRecord = \App\Models\Operativo\CorteYear::where('id_estacion', $idEstacion)
->where('year', $idYear)
->value('id');

if (!$idYearRecord) return;

$items = [
['G SUPER', 1],
['G PREMIUM', 1],
['Aceites y Lubricantes', 1],
['IEPS', 1],
['Público en General', 2],
['Clientes crédito', 2],
['Monederos electronicos', 2],
['Facturas aceites y lubricantes', 2],
['Clientes débito', 2],
['Ventas mostrador', 2],
['TPV', 2],
['Página WEB', 2],
['Clientes débito', 2],
];

$estacion = Estacion::find($idEstacion);
if ($estacion && !empty($estacion->producto_tres)) {
array_unshift($items, ['G DIESEL', 1]);
}

foreach ($items as $item) {
$exists = IngresosFacturacionContabilidad::where('id_year', $idYearRecord)
->where('detalle', $item[0])
->exists();

if (!$exists) {
IngresosFacturacionContabilidad::create([
'id_year' => $idYearRecord,
'detalle' => $item[0],
'posicion' => $item[1],
'enero' => 0,
'febrero' => 0,
'marzo' => 0,
'abril' => 0,
'mayo' => 0,
'junio' => 0,
'julio' => 0,
'agosto' => 0,
'septiembre' => 0,
'octubre' => 0,
'noviembre' => 0,
'diciembre' => 0,
]);
}
}
}

// ================================================================
// DATA FETCHING
// ================================================================

public static function getData(int $idMes): array
{
$resumen = ControlVolumetricoResumen::where('id_mes', $idMes)
->orderBy('id', 'asc')
->get();

$productos = [];
$sumDato3 = 0;
$sumDato4 = 0;
$sumDato5 = 0;
$sumDato6 = 0;
$sumDato7 = 0;
$sumDato8 = 0;
$sumDato9 = 0;
$sumDato10 = 0;
$sumDato11 = 0;
$sumDato12 = 0;
$sumDato13 = 0;
$sumDato14 = 0;

foreach ($resumen as $row) {
$d1 = (float) $row->dato1;
$d2 = (float) $row->dato2;
$d3 = (float) $row->dato3;
$d4 = (float) $row->dato4;
$d5 = (float) $row->dato5;
$d6 = (float) $row->dato6;
$d7 = (float) $row->dato7;
$d8 = (float) $row->dato8;
$d9 = (float) $row->dato9;
$d10 = (float) $row->dato10;
$d11 = (float) $row->dato11;
$d12 = (float) $row->dato12;
$d13 = (float) $row->dato13;
$d14 = (float) $row->dato14;

$dif1 = $d2 != 0 ? $d1 - $d2 : 0;
$dif2 = $d3 - $d4;
$dif3 = $d5 - $d6;
$dif4 = $d7 - $d8;
$dif5 = $d9 - $d10;
$dif6 = $d11 - $d12;
$dif7 = $d13 - $d14;

$valP1 = $d4 != 0 ? ($d3 * 100) / $d4 : 0;
$param = $valP1 - 100;
if (is_nan($param) || is_infinite($param)) $param = 0;

$productos[] = [
'id' => $row->id,
'producto' => $row->producto,
'dato1' => $d1, 'dato2' => $d2, 'dato3' => $d3, 'dato4' => $d4,
'dato5' => $d5, 'dato6' => $d6, 'dato7' => $d7, 'dato8' => $d8,
'dato9' => $d9, 'dato10' => $d10, 'dato11' => $d11, 'dato12' => $d12,
'dato13' => $d13, 'dato14' => $d14,
'dif1' => $dif1, 'dif2' => $dif2, 'dif3' => $dif3, 'dif4' => $dif4,
'dif5' => $dif5, 'dif6' => $dif6, 'dif7' => $dif7,
'parametro' => round($param, 2),
'comentario' => $row->comentario,
];

$sumDato3 += $d3; $sumDato4 += $d4;
$sumDato5 += $d5; $sumDato6 += $d6;
$sumDato7 += $d7; $sumDato8 += $d8;
$sumDato9 += $d9; $sumDato10 += $d10;
$sumDato11 += $d11; $sumDato12 += $d12;
$sumDato13 += $d13; $sumDato14 += $d14;
}

$sumDif2 = $sumDato3 - $sumDato4;
$sumDif3 = $sumDato5 - $sumDato6;
$sumDif4 = $sumDato7 - $sumDato8;
$sumDif5 = $sumDato9 - $sumDato10;
$sumDif6 = $sumDato11 - $sumDato12;
$sumDif7 = $sumDato13 - $sumDato14;

$aceitesData = self::getAceitesData($idMes);

return [
'productos' => $productos,
'aceites' => $aceitesData,
'totales' => [
'dato3' => $sumDato3, 'dato4' => $sumDato4,
'dato5' => $sumDato5, 'dato6' => $sumDato6,
'dato7' => $sumDato7, 'dato8' => $sumDato8,
'dato9' => $sumDato9, 'dato10' => $sumDato10,
'dato11' => $sumDato11, 'dato12' => $sumDato12,
'dato13' => $sumDato13, 'dato14' => $sumDato14,
'dif2' => $sumDif2, 'dif3' => $sumDif3,
'dif4' => $sumDif4, 'dif5' => $sumDif5,
'dif6' => $sumDif6, 'dif7' => $sumDif7,
],
];
}

public static function getAceitesData(int $idMes): array
{
$aceiteRow = ControlVolumetricoResumenAceite::where('id_mes', $idMes)->first();
$volumetrico = $aceiteRow ? (float) $aceiteRow->volumetrico : 0;

$corteIds = CorteDia::where('id_mes', $idMes)->pluck('id');
$totPiezas = 0;
$grantotal = 0;

if ($corteIds->isNotEmpty()) {
$aceitesReporte = AceiteLubricanteReporte::where('id_mes', $idMes)->get();

foreach ($aceitesReporte as $ar) {
$cantidadTotal = AceiteLubricante::where('id_aceite', $ar->id_aceite)
->whereIn('idreporte_dia', $corteIds)
->sum('cantidad');

$total = $ar->precio * $cantidadTotal;
$totPiezas += $cantidadTotal;
$grantotal += $total;
}
}

$diferenciaA = $volumetrico - $grantotal;

return [
'piezas' => $totPiezas,
'volumetrico' => $volumetrico,
'contables' => $grantotal,
'diferencia' => $diferenciaA,
];
}

public static function getDocumentos(int $idMes): array
{
$rows = ControlVolumetrico::where('id_mes', $idMes)
->orderBy('id', 'desc')
->get()
->toArray();

foreach ($rows as &$row) {
$row['fecha_formateada'] = $row['fecha_hora'] ? formatearFecha($row['fecha_hora']) : '';
}
unset($row);

return $rows;
}

public static function getPrefijos(int $idMes): array
{
return ControlVolumetricoPrefijo::where('id_mes', $idMes)
->orderBy('id', 'asc')
->get()
->toArray();
}

public static function getPrefijoTotals(int $idMes, int $idEstacion): array
{
$prefijos = ControlVolumetricoPrefijo::where('id_mes', $idMes)->get();

$SumGasolina = 0;
$SumRentas = 0;
$SumSodexo = 0;
$SumAutolavado = 0;
$SumGTotal = 0;

foreach ($prefijos as $p) {
$total = (!in_array($p->serie, ['K', 'CP'])) ? (float) $p->total : 0;
$gasolina = (!in_array($p->serie, ['RL', 'S', 'K', 'CP', 'CA'])) ? (float) $p->total : 0;
$rentas = ($p->serie === 'RL') ? (float) $p->total : 0;
$sodexo = ($p->serie === 'S') ? (float) $p->total : 0;
$autolavado = ($p->serie === 'AL') ? (float) $p->total : 0;

$SumGasolina += $gasolina;
$SumRentas += $rentas;
$SumSodexo += $sodexo;
$SumAutolavado += $autolavado;
$SumGTotal += $total;
}

return [
'sum_gasolina' => $SumGasolina,
'sum_rentas' => $SumRentas,
'sum_sodexo' => $SumSodexo,
'sum_autolavado' => $SumAutolavado,
'sum_gtotal' => $SumGTotal,
];
}

public static function getGranTotal(int $idMes, int $idEstacion): float
{
$sumDato9 = ControlVolumetricoResumen::where('id_mes', $idMes)->sum('dato9');
$prefijoTotals = self::getPrefijoTotals($idMes, $idEstacion);
return (float) $sumDato9 - $prefijoTotals['sum_gasolina'];
}

public static function getComentarios(int $idMes): array
{
$rows = ControlVolumetricoComentario::where('id_mes', $idMes)
->orderBy('id', 'asc')
->get()
->toArray();

$currentUserId = Session::get('usuario')['id'] ?? 0;

foreach ($rows as &$row) {
$row['fecha_formateada'] = $row['fecha_hora'] ? formatearFecha($row['fecha_hora']) : '';
$row['es_mio'] = ((int) $row['id_usuario']) === $currentUserId;
$usuario = Usuario::find($row['id_usuario']);
$row['usuario'] = $usuario ? $usuario->nombre : 'Usuario';
}
unset($row);

return $rows;
}

// ================================================================
// CRUD OPERATIONS
// ================================================================

public static function editarResumenDato(int $id, string $campo, string $valor): bool
{
$allowed = [
'dato1','dato2','dato3','dato4','dato5','dato6',
'dato7','dato8','dato9','dato10','dato11','dato12','dato13','dato14'
];
if (!in_array($campo, $allowed)) return false;

$row = ControlVolumetricoResumen::find($id);
if (!$row) return false;

return $row->update([$campo => (float) $valor]);
}

public static function editarComentarioResumen(int $id, string $comentario): bool
{
$row = ControlVolumetricoResumen::find($id);
if (!$row) return false;
return $row->update(['comentario' => $comentario]);
}

public static function editarAceiteVolumetrico(int $idMes, string $valor): bool
{
$row = ControlVolumetricoResumenAceite::firstOrNew(['id_mes' => $idMes]);
$row->volumetrico = (float) $valor;
return $row->save();
}

public static function editarPrefijoTotal(int $id, string $total): bool
{
$row = ControlVolumetricoPrefijo::find($id);
if (!$row) return false;
return $row->update(['total' => (float) $total]);
}

public static function agregarComentario(int $idMes, int $idUsuario, string $comentario): bool
{
ControlVolumetricoComentario::create([
'id_mes' => $idMes,
'fecha_hora' => date('Y-m-d H:i:s'),
'id_usuario' => $idUsuario,
'comentario' => $comentario,
]);
return true;
}

public static function subirDocumento(int $idMes, array $file, string $fecha, string $anexos): bool
{
$aleatorio = uniqid();
$archivo = basename($file['name']);
$nombreArchivo = $aleatorio . '-' . $archivo;
$uploadDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos');
if (!$uploadDir) {
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos';
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}
}
$uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
return false;
}

ControlVolumetrico::create([
'id_mes' => $idMes,
'fecha_hora' => $fecha ? $fecha . ' 00:00:00' : date('Y-m-d H:i:s'),
'anexos' => $anexos,
'documento' => $nombreArchivo,
]);

return true;
}

public static function eliminarDocumento(int $id): bool
{
$row = ControlVolumetrico::find($id);
if (!$row) return false;

$uploadDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archivos');
if ($uploadDir) {
$ruta = $uploadDir . DIRECTORY_SEPARATOR . $row->documento;
if (file_exists($ruta)) unlink($ruta);
}

return $row->delete();
}

private static function getEstacionIdFromMes(int $idMes): int
{
return (int) CorteMes::from('op_corte_mes as m')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('m.id', $idMes)
->value('y.id_estacion');
}

private static function getMesYearFromMes(int $idMes): ?\stdClass
{
$corteMes = CorteMes::with('year')->find($idMes);
if (!$corteMes || !$corteMes->year) return null;
return (object) [
'mes' => nombremes((int) $corteMes->mes),
'year' => $corteMes->year->year,
];
}

private static function enviarTelegramControl(int $idEstacion, int $excludeUserId, string $mensaje): void
{
$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $excludeUserId);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

$telegram->sendMessageToMultiple($userIds, $mensaje);
}

public static function notificarAgregarAnexo(int $idMes, int $idUsuario, string $nombreUsuario, string $anexo): void
{
try {
$idEstacion = self::getEstacionIdFromMes($idMes);
if (!$idEstacion) return;

$my = self::getMesYearFromMes($idMes);
$periodo = $my ? $my->mes . ' ' . $my->year : '';

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '📎 Se ha agregado un nuevo anexo al apartado de <b>Control Volumétrico</b>, correspondiente al <b>Corte Diario</b> del periodo <b>' . $periodo . '</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Nombre del anexo:</b> ' . $anexo . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;


self::enviarTelegramControl($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarAgregarAnexo: ' . $e->getMessage());
}
}

public static function notificarEliminarAnexo(int $idMes, int $idUsuario, string $nombreUsuario): void
{
try {
$idEstacion = self::getEstacionIdFromMes($idMes);
if (!$idEstacion) return;

$my = self::getMesYearFromMes($idMes);
$periodo = $my ? $my->mes . ' ' . $my->year : '';

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '🗑️ Se ha eliminado un anexo del apartado de <b>Control Volumétrico</b>, correspondiente al <b>Corte Diario</b> del periodo <b>' . $periodo . '</b>:' . PHP_EOL . PHP_EOL

. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;


self::enviarTelegramControl($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEliminarAnexo: ' . $e->getMessage());
}
}

public static function notificarEnviarComentario(int $idMes, int $idUsuario, string $nombreUsuario, string $comentario): void
{
try {
$idEstacion = self::getEstacionIdFromMes($idMes);
if (!$idEstacion) return;

$my = self::getMesYearFromMes($idMes);
$periodo = $my ? $my->mes . ' ' . $my->year : '';

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '💬 Se ha agregado un comentario al apartado de <b>Control Volumétrico</b>, correspondiente al <b>Corte Diario</b> del periodo <b>' . $periodo . '</b>:' . PHP_EOL . PHP_EOL
. '📝 <b>Comentario:</b> ' . $comentario . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramControl($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEnviarComentario: ' . $e->getMessage());
}
}
}
